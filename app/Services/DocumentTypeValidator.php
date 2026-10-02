<?php

namespace App\Services;

/**
 * Validador genérico de tipos de documentos (Contrato, Adenda, etc.)
 * Define los marcadores requeridos para cada tipo y reutiliza la lógica común
 */
class DocumentTypeValidator
{
    // Constantes para tipos de documento
    public const TYPE_CONTRATO = 1;
    public const TYPE_ADENDA = 2;

    /**
     * Marcadores requeridos por tipo de documento
     */
    private static $marcadoresPorTipo = [
        self::TYPE_CONTRATO => [
            // Encabezado
            'codigo_contrato',
            // Fechas
            'fecha_inicio', 'fecha_fin',
            // Empresa
            'nombre_empresa', 'direccion', 'telefono', 'email', 'web', 'ciudad', 'pais', 'nit',
            // Representante
            'representante_legal', 'ci_representante', 'telefono_representante', 'ci_gerente', 'exp_ci_gerente',
            // Documentación
            'productos', 'escritura', 'fecha_escritura', 'matricula', 'poder', 'notaria', 'fecha_poder', 'distrito', 'observaciones',
            // Stand
            'nombre_pabellon', 'metraje', 'credenciales', 'tipo_credenciales', 'stand',
            // Económico
            'precio_unit', 'potencia', 'monto1', 'monto_literal', 'cent', 'descuento_texto',
            // Plan de pagos
            'porcentaje_inicial', 'monto_inicial', 'fecha_inicial', 'porcentaje_resto', 'monto_resto', 'fecha_final',
            // Firma
            'fecha_contrato', 'nombre_gerente',
        ],
        self::TYPE_ADENDA => [
            'evento',
            'nombre_empresa',
            'nit',
            'pass',
        ],
    ];

    /**
     * Obtiene los marcadores requeridos para un tipo específico
     * 
     * @param int $tipo Tipo de documento (1=Contrato, 2=Adenda)
     * @return array Lista de marcadores requeridos
     */
    public static function getMarcadoresRequeridos($tipo = self::TYPE_CONTRATO)
    {
        return self::$marcadoresPorTipo[$tipo] ?? self::$marcadoresPorTipo[self::TYPE_CONTRATO];
    }

    /**
     * Obtiene el nombre legible del tipo de documento
     * 
     * @param int $tipo Tipo de documento
     * @return string Nombre del tipo
     */
    public static function getNombreTipo($tipo)
    {
        $nombres = [
            self::TYPE_CONTRATO => 'Contrato',
            self::TYPE_ADENDA => 'Adenda',
        ];

        return $nombres[$tipo] ?? 'Desconocido';
    }

    /**
     * Verifica si un tipo de documento es válido
     * 
     * @param int $tipo Tipo de documento
     * @return bool
     */
    public static function esValidoTipo($tipo)
    {
        return isset(self::$marcadoresPorTipo[$tipo]);
    }

    /**
     * Obtiene todos los tipos disponibles
     * 
     * @return array [tipo => nombre, ...]
     */
    public static function getTiposDisponibles()
    {
        $tipos = [];
        foreach (array_keys(self::$marcadoresPorTipo) as $tipo) {
            $tipos[$tipo] = self::getNombreTipo($tipo);
        }
        return $tipos;
    }
}
