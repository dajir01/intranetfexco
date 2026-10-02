<?php

namespace App\Services;

use PhpOffice\PhpWord\TemplateProcessor;

class ContratoTemplateVerifier
{
    /**
     * Verifica que una plantilla Word tenga los marcadores requeridos
     * 
     * @param string $rutaArchivo Ruta absoluta al archivo DOCX
     * @param int $tipo Tipo de documento (1=Contrato, 2=Adenda). Por defecto 1
     * @return array Con estructura: ['valido' => bool, 'faltantes' => [], 'extras' => [], 'mensaje' => string]
     */
    public static function verificar($rutaArchivo, $tipo = DocumentTypeValidator::TYPE_CONTRATO)
    {
        $resultado = [
            'valido' => false,
            'faltantes' => [],
            'extras' => [],
            'mensaje' => '',
            'marcadores_encontrados' => [],
        ];

        try {
            if (!file_exists($rutaArchivo)) {
                $resultado['mensaje'] = 'El archivo no existe: ' . $rutaArchivo;
                return $resultado;
            }

            // Abrir DOCX como ZIP y leer document.xml directamente
            $zip = new \ZipArchive();
            if ($zip->open($rutaArchivo) !== true) {
                $resultado['mensaje'] = 'No se pudo abrir el archivo DOCX';
                return $resultado;
            }

            $xml = $zip->getFromName('word/document.xml');
            $zip->close();

            if (!$xml) {
                $resultado['mensaje'] = 'No se pudo extraer document.xml del DOCX';
                return $resultado;
            }

            // Obtener marcadores del documento
            $marcadoresEncontrados = self::extraerMarcadoresDelXml($xml);
            $resultado['marcadores_encontrados'] = $marcadoresEncontrados;

            $errorBloqueCondicional = self::validarBloquePagoRestante($xml, $tipo);
            if ($errorBloqueCondicional !== null) {
                $resultado['mensaje'] = $errorBloqueCondicional;
                return $resultado;
            }

            // Normalizar marcadores encontrados
            $marcadoresLimpios = [];
            foreach ($marcadoresEncontrados as $m) {
                $limpio = trim(str_replace(['{', '}'], '', $m));
                // Normalizar acentos (remover tildes)
                $limpio = self::normalizarAcentos($limpio);
                if (!empty($limpio)) {
                    $marcadoresLimpios[] = $limpio;
                }
            }

            // Comparar
            $requeridos = array_unique(DocumentTypeValidator::getMarcadoresRequeridos($tipo));
            $encontrados = array_unique($marcadoresLimpios);

            $faltantes = array_diff($requeridos, $encontrados);
            $extras = array_diff($encontrados, $requeridos);
            $extras = array_diff($extras, ['plan_pago_resto', '/plan_pago_resto']);

            $resultado['faltantes'] = array_values($faltantes);
            $resultado['extras'] = array_values($extras);

            if (!empty($faltantes)) {
                $resultado['mensaje'] = 'Plantilla inválida. Marcadores faltantes: ' . implode(', ', array_map(fn($m) => '{' . $m . '}', $faltantes));
                return $resultado;
            }

            if (!empty($extras)) {
                $resultado['mensaje'] = 'Advertencia: Marcadores no esperados encontrados: ' . implode(', ', array_map(fn($m) => '{' . $m . '}', $extras));
                $resultado['valido'] = true; // Permitir pero advertir
                return $resultado;
            }

            $resultado['valido'] = true;
            $resultado['mensaje'] = 'Plantilla válida. Todos los marcadores coinciden.';
            return $resultado;

        } catch (\Exception $e) {
            $resultado['mensaje'] = 'Error al procesar plantilla: ' . $e->getMessage();
            return $resultado;
        }
    }

