<?php

namespace App\Http\Controllers;

use App\Models\Feria;
use App\Models\Modelo_Contrato;
use App\Services\ContratoTemplateVerifier;
use App\Services\DocumentTypeValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class ModeloContratoController extends Controller
{
    private const DOCUMENTS_DIR = 'images/modelocontratos';

    public function index()
    {
        $modelos = Modelo_Contrato::query()
            ->orderByDesc('id_modelo_contrato')
            ->get()
            ->map(function ($item) {
                return [
                    'id_modelo_contrato' => $item->id_modelo_contrato,
                    'nombre' => $item->nombre,
                    'fecha_creacion' => $item->fecha,
                    'estado' => (int) $item->estado,
                    'ruta_documento' => $item->ruta_documento,
                    'tipo' => (int) ($item->tipo ?? DocumentTypeValidator::TYPE_CONTRATO),
                    'tipo_nombre' => DocumentTypeValidator::getNombreTipo($item->tipo ?? DocumentTypeValidator::TYPE_CONTRATO),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $modelos,
        ]);
    }

    public function download($id)
    {
        $modelo = Modelo_Contrato::findOrFail($id);
        $rutaDocumento = trim((string) $modelo->ruta_documento);

        if ($rutaDocumento === '') {
            return response()->json([
                'success' => false,
                'message' => 'El modelo de contrato no tiene documento asociado.',
            ], 404);
        }

        $rutaDocumento = str_replace('\\', '/', ltrim($rutaDocumento, '/'));
        if (! str_starts_with($rutaDocumento, self::DOCUMENTS_DIR . '/') || in_array('..', explode('/', $rutaDocumento), true)) {
            abort(404);
        }

        $rutaPublica = public_path($rutaDocumento);
        $disk = Storage::disk('private');
        $ruta = File::exists($rutaPublica) ? $rutaPublica : $disk->path($rutaDocumento);
        if (!File::exists($ruta)) {
            return response()->json([
                'success' => false,
                'message' => 'No se encontró el documento en el servidor.',
            ], 404);
        }

        $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
        $nombreDescarga = $modelo->id_modelo_contrato.'.'.$extension;

        return response()->download($ruta, $nombreDescarga, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function updateEstado(Request $request, $id)
    {
        $validated = $request->validate([
            'estado' => ['required', 'integer', 'in:0,1'],
        ], [
            'estado.required' => 'El estado es obligatorio.',
            'estado.in' => 'El estado debe ser 0 o 1.',
        ]);

        $estadoSolicitado = (int) $validated['estado'];
        if ($estadoSolicitado === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Siempre debe existir un modelo de contrato activo. No se puede inhabilitar.',
            ], 422);
        }

        $modelo = DB::transaction(function () use ($id) {
            // Al activar uno, todos los demás deben quedar inactivos.
            Modelo_Contrato::query()
                ->where('id_modelo_contrato', '<>', $id)
                ->where('estado', 1)
                ->update(['estado' => 0]);

            $modelo = Modelo_Contrato::lockForUpdate()->findOrFail($id);
            if ((int) $modelo->estado !== 1) {
                $modelo->estado = 1;
                $modelo->save();
            }

            return $modelo;
        });

        return response()->json([
            'success' => true,
            'message' => 'Modelo de contrato activado correctamente. Los demás modelos fueron desactivados.',
            'data' => [
                'id_modelo_contrato' => $modelo->id_modelo_contrato,
                'estado' => (int) $modelo->estado,
            ],
        ]);
    }

    public function destroy($id)
    {
        $modelo = Modelo_Contrato::findOrFail($id);

        $tieneFeriasAsignadas = Feria::query()
            ->where('id_modelocontrato', $modelo->id_modelo_contrato)
            ->exists();

        if ($tieneFeriasAsignadas) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar el modelo porque ya está asignado a una o más ferias.',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $rutaDocumento = null;
            if (!empty($modelo->ruta_documento)) {
                $rutaDocumento = str_replace('\\', '/', ltrim($modelo->ruta_documento, '/'));
            }

            $modelo->delete();

            if ($rutaDocumento && str_starts_with($rutaDocumento, self::DOCUMENTS_DIR . '/')
                && ! in_array('..', explode('/', $rutaDocumento), true)) {
                File::delete(public_path($rutaDocumento));
                Storage::disk('private')->delete($rutaDocumento);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Modelo de contrato eliminado correctamente.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'fecha_creacion' => ['required', 'date'],
            'tipo_documento' => ['required', 'integer', 'in:1,2'],
            'archivo_modelo' => ['required', 'file', 'mimes:doc,docx', 'max:10240'],
        ], [
            'nombre.required' => 'El nombre es obligatorio.',
            'fecha_creacion.required' => 'La fecha de creación es obligatoria.',
            'fecha_creacion.date' => 'La fecha de creación no es válida.',
            'tipo_documento.required' => 'El tipo de documento es obligatorio.',
            'tipo_documento.in' => 'El tipo de documento debe ser 1 (Contrato) o 2 (Adenda).',
            'archivo_modelo.required' => 'Debe seleccionar un archivo Word.',
            'archivo_modelo.mimes' => 'Solo se permiten archivos Word (.doc o .docx).',
        ]);

        $tipoDocumento = (int) $validated['tipo_documento'];

        $directorioRelativo = self::DOCUMENTS_DIR;
        $directorioPublico = public_path($directorioRelativo);

        $archivo = $validated['archivo_modelo'];
        $extension = strtolower((string) $archivo->extension());
        if (! in_array($extension, ['doc', 'docx'], true)) {
            return response()->json(['success' => false, 'message' => 'El formato del documento no es válido.'], 422);
        }
        $rutaArchivoTemporal = null;
        $rutaArchivoGuardado = null;

        try {
            // 1️⃣ Subir archivo temporal para verificación
            $nombreArchivoTemp = 'temp_modelo_' . \Illuminate\Support\Str::uuid() . '.' . $extension;
            $directorioTemporal = sys_get_temp_dir();
            $rutaArchivoTemporal = $directorioTemporal . DIRECTORY_SEPARATOR . $nombreArchivoTemp;
            $archivo->move($directorioTemporal, $nombreArchivoTemp);

            // LIMPIAR el archivo DOCX para remover fragmentos XML que rompen marcadores
            self::limpiarDocxFragmentado($rutaArchivoTemporal);

            // 2️⃣ VERIFICAR MARCADORES EN LA PLANTILLA SEGÚN TIPO DE DOCUMENTO
            $verificacion = ContratoTemplateVerifier::verificar($rutaArchivoTemporal, $tipoDocumento);
            
            if (!$verificacion['valido']) {
                // Plantilla inválida - eliminar archivo temporal y retornar error
                if (File::exists($rutaArchivoTemporal)) {
                    File::delete($rutaArchivoTemporal);
                }
                
                return response()->json([
                    'success' => false,
                    'message' => $verificacion['mensaje'],
                    'error_detail' => [
                        'faltantes' => $verificacion['faltantes'],
                        'extras' => $verificacion['extras'],
                        'marcadores_encontrados' => $verificacion['marcadores_encontrados'],
                    ],
                ], 422);
            }

            // 3️⃣ PLANTILLA VÁLIDA - Proceder a guardar
            DB::beginTransaction();

            $modelo = Modelo_Contrato::create([
                'nombre' => trim($validated['nombre']),
                'fecha' => $validated['fecha_creacion'],
                'tipo' => $tipoDocumento,
                'estado' => 0,
                'ruta_documento' => null,
            ]);

            // Renombrar archivo temporal al nombre final
            $nombreArchivo = $modelo->id_modelo_contrato . '.' . $extension;
            File::ensureDirectoryExists($directorioPublico);
            $rutaArchivoFinal = $directorioPublico . DIRECTORY_SEPARATOR . $nombreArchivo;
            File::move($rutaArchivoTemporal, $rutaArchivoFinal);

            $rutaDocumento = $directorioRelativo . '/' . $nombreArchivo;
            $modelo->ruta_documento = $rutaDocumento;
            $modelo->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Modelo de ' . DocumentTypeValidator::getNombreTipo($tipoDocumento) . ' registrado correctamente. ✅ Plantilla validada.',
                'data' => [
                    'id_modelo_contrato' => $modelo->id_modelo_contrato,
                    'nombre' => $modelo->nombre,
                    'fecha_creacion' => $modelo->fecha,
                    'tipo' => (int) $modelo->tipo,
                    'tipo_nombre' => DocumentTypeValidator::getNombreTipo($modelo->tipo),
                    'estado' => (int) $modelo->estado,
                    'ruta_documento' => $modelo->ruta_documento,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            // Limpiar archivos
            if ($rutaArchivoTemporal && File::exists($rutaArchivoTemporal)) {
                File::delete($rutaArchivoTemporal);
            }
            if ($rutaArchivoGuardado && File::exists($rutaArchivoGuardado)) {
                File::delete($rutaArchivoGuardado);
            }

            throw $e;
        }
    }

    /**
     * Limpia el archivo DOCX removiendo fragmentos XML que rompen los marcadores
     * Esto debe ejecutarse ANTES de usar el archivo con PhpOffice
     */
    public static function limpiarDocxFragmentado($rutaDocx)
    {
        try {
            if (!file_exists($rutaDocx)) {
                return;
            }

            $zip = new \ZipArchive();
            if ($zip->open($rutaDocx) !== true) {
                return;
            }

            $xmlOriginal = $zip->getFromName('word/document.xml');
            if (!$xmlOriginal) {
                $zip->close();
                return;
            }

            $xmlLimpio = self::consolidarMarcadoresEnXml($xmlOriginal);

            $zip->addFromString('word/document.xml', $xmlLimpio);
            $zip->close();

        } catch (\Throwable $e) {
            // No es crítico, continuar igual
        }
    }

    public static function aplicarBloquePagoRestante(string $rutaDocx, bool $mostrar): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($rutaDocx) !== true) {
            throw new \RuntimeException('No se pudo abrir la plantilla Word para procesar el bloque de pago.');
        }

        $xml = $zip->getFromName('word/document.xml');
        if (!$xml) {
            $zip->close();
            throw new \RuntimeException('No se encontró document.xml en la plantilla Word.');
        }

        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        if (!@$dom->loadXML($xml)) {
            $zip->close();
            throw new \RuntimeException('No se pudo leer la estructura XML de la plantilla Word.');
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $aperturas = [];
        $cierres = [];

        foreach ($xpath->query('//w:p') as $parrafo) {
            $texto = trim($parrafo->textContent);
            if ($texto === '{plan_pago_resto}') {
                $aperturas[] = $parrafo;
            } elseif ($texto === '{/plan_pago_resto}') {
                $cierres[] = $parrafo;
            }
        }

        if (count($aperturas) === 0 && count($cierres) === 0) {
            $zip->close();
            return;
        }

        if (count($aperturas) !== 1 || count($cierres) !== 1) {
            $zip->close();
            throw new \RuntimeException('El bloque de pago restante debe tener una apertura y un cierre válidos.');
        }

        $apertura = $aperturas[0];
        $cierre = $cierres[0];
        $contenedor = $apertura->parentNode;
        if (!$contenedor || $contenedor !== $cierre->parentNode) {
            $zip->close();
            throw new \RuntimeException('Los marcadores del bloque de pago restante deben estar en el mismo nivel del documento.');
        }

        if ($mostrar) {
            $contenedor->removeChild($apertura);
            $contenedor->removeChild($cierre);
        } else {
            $nodo = $apertura;
            while ($nodo) {
                $siguiente = $nodo->nextSibling;
                $contenedor->removeChild($nodo);
                if ($nodo === $cierre) {
                    break;
                }
                $nodo = $siguiente;
            }
        }

        $xmlProcesado = $dom->saveXML();
        if (!$xmlProcesado || $zip->addFromString('word/document.xml', $xmlProcesado) !== true) {
            $zip->close();
            throw new \RuntimeException('No se pudo guardar el bloque procesado en la plantilla Word.');
        }

        $zip->close();
    }

    /**
     * Consolida runs fragmentados que contienen marcadores {variable}
     * 
     * El problema: Word divide {marcador} en múltiples <w:r> con distintos rsidR:
     *   <w:r rsidR="A"><w:t>{</w:t></w:r>
     *   <w:r rsidR="B"><w:t>marcador</w:t></w:r>
     *   <w:r rsidR="C"><w:t>}</w:t></w:r>
     * 
     * La solución: buscar estos patrones y unir el texto dentro de un único run.
     */
    private static function consolidarMarcadoresEnXml($xml)
    {
        // Paso 0: Consolidar runs consecutivos que no tienen styles/props diferentes
        // Esto une cualquier <w:r>text1</w:r><w:r>text2</w:r> en un solo run
        $xml = preg_replace(
            '/<\/w:t><\/w:r><w:r[^>]*?><w:t([^>]*)>/u',
            '',
            $xml
        );
        
        // Paso 1: Remover proofErr
        $xml = preg_replace('/<w:proofErr[^>]*\/>/ui', '', $xml);

        // Paso 2: Remover w:hint="cs" - evita que Word use la fuente Complex Script
        $xml = preg_replace('/(<w:rFonts[^>]*)\s+w:hint="cs"([^>]*>)/ui', '$1$2', $xml);
        $xml = preg_replace('/\s+w:hint="cs"/ui', '', $xml);

        // Paso 3: Sustituir Helvetica por Arial
        // Helvetica es una fuente PostScript Type1 con encoding limitado para caracteres
        // acentuados. Arial tiene soporte Unicode completo y mantiene el mismo diseño visual.
        $xml = str_replace(
            ['w:ascii="Helvetica"', 'w:hAnsi="Helvetica"', 'w:cs="Helvetica"', 'w:eastAsia="Helvetica"'],
            ['w:ascii="Arial"',     'w:hAnsi="Arial"',     'w:cs="Arial"',     'w:eastAsia="Arial"'],
            $xml
        );

        // Paso 4: Usar DOMDocument para consolidar runs dentro de cada párrafo
        $dom = new \DOMDocument();
        $dom->preserveWhiteSpace = true;
        
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($xml);
        libxml_clear_errors();
        
        if (!$loaded) {
            return self::limpiarXmlConRegex($xml);
        }

        $xpath = new \DOMXPath($dom);
        // Registrar namespace de Word
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        // Obtener todos los párrafos
        $parrafos = $xpath->query('//w:p');

        foreach ($parrafos as $parrafo) {
            self::consolidarRunsEnParrafo($parrafo, $dom, $xpath);
        }

        $xmlFinal = $dom->saveXML();
        return $xmlFinal ?: $xml;
    }

    /**
     * Dentro de un párrafo, buscar runs consecutivos cuyo texto combinado forme {marcador}
     * y unirlos en un único run.
     */
    private static function consolidarRunsEnParrafo(\DOMElement $parrafo, \DOMDocument $dom, \DOMXPath $xpath)
    {
        // Obtener todos los <w:r> del párrafo (directos)
        $runs = $xpath->query('w:r', $parrafo);
        if (!$runs || $runs->length === 0) return;

        // Construir array de runs con su texto
        $runArray = [];
        foreach ($runs as $run) {
            $textos = $xpath->query('w:t', $run);
            $textoCompleto = '';
            foreach ($textos as $t) {
                $textoCompleto .= $t->textContent;
            }
            $runArray[] = ['node' => $run, 'texto' => $textoCompleto];
        }

        // Buscar y consolidar todos los marcadores, incluso varios dentro de un mismo run.
        $i = 0;
        $offsetTexto = 0;
        while ($i < count($runArray)) {
            $texto = $runArray[$i]['texto'];
            $inicioMarcador = strpos($texto, '{', $offsetTexto);

            if ($inicioMarcador === false) {
                $i++;
                $offsetTexto = 0;
                continue;
            }

            $acumulado = $texto;
            $finMarcador = strpos($acumulado, '}', $inicioMarcador);
            $j = $i + 1;

            while ($finMarcador === false && $j < count($runArray)) {
                $acumulado .= $runArray[$j]['texto'];
                $finMarcador = strpos($acumulado, '}', $inicioMarcador);
                $j++;
            }

            $textoDesdeInicio = substr($acumulado, $inicioMarcador);
            if ($finMarcador === false || !preg_match('/^\{\/?[a-zA-Z0-9_]+\}/', $textoDesdeInicio, $matches)) {
                $offsetTexto = $inicioMarcador + 1;
                continue;
            }

            // Fusionar los runs que atraviesa este marcador en el primero.
            $runPrincipal = $runArray[$i]['node'];
            $textosPrincipal = $xpath->query('w:t', $runPrincipal);
            $tPrincipal = $textosPrincipal->item(0);

            if ($tPrincipal) {
                $tPrincipal->textContent = $acumulado;
                if (strpos($acumulado, ' ') !== false && $tPrincipal instanceof \DOMElement) {
                    $tPrincipal->setAttribute('xml:space', 'preserve');
                }

                for ($textIndex = 1; $textIndex < $textosPrincipal->length; $textIndex++) {
                    $textosPrincipal->item($textIndex)->textContent = '';
                }
            }

            for ($k = $i + 1; $k < $j; $k++) {
                $nodo = $runArray[$k]['node'];
                if ($nodo->parentNode) {
                    $nodo->parentNode->removeChild($nodo);
                }
            }

            $runArray[$i]['texto'] = $acumulado;
            array_splice($runArray, $i + 1, $j - $i - 1);
            $offsetTexto = $inicioMarcador + strlen($matches[0]);
        }
    }

    /**
     * Fallback: limpieza con regex cuando DOMDocument no puede parsear
     * Intenta consolidar marcadores fragmentados reemplazando los límites de runs
     */
    private static function limpiarXmlConRegex($xml)
    {
        // Remover proofErr
        $xml = preg_replace('/<w:proofErr[^>]*\/>/ui', '', $xml);
        
        // Consolidar marcadores fragmentados usando regex
        // Patrón: </w:t></w:r><w:r...><w:t> entre caracteres
        // Esto une runs que están separando partes de un marcador
        
        // Paso 1: Remover tags innecesarios entre el contenido de texto
        // <w:t>parte1</w:t></w:r><w:r rsidR="..."><w:t>parte2</w:t>
        // → <w:t>parte1parte2</w:t>
        $xml = preg_replace(
            '/<w:t>([^<]*)<\/w:t><\/w:r><w:r[^>]*(?:<w:rPr[^>]*>.*?<\/w:rPr>)?><w:t>/',
            '<w:t>$1',
            $xml
        );
        
        // Paso 2: Consolidar runs que contienen "{" y "}"
        // En caso de {marcador} fragmentado como { } marcador } }
        $xml = preg_replace(
            '/<w:t>([^<]*\{[^<}]*)<\/w:t><\/w:r>.*?<w:r[^>]*><w:t>([^<}]*\}[^<]*)<\/w:t>/',
            '<w:t>$1$2</w:t>',
            $xml
        );
        
        return $xml;
    }
}

