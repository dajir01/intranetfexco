<?php

namespace App\Exports;

use App\Models\Contrato;
use App\Models\Feria;
use App\Models\Stand;
use App\Models\Pabellon;
use App\Models\Rubro;
use App\Models\Subrubro;
use App\Models\Pais;
use App\Models\Localidad;
use App\Models\Usuario;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;

class ContratosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    protected $idFeria;
    protected $stands;
    protected $pabellones;
    protected $rubros;
    protected $subrubros;
    protected $paises;
    protected $localidades;
    protected $usuarios;

    public function __construct($idFeria)
    {
        $this->idFeria = $idFeria;
        
        // Pre-cargar todos los datos necesarios para evitar queries en bucle
        $this->precargarDatos();
    }

    /**
     * Pre-cargar todos los datos relacionados una sola vez
     */
    protected function precargarDatos()
    {
        // Cargar stands de la feria
        $this->stands = Stand::where('feria', $this->idFeria)
            ->get()
            ->keyBy('id_stand');

        // Cargar pabellones de la feria
        $this->pabellones = Pabellon::where('feria', $this->idFeria)
            ->get()
            ->keyBy('id_pabellon');

        // Cargar catálogos generales
        $this->rubros = Rubro::all()->keyBy('id_rubro');
        $this->subrubros = Subrubro::all()->keyBy('id_subrubro');
        $this->paises = Pais::all()->keyBy('id');
        $this->localidades = Localidad::all()->keyBy('id');
        $this->usuarios = Usuario::all()->keyBy('id_usuario');
    }

    /**
     * Consulta optimizada con eager loading
     */
    public function collection()
    {
        return Contrato::with([
            'empresa',
            'feria'
        ])
        ->where('id_feria', $this->idFeria)
        ->orderBy('codigo_contrato', 'asc')
        ->get();
    }

    /**
     * Encabezados de las columnas
     */
    public function headings(): array
    {
        return [
            'Nombre de la Empresa',
            'NIT',
            'Nombre del Gerente/Responsable',
            'CI Gerente/Responsable',
            'Cargo del Gerente/Responsable',
            'Teléfono',
            'Fax',
            'Correo Electrónico',
            'Página Web',
            'País',
            'Ciudad',
            'Dirección',
            'Código de Contrato',
            'Feria',
            'Pabellón',
            'Stands',
            'Nro. Credenciales',
            'Metraje Total (m²)',
            'Precio por m²',
            'Precio Total',
            'Descuento (%)',
            'Precio con Descuento',
            'Tipo de Descuento',
            'Rubro',
            'Subrubro',
            'Otros Rubros',
            'Marcas Nacionales',
            'Marcas Internacionales',
            'Marcas Multinacionales',
            'Productos',
            'Observaciones',
            'Usuario Reserva',
            'Usuario Contrato',
        ];
    }

    /**
     * Mapear cada contrato a una fila del Excel
     */
    public function map($contrato): array
    {
        $empresa = $contrato->empresa;
        $feria = $contrato->feria;

        // Obtener stands del contrato
        $standsInfo = $this->obtenerStandsContrato($contrato);
        
        // Obtener información de país y ciudad
        $paisNombre = $this->obtenerNombrePais($empresa->pais ?? null);
        $ciudadNombre = $this->obtenerNombreCiudad($empresa->ciudad ?? null);

        // Obtener rubro y subrubro
        $rubroNombre = $this->obtenerNombreRubro($empresa->rubro ?? null);
        $subrubroNombre = $this->obtenerNombreSubrubro($empresa->subrubro ?? null);

        // Obtener usuarios
        $usuarioReserva = $this->obtenerNombreUsuario($contrato->id_usuario_reserva ?? null);
        $usuarioContrato = $this->obtenerNombreUsuario($contrato->id_usuario_contrato ?? null);

        // Formatear código de contrato
        $codigoContrato = $feria ? 
            ($feria->codigo_contrato . str_pad($contrato->codigo_contrato, 5, '0', STR_PAD_LEFT)) : 
            $contrato->codigo_contrato;

        // Calcular precio con descuento
        $precioTotal = $contrato->precio_total ?? 0;
        $descuento = $contrato->descuento ?? 0;
        $precioConDescuento = $precioTotal;
        
        if ($descuento > 0) {
            $precioConDescuento = $precioTotal - ($precioTotal * ($descuento / 100));
        }

        // Otros rubros
        $otrosRubros = $empresa->otro_rubro ?? '';

        // Obtener nombre del gerente/responsable (priorizar gerente)
        $nombreGerenteResponsable = $empresa->nombre_gerente ?? $empresa->nombre_responsable ?? '';
        
        // Obtener CI del gerente/responsable (priorizar gerente)
        $ciGerente = $empresa->ci_gerente ?? $empresa->ci_responsable ?? '';
        $expCi = $empresa->exp_ci_gerente ?? $empresa->exp_ci_responsable ?? '';
        $ciCompleto = trim($ciGerente . ($expCi ? ' ' . $expCi : ''));

        // Obtener marcas separadas por tipo (corregido según especificación)
        $marcasNacionales = $this->formatearMarcaNacional($contrato);
        $marcasInternacionales = $this->formatearPaisPrincipal($contrato);
        $marcasMultinacionales = $this->formatearMarcasSecundarias($contrato);

        return [
            $empresa->nombre_empresa ?? 'N/A',
            $empresa->nit ?? '',
            $nombreGerenteResponsable,
            $ciCompleto,
            $empresa->cargo_gerente ?? '',
            $empresa->telefono ?? '',
            $empresa->fax ?? '',
            $empresa->email ?? '',
            $empresa->web ?? '',
            $paisNombre,
            $ciudadNombre,
            $empresa->direccion ?? '',
            $codigoContrato,
            $feria->nombre_feria ?? '',
            $standsInfo['pabellon'],
            $standsInfo['stands_texto'],
            $contrato->nro_credenciales ?? 0,
            round($standsInfo['metraje_total'], 2),
            round($contrato->precio_unit ?? 0, 2),
            round($precioTotal, 2),
            $descuento,
            round($precioConDescuento, 2),
            $contrato->tipo_descuento ?? '',
            $rubroNombre,
            $subrubroNombre,
            $otrosRubros,
            $marcasNacionales,
            $marcasInternacionales,
            $marcasMultinacionales,
            $contrato->productos ?? '',
            $contrato->observaciones ?? '',
            $usuarioReserva,
            $usuarioContrato,
        ];
    }

    /**
     * Obtener información de stands del contrato
     */
    protected function obtenerStandsContrato($contrato): array
    {
        $standsArray = [];
        $pabellonNombre = 'N/A';
        $metrajeTotal = 0;

        // Verificar si el contrato está anulado
        if ($contrato->total_stands == 0) {
            return [
                'pabellon' => 'ANULADO',
                'stands_texto' => 'ANULADO',
                'metraje_total' => 0,
            ];
        }

        // Recorrer los 12 posibles stands
        for ($i = 1; $i <= 12; $i++) {
            $standField = "stand_$i";
            $idStand = $contrato->$standField;
            
            if ($idStand && isset($this->stands[$idStand])) {
                $stand = $this->stands[$idStand];
                $standsArray[] = $stand->numero_stand;
                
                // Obtener el pabellón del primer stand
                if ($pabellonNombre === 'N/A' && isset($this->pabellones[$stand->id_pabellon])) {
                    $pabellonNombre = $this->pabellones[$stand->id_pabellon]->nombre_pabellon;
                }
                
                $metrajeTotal += $stand->area_stand ?? 0;
            }
        }

        return [
            'pabellon' => $pabellonNombre,
            'stands_texto' => !empty($standsArray) ? implode(', ', $standsArray) : 'N/A',
            'metraje_total' => $metrajeTotal,
        ];
    }

    /**
     * Obtener nombre del país
     */
    protected function obtenerNombrePais($idPais): string
    {
        if (!$idPais || !isset($this->paises[$idPais])) {
            return '';
        }
        
        return $this->paises[$idPais]->nombre ?? '';
    }

    /**
     * Obtener nombre de la ciudad
     */
    protected function obtenerNombreCiudad($idCiudad): string
    {
        if (!$idCiudad || !isset($this->localidades[$idCiudad])) {
            return '';
        }
        
        return $this->localidades[$idCiudad]->nombre ?? '';
    }

    /**
     * Obtener nombre del rubro
     */
    protected function obtenerNombreRubro($idRubro): string
    {
        if (!$idRubro || !isset($this->rubros[$idRubro])) {
            return '';
        }
        
        return $this->rubros[$idRubro]->nombre_rubro ?? '';
    }

    /**
     * Obtener nombre del subrubro
     */
    protected function obtenerNombreSubrubro($idSubrubro): string
    {
        if (!$idSubrubro || !isset($this->subrubros[$idSubrubro])) {
            return '';
        }
        
        return $this->subrubros[$idSubrubro]->nombre_subrubro ?? '';
    }

    /**
     * Obtener nombre del usuario
     */
    protected function obtenerNombreUsuario($idUsuario): string
    {
        if (!$idUsuario || !isset($this->usuarios[$idUsuario])) {
            return '';
        }
        
        $usuario = $this->usuarios[$idUsuario];
        return $usuario->nombre_usuario ?? '';
    }

    /**
     * Formatear marca nacional (marca_principal)
     * Parsea formato: "NombreMarca;idPais" y muestra "NombreMarca (NombrePais)"
     */
    protected function formatearMarcaNacional($contrato): string
    {
        $marcaPrincipal = $contrato->marca_principal ?? '';
        
        if (empty($marcaPrincipal)) {
            return '';
        }
        
        return $this->parsearMarcaConPais($marcaPrincipal);
    }

    /**
     * Formatear país principal como marca internacional (pais_principal)
     * Convierte ID de país a nombre, o devuelve el valor si no es numérico
     */
    protected function formatearPaisPrincipal($contrato): string
    {
        $paisPrincipal = $contrato->pais_principal ?? null;
        
        if (empty($paisPrincipal)) {
            return '';
        }
        
        // Si es numérico, convertir ID a nombre de país
        if (is_numeric($paisPrincipal)) {
            return $this->obtenerNombrePais($paisPrincipal);
        }
        
        // Si no es numérico, parsear por si tiene formato "marca, id"
        return $this->parsearMarcaConPais($paisPrincipal);
    }

    /**
     * Formatear marcas secundarias como multinacionales (marcas_secundarios)
     * Parsea formato: "Marca1, idPais1" con saltos de línea para múltiples marcas
     */
    protected function formatearMarcasSecundarias($contrato): string
    {
        $marcasSecundarios = $contrato->marcas_secundarios ?? '';
        
        if (empty($marcasSecundarios)) {
            return '';
        }
        
        // Separar por saltos de línea o barras verticales para múltiples marcas
        $marcasArray = preg_split('/[\r\n|]+/', $marcasSecundarios);
        $marcasFormateadas = [];
        
        foreach ($marcasArray as $marca) {
            $marca = trim($marca);
            if (!empty($marca)) {
                $marcasFormateadas[] = $this->parsearMarcaConPais($marca);
            }
        }
        
        return !empty($marcasFormateadas) ? implode(', ', $marcasFormateadas) : '';
    }

    /**
     * Parsear marca en formato "NombreMarca, idPais" y convertir a "NombreMarca (NombrePais)"
     * Soporta ambos formatos: "marca, 144" y "marca;144"
     */
    protected function parsearMarcaConPais($marcaTexto): string
    {
        if (empty($marcaTexto)) {
            return '';
        }
        
        // Verificar si tiene el formato "marca, id_pais" (con coma)
        if (strpos($marcaTexto, ',') !== false) {
            $partes = explode(',', $marcaTexto, 2);
            $nombreMarca = trim($partes[0]);
            $idPais = trim($partes[1]);
            
            // Convertir ID de país a nombre
            if (is_numeric($idPais)) {
                $nombrePais = $this->obtenerNombrePais($idPais);
                return $nombreMarca . ($nombrePais ? ' (' . $nombrePais . ')' : '');
            }
            
            return $nombreMarca;
        }
        
        // Verificar si tiene el formato "marca;id_pais" (con punto y coma)
        if (strpos($marcaTexto, ';') !== false) {
            $partes = explode(';', $marcaTexto, 2);
            $nombreMarca = trim($partes[0]);
            $idPais = trim($partes[1]);
            
            // Convertir ID de país a nombre
            if (is_numeric($idPais)) {
                $nombrePais = $this->obtenerNombrePais($idPais);
                return $nombreMarca . ($nombrePais ? ' (' . $nombrePais . ')' : '');
            }
            
            return $nombreMarca;
        }
        
        // Si no tiene coma ni punto y coma, devolver tal cual
        return $marcaTexto;
    }

    /**
     * Aplicar estilos al Excel
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo para el encabezado (fila 1)
        $sheet->getStyle('A1:AG1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 11,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Auto-filtro en la primera fila
        $sheet->setAutoFilter('A1:AG1');

        // Congelar la primera fila
        $sheet->freezePane('A2');

        // Alineación para columnas numéricas
        $sheet->getStyle('Q:Q')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Nro. Credenciales
        $sheet->getStyle('R:R')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Metraje
        $sheet->getStyle('S:S')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Precio m2
        $sheet->getStyle('T:T')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Precio total
        $sheet->getStyle('U:U')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Descuento
        $sheet->getStyle('V:V')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT); // Precio con desc
        $sheet->getStyle('W:W')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); // Tipo de Descuento

        return [];
    }

    /**
     * Anchos de columnas
     */
    public function columnWidths(): array
    {
        return [
            'A' => 30, // Empresa
            'B' => 15, // NIT
            'C' => 30, // Nombre Gerente/Responsable
            'D' => 18, // CI Gerente/Responsable
            'E' => 25, // Cargo
            'F' => 15, // Teléfono
            'G' => 15, // Fax
            'H' => 30, // Correo Electrónico
            'I' => 25, // Página Web
            'J' => 15, // País
            'K' => 20, // Ciudad
            'L' => 25, // Feria
            'M' => 30, // Dirección
            'N' => 18, // Código Contrato
            'O' => 15, // Pabellón
            'P' => 20, // Stands
            'Q' => 15, // Nro. Credenciales
            'R' => 15, // Metraje
            'S' => 15, // Precio m2
            'T' => 15, // Precio Total
            'U' => 12, // Descuento
            'V' => 18, // Precio con Desc
            'W' => 18, // Tipo de Descuento
            'X' => 25, // Rubro
            'Y' => 25, // Subrubro
            'Z' => 30, // Otros Rubros
            'AA' => 30, // Marcas Nacionales
            'AB' => 30, // Marcas Internacionales
            'AC' => 30, // Marcas Multinacionales
            'AD' => 35, // Productos
            'AE' => 35, // Observaciones
            'AF' => 20, // Usuario Reserva
            'AG' => 20, // Usuario Contrato
        ];
    }

    /**
     * Título de la hoja
     */
    public function title(): string
    {
        return 'Listado ' . Carbon::now()->format('d-m-Y');
    }
}