    private static function validarBloquePagoRestante($xml, $tipo): ?string
    {
        if ((int) $tipo !== DocumentTypeValidator::TYPE_CONTRATO) {
            return null;
        }

        $marcadores = self::extraerMarcadoresDelXml($xml);
        foreach ($marcadores as $marcador) {
            if (preg_match('/^\{\/?if(?:_|\s|:|\})/i', $marcador)) {
                return 'No se admiten expresiones if dentro de Word. Use {plan_pago_resto} y {/plan_pago_resto} en párrafos separados para el bloque opcional.';
            }
        }

        $apertura = '{plan_pago_resto}';
        $cierre = '{/plan_pago_resto}';
        $cantidadAperturas = count(array_filter($marcadores, fn ($marcador) => $marcador === $apertura));
        $cantidadCierres = count(array_filter($marcadores, fn ($marcador) => $marcador === $cierre));

        if ($cantidadAperturas === 0 && $cantidadCierres === 0) {
            return null;
        }

        if ($cantidadAperturas !== 1 || $cantidadCierres !== 1) {
            return 'El bloque de saldo pendiente debe tener una sola apertura {plan_pago_resto} y un solo cierre {/plan_pago_resto}.';
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!@$dom->loadXML($xml)) {
            return 'No se pudo validar la estructura Word del bloque de saldo pendiente.';
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $parrafosApertura = [];
        $parrafosCierre = [];
        $ordenApertura = null;
        $ordenCierre = null;
        $orden = 0;

        foreach ($xpath->query('//w:p') as $parrafo) {
            $texto = trim($parrafo->textContent);
            if ($texto === $apertura) {
                $parrafosApertura[] = $parrafo;
                $ordenApertura ??= $orden;
            } elseif ($texto === $cierre) {
                $parrafosCierre[] = $parrafo;
                $ordenCierre ??= $orden;
            }
            $orden++;
        }

        if (count($parrafosApertura) !== 1 || count($parrafosCierre) !== 1) {
            return 'Cada marcador del bloque debe estar solo en su propio párrafo de Word.';
        }

        if ($parrafosApertura[0]->parentNode !== $parrafosCierre[0]->parentNode) {
            return 'Los dos marcadores del bloque deben estar en el mismo nivel del documento Word.';
        }

        if ($ordenCierre < $ordenApertura) {
            return 'El cierre {/plan_pago_resto} debe estar después de {plan_pago_resto}.';
        }

        foreach ([$parrafosApertura[0], $parrafosCierre[0]] as $parrafo) {
            for ($nodo = $parrafo->parentNode; $nodo instanceof \DOMElement; $nodo = $nodo->parentNode) {
                if ($nodo->localName === 'tbl') {
                    return 'Coloque los marcadores del bloque fuera de las tablas y envuelva con ellos la tabla opcional completa.';
                }
            }
        }

        return null;
    }

    /**
     * Extrae marcadores directamente del XML sin usar reflexión
     */
    private static function extraerMarcadoresDelXml($xml)
    {
        // Limpiar primero los fragmentos que rompen los marcadores
        $xml = preg_replace('/<w:proofErr[^>]*\/>/ui', '', $xml);
        
        // Buscar todos los patrones {texto}
        preg_match_all('/\{[^}]*\}/', $xml, $matches);
        
        $encontrados = array_unique($matches[0] ?? []);
        
        // Limpiar cada marcador de tags XML residuales
        $marcadoresLimpios = [];
        foreach ($encontrados as $m) {
            // Remover cualquier tag XML que haya quedado
            $limpio = preg_replace('/<[^>]+>/u', '', $m);
            // Validar que sea un marcador válido
            if (preg_match('/^\{[^}]+\}$/', $limpio)) {
                $marcadoresLimpios[] = $limpio;
            }
        }
        
        return array_unique($marcadoresLimpios);
    }

    /**
     * Obtiene la lista de marcadores requeridos
     * DEPRECATED: Usar DocumentTypeValidator::getMarcadoresRequeridos() directamente
     */
    public static function getMarcadoresRequeridos()
    {
        return DocumentTypeValidator::getMarcadoresRequeridos(DocumentTypeValidator::TYPE_CONTRATO);
    }

    /**
     * Normaliza acentos removiendo tildes
     * Convierte: á, é, í, ó, ú → a, e, i, o, u
     */
    private static function normalizarAcentos($texto)
    {
        // Usar iconv para remover acentos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT', $texto);
        // Remover cualquier carácter no alfanumérico excepto guiones bajos
        return preg_replace('/[^a-z0-9_]/i', '', $texto);
    }
}
