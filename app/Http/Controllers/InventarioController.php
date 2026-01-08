<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Area;
use App\Models\Asignacion_Producto;
use App\Models\Baja_Producto;
use App\Models\Detalle_Ingreso;
use App\Models\Detalle_Movimiento;
use App\Models\Ingreso;
use App\Models\Movimiento;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Usuario;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

// Subclase FPDF para encabezado con número de página y número de ingreso.
// Definida fuera del controller para evitar error "Class declarations may not be nested".
if (!class_exists('IngresoPDF')) {
    class IngresoPDF extends \FPDF {
        public $numPadded;
        public $fechaIngreso; // fecha y hora cruda (string) para formatear en Header
        
        /**
         * Método para dibujar texto rotado (marca de agua)
         * Compatible con FPDF estándar
         */
        public function rotatedWatermark($text, $fontSize, $color = [255, 0, 0], $transparency = 0.3)
        {
            // Guardar estado actual
            $x = $this->GetX();
            $y = $this->GetY();
            
            // Establecer fuente
            $this->SetFont('Arial', 'B', $fontSize);
            
            // Posición: centro de la página
            $pageWidth = $this->w;
            $centerX = 60;
            $centerY = 20; // Posición vertical centrada
            
            // Ángulo de rotación
            $angle = -35;
            $angleRad = deg2rad($angle);
            
            // Calcular transformación
            $cos = cos($angleRad);
            $sin = sin($angleRad);
            
            // Convertir de mm a puntos (1 mm = 2.834645669291339 puntos)
            $xPt = $centerX * 2.834645669291339;
            $yPt = ($this->h - $centerY) * 2.834645669291339;
            
            // Normalizar color RGB (0-1)
            $r = $color[0] / 255;
            $g = $color[1] / 255;
            $b = $color[2] / 255;
            
            // Guardar estado gráfico
            $this->_out('q');
            
            // Aplicar color rojo en el stream PDF
            $this->_out(sprintf('%.3F %.3F %.3F rg', $r, $g, $b)); // Fill color
            $this->_out(sprintf('%.3F %.3F %.3F RG', $r, $g, $b)); // Stroke color
            
            // Comenzar bloque de texto con transformación
            $this->_out('BT');
            $this->_out(sprintf('%.4F %.4F %.4F %.4F %.2F %.2F Tm', $cos, $sin, -$sin, $cos, $xPt, $yPt));
            $this->_out('(' . $this->_escape($text) . ') Tj');
            $this->_out('ET');
            
            // Restaurar estado gráfico
            $this->_out('Q');
            
            // Restaurar posición
            $this->SetXY($x, $y);
        }
        
        function Header() {
            // Reducir margen superior
            $this->SetY(10); // Antes era 15 por defecto
            $this->SetFont('Arial', 'B', 10);
            // Logo PNG a la izquierda y Cochabamba centrado verticalmente
            $logoPath = base_path('resources/images/logo3.png');
            $logoHeight = 14; // mm
            $logoWidth = 0; // auto
            $logoY = $this->GetY();
            $logoX = 15; // margen izquierdo
            $cochaY = $logoY + ($logoHeight / 2) - 2.5;
            $rightX = 120; // posición derecha para N° y fecha
            if (file_exists($logoPath)) {
                $this->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight); // X, Y, W, H
                $this->SetXY($logoX + 22, $cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');
                // N° y fecha alineados a la derecha, misma altura que el logo
                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° NI-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                $fechaStr = '';
                if (!empty($this->fechaIngreso)) {
                    $ts = strtotime($this->fechaIngreso);
                    if ($ts) {
                        $fechaStr = date('d/m/Y H:i', $ts);
                    }
                }
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            } else {
                $this->SetY($cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');
                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° NI-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                $fechaStr = '';
                if (!empty($this->fechaIngreso)) {
                    $ts = strtotime($this->fechaIngreso);
                    if ($ts) {
                        $fechaStr = date('d/m/Y H:i', $ts);
                    }
                }
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            }
            $this->Ln(3);
        }
    }
}

// Subclase FPDF para salidas con la misma estética que IngresoPDF.
if (!class_exists('SalidaPDF')) {
    class SalidaPDF extends \FPDF {
        public $numPadded;
        public $fechaSalida;

        public function rotatedWatermark($text, $fontSize, $color = [255, 0, 0], $transparency = 0.3)
        {
            $x = $this->GetX();
            $y = $this->GetY();
            $this->SetFont('Arial', 'B', $fontSize);

            $angle = -35;
            $angleRad = deg2rad($angle);
            $cos = cos($angleRad);
            $sin = sin($angleRad);

            $xPt = 60 * 2.834645669291339;
            $yPt = ($this->h - 20) * 2.834645669291339;

            $r = $color[0] / 255;
            $g = $color[1] / 255;
            $b = $color[2] / 255;

            $this->_out('q');
            $this->_out(sprintf('%.3F %.3F %.3F rg', $r, $g, $b));
            $this->_out(sprintf('%.3F %.3F %.3F RG', $r, $g, $b));
            $this->_out('BT');
            $this->_out(sprintf('%.4F %.4F %.4F %.4F %.2F %.2F Tm', $cos, $sin, -$sin, $cos, $xPt, $yPt));
            $this->_out('(' . $this->_escape($text) . ') Tj');
            $this->_out('ET');
            $this->_out('Q');

            $this->SetXY($x, $y);
        }

        function Header()
        {
            $this->SetY(10);
            $this->SetFont('Arial', 'B', 10);

            $logoPath = base_path('resources/images/logo3.png');
            $logoHeight = 14;
            $logoWidth = 0;
            $logoY = $this->GetY();
            $logoX = 15;
            $cochaY = $logoY + ($logoHeight / 2) - 2.5;
            $rightX = 120;

            $fechaStr = '';
            if (!empty($this->fechaSalida)) {
                $ts = strtotime($this->fechaSalida);
                if ($ts) {
                    $fechaStr = date('d/m/Y H:i', $ts);
                }
            }

            if (file_exists($logoPath)) {
                $this->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight);
                $this->SetXY($logoX + 22, $cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');

                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° SA-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            } else {
                $this->SetY($cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');

                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° SA-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            }
            $this->Ln(3);
        }
    }
}

// Subclase FPDF exclusiva para ingresos de almacén (prefijo IA) con encabezado independiente.
if (!class_exists('IngresoAlmacenPDF')) {
    class IngresoAlmacenPDF extends \FPDF {
        public $numPadded;
        public $fechaIngreso;

        /**
         * Marca de agua rotada opcional (misma firma que las otras clases PDF).
         */
        public function rotatedWatermark($text, $fontSize, $color = [255, 0, 0], $transparency = 0.3)
        {
            $x = $this->GetX();
            $y = $this->GetY();
            $this->SetFont('Arial', 'B', $fontSize);

            $angle = -35;
            $angleRad = deg2rad($angle);
            $cos = cos($angleRad);
            $sin = sin($angleRad);

            $xPt = 60 * 2.834645669291339;
            $yPt = ($this->h - 20) * 2.834645669291339;

            $r = $color[0] / 255;
            $g = $color[1] / 255;
            $b = $color[2] / 255;

            $this->_out('q');
            $this->_out(sprintf('%.3F %.3F %.3F rg', $r, $g, $b));
            $this->_out(sprintf('%.3F %.3F %.3F RG', $r, $g, $b));
            $this->_out('BT');
            $this->_out(sprintf('%.4F %.4F %.4F %.4F %.2F %.2F Tm', $cos, $sin, -$sin, $cos, $xPt, $yPt));
            $this->_out('(' . $this->_escape($text) . ') Tj');
            $this->_out('ET');
            $this->_out('Q');

            $this->SetXY($x, $y);
        }

        function Header()
        {
            $this->SetY(10);
            $this->SetFont('Arial', 'B', 10);

            $logoPath = base_path('resources/images/logo3.png');
            $logoHeight = 14;
            $logoWidth = 0;
            $logoY = $this->GetY();
            $logoX = 15;
            $cochaY = $logoY + ($logoHeight / 2) - 2.5;
            $rightX = 120;

            $fechaStr = '';
            if (!empty($this->fechaIngreso)) {
                $ts = strtotime($this->fechaIngreso);
                if ($ts) {
                    $fechaStr = date('d/m/Y H:i', $ts);
                }
            }

            if (file_exists($logoPath)) {
                $this->Image($logoPath, $logoX, $logoY, $logoWidth, $logoHeight);
                $this->SetXY($logoX + 22, $cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');

                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° IA-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            } else {
                $this->SetY($cochaY);
                $this->SetFont('Arial', 'B', 10);
                $this->Cell(60, 5, 'Cochabamba', 0, 0, 'L');

                $this->SetXY($rightX, $logoY);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 5, utf8_decode('N° IA-' . $this->numPadded), 0, 2, 'R');
                $this->SetFont('Arial', '', 9);
                if ($fechaStr !== '') {
                    $this->Cell(0, 5, utf8_decode('Fecha: ' . $fechaStr), 0, 2, 'R');
                }
                $this->Cell(0, 5, utf8_decode('Página: ') . $this->PageNo(), 0, 1, 'R');
            }
            $this->Ln(3);
        }
    }
}

// PDF específico para Kardex con logo y número de página
if (!class_exists('KardexPDF')) {
    class KardexPDF extends \FPDF {
        function Header()
        {
            $this->SetY(8);
            $logoPath = base_path('resources/images/logo3.png');
            if (file_exists($logoPath)) {
                // Logo a la izquierda
                $this->Image($logoPath, 8, 6, 22); // X, Y, Ancho
            }

            // Número de página en esquina superior derecha
            $this->SetFont('Arial', '', 8);
            $this->SetXY(-35, 8); // 35mm desde el borde derecho
            $this->Cell(0, 5, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');

            $this->Ln(10);
        }

        function Footer()
        {
            // Ubicar 12mm desde el borde inferior
            $this->SetY(-12);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 8, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
        }
    }
}

// PDF para el Reporte de Notas de Ingreso
if (!class_exists('ReporteIngresosPDF')) {
    class ReporteIngresosPDF extends \FPDF {
        function Header()
        {
            $this->SetY(8);
            $logoPath = base_path('resources/images/logo3.png');
            if (file_exists($logoPath)) {
                // Logo a la izquierda
                $this->Image($logoPath, 8, 6, 22); // X, Y, Ancho
            }

            // Número de página en esquina superior derecha
            $this->SetFont('Arial', '', 8);
            $this->SetXY(-35, 8); // 35mm desde el borde derecho
            $this->Cell(0, 5, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');

            $this->Ln(10);
        }

        function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 8, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
        }
    }

    class ReporteMovimientosPDF extends \FPDF {
        function Header()
        {
            $this->SetY(8);
            $logoPath = base_path('resources/images/logo3.png');
            if (file_exists($logoPath)) {
                // Logo a la izquierda
                $this->Image($logoPath, 8, 6, 22); // X, Y, Ancho
            }

            // Número de página en esquina superior derecha
            $this->SetFont('Arial', '', 8);
            $this->SetXY(-35, 8); // 35mm desde el borde derecho
            $this->Cell(0, 5, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');

            $this->Ln(10);
        }

        function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 8, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
        }
    }

    class ReporteProductosPDF extends \FPDF {
        function Header()
        {
            $this->SetY(8);
            $logoPath = base_path('resources/images/logo3.png');
            if (file_exists($logoPath)) {
                // Logo a la izquierda
                $this->Image($logoPath, 8, 6, 22); // X, Y, Ancho
            }

            // Número de página en esquina superior derecha
            $this->SetFont('Arial', '', 8);
            $this->SetXY(-35, 8); // 35mm desde el borde derecho
            $this->Cell(0, 5, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');

            $this->Ln(10);
        }

        function Footer()
        {
            $this->SetY(-12);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 8, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'R');
        }
    }
}

class InventarioController extends Controller
{
    public function index()
    {
        // Lógica para mostrar el inventario
        return view('inventario.index');
    }
    /**
     * Lista de productos con búsqueda, orden y paginación.
     * Parámetros opcionales:
     * - q: término de búsqueda (código, nombre, código de barras)
     * - page: página (por defecto 1)
     * - per_page: elementos por página (1..100, por defecto 10)
     * - sort_by: columna de orden (id_producto, codigo, nombre, stock_actual, costo_total)
     * - sort_dir: asc|desc (por defecto desc)
     */
    public function productos(Request $request): JsonResponse
    {
        $allowedSort = ['area', 'codigo', 'nombre', 'tipo', 'unidad_medida', 'stock', 'costo_total'];

        $search = (string) $request->input('q', '');
        $searchCodigo = (string) $request->input('codigo', '');
        $areaIdsCsv = $request->input('area_ids');
        $tipoProducto = (string) $request->input('tipo', '');
        
        // Procesar área_ids: convertir CSV en array de IDs válidos
        $areaIds = [];
        if ($areaIdsCsv) {
            $areaIds = array_filter(array_map('trim', explode(',', (string) $areaIdsCsv)), function ($v) {
                return $v !== '' && is_numeric($v);
            });
            $areaIds = array_values(array_unique($areaIds));
        }

        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'id_producto');
        if (! in_array($sortBy, $allowedSort, true))
            $sortBy = 'id_producto';
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        // Mapear el campo de ordenamiento a la columna correcta
        $sortByColumn = $sortBy;
        if ($sortBy === 'area') {
            $sortByColumn = 'a.nombre';
        } elseif ($sortBy === 'codigo') {
            $sortByColumn = 'ap.codigo';
        } elseif ($sortBy === 'nombre') {
            $sortByColumn = 'i_producto.nombre';
        } elseif ($sortBy === 'tipo') {
            $sortByColumn = 'i_producto.tipo';
        } elseif ($sortBy === 'unidad_medida') {
            $sortByColumn = 'i_producto.unidad_medida';
        } elseif ($sortBy === 'stock') {
            $sortByColumn = 'ap.stock';
        } elseif ($sortBy === 'costo_total') {
            $sortByColumn = 'ap.costo_total';
        }

        // Construir query con leftJoin y distinct() para evitar duplicados
        $query = Producto::query()
            ->leftJoin('i_asignaciones_productos as ap', 'ap.producto_id', '=', 'i_producto.id_producto')
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'ap.area_id')
            ->distinct();

        // Aplicar filtros de búsqueda
        if ($searchCodigo !== '') {
            $query->where('ap.codigo', 'like', '%'.$searchCodigo.'%');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $like = '%'.$search.'%';
                $q->where('i_producto.nombre', 'like', $like)
                  ->orWhere('i_producto.descripcion', 'like', $like);
            });
        }

        // Filtrar por áreas si se especificaron
        if (!empty($areaIds)) {
            $query->whereIn('ap.area_id', $areaIds);
        }

        // Filtrar por tipo de producto si se especificó
        if ($tipoProducto !== '') {
            $query->where('i_producto.tipo', '=', $tipoProducto);
        }

        $select = [
            'i_producto.id_producto',
            'i_producto.nombre',
            'i_producto.tipo',
                'i_producto.unidad_medida',
            'i_producto.codigo_barras',
            'i_producto.descripcion',
                'a.nombre as area',
                'ap.id_asignacion',
            'ap.codigo',
            'ap.stock',
            'ap.costo_total',
            'ap.estado_dado_baja',
        ];

        $paginator = $query
            ->select($select)
            ->orderBy($sortByColumn, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'q' => $search,
                'area_ids' => $areaIds,
                'tipo' => $tipoProducto,
            ],
        ]);
    }

    public function areas(Request $request): JsonResponse
    {
        try {
            $areas = Area::query()
                ->orderBy('nombre')
                ->get(['id_area', 'codigo', 'nombre']);

            return response()->json($areas);
        } catch (\Exception $e) {
            \Log::error('Error en areas(): ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene el último código de salida (tipo = 1) registrado en movimientos.
     */
    public function ultimoCodigoSalida(): JsonResponse
    {
        $ultimoMovimiento = Movimiento::where('tipo', 1)
            ->orderBy('id_movimiento', 'desc')
            ->first(['codigo']);

        $codigo = $ultimoMovimiento?->codigo ?? 0;

        return response()->json([
            'codigo' => $codigo,
        ]);
    }

    /**
     * Obtiene el último código de ingreso (tipo = 2) registrado en movimientos.
     */
    public function ultimoCodigoIngresoMovimiento(): JsonResponse
    {
        $ultimoMovimiento = Movimiento::where('tipo', 2)
            ->orderBy('id_movimiento', 'desc')
            ->first(['codigo']);

        $codigo = $ultimoMovimiento?->codigo ?? 0;

        return response()->json([
            'codigo' => $codigo,
        ]);
    }

    /**
     * Obtiene un producto específico por ID con sus datos de asignación.
     */
    public function getProducto($id): JsonResponse
    {
        $producto = Producto::query()
            ->leftJoin('i_asignaciones_productos as ap', function($join) use ($id) {
                $join->on('ap.producto_id', '=', 'i_producto.id_producto')
                     ->whereRaw('ap.id_asignacion = (SELECT MAX(id_asignacion) FROM i_asignaciones_productos WHERE producto_id = ?)', [$id]);
            })
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'ap.area_id')
            ->where('i_producto.id_producto', $id)
            ->select([
                'i_producto.id_producto',
                'i_producto.nombre',
                'i_producto.tipo',
                'i_producto.unidad_medida',
                'i_producto.codigo_barras',
                'i_producto.descripcion',
                'ap.codigo',
                'ap.area_id',
                'a.nombre as area_nombre',
                'ap.estado_dado_baja',
            ])
            ->first();

        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        return response()->json($producto, 200);
    }

    /**
     * Lista de ingresos con proveedor y agregados de detalles.
     * Parámetros opcionales:
     * - q: término de búsqueda (factura_numero, proveedor nombre, persona_recibe)
     * - page, per_page, sort_by (id_ingreso, factura_numero, fecha_ingreso, fecha_factura, proveedor_nombre, total_items, total_costo), sort_dir
     */
    public function ingresos(Request $request): JsonResponse
    {
        $allowedSort = ['numero','id_ingreso','factura_numero','fecha_ingreso','fecha_factura','proveedor_nombre','total_items','total_costo'];

        $search = (string) $request->input('q', '');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'id_ingreso');
        if (! in_array($sortBy, $allowedSort, true))
            $sortBy = 'id_ingreso';
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Ingreso::query()
            ->selectRaw('i_ingresos.numero, i_ingresos.id_ingreso, i_ingresos.factura_numero, i_ingresos.fecha_ingreso, i_ingresos.fecha_factura, i_ingresos.persona_recibe, i_ingresos.Observaciones, i_ingresos.importe, i_ingresos.estado, p.nombre AS proveedor_nombre, COALESCE(SUM(d.cantidad),0) AS total_items, COALESCE(SUM(d.cantidad * d.costo),0) AS total_costo')
            ->leftJoin('i_proveedores as p', 'p.id_proveedores', '=', 'i_ingresos.proveedor_id')
            ->leftJoin('i_detalle_ingreso as d', 'd.ingreso_id', '=', 'i_ingresos.id_ingreso')
            ->groupBy('i_ingresos.id_ingreso','i_ingresos.numero','i_ingresos.factura_numero','i_ingresos.fecha_ingreso','i_ingresos.fecha_factura','i_ingresos.persona_recibe','i_ingresos.Observaciones','i_ingresos.importe','p.nombre');

        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function($q) use ($like) {
                                $q->where('i_ingresos.numero','like',$like)
                                    ->orWhere('i_ingresos.factura_numero','like',$like)
                  ->orWhere('p.nombre','like',$like)
                  ->orWhere('i_ingresos.persona_recibe','like',$like)
                  ->orWhere('i_ingresos.Observaciones','like',$like);
            });
        }

        // Adaptar sort a alias calculado si aplica
        if ($sortBy === 'proveedor_nombre')
            $sortBy = 'p.nombre';
        elseif ($sortBy === 'total_items')
            $sortBy = 'total_items';
        elseif ($sortBy === 'total_costo')
            $sortBy = 'total_costo';
        else {
            // numero e id_ingreso están en i_ingresos
            if (in_array($sortBy, ['numero','id_ingreso','factura_numero','fecha_ingreso','fecha_factura'], true)) {
                $sortBy = 'i_ingresos.'.$sortBy;
            }
        }

        $paginator = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Obtiene el siguiente número de ingreso sugerido (max(numero) + 1).
     */
    public function nextIngresoNumero(): JsonResponse
    {
        $max = Ingreso::query()->max('numero');
        $next = $max ? ((int) $max) + 1 : 1;

        return response()->json([
            'numero' => $next,
            'max' => $max,
        ]);
    }

    /**
     * Obtiene el siguiente número de salida sugerido (max(codigo) + 1 para tipo=1).
     */
    public function nextSalidaNumero(): JsonResponse
    {
        $max = Movimiento::query()
            ->where('tipo', 1)
            ->max('codigo');
        $next = $max ? ((int) $max) + 1 : 1;

        return response()->json([
            'numero' => $next,
            'max' => $max,
        ]);
    }

    /**
     * Obtiene el siguiente número de ingreso (tipo = 2) sugerido (max(codigo) + 1).
     */
    public function nextIngresoMovimientoNumero(): JsonResponse
    {
        $max = Movimiento::query()
            ->where('tipo', 2)
            ->max('codigo');
        $next = $max ? ((int) $max) + 1 : 1;

        return response()->json([
            'numero' => $next,
            'max' => $max,
        ]);
    }

    /**
     * Lista de proveedores (sólo id y nombre) con búsqueda opcional 'q'.
     */
    public function proveedores(Request $request): JsonResponse
    {
        $search = (string) $request->input('q', '');
        $query = Proveedor::query();
        if ($search !== '') {
            $query->where('nombre', 'like', '%'.$search.'%');
        }
        $proveedores = $query->orderBy('nombre')->get(['id_proveedores','nombre']);
        return response()->json([
            'data' => $proveedores,
        ]);
    }

    /**
     * Crea un proveedor mínimo (solo nombre requerido).
     */
    public function storeProveedor(Request $request): JsonResponse
    {
        $nombre = trim((string) $request->input('nombre', ''));
        if ($nombre === '') {
            return response()->json([
                'message' => 'El nombre es obligatorio.'
            ], 422);
        }
        $proveedor = Proveedor::create(['nombre' => $nombre]);
        return response()->json([
            'data' => [
                'id_proveedores' => $proveedor->id_proveedores,
                'nombre' => $proveedor->nombre,
            ],
        ], 201);
    }

    /**
     * Crea un producto mínimo (nombre requerido). Permite opcionalmente código y código de barras.
     */
    public function storeProducto(Request $request): JsonResponse
    {
        $nombre = trim((string)$request->input('nombre',''));
        if ($nombre === '') {
            return response()->json(['message'=>'El nombre es obligatorio.'],422);
        }

        // Si ya existe un producto con ese nombre: actualizar código si llega y está vacío
        $existing = Producto::query()->where('nombre',$nombre)->first();
        if ($existing) {
            return response()->json([
                'id_producto' => $existing->id_producto,
                'nombre' => $existing->nombre,
                'codigo_barras' => $existing->codigo_barras,
                'descripcion' => $existing->descripcion,
                'tipo' => $existing->tipo,
                'unidad_medida' => $existing->unidad_medida,
            ], 200);
        }

        $tipo = trim((string)$request->input('tipo','')) ?: null; // ahora varchar
        $unidadMedida = trim((string)$request->input('unidad_medida','')) ?: null; // unidad física

        $producto = Producto::create([
            'nombre' => $nombre,
            'codigo_barras' => null,
            'descripcion' => null,
            'tipo' => $tipo,
            'unidad_medida' => $unidadMedida,
            'permite_ingreso' => 1,
        ]);

        return response()->json([
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'codigo_barras' => $producto->codigo_barras,
            'descripcion' => $producto->descripcion,
            'tipo' => $producto->tipo,
            'unidad_medida' => $producto->unidad_medida,
        ], 201);
    }

    /**
     * Actualiza campos básicos de un producto existente.
     * Permite: tipo, unidad_medida, descripcion, codigo_barras.
     * Nota: código y área ahora se gestionan exclusivamente mediante Asignacion_Producto.
     */
    public function updateProducto(Request $request, $productoId): JsonResponse
    {
        $producto = Producto::find($productoId);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }
        $tipo = trim((string)$request->input('tipo','')) ?: null;
        $unidadMedida = trim((string)$request->input('unidad_medida','')) ?: null;
        $descripcion = $request->input('descripcion');
        $codigoBarras = $request->input('codigo_barras');

        if ($tipo !== null) $producto->tipo = $tipo;
        if ($unidadMedida !== null) $producto->unidad_medida = $unidadMedida;
        if ($descripcion !== null) $producto->descripcion = $descripcion;
        if ($codigoBarras !== null) $producto->codigo_barras = $codigoBarras;

        // Loggear los datos antes de guardar
        Log::info('Datos recibidos para actualizar producto:', [
            'tipo' => $tipo,
            'unidad_medida' => $unidadMedida,
            'descripcion' => $descripcion,
            'codigo_barras' => $codigoBarras,
        ]);

        $producto->save();

        return response()->json([
            'id_producto' => $producto->id_producto,
            'nombre' => $producto->nombre,
            'codigo_barras' => $producto->codigo_barras,
            'descripcion' => $producto->descripcion,
            'tipo' => $producto->tipo,
            'unidad_medida' => $producto->unidad_medida,
        ], 200);
    }

    /**
     * Nueva función: Edita campos básicos del producto (nombre, codigo_barras, descripcion, tipo, unidad_medida).
     * No modifica asignaciones ni otras tablas.
     */
    public function editarProductoBasico(Request $request, $id): JsonResponse
    {
        $producto = Producto::find($id);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $nombre = $request->input('nombre');
        $tipo = $request->input('tipo');
        $unidadMedida = $request->input('unidad_medida');
        $descripcion = $request->input('descripcion');
        $codigoBarras = $request->input('codigo_barras');

        $payload = [];
        if ($nombre !== null) $payload['nombre'] = trim((string)$nombre);
        if ($tipo !== null) $payload['tipo'] = trim((string)$tipo);
        if ($unidadMedida !== null) $payload['unidad_medida'] = trim((string)$unidadMedida);
        if ($descripcion !== null) $payload['descripcion'] = $descripcion === '' ? null : $descripcion;
        if ($codigoBarras !== null) $payload['codigo_barras'] = $codigoBarras === '' ? null : $codigoBarras;

        if (array_key_exists('nombre',$payload) && $payload['nombre'] === '') {
            return response()->json(['message' => 'El nombre es obligatorio.'], 422);
        }

        if (!empty($payload)) {
            $producto->fill($payload);
            $producto->save();
        }

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'data' => [
                'id_producto' => $producto->id_producto,
                'nombre' => $producto->nombre,
                'codigo_barras' => $producto->codigo_barras,
                'descripcion' => $producto->descripcion,
                'tipo' => $producto->tipo,
                'unidad_medida' => $producto->unidad_medida,
            ],
        ], 200);
    }

    /**
     * Baja de producto (solo Activo Fijo): registra en i_bajas_productos y marca estado_dado_baja=1 en todas sus asignaciones.
     * Payload esperado: { producto_id, motivo }
     */
    public function bajaProducto(Request $request): JsonResponse
    {
        $asignacionId = (int) $request->input('asignacion_id', 0);
        $motivo = trim((string) $request->input('motivo', ''));

        if ($asignacionId <= 0) {
            return response()->json(['message' => 'asignacion_id es requerido'], 422);
        }
        if ($motivo === '') {
            return response()->json(['message' => 'El motivo de baja es obligatorio'], 422);
        }

        $asignacion = Asignacion_Producto::query()
            ->with('producto')
            ->lockForUpdate()
            ->find($asignacionId);
        if (!$asignacion) {
            return response()->json(['message' => 'Asignación no encontrada'], 404);
        }

        // Validar tipo de producto
        $tipo = trim((string) ($asignacion->producto->tipo ?? ''));
        if (strcasecmp($tipo, 'Activo Fijo') !== 0) {
            return response()->json(['message' => 'Solo los productos de tipo Activo Fijo pueden darse de baja'], 422);
        }

        // Verificar si ya está dada de baja
        if ((int)($asignacion->estado_dado_baja ?? 0) === 1) {
            return response()->json(['message' => 'La asignación ya está dada de baja'], 422);
        }

        try {
            DB::transaction(function () use ($asignacion, $asignacionId, $motivo) {
                // Obtener ID entero del usuario autenticado (compatibilidad con distintos modelos)
                $authUser = auth()->user();
                $usuarioId = 0;
                if ($authUser) {
                    $candidatos = [
                        $authUser->id ?? null,
                        $authUser->id_usuario ?? null,
                        $authUser->usuario_id ?? null,
                    ];
                    foreach ($candidatos as $cand) {
                        if (is_int($cand)) { $usuarioId = $cand; break; }
                        if (is_string($cand) && ctype_digit($cand)) { $usuarioId = (int)$cand; break; }
                    }
                }
                // Crear registro de baja con asignacion_id
                Baja_Producto::create([
                    'asignacion_id' => $asignacionId,
                    'fecha_baja' => now(),
                    'motivo' => $motivo,
                    'usuario_registra' => $usuarioId,
                ]);

                // Marcar solo esta asignación como dada de baja
                $asignacion->estado_dado_baja = 1;
                $asignacion->save();
            });

            return response()->json([
                'message' => 'Producto dado de baja correctamente',
                'asignacion_id' => $asignacionId,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al dar de baja producto', [
                'asignacion_id' => $asignacionId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error al dar de baja: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Genera o recupera el código de producto para un área según el tipo solicitado.
     * Parámetros:
     * - tipo=Activo Fijo => siempre genera el siguiente correlativo dentro del área.
     * - tipo=Consumible => intenta recuperar un código existente para algún consumible del área;
     *                      si ninguno tiene código genera el siguiente correlativo.
     * Si no se envía tipo se comporta como Activo Fijo.
     * Códigos asumidos numéricos; se retorna con padding a 4 dígitos.
     */
    /**
     * Obtiene un área específica por su ID.
     */
    public function getArea($id): JsonResponse
    {
        $area = Area::find($id);

        if (!$area) {
            return response()->json(['error' => 'Área no encontrada'], 404);
        }

        return response()->json($area);
    }

    /**
     * Obtiene un área específica por su nombre.
     */
    public function getAreaByName($name): JsonResponse
    {
        $area = Area::where('nombre', $name)->first();

        if (!$area) {
            return response()->json(['error' => 'Área no encontrada'], 404);
        }

        return response()->json($area);
    }

    /**
     * Lista asignaciones de productos con búsqueda por código, área o producto.
     * GET /inventario/asignaciones-productos?q=search&per_page=15
     */
    public function getAsignacionesProductos(Request $request): JsonResponse
    {
        try {
            $search = $request->input('q', '');
            $perPage = (int)$request->input('per_page', 15);

            $query = Asignacion_Producto::query()
                ->with(['producto' => function ($q) {
                    $q->select('id_producto', 'nombre', 'tipo', 'codigo_barras');
                }, 'area' => function ($q) {
                    $q->select('id_area', 'nombre', 'codigo');
                }])
                ->where('stock', '>', 0);

            if ($search) {
                $search = trim($search);
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'LIKE', "%{$search}%")
                      ->orWhereHas('producto', function ($pq) use ($search) {
                          $pq->where('nombre', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('area', function ($aq) use ($search) {
                          $aq->where('nombre', 'LIKE', "%{$search}%");
                      });
                });
            }

            $asignaciones = $query
                ->select('id_asignacion', 'producto_id', 'area_id', 'codigo', 'stock', 'costo_total')
                ->orderBy('codigo', 'asc')
                ->paginate($perPage);

            // Transformar la respuesta para agregar displayName
            $asignaciones->getCollection()->transform(function ($item) {
                $item->displayName = sprintf(
                    '%s - %s - %s',
                    $item->codigo ?? '—',
                    $item->area?->nombre ?? '—',
                    $item->producto?->nombre ?? '—'
                );
                return $item;
            });

            return response()->json($asignaciones);
        } catch (\Exception $e) {
            \Log::error('Error en getAsignacionesProductos: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Autocomplete exclusivo para seleccionar asignaciones disponibles (consumibles o activos fuera).
     * Filtra por estado_movimiento IN (0,2) y stock > 0. Permite búsqueda por código, área o nombre de producto.
     * GET /inventario/asignaciones-productos/disponibles?q=search&per_page=15
     */
    public function getAsignacionesDisponiblesAutocomplete(Request $request): JsonResponse
    {
        try {
            $search = trim((string)$request->input('q', ''));
            $perPage = (int)$request->input('per_page', 15);

            // Tomar el último detalle_movimiento por asignación para exponer stock y costo_total
            $latestStockSub = DB::table('i_detalle_movimientos as dm')
                ->select('dm.cantidad')
                ->whereColumn('dm.asignacion_id', 'i_asignaciones_productos.id_asignacion')
                ->orderByDesc('dm.id_detalle_movimiento')
                ->limit(1);

            $latestCostoSub = DB::table('i_detalle_movimientos as dm')
                ->select('dm.costo')
                ->whereColumn('dm.asignacion_id', 'i_asignaciones_productos.id_asignacion')
                ->orderByDesc('dm.id_detalle_movimiento')
                ->limit(1);

            $query = Asignacion_Producto::query()
                ->with([
                    'producto' => function ($q) {
                        $q->select('id_producto', 'nombre', 'tipo', 'codigo_barras');
                    },
                    'area' => function ($q) {
                        $q->select('id_area', 'nombre', 'codigo');
                    },
                ])
                ->select([
                    'i_asignaciones_productos.id_asignacion',
                    'i_asignaciones_productos.producto_id',
                    'i_asignaciones_productos.area_id',
                    'i_asignaciones_productos.codigo',
                    'i_asignaciones_productos.estado_movimiento',
                ])
                ->selectSub($latestStockSub, 'stock')
                ->selectSub($latestCostoSub, 'costo_total')
                // Consumibles con stock > 0 (según último movimiento) o Activos Fijos fuera (2)
                ->havingRaw('((estado_movimiento = 0 AND stock > 0) OR estado_movimiento = 2)');

            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('codigo', 'LIKE', "%{$search}%")
                      ->orWhereHas('producto', function ($pq) use ($search) {
                          $pq->where('nombre', 'LIKE', "%{$search}%");
                      })
                      ->orWhereHas('area', function ($aq) use ($search) {
                          $aq->where('nombre', 'LIKE', "%{$search}%");
                      });
                });
            }

            $asignaciones = $query
                ->orderBy('codigo', 'asc')
                ->paginate($perPage);

            // Agregar displayName: CODIGO – AREA – NOMBRE DEL PRODUCTO
            $asignaciones->getCollection()->transform(function ($item) {
                $item->displayName = sprintf(
                    '%s - %s - %s',
                    $item->codigo ?? '—',
                    $item->area?->nombre ?? '—',
                    $item->producto?->nombre ?? '—'
                );
                return $item;
            });

            return response()->json($asignaciones);
        } catch (\Exception $e) {
            \Log::error('Error en getAsignacionesDisponiblesAutocomplete: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Obtiene o crea la asignación de un producto a un área.
     * - Si existe la asignación, retorna la existente (created=false)
     * - Si no existe, genera un código y crea la asignación (created=true)
     * Lógica de generación:
     *   Prefijo = Area.codigo (uppercase) o 'AREA{areaId}' si vacío.
     *   Si el producto es de tipo 'Consumible' y ya existe un consumible con código en el área, reutiliza ese código.
     *   En caso contrario genera correlativo PREFIX-000001, PREFIX-000002, ...
     */
    public function assignOrFetchAsignacionProducto(Request $request): JsonResponse
    {
        $productoId = (int)$request->input('producto_id');
        $areaId = (int)$request->input('area_id');
        $dryRun = filter_var($request->input('dry_run', false), FILTER_VALIDATE_BOOLEAN);
        
        // Validar que área_id sea válido
        if (!$areaId) {
            return response()->json(['message' => 'area_id es requerido'], 422);
        }
        
        $area = Area::find($areaId);
        if (!$area) {
            return response()->json(['message' => 'Área no encontrada'], 404);
        }
        
        // Si es dry_run y producto_id es 0 o null, solo calcular el siguiente código
        if ($dryRun && !$productoId) {
            $prefix = strtoupper(trim((string)$area->codigo));
            if ($prefix === '') $prefix = 'AREA'.$areaId;

            $codes = Asignacion_Producto::query()
                ->where('area_id', $areaId)
                ->whereNotNull('codigo')
                ->pluck('codigo');

            $maxNum = 0;
            foreach ($codes as $c) {
                if (!is_string($c)) continue;
                if (preg_match('/^'.preg_quote($prefix,'/').'-([0-9]{1,})$/', $c, $m)) {
                    $n = (int)$m[1];
                    if ($n > $maxNum) $maxNum = $n;
                }
            }
            $codigoFinal = $prefix.'-'.str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'data' => [
                    'id_asignacion' => null,
                    'producto_id' => null,
                    'area_id' => $areaId,
                    'codigo' => $codigoFinal,
                ],
                'created' => false,
                'dry_run' => true,
            ], 200);
        }
        
        // Para operaciones normales, producto_id es requerido
        if (!$productoId) {
            return response()->json(['message' => 'producto_id es requerido'], 422);
        }
        
        $producto = Producto::find($productoId);
        if (!$producto) {
            return response()->json(['message' => 'Producto no encontrado'], 404);
        }

        $tipoSolicitud = $request->input('tipo');
        if (is_string($tipoSolicitud)) {
            $tipoNormalizado = trim($tipoSolicitud);
            if ($tipoNormalizado !== '') {
                if (strcasecmp($tipoNormalizado, 'Consumible') === 0) {
                    $tipoNormalizado = 'Consumible';
                } elseif (strcasecmp($tipoNormalizado, 'Activo Fijo') === 0) {
                    $tipoNormalizado = 'Activo Fijo';
                }

                if (!$producto->tipo) {
                    $producto->tipo = $tipoNormalizado;
                    $producto->save();
                }
            }
        }

        $existente = Asignacion_Producto::query()
            ->where('producto_id', $productoId)
            ->where('area_id', $areaId)
            ->first();
        
        if ($existente && !$dryRun) {
            $tipoProducto = trim((string)$producto->tipo);
            
            // Si es Consumible: retornar la asignación existente con su código actual
            if ($tipoProducto === 'Consumible') {
                return response()->json([
                    'data' => $existente,
                    'created' => false,
                ], 200);
            }
            
            // Si es Activo Fijo: NO retornar, continuar para crear una nueva asignación
            // (el código sigue abajo para crear una nueva)
        }

        // Modo simulación: calcular siguiente código correlativo sin crear registro
        if ($dryRun) {
            $prefix = strtoupper(trim((string)$area->codigo));
            if ($prefix === '') $prefix = 'AREA'.$areaId;

            $codes = Asignacion_Producto::query()
                ->where('area_id', $areaId)
                ->whereNotNull('codigo')
                ->pluck('codigo');

            $maxNum = 0;
            foreach ($codes as $c) {
                if (!is_string($c)) continue;
                if (preg_match('/^'.preg_quote($prefix,'/').'-([0-9]{1,})$/', $c, $m)) {
                    $n = (int)$m[1];
                    if ($n > $maxNum) $maxNum = $n;
                }
            }
            $codigoFinal = $prefix.'-'.str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

            return response()->json([
                'data' => [
                    'id_asignacion' => null,
                    'producto_id' => $producto->id_producto,
                    'area_id' => $areaId,
                    'codigo' => $codigoFinal,
                ],
                'created' => false,
                'dry_run' => true,
            ], 200);
        }

        $asignacion = null;
        DB::transaction(function () use (&$asignacion, $producto, $areaId, $area) {
            $prefix = strtoupper(trim((string)$area->codigo));
            if ($prefix === '') $prefix = 'AREA'.$areaId;

            $codigoFinal = null;
            $tipoProducto = trim((string)$producto->tipo);
            
            // Tanto para Activo Fijo como Consumible: generar nuevo código correlativo
            $codes = Asignacion_Producto::query()
                ->where('area_id', $areaId)
                ->whereNotNull('codigo')
                ->lockForUpdate()
                ->pluck('codigo');
            $maxNum = 0;
            foreach ($codes as $c) {
                if (!is_string($c)) continue;
                if (preg_match('/^'.preg_quote($prefix,'/').'-([0-9]{1,})$/', $c, $m)) {
                    $n = (int)$m[1];
                    if ($n > $maxNum) $maxNum = $n;
                }
            }
            $codigoFinal = $prefix.'-'.str_pad($maxNum + 1, 6, '0', STR_PAD_LEFT);

            // Determinar estado_movimiento basado en tipo de producto
            $estadoMovimiento = 0; // Por defecto: Consumible
            if ($tipoProducto === 'Activo Fijo') {
                $estadoMovimiento = 1; // Activo Fijo dentro del almacén
            }

            $asignacion = Asignacion_Producto::create([
                'producto_id' => $producto->id_producto,
                'area_id' => $areaId,
                'codigo' => $codigoFinal,
                'stock' => 0,
                'precio' => 0,
                'costo_total' => 0,
                'fecha_asignacion' => now(),
                'estado_dado_baja' => 0,
                'estado_movimiento' => $estadoMovimiento,
            ]);
        });

        return response()->json([
            'data' => $asignacion,
            'created' => true,
        ], 201);
    }

    /**
     * Elimina una asignación específica de producto por su ID. Mantiene el producto.
     * Params JSON: id_asignacion (requerido)
     */
    public function deleteAsignacionProducto(Request $request): JsonResponse
    {
        $idAsignacion = (int) $request->input('id_asignacion');
        if (!$idAsignacion) {
            return response()->json(['message' => 'id_asignacion es requerido'], 422);
        }

        $asignacion = Asignacion_Producto::find($idAsignacion);
        if (!$asignacion) {
            return response()->json(['message' => 'Asignación no encontrada'], 404);
        }

        $asignacion->delete();

        return response()->json([
            'message' => 'Asignación eliminada correctamente',
            'id_asignacion' => $idAsignacion,
        ], 200);
    }

    /**
     * Guarda un ingreso y sus detalles.
     * Espera payload con: numero, proveedor_id, fecha_ingreso, recibido_por, entregado_por,
     * factura_numero, fecha_factura, descripcion, total_importe,
     * items[] (asignacion_id,cantidad,precio,costo,importe)
     */
    public function storeIngreso(Request $request): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'Debe enviar al menos un item.'], 422);
        }

        $proveedorId = $request->input('proveedor_id');
        if (!is_numeric($proveedorId)) {
            return response()->json(['message' => 'Proveedor inválido.'], 422);
        }

        $numero = $request->input('numero');
        // Aceptar tanto string como entero; validar que sea numérico y > 0
        if (!is_numeric($numero) || (int)$numero <= 0) {
            return response()->json(['message' => 'Número de ingreso no asignado.'], 422);
        }
        $numero = (int)$numero;
        $fechaIngreso = $request->input('fecha_ingreso');
        $personaRecibe = (string)$request->input('recibido_por', '');
        $personaEntrega = (string)$request->input('entregado_por', '');
        $facturaNumero = $request->input('factura_numero');
        $fechaFactura = $request->input('fecha_factura');
        $descripcion = $request->input('descripcion');
        $totalImporte = (float)$request->input('total_importe', 0);

        try {
            $created = null;
            DB::transaction(function () use (&$created, $numero, $proveedorId, $facturaNumero, $fechaFactura, $fechaIngreso, $personaRecibe, $personaEntrega, $totalImporte, $descripcion, $items) {
                $created = Ingreso::create([
                    'numero' => $numero,
                    'proveedor_id' => $proveedorId,
                    'factura_numero' => $facturaNumero ?: null,
                    'fecha_factura' => $fechaFactura ?: null,
                    'fecha_ingreso' => $fechaIngreso ?: now(),
                    'persona_recibe' => $personaRecibe,
                    'persona_entrega' => $personaEntrega,
                    'importe' => $totalImporte,
                    'Observaciones' => $descripcion,
                    'estado' => 1,
                ]);

                foreach ($items as $it) {
                    $asigId = isset($it['asignacion_id']) ? (int)$it['asignacion_id'] : 0;
                    $cant = isset($it['cantidad']) ? (float)$it['cantidad'] : 0;
                    $precio = isset($it['precio']) ? (float)$it['precio'] : 0;
                    $costo = isset($it['costo']) ? (float)$it['costo'] : 0;
                    $importeItem = isset($it['importe']) ? (float)$it['importe'] : ($cant * $precio);
                    if ($asigId <= 0 || $cant <= 0) continue; // omitir inválidos

                    Detalle_Ingreso::create([
                        'ingreso_id' => $created->id_ingreso,
                        'asignacion_id' => $asigId,
                        'cantidad' => $cant,
                        'precio' => $precio,
                        'costo' => $costo,
                        'importe' => $importeItem,
                    ]);

                    // Actualizar stock y costo_total en Asignacion_Producto
                    $asignacion = Asignacion_Producto::find($asigId);
                    if ($asignacion) {
                        // Sumar cantidad y costo
                        $asignacion->stock = ($asignacion->stock ?? 0) + $cant;
                        $asignacion->costo_total = ($asignacion->costo_total ?? 0) + $costo;
                        $asignacion->save();
                    }
                }
            });

            return response()->json([
                'message' => 'Ingreso registrado correctamente',
                'data' => [
                    'id_ingreso' => $created->id_ingreso,
                    'numero' => $created->numero,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar ingreso', ['e' => $e->getMessage()]);
            return response()->json(['message' => 'Error al guardar ingreso'], 500);
        }
    }

    /**
     * Actualiza campos básicos de un ingreso existente (sin tocar detalles).
     */
    public function updateIngreso(Request $request, $id): JsonResponse
    {
        $ingreso = Ingreso::find($id);
        if (!$ingreso) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        $proveedorId = $request->input('proveedor_id');
        if ($proveedorId !== null && !is_numeric($proveedorId)) {
            return response()->json(['message' => 'Proveedor inválido.'], 422);
        }

        $payload = [
            'proveedor_id'   => $proveedorId !== null ? (int)$proveedorId : $ingreso->proveedor_id,
            'factura_numero' => $request->input('factura_numero', $ingreso->factura_numero),
            'fecha_ingreso'  => $request->input('fecha_ingreso', $ingreso->fecha_ingreso),
            'fecha_factura'  => $request->input('fecha_factura', $ingreso->fecha_factura),
            'persona_recibe' => $request->input('persona_recibe', $ingreso->persona_recibe),
            'persona_entrega'=> $request->input('persona_entrega', $ingreso->persona_entrega),
            'Observaciones'  => $request->input('Observaciones', $ingreso->Observaciones),
            'importe'        => $request->has('importe') ? (float)$request->input('importe') : $ingreso->importe,
        ];

        // Evitar asignar null a campos que no se enviaron de forma intencional
        foreach ($payload as $k => $v) {
            if ($v === null) unset($payload[$k]);
        }

        $ingreso->fill($payload);
        $ingreso->save();

        return response()->json([
            'message' => 'Ingreso actualizado correctamente',
            'data' => [
                'id_ingreso' => $ingreso->id_ingreso,
            ],
        ], 200);
    }

    /**
     * Recibe el payload de "Registrar Entrada" y lo devuelve tal cual para previsualización.
     * Útil para validar datos antes de implementar la persistencia en i_ingresos y i_detalle_ingreso.
     */
    public function previewIngreso(Request $request): JsonResponse
    {
        $data = $request->all();
        Log::info('Preview de ingreso recibido', ['payload' => $data]);

        return response()->json([
            'received' => $data,
            'message' => 'Payload recibido correctamente para previsualización.'
        ], 200);
    }

    /**
     * Muestra un ingreso específico con sus detalles.
     * Retorna estructura apta para la vista de previsualización.
     */
    public function showIngreso($id): JsonResponse
    {
        $ingreso = Ingreso::query()
            ->leftJoin('i_proveedores as p', 'p.id_proveedores', '=', 'i_ingresos.proveedor_id')
            ->where('i_ingresos.id_ingreso', $id)
            ->selectRaw('i_ingresos.id_ingreso, i_ingresos.numero, i_ingresos.factura_numero, i_ingresos.fecha_ingreso, i_ingresos.fecha_factura, i_ingresos.persona_recibe, i_ingresos.persona_entrega, i_ingresos.Observaciones, i_ingresos.importe, i_ingresos.estado, p.nombre AS proveedor_nombre')
            ->first();

        if (!$ingreso) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        // Obtener motivo de anulación si el ingreso está anulado
        $motivoAnulacion = null;
        if ($ingreso->estado == 0) {
            $anulacion = \App\Models\Anulacion_Ingreso::query()
                ->where('ingreso_id', $ingreso->id_ingreso)
                ->orderBy('id_anulacion_ingreso', 'desc')
                ->first();
            $motivoAnulacion = $anulacion?->motivo ?? null;
        }

        $detalles = Detalle_Ingreso::query()
            ->where('ingreso_id', $ingreso->id_ingreso)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_ingreso.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->selectRaw('i_detalle_ingreso.id_detalle_ingreso as id, ap.codigo as codigo, pr.nombre as producto_nombre, i_detalle_ingreso.cantidad, i_detalle_ingreso.precio, i_detalle_ingreso.costo, i_detalle_ingreso.importe')
            ->get();

        return response()->json([
            'id_ingreso' => $ingreso->id_ingreso,
            'numero' => $ingreso->numero,
            'factura_numero' => $ingreso->factura_numero,
            'fecha_ingreso' => $ingreso->fecha_ingreso,
            'fecha_factura' => $ingreso->fecha_factura,
            'persona_recibe' => $ingreso->persona_recibe,
            'persona_entrega' => $ingreso->persona_entrega,
            'Observaciones' => $ingreso->Observaciones,
            'importe' => $ingreso->importe,
            'estado' => $ingreso->estado,
            'anulacion_motivo' => $motivoAnulacion,
            'proveedor_nombre' => $ingreso->proveedor_nombre,
            'detalles' => $detalles,
        ], 200);
    }

    /**
     * Endpoint para edición: retorna el ingreso y sus detalles por ID.
     */
    public function edisingreso($id): JsonResponse
    {
        $ingreso = Ingreso::query()
            ->leftJoin('i_proveedores as p', 'p.id_proveedores', '=', 'i_ingresos.proveedor_id')
            ->where('i_ingresos.id_ingreso', $id)
            ->selectRaw('i_ingresos.id_ingreso, i_ingresos.numero, i_ingresos.factura_numero, i_ingresos.fecha_ingreso, i_ingresos.fecha_factura, i_ingresos.persona_recibe, i_ingresos.persona_entrega, i_ingresos.Observaciones, i_ingresos.importe, i_ingresos.proveedor_id, p.nombre AS proveedor_nombre')
            ->first();

        if (!$ingreso) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        $detalles = Detalle_Ingreso::query()
            ->where('ingreso_id', $ingreso->id_ingreso)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_ingreso.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->leftJoin('i_areas as ar', 'ar.id_area', '=', 'ap.area_id')
            ->selectRaw('i_detalle_ingreso.id_detalle_ingreso, i_detalle_ingreso.asignacion_id, ap.producto_id, ap.area_id, ap.codigo as codigo, pr.nombre as producto_nombre, pr.tipo, pr.unidad_medida, pr.codigo_barras, pr.descripcion, ar.nombre as area_nombre, i_detalle_ingreso.cantidad, i_detalle_ingreso.precio, i_detalle_ingreso.costo, i_detalle_ingreso.importe')
            ->get();

        return response()->json([
            'id_ingreso' => $ingreso->id_ingreso,
            'numero' => $ingreso->numero,
            'factura_numero' => $ingreso->factura_numero,
            'fecha_ingreso' => $ingreso->fecha_ingreso,
            'fecha_factura' => $ingreso->fecha_factura,
            'persona_recibe' => $ingreso->persona_recibe,
            'persona_entrega' => $ingreso->persona_entrega,
            'Observaciones' => $ingreso->Observaciones,
            'importe' => $ingreso->importe,
            'proveedor_id' => $ingreso->proveedor_id,
            'proveedor_nombre' => $ingreso->proveedor_nombre,
            'detalles' => $detalles,
        ], 200);
    }

    /**
     * Actualiza los detalles de un ingreso ajustando stock y costo_total.
     * items[]: { id_detalle_ingreso|detalle_id|id, asignacion_id, cantidad, precio, costo, importe }
     */
    public function updateIngresoDetalles(Request $request, $id): JsonResponse
    {
        $items = $request->input('items', []);
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'Debe enviar al menos un detalle.'], 422);
        }

        try {
            DB::transaction(function () use ($items, $id) {
                foreach ($items as $it) {
                    $detalleId = 0;
                    if (isset($it['id_detalle_ingreso'])) $detalleId = (int)$it['id_detalle_ingreso'];
                    elseif (isset($it['detalle_id'])) $detalleId = (int)$it['detalle_id'];
                    elseif (isset($it['id'])) $detalleId = (int)$it['id'];
                    if ($detalleId <= 0) continue;

                    $detalle = Detalle_Ingreso::query()
                        ->where('id_detalle_ingreso', $detalleId)
                        ->where('ingreso_id', $id)
                        ->lockForUpdate()
                        ->first();
                    if (!$detalle) continue;

                    $newAsignacionId = isset($it['asignacion_id']) ? (int)$it['asignacion_id'] : (int)$detalle->asignacion_id;
                    $newCantidad = isset($it['cantidad']) ? (float)$it['cantidad'] : (float)$detalle->cantidad;
                    $newPrecio = isset($it['precio']) ? (float)$it['precio'] : (float)$detalle->precio;
                    $newCosto = isset($it['costo']) ? (float)$it['costo'] : (float)$detalle->costo;
                    $newImporte = isset($it['importe']) ? (float)$it['importe'] : (float)$detalle->importe;

                    $oldAsignacionId = (int)$detalle->asignacion_id;
                    $oldCantidad = (float)$detalle->cantidad;
                    $oldCosto = (float)$detalle->costo;

                    if ($newAsignacionId !== $oldAsignacionId) {
                        if ($oldAsignacionId) {
                            $oldAsig = Asignacion_Producto::find($oldAsignacionId);
                            if ($oldAsig) {
                                $oldAsig->stock = ($oldAsig->stock ?? 0) - $oldCantidad;
                                $oldAsig->costo_total = ($oldAsig->costo_total ?? 0) - $oldCosto;
                                $oldAsig->save();
                            }
                        }
                        if ($newAsignacionId) {
                            $newAsig = Asignacion_Producto::find($newAsignacionId);
                            if ($newAsig) {
                                $newAsig->stock = ($newAsig->stock ?? 0) + $newCantidad;
                                $newAsig->costo_total = ($newAsig->costo_total ?? 0) + $newCosto;
                                $newAsig->save();
                            }
                        }

                        $detalle->asignacion_id = $newAsignacionId;
                        $detalle->cantidad = $newCantidad;
                        $detalle->precio = $newPrecio;
                        $detalle->costo = $newCosto;
                        $detalle->importe = $newImporte;
                        $detalle->save();
                    } else {
                        $deltaCantidad = $newCantidad - $oldCantidad;
                        $deltaCosto = $newCosto - $oldCosto;
                        if ($deltaCantidad != 0 || $deltaCosto != 0) {
                            $asig = Asignacion_Producto::find($oldAsignacionId);
                            if ($asig) {
                                $asig->stock = ($asig->stock ?? 0) + $deltaCantidad;
                                $asig->costo_total = ($asig->costo_total ?? 0) + $deltaCosto;
                                $asig->save();
                            }
                        }
                        $detalle->cantidad = $newCantidad;
                        $detalle->precio = $newPrecio;
                        $detalle->costo = $newCosto;
                        $detalle->importe = $newImporte;
                        $detalle->save();
                    }
                }
            });

            return response()->json(['message' => 'Detalles actualizados correctamente'], 200);
        } catch (\Throwable $e) {
            Log::error('updateIngresoDetalles error', ['e' => $e->getMessage()]);
            return response()->json(['message' => 'Error al actualizar detalles'], 500);
        }
    }

    /**
     * Genera PDF del ingreso usando FPDF replicando el diseño de nota de recepción.
     */
    public function generateIngresoPdf($id)
    {
        // Optimizado: consultas directas sin modelos para menor overhead
        $ingresoRows = DB::select(
            "SELECT i.id_ingreso, i.numero, i.factura_numero, i.fecha_ingreso, i.fecha_factura, i.persona_recibe, i.persona_entrega, i.Observaciones, i.importe, i.estado, p.nombre AS proveedor_nombre
             FROM i_ingresos i
             LEFT JOIN i_proveedores p ON p.id_proveedores = i.proveedor_id
             WHERE i.id_ingreso = ?
             LIMIT 1",
            [$id]
        );

        if (empty($ingresoRows)) {
            abort(404, 'Ingreso no encontrado');
        }

        $ingreso = $ingresoRows[0];

        // Obtener motivo de anulación si existe
        $motivoAnulacion = null;
        if ($ingreso->estado == 0) {
            $anulacionRows = DB::select(
                "SELECT motivo FROM i_anulacion_ingreso WHERE ingreso_id = ? ORDER BY id_anulacion_ingreso DESC LIMIT 1",
                [$ingreso->id_ingreso]
            );
            if (!empty($anulacionRows)) {
                $motivoAnulacion = $anulacionRows[0]->motivo;
            }
        }

        $detalles = DB::select(
            "SELECT ap.codigo AS codigo, pr.nombre AS producto_nombre, d.cantidad, d.precio, d.costo, d.importe
             FROM i_detalle_ingreso d
             LEFT JOIN i_asignaciones_productos ap ON ap.id_asignacion = d.asignacion_id
             LEFT JOIN i_producto pr ON pr.id_producto = ap.producto_id
             WHERE d.ingreso_id = ?",
            [$ingreso->id_ingreso]
        );

        // Preparar número con padding
        $numPadded = str_pad($ingreso->numero, 6, '0', STR_PAD_LEFT);

        // La clase IngresoPDF ya está definida fuera del método.

        // Crear PDF
        $pdf = new IngresoPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(15, 15, 15);
        $pdf->numPadded = $numPadded;
        $pdf->fechaIngreso = $ingreso->fecha_ingreso ?? '';
        $pdf->AddPage();

        // Título centrado
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 7, 'NOTA DE INGRESO', 0, 1, 'C');
        $pdf->Ln(3);

        // Info de factura y proveedor
        $pdf->SetFont('Arial', '', 9);
        $proveedorNombre = isset($ingreso->proveedor_nombre) ? utf8_decode((string)$ingreso->proveedor_nombre) : '';
        $pdf->Cell(100, 5, 'PROVEEDOR: ' . $proveedorNombre, 0, 0, 'L');
        if ($ingreso->factura_numero) {
            $pdf->Cell(0, 5, utf8_decode('Factura N°: ') . $ingreso->factura_numero, 0, 1, 'R');
            if ($ingreso->fecha_factura) {
                $fFact = date('d/m/Y', strtotime($ingreso->fecha_factura));
                $pdf->Cell(0, 5, 'FECHA: ' . $fFact, 0, 1, 'R');
            } else {
                $pdf->Ln(5);
            }
        } else {
            $pdf->Cell(0, 5, utf8_decode('Sin factura registrada'), 0, 1, 'R');
            $pdf->Ln(5);
        }
        $pdf->Ln(2);
        // Tabla de productos
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(20, 6, iconv('UTF-8', 'ISO-8859-1', 'Código'), 1, 0, 'C', true);
        $pdf->Cell(12, 6, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell(65, 6, iconv('UTF-8', 'ISO-8859-1', 'Descripción'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Precio', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'P.Costo', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Importe', 1, 0, 'C', true);
        $pdf->Cell(23, 6, 'Total Bs', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $totalCalculado = 0.0;
        foreach ($detalles as $det) {
            $pdf->Cell(20, 5, $det->codigo ?: '', 1, 0, 'L');
            $cantidadVal = (float)$det->cantidad;
            $cantidadStr = (floor($cantidadVal) == $cantidadVal)
                ? number_format($cantidadVal, 0, ',', '.')
                : number_format($cantidadVal, 2, ',', '.');
            $pdf->Cell(12, 5, $cantidadStr, 1, 0, 'C');
            $desc = isset($det->producto_nombre) ? utf8_decode((string)$det->producto_nombre) : '';
            if (strlen($desc) > 40) $desc = substr($desc, 0, 40);
            $pdf->Cell(65, 5, $desc, 1, 0, 'L');
            $pdf->Cell(20, 5, number_format($det->precio, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->costo, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->importe, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(23, 5, number_format($det->importe, 2, '.', ','), 1, 1, 'R');
            $totalCalculado += (float)$det->importe;
        }

        // Totales
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 9);
        
        $pdf->Cell(137, 5, '', 0, 0);
        $pdf->Cell(20, 5, 'TOTAL Bs', 0, 0, 'L');
        $pdf->Cell(23, 5, number_format($ingreso->importe ?: $totalCalculado, 2, '.', ','), 0, 1, 'R');
        $pdf->Ln(3);

        // Detalle/Observaciones
        if (!empty($ingreso->Observaciones)) {
            $pdf->SetFont('Arial', '', 9);
            $detalleTxt = utf8_decode((string)$ingreso->Observaciones);
            $pdf->MultiCell(0, 5, 'Detalle: ' . $detalleTxt, 0, 'L');
            $pdf->Ln(2);
        }

        // 🔴 MOTIVO DE ANULACIÓN (si está anulado)
        if ($ingreso->estado == 0 && $motivoAnulacion) {
            $pdf->Ln(3);
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->SetTextColor(255, 0, 0); // Rojo
            $motivoTxt = utf8_decode((string)$motivoAnulacion);
            $pdf->MultiCell(0, 5, utf8_decode('MOTIVO DE LA ANULACIÓN: ') . $motivoTxt, 0, 'L');
            $pdf->SetTextColor(0, 0, 0); // Volver a negro
            $pdf->Ln(2);
        }

        // Firmas
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, '______________________________', 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, '______________________________', 0, 1, 'C');
        $pdf->Cell(60, 5, 'Recibido por: ' . utf8_decode((string)($ingreso->persona_recibe ?: '')), 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, 'Entregado por: ' . utf8_decode((string)($ingreso->persona_entrega ?: '')), 0, 1, 'C');

        // 🔴 MARCA DE AGUA ROJA SI ESTÁ ANULADO (DIBUJADA AL FINAL, ENCIMA DE TODO)
        if ($ingreso->estado == 0) {
            $pdf->rotatedWatermark('ANULADO', 70, [255, 80, 80], 0.3);
        }

        // Permitir modo de visualización o descarga según query param
        $disposition = request()->query('view') === '1'
            ? 'inline'
            : 'attachment';
        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="NotaDeIngreso_NI-' . $numPadded . '.pdf"',
        ]);
    }

    /**
     * Anula un ingreso: actualiza estado, crea registros de anulación y revierte stock/costo.
     * Payload esperado: { ingreso_id, motivo }
     */
    public function anularIngreso(Request $request): JsonResponse
    {
        $ingresoId = (int)$request->input('ingreso_id', 0);
        $motivo = (string)$request->input('motivo', '');

        if (!$ingresoId) {
            return response()->json(['message' => 'ingreso_id es requerido'], 422);
        }
        if (!$motivo || trim($motivo) === '') {
            return response()->json(['message' => 'motivo es requerido'], 422);
        }

        try {
            DB::transaction(function () use ($ingresoId, $motivo) {
                // 1. Verificar que el ingreso existe y no está anulado
                $ingreso = Ingreso::query()
                    ->lockForUpdate()
                    ->find($ingresoId);
                if (!$ingreso) {
                    throw new \Exception('Ingreso no encontrado');
                }
                if ($ingreso->estado == 0) {
                    throw new \Exception('El ingreso ya ha sido anulado');
                }

                // 2. Actualizar estado del ingreso a 0 (anulado)
                $ingreso->estado = 0;
                $ingreso->save();

                // 3. Crear registro en anulacion_ingreso
                $anulacionIngreso = \App\Models\Anulacion_Ingreso::create([
                    'ingreso_id' => $ingresoId,
                    'usuario' => auth()->id() ?? 0,
                    'motivo' => trim($motivo),
                    'fecha_anulacion' => now(),
                ]);
                $idAnulacionIngreso = $anulacionIngreso->id_anulacion_ingreso ?? $anulacionIngreso->id;

                // 4. Obtener detalles del ingreso
                $detalles = Detalle_Ingreso::query()
                    ->where('ingreso_id', $ingresoId)
                    ->lockForUpdate()
                    ->get();

                // 5. Procesar cada detalle
                foreach ($detalles as $detalle) {
                    $asignacionId = (int)$detalle->asignacion_id;
                    $cantidadRevertida = (float)$detalle->cantidad;
                    $costoDetalle = (float)$detalle->costo;

                    // Obtener asignación actual
                    $asignacion = Asignacion_Producto::query()
                        ->lockForUpdate()
                        ->find($asignacionId);
                    if (!$asignacion) continue;

                    $stockPrevio = (float)($asignacion->stock ?? 0);
                    $stockResultante = $stockPrevio - $cantidadRevertida;
                    $nuevoCostoTotal = (float)($asignacion->costo_total ?? 0) - $costoDetalle;

                    // 6. Crear registro en anulacion_detalle
                    \App\Models\Anulacion_Detalle::create([
                        'anulacion_ingreso_id' => $idAnulacionIngreso,
                        'asignacion_id' => $asignacionId,
                        'cantidad_revertida' => $cantidadRevertida,
                        'stock_previo' => $stockPrevio,
                        'stock_resultante' => $stockResultante,
                    ]);

                    // 7. Actualizar asignación_productos
                    $asignacion->stock = $stockResultante;
                    $asignacion->costo_total = max(0, $nuevoCostoTotal); // Evitar negativos
                    $asignacion->save();
                }
            });

            return response()->json([
                'message' => 'Ingreso anulado correctamente',
                'ingreso_id' => $ingresoId,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al anular ingreso', [
                'ingreso_id' => $ingresoId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error al anular ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Guarda una salida (movimiento de tipo 1).
     * Payload esperado: {
     *   numero, id_visual, fecha_salida, area_id, area_nombre,
     *   solicitado_por, entregado_por, persona_entrega, persona_recibe,
     *   descripcion, observaciones, total_importe,
     *   items[] { asignacion_id, producto_nombre, codigo, cantidad, precio_unitario, importe }
     * }
     */
    public function storeSalida(Request $request): JsonResponse
    {
        $numero = $request->input('numero');
        $items = $request->input('items', []);

        if (!is_numeric($numero) || (int)$numero <= 0) {
            return response()->json(['message' => 'Número de salida no válido.'], 422);
        }
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'Debe enviar al menos un artículo.'], 422);
        }

        $areaId = (int)$request->input('area_id', 0);
        if ($areaId <= 0) {
            return response()->json(['message' => 'Área no válida.'], 422);
        }

        $numero = (int)$numero;
        $solicitadoPor = (string)$request->input('solicitado_por', '');
        $entregadoPor = (string)$request->input('entregado_por', '');
        $personaEntrega = (string)$request->input('persona_entrega', '');
        $personaRecibe = (string)$request->input('persona_recibe', '');
        $fechaSalida = $request->input('fecha_salida', now()->format('Y-m-d H:i:s'));
        $descripcion = $request->input('descripcion', '');
        $observaciones = $request->input('observaciones', '');
        $totalImporte = (float)$request->input('total_importe', 0);

        try {
            $movimiento = null;
            DB::transaction(function () use (
                &$movimiento, $numero, $areaId, $personaEntrega, $personaRecibe,
                $descripcion, $observaciones, $totalImporte, $items, $fechaSalida
            ) {
                // 1. Crear movimiento (tipo 1 = salida)
                $movimiento = Movimiento::create([
                    'codigo' => $numero,
                    'area' => $areaId,
                    'fecha' => $fechaSalida,
                    'persona_entrega' => $personaEntrega,
                    'persona_recibe' => $personaRecibe,
                    'observaciones' => $observaciones,
                    'tipo' => 1, // Salida
                    'total' => $totalImporte,
                ]);

                $movimientoId = $movimiento->id_movimiento;

                // 2. Procesar detalles de la salida
                foreach ($items as $it) {
                    $asignacionId = (int)($it['asignacion_id'] ?? 0);
                    $cantidad = (float)($it['cantidad'] ?? 0);
                    $costoUnitario = (float)($it['precio_unitario'] ?? 0);
                    $importeItem = (float)($it['importe'] ?? 0);

                    if ($asignacionId <= 0 || $cantidad <= 0) continue;

                    // Crear detalle de movimiento
                    Detalle_Movimiento::create([
                        'movimiento_id' => $movimientoId,
                        'asignacion_id' => $asignacionId,
                        'cantidad' => $cantidad,
                        'costo' => $costoUnitario,
                        'total' => $importeItem,
                    ]);

                    // 3. Actualizar stock en asignación_productos (restar)
                    $asignacion = Asignacion_Producto::query()
                        ->with('producto')
                        ->lockForUpdate()
                        ->find($asignacionId);
                    // Bloquear movimientos si el producto está dado de baja
                    if ($asignacion && (int)($asignacion->estado_dado_baja ?? 0) === 1) {
                        throw new \Exception('El producto está dado de baja y no permite movimientos.');
                    }
                    if ($asignacion) {
                        $stockPrevio = (float)($asignacion->stock ?? 0);
                        $nuevoStock = max(0, $stockPrevio - $cantidad);
                        $nuevoCostoTotal = (float)($asignacion->costo_total ?? 0) - ($cantidad * $costoUnitario);

                        $asignacion->stock = $nuevoStock;
                        $asignacion->costo_total = max(0, $nuevoCostoTotal);

                        // 4. Actualizar estado_movimiento según tipo de producto
                        // Si es Activo Fijo, cambiar estado a 2 (fuera de almacén)
                        // Si es Consumible, mantener en 0
                        if ($asignacion->producto) {
                            $tipoProducto = trim($asignacion->producto->tipo);
                            if (strcasecmp($tipoProducto, 'Activo Fijo') === 0) {
                                $asignacion->estado_movimiento = 2; // Activo Fijo: fuera de almacén
                            }
                        }

                        $asignacion->save();

                        Log::info('Salida registrada', [
                            'asignacion_id' => $asignacionId,
                            'cantidad_salida' => $cantidad,
                            'stock_anterior' => $stockPrevio,
                            'stock_nuevo' => $nuevoStock,
                            'tipo_producto' => $asignacion->producto->tipo ?? 'desconocido',
                            'estado_movimiento' => $asignacion->estado_movimiento,
                        ]);
                    }
                }
            });

            return response()->json([
                'message' => 'Salida registrada correctamente',
                'data' => [
                    'id_movimiento' => $movimiento->id_movimiento,
                    'numero' => $movimiento->codigo,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar salida', [
                'numero' => $numero,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error al guardar salida: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Nueva función para guardar ingresos al almacén (retorno de productos).
     * Esta función suma stock y costo_total en Asignacion_Producto,
     * actualiza estado_movimiento según tipo de producto (activo fijo o consumible),
     * y registra el movimiento tipo=2 con sus detalles.
     */
    public function storeIngresoAlmacen(Request $request): JsonResponse
    {
        $numero = $request->input('numero');
        $items = $request->input('items', []);

        if (!is_numeric($numero) || (int)$numero <= 0) {
            return response()->json(['message' => 'Número de ingreso no válido.'], 422);
        }
        if (!is_array($items) || count($items) === 0) {
            return response()->json(['message' => 'Debe enviar al menos un artículo.'], 422);
        }

        $areaId = (int)$request->input('area_id', 0);
        if ($areaId <= 0) {
            return response()->json(['message' => 'Área no válida.'], 422);
        }

        $numero = (int)$numero;
        $personaEntrega = (string)$request->input('persona_entrega', '');
        $personaRecibe = (string)$request->input('persona_recibe', '');
        $fechaIngreso = $request->input('fecha_ingreso', now()->format('Y-m-d H:i:s'));
        $descripcion = $request->input('descripcion', '');
        $observaciones = $request->input('observaciones', '');
        $totalImporte = (float)$request->input('total_importe', 0);

        try {
            $movimiento = null;
            DB::transaction(function () use (
                &$movimiento, $numero, $areaId, $personaEntrega, $personaRecibe,
                $descripcion, $observaciones, $totalImporte, $items, $fechaIngreso
            ) {
                // 1. Crear movimiento (tipo 2 = ingreso)
                $movimiento = Movimiento::create([
                    'codigo' => $numero,
                    'area' => $areaId,
                    'fecha' => $fechaIngreso,
                    'persona_entrega' => $personaEntrega,
                    'persona_recibe' => $personaRecibe,
                    'observaciones' => $observaciones,
                    'tipo' => 2, // Ingreso
                    'total' => $totalImporte,
                ]);

                $movimientoId = $movimiento->id_movimiento;

                // 2. Procesar cada item del ingreso
                foreach ($items as $it) {
                    $asignacionId = (int)($it['asignacion_id'] ?? 0);
                    $cantidad = (float)($it['cantidad'] ?? 0);
                    $precioUnitario = (float)($it['precio_unitario'] ?? 0);
                    $importe = (float)($it['importe'] ?? 0);

                    if ($asignacionId <= 0 || $cantidad <= 0) continue;

                    // 3. Buscar la asignación del producto
                    $asignacion = Asignacion_Producto::query()
                        ->with('producto')
                        ->lockForUpdate()
                        ->find($asignacionId);

                    // Bloquear movimientos si el producto está dado de baja
                    if ($asignacion && (int)($asignacion->estado_dado_baja ?? 0) === 1) {
                        throw new \Exception('El producto está dado de baja y no permite movimientos.');
                    }

                    if (!$asignacion) {
                        Log::warning("Asignación no encontrada: {$asignacionId}");
                        continue;
                    }

                    // 4. Sumar cantidad al stock y sumar importe al costo_total
                    $stockAnterior = (float)($asignacion->stock ?? 0);
                    $costoTotalAnterior = (float)($asignacion->costo_total ?? 0);

                    $nuevoStock = $stockAnterior + $cantidad;
                    $nuevoCostoTotal = $costoTotalAnterior + $importe;

                    $asignacion->stock = $nuevoStock;
                    $asignacion->costo_total = $nuevoCostoTotal;

                    // 5. Validar si es activo fijo o consumible
                    if ($asignacion->producto) {
                        $tipoProducto = trim(strtolower($asignacion->producto->tipo ?? ''));

                        // Si es activo fijo: estado_movimiento = 1 (ingresó al almacén)
                        if ($tipoProducto === 'activo fijo') {
                            $asignacion->estado_movimiento = 1;
                        } else {
                            // Si es consumible: estado_movimiento = 0
                            $asignacion->estado_movimiento = 0;
                        }
                    }

                    $asignacion->save();

                    // 6. Registrar detalle del movimiento
                    Detalle_Movimiento::create([
                        'movimiento_id' => $movimientoId,
                        'asignacion_id' => $asignacionId,
                        'cantidad' => $cantidad,
                        'costo' => $precioUnitario,
                        'total' => $importe,
                    ]);

                    Log::info('Ingreso al almacén registrado', [
                        'asignacion_id' => $asignacionId,
                        'producto_id' => $asignacion->producto_id ?? null,
                        'cantidad_ingreso' => $cantidad,
                        'stock_anterior' => $stockAnterior,
                        'stock_nuevo' => $nuevoStock,
                        'costo_total_anterior' => $costoTotalAnterior,
                        'costo_total_nuevo' => $nuevoCostoTotal,
                        'tipo_producto' => $asignacion->producto->tipo ?? 'desconocido',
                        'estado_movimiento' => $asignacion->estado_movimiento,
                    ]);
                }
            });

            return response()->json([
                'message' => 'Ingreso al almacén registrado correctamente',
                'data' => [
                    'id_movimiento' => $movimiento->id_movimiento,
                    'numero' => $movimiento->codigo,
                ],
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Error al guardar ingreso al almacén', [
                'numero' => $numero,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error al guardar ingreso: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lista de salidas (movimientos tipo=1) con paginación, búsqueda y ordenamiento.
     * Parámetros opcionales:
     * - q: término de búsqueda
     * - page, per_page, sort_by, sort_dir
     */
    public function getSalidas(Request $request): JsonResponse
    {
        $allowedSort = ['id_movimiento', 'codigo', 'area', 'fecha', 'total', 'observaciones'];

        $search = (string) $request->input('q', '');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'id_movimiento');
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id_movimiento';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Movimiento::query()
            ->where('tipo', 1) // Solo salidas
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'a.nombre as area_nombre',
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('i_movimiento.codigo', 'like', $like)
                    ->orWhere('a.nombre', 'like', $like)
                    ->orWhere('i_movimiento.observaciones', 'like', $like)
                    ->orWhere('i_movimiento.persona_entrega', 'like', $like)
                    ->orWhere('i_movimiento.persona_recibe', 'like', $like);
            });
        }

        // Mapear sort_by a columna real
        $sortColumn = $sortBy;
        if ($sortBy === 'area') {
            $sortColumn = 'a.nombre';
        } elseif (in_array($sortBy, ['codigo', 'fecha', 'total', 'observaciones'], true)) {
            $sortColumn = 'i_movimiento.' . $sortBy;
        }

        $paginator = $query
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Lista de ingresos (movimientos tipo=2) con paginación, búsqueda y ordenamiento.
     */
    public function getIngresosMovimiento(Request $request): JsonResponse
    {
        $allowedSort = ['id_movimiento', 'codigo', 'area', 'fecha', 'total', 'observaciones'];

        $search = (string) $request->input('q', '');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = max(1, min($perPage, 100));
        $page = (int) $request->input('page', 1);
        $sortBy = $request->input('sort_by', 'id_movimiento');
        if (!in_array($sortBy, $allowedSort, true)) {
            $sortBy = 'id_movimiento';
        }
        $sortDir = strtolower((string) $request->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';

        $query = Movimiento::query()
            ->where('tipo', 2) // Solo ingresos
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'a.nombre as area_nombre',
            ]);

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('i_movimiento.codigo', 'like', $like)
                    ->orWhere('a.nombre', 'like', $like)
                    ->orWhere('i_movimiento.observaciones', 'like', $like)
                    ->orWhere('i_movimiento.persona_entrega', 'like', $like)
                    ->orWhere('i_movimiento.persona_recibe', 'like', $like);
            });
        }

        $sortColumn = $sortBy;
        if ($sortBy === 'area') {
            $sortColumn = 'a.nombre';
        } elseif (in_array($sortBy, ['codigo', 'fecha', 'total', 'observaciones'], true)) {
            $sortColumn = 'i_movimiento.' . $sortBy;
        }

        $paginator = $query
            ->orderBy($sortColumn, $sortDir)
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'sort_by' => $sortBy,
                'sort_dir' => $sortDir,
                'q' => $search,
            ],
        ]);
    }

    /**
     * Obtiene una salida específica con sus detalles.
     */
    public function showSalida($id): JsonResponse
    {
        $movimiento = Movimiento::query()
            ->where('tipo', 1)
            ->where('id_movimiento', $id)
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'i_movimiento.area',
                'a.nombre as area_nombre',
            ])
            ->first();

        if (!$movimiento) {
            return response()->json(['message' => 'Salida no encontrada'], 404);
        }

        $detalles = Detalle_Movimiento::query()
            ->where('movimiento_id', $movimiento->id_movimiento)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_movimientos.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->select([
                'i_detalle_movimientos.id_detalle_movimiento',
                'i_detalle_movimientos.asignacion_id',
                'ap.codigo',
                'pr.nombre as producto_nombre',
                'i_detalle_movimientos.cantidad',
                'i_detalle_movimientos.costo',
                'i_detalle_movimientos.total',
            ])
            ->get();

        return response()->json([
            'movimiento' => $movimiento,
            'detalles' => $detalles,
        ], 200);
    }

    /**
     * Obtiene un ingreso (movimiento) específico con sus detalles.
     */
    public function showIngresoMovimiento($id): JsonResponse
    {
        $movimiento = Movimiento::query()
            ->where('tipo', 2)
            ->where('id_movimiento', $id)
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'i_movimiento.area',
                'a.nombre as area_nombre',
            ])
            ->first();

        if (!$movimiento) {
            return response()->json(['message' => 'Ingreso no encontrado'], 404);
        }

        $detalles = Detalle_Movimiento::query()
            ->where('movimiento_id', $movimiento->id_movimiento)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_movimientos.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->select([
                'i_detalle_movimientos.id_detalle_movimiento',
                'i_detalle_movimientos.asignacion_id',
                'ap.codigo',
                'pr.nombre as producto_nombre',
                'i_detalle_movimientos.cantidad',
                'i_detalle_movimientos.costo',
                'i_detalle_movimientos.total',
            ])
            ->get();

        return response()->json([
            'movimiento' => $movimiento,
            'detalles' => $detalles,
        ], 200);
    }

    /**
     * Genera PDF de una salida específica con el mismo formato que generateIngresoPdf.
     */
    public function generateSalidaPdf($id)
    {
        $movimiento = Movimiento::query()
            ->where('tipo', 1)
            ->where('id_movimiento', $id)
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'a.nombre as area_nombre',
            ])
            ->first();

        if (!$movimiento) {
            abort(404, 'Salida no encontrada');
        }

        $detalles = Detalle_Movimiento::query()
            ->where('movimiento_id', $movimiento->id_movimiento)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_movimientos.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->select([
                'ap.codigo',
                'pr.nombre as producto_nombre',
                'i_detalle_movimientos.cantidad',
                'i_detalle_movimientos.costo',
                'i_detalle_movimientos.total',
            ])
            ->get();

        $numPadded = str_pad($movimiento->codigo, 6, '0', STR_PAD_LEFT);

        $pdf = new SalidaPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(15, 15, 15);
        $pdf->numPadded = $numPadded;
        $pdf->fechaSalida = $movimiento->fecha ?? '';
        $pdf->AddPage();

        // Título
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'SALIDA ALMACEN', 0, 1, 'C');
        $pdf->Ln(3);

        // Info general (mismo layout que ingreso)
        $pdf->SetFont('Arial', '', 9);
        $areaNombre = isset($movimiento->area_nombre) ? utf8_decode((string)$movimiento->area_nombre) : '';
        $pdf->Cell(100, 5, 'AREA: ' . $areaNombre, 0, 0, 'L');
        $pdf->Ln(5);

        // Observaciones
        if (!empty($movimiento->observaciones)) {
            $pdf->SetFont('Arial', '', 9);
            $obsTxt = utf8_decode((string)$movimiento->observaciones);
            $pdf->MultiCell(0, 5, 'Observaciones: ' . $obsTxt, 0, 'L');
            $pdf->Ln(2);
        }

        // Tabla de productos (mismos estilos/anchos que ingreso)
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(20, 6, iconv('UTF-8', 'ISO-8859-1', 'Código'), 1, 0, 'C', true);
        $pdf->Cell(12, 6, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell(65, 6, iconv('UTF-8', 'ISO-8859-1', 'Descripción'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Precio', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'P.Costo', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Importe', 1, 0, 'C', true);
        $pdf->Cell(23, 6, 'Total Bs', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $totalCalculado = 0.0;
        foreach ($detalles as $det) {
            $pdf->Cell(20, 5, $det->codigo ?: '', 1, 0, 'L');

            $cantidadVal = (float)$det->cantidad;
            $cantidadStr = (floor($cantidadVal) == $cantidadVal)
                ? number_format($cantidadVal, 0, ',', '.')
                : number_format($cantidadVal, 2, ',', '.');
            $pdf->Cell(12, 5, $cantidadStr, 1, 0, 'C');

            $desc = isset($det->producto_nombre) ? utf8_decode((string)$det->producto_nombre) : '';
            if (strlen($desc) > 40) $desc = substr($desc, 0, 40);
            $pdf->Cell(65, 5, $desc, 1, 0, 'L');

            // Para salidas usamos costo para rellenar columnas Precio/P.Costo/Importe/Total
            $pdf->Cell(20, 5, number_format($det->costo, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->costo, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->total, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(23, 5, number_format($det->total, 2, '.', ','), 1, 1, 'R');

            $totalCalculado += (float)$det->total;
        }

        // Totales
        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(137, 5, '', 0, 0);
        $pdf->Cell(20, 5, 'TOTAL Bs', 0, 0, 'L');
        $pdf->Cell(23, 5, number_format($movimiento->total ?: $totalCalculado, 2, '.', ','), 0, 1, 'R');
        $pdf->Ln(3);

        // Firmas
        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, '______________________________', 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, '______________________________', 0, 1, 'C');
        $pdf->Cell(60, 5, 'Recibido por: ' . utf8_decode((string)($movimiento->persona_recibe ?: '')), 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, 'Entregado por: ' . utf8_decode((string)($movimiento->persona_entrega ?: '')), 0, 1, 'C');

        // Disposición inline o descarga según query param view=1
        $disposition = request()->query('view') === '1'
            ? 'inline'
            : 'attachment';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="SalidaAlmacen_SA-' . $numPadded . '.pdf"',
        ]);
    }

    /**
     * Genera un PDF exclusivo para ingresos de almacén (prefijo IA) sin afectar otros PDFs.
     */
    public function generateIngresoAlmacenPdf($id)
    {
        $movimiento = Movimiento::query()
            ->where('tipo', 2)
            ->where('id_movimiento', $id)
            ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_movimiento.area')
            ->select([
                'i_movimiento.id_movimiento',
                'i_movimiento.codigo',
                'i_movimiento.fecha',
                'i_movimiento.persona_entrega',
                'i_movimiento.persona_recibe',
                'i_movimiento.observaciones',
                'i_movimiento.total',
                'a.nombre as area_nombre',
            ])
            ->first();

        if (!$movimiento) {
            abort(404, 'Ingreso no encontrado');
        }

        $detalles = Detalle_Movimiento::query()
            ->where('movimiento_id', $movimiento->id_movimiento)
            ->leftJoin('i_asignaciones_productos as ap', 'ap.id_asignacion', '=', 'i_detalle_movimientos.asignacion_id')
            ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'ap.producto_id')
            ->select([
                'ap.codigo',
                'pr.nombre as producto_nombre',
                'i_detalle_movimientos.cantidad',
                'i_detalle_movimientos.costo',
                'i_detalle_movimientos.total',
            ])
            ->get();

        $numPadded = str_pad($movimiento->codigo, 6, '0', STR_PAD_LEFT);

        $pdf = new IngresoAlmacenPDF('P', 'mm', 'Letter');
        $pdf->SetMargins(15, 15, 15);
        $pdf->numPadded = $numPadded;
        $pdf->fechaIngreso = $movimiento->fecha ?? '';
        $pdf->AddPage();

        // Título
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, 'INGRESO ALMACEN', 0, 1, 'C');
        $pdf->Ln(3);

        // Info general (mismo layout base, pero con prefijo IA)
        $pdf->SetFont('Arial', '', 9);
        $areaNombre = isset($movimiento->area_nombre) ? utf8_decode((string)$movimiento->area_nombre) : '';
        $pdf->Cell(100, 5, 'AREA: ' . $areaNombre, 0, 0, 'L');
        $pdf->Ln(5);

        if (!empty($movimiento->observaciones)) {
            $pdf->SetFont('Arial', '', 9);
            $obsTxt = utf8_decode((string)$movimiento->observaciones);
            $pdf->MultiCell(0, 5, 'Observaciones: ' . $obsTxt, 0, 'L');
            $pdf->Ln(2);
        }

        // Tabla de productos
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->Cell(20, 6, iconv('UTF-8', 'ISO-8859-1', 'Código'), 1, 0, 'C', true);
        $pdf->Cell(12, 6, 'Cantidad', 1, 0, 'C', true);
        $pdf->Cell(65, 6, iconv('UTF-8', 'ISO-8859-1', 'Descripción'), 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Precio', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'P.Costo', 1, 0, 'C', true);
        $pdf->Cell(20, 6, 'Importe', 1, 0, 'C', true);
        $pdf->Cell(23, 6, 'Total Bs', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 8);
        $totalCalculado = 0.0;
        foreach ($detalles as $det) {
            $pdf->Cell(20, 5, $det->codigo ?: '', 1, 0, 'L');

            $cantidadVal = (float)$det->cantidad;
            $cantidadStr = (floor($cantidadVal) == $cantidadVal)
                ? number_format($cantidadVal, 0, ',', '.')
                : number_format($cantidadVal, 2, ',', '.');
            $pdf->Cell(12, 5, $cantidadStr, 1, 0, 'C');

            $desc = isset($det->producto_nombre) ? utf8_decode((string)$det->producto_nombre) : '';
            if (strlen($desc) > 40) $desc = substr($desc, 0, 40);
            $pdf->Cell(65, 5, $desc, 1, 0, 'L');

            $pdf->Cell(20, 5, number_format($det->costo, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->costo, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(20, 5, number_format($det->total, 2, '.', ','), 1, 0, 'R');
            $pdf->Cell(23, 5, number_format($det->total, 2, '.', ','), 1, 1, 'R');

            $totalCalculado += (float)$det->total;
        }

        $pdf->Ln(2);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(137, 5, '', 0, 0);
        $pdf->Cell(20, 5, 'TOTAL Bs', 0, 0, 'L');
        $pdf->Cell(23, 5, number_format($movimiento->total ?: $totalCalculado, 2, '.', ','), 0, 1, 'R');
        $pdf->Ln(3);

        $pdf->Ln(10);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, '______________________________', 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, '______________________________', 0, 1, 'C');
        $pdf->Cell(60, 5, 'Recibido por: ' . utf8_decode((string)($movimiento->persona_recibe ?: '')), 0, 0, 'C');
        $pdf->Cell(60, 5, ' ', 0, 0, 'C');
        $pdf->Cell(60, 5, 'Entregado por: ' . utf8_decode((string)($movimiento->persona_entrega ?: '')), 0, 1, 'C');

        $disposition = request()->query('view') === '1'
            ? 'inline'
            : 'attachment';

        return response($pdf->Output('S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition . '; filename="IngresoAlmacen_IA-' . $numPadded . '.pdf"',
        ]);
    }

    /**
     * Calcula el kardex de una asignación de producto.
     * Retorna array de movimientos ordenados cronológicamente con cálculo de saldo por promedio ponderado.
     */
    private function calcularKardex($asignacionId)
    {
        // 1. Obtener ingresos del producto
        $ingresos = DB::table('i_ingresos as ing')
            ->join('i_detalle_ingreso as di', 'ing.id_ingreso', '=', 'di.ingreso_id')
            ->where('di.asignacion_id', $asignacionId)
            ->where('ing.estado', 1) // Solo ingresos activos
            ->select([
                'ing.fecha_ingreso as fecha',
                DB::raw("'NI' as tipo_doc"),
                DB::raw("CONCAT('NI-', LPAD(ing.numero, 6, '0')) as documento"),
                DB::raw("CONCAT('Ingreso: ', COALESCE(ing.factura_numero, 'S/F')) as detalle"),
                'di.cantidad',
                // Costo unitario debe salir de detalle_ingreso.costo (total de la línea) dividido entre la cantidad
                DB::raw('CASE WHEN di.cantidad > 0 THEN di.costo / di.cantidad ELSE 0 END as costo_unitario'),
                // El valor de ingreso (ing_val) debe considerar el costo total de la línea
                'di.costo as ing_val',
            ])
            ->orderBy('ing.fecha_ingreso', 'ASC')
            ->orderBy('ing.id_ingreso', 'ASC')
            ->get();

        // 2. Obtener movimientos (salidas y entradas de almacén)
        $movimientos_bd = DB::table('i_movimiento as mov')
            ->join('i_detalle_movimientos as dm', 'mov.id_movimiento', '=', 'dm.movimiento_id')
            ->where('dm.asignacion_id', $asignacionId)
            ->select([
                'mov.fecha',
                'mov.tipo',
                DB::raw("LPAD(mov.codigo, 6, '0') as codigo"),
                DB::raw("CONCAT('Movimiento: ', COALESCE(mov.observaciones, '')) as detalle"),
                'dm.cantidad',
                'dm.costo as costo_unitario',
                'dm.total as valor',
            ])
            ->orderBy('mov.fecha', 'ASC')
            ->orderBy('mov.id_movimiento', 'ASC')
            ->get();

        // 3. Obtener anulaciones de ingresos (solo de ingresos activos)
        $anulaciones = DB::table('i_anulacion_ingreso as anu')
            ->join('i_anulacion_detalle as ad', 'anu.id_anulacion_ingreso', '=', 'ad.anulacion_ingreso_id')
            ->join('i_ingresos as ing', 'anu.ingreso_id', '=', 'ing.id_ingreso')
            ->where('ad.asignacion_id', $asignacionId)
            ->where('ing.estado', 1) // Solo anulaciones de ingresos activos
            ->select([
                'anu.fecha_anulacion as fecha',
                DB::raw("'IA' as tipo_doc"),
                DB::raw("CONCAT('IA-', LPAD(anu.id_anulacion_ingreso, 6, '0')) as documento"),
                DB::raw("CONCAT('Anulación: ', COALESCE(anu.motivo, '')) as detalle"),
                'ad.cantidad_revertida as cantidad',
            ])
            ->orderBy('anu.fecha_anulacion', 'ASC')
            ->orderBy('anu.id_anulacion_ingreso', 'ASC')
            ->get();

        // 4. Combinar todos los movimientos
        $movimientos = [];
        
        foreach ($ingresos as $ing) {
            $movimientos[] = [
                'fecha' => $ing->fecha,
                'tipo_doc' => $ing->tipo_doc,
                'documento' => $ing->documento,
                'detalle' => $ing->detalle,
                'tipo' => 'ingreso',
                'ingreso' => (float)$ing->cantidad,
                'salida' => 0,
                'costo_unitario' => (float)$ing->costo_unitario,
                'ing_val' => (float)$ing->ing_val,
            ];
        }

        foreach ($movimientos_bd as $mov_bd) {
            if ($mov_bd->tipo == 1) {
                // Tipo 1 = Salida (SA)
                $movimientos[] = [
                    'fecha' => $mov_bd->fecha,
                    'tipo_doc' => 'SA',
                    'documento' => 'SA-' . $mov_bd->codigo,
                    'detalle' => $mov_bd->detalle,
                    'tipo' => 'salida',
                    'ingreso' => 0,
                    'salida' => (float)$mov_bd->cantidad,
                    'costo_unitario' => (float)$mov_bd->costo_unitario,
                    'sal_val' => (float)$mov_bd->valor,
                ];
            } elseif ($mov_bd->tipo == 2) {
                // Tipo 2 = Entrada de almacén (IA)
                $movimientos[] = [
                    'fecha' => $mov_bd->fecha,
                    'tipo_doc' => 'IA',
                    'documento' => 'IA-' . $mov_bd->codigo,
                    'detalle' => $mov_bd->detalle,
                    'tipo' => 'ingreso',
                    'ingreso' => (float)$mov_bd->cantidad,
                    'salida' => 0,
                    'costo_unitario' => (float)$mov_bd->costo_unitario,
                    'ing_val' => (float)$mov_bd->valor,
                ];
            }
        }

        foreach ($anulaciones as $anu) {
            $movimientos[] = [
                'fecha' => $anu->fecha,
                'tipo_doc' => $anu->tipo_doc,
                'documento' => $anu->documento,
                'detalle' => $anu->detalle,
                'tipo' => 'anulacion',
                'ingreso' => 0,
                'salida' => (float)$anu->cantidad,
            ];
        }

        // 5. Ordenar por fecha y luego por tipo_doc (para mantener consistencia)
        usort($movimientos, function ($a, $b) {
            $fechaComp = strtotime($a['fecha']) - strtotime($b['fecha']);
            if ($fechaComp !== 0) return $fechaComp;
            
            // Orden: NI (ingresos), SA (salidas), IA (anulaciones)
            $orden = ['NI' => 0, 'SA' => 1, 'IA' => 2];
            return ($orden[$a['tipo_doc']] ?? 999) - ($orden[$b['tipo_doc']] ?? 999);
        });

        // 6. Calcular saldos con promedio ponderado
        $saldo = 0;
        $saldo_val = 0;
        $costo_promedio = 0;
        $kardex = [];

        foreach ($movimientos as $mov) {
            $sal_val_movimiento = 0;
            $costo_unitario_final = $costo_promedio;
            
            if ($mov['tipo'] === 'ingreso') {
                // Es un ingreso (NI o IA): actualizar promedio ponderado
                $valor_ingreso = $mov['ing_val'];
                $cantidad_anterior = $saldo;
                $valor_anterior = $saldo_val;
                
                // Promedio ponderado: (valor_anterior + valor_ingreso) / (cantidad_anterior + cantidad_ingreso)
                $cantidad_nueva = $cantidad_anterior + $mov['ingreso'];
                if ($cantidad_nueva > 0) {
                    $costo_promedio = ($valor_anterior + $valor_ingreso) / $cantidad_nueva;
                } else {
                    $costo_promedio = 0;
                }
                
                // Mostrar en la fila de ingreso el costo unitario del movimiento (di.costo / cantidad)
                // en lugar del promedio actualizado, tal como se requiere
                if (isset($mov['costo_unitario']) && $mov['costo_unitario'] > 0) {
                    $costo_unitario_final = $mov['costo_unitario'];
                } else {
                    $costo_unitario_final = $costo_promedio;
                }
                $saldo = $cantidad_nueva;
                $saldo_val = $valor_anterior + $valor_ingreso;
            } else {
                // Es una salida (SA)
                $cantidad_salida = $mov['salida'];
                
                // Usar el costo_unitario del movimiento si existe, si no usar promedio
                if (isset($mov['costo_unitario']) && $mov['costo_unitario'] > 0) {
                    $costo_unitario_final = $mov['costo_unitario'];
                    $sal_val_movimiento = $cantidad_salida * $mov['costo_unitario'];
                } else {
                    $costo_unitario_final = $costo_promedio;
                    if ($costo_promedio > 0 && $cantidad_salida > 0) {
                        $sal_val_movimiento = $cantidad_salida * $costo_promedio;
                    }
                }
                
                $saldo = max(0, $saldo - $cantidad_salida);
                $saldo_val = $saldo * $costo_promedio;
            }

            $kardex[] = [
                'fecha' => $mov['fecha'],
                'tipo_doc' => $mov['tipo_doc'],
                'documento' => $mov['documento'],
                'detalle' => $mov['detalle'],
                'ingreso' => $mov['ingreso'],
                'salida' => $mov['salida'],
                'saldo' => round($saldo, 2),
                'costo_unitario' => round($costo_unitario_final, 2),
                'ing_val' => round($mov['ing_val'] ?? 0, 2),
                'sal_val' => round($sal_val_movimiento, 2),
                'saldo_val' => round($saldo_val, 2),
            ];
        }

        return $kardex;
    }

    /**
     * Obtiene los detalles de una asignación de producto específica.
     * Devuelve información del producto, área y asignación en formato JSON.
     */
    public function getAsignacionProducto($id_asignacion): JsonResponse
    {
        try {
            $asignacion = Asignacion_Producto::query()
                ->where('i_asignaciones_productos.id_asignacion', $id_asignacion)
                ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'i_asignaciones_productos.producto_id')
                ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_asignaciones_productos.area_id')
                ->select([
                    'i_asignaciones_productos.id_asignacion',
                    'i_asignaciones_productos.codigo as codigo_asignacion',
                    'i_asignaciones_productos.stock',
                    'i_asignaciones_productos.costo_total',
                    'i_asignaciones_productos.estado_dado_baja',
                    'pr.codigo_barras as codigo',
                    'pr.nombre',
                    'pr.descripcion',
                    'pr.tipo',
                    'pr.unidad_medida',
                    'a.nombre as nombre_area',
                ])
                ->first();

            if (!$asignacion) {
                return response()->json([
                    'message' => 'Asignación de producto no encontrada',
                ], 404);
            }

            // Calcular kardex para esta asignación
            $kardex = $this->calcularKardex($id_asignacion);

            $baja = null;
            if ((int)($asignacion->estado_dado_baja ?? 0) === 1) {
                $bajaRow = Baja_Producto::query()
                    ->where('asignacion_id', $id_asignacion)
                    ->orderByDesc('fecha_baja')
                    ->orderByDesc('id_baja')
                    ->first();
                if ($bajaRow) {
                    $usuarioNombre = null;
                    if ($bajaRow->usuario_registra) {
                        $u = Usuario::find($bajaRow->usuario_registra);
                        $usuarioNombre = $u?->nombre_usuario;
                    }
                    $baja = [
                        'fecha_baja' => $bajaRow->fecha_baja,
                        'motivo' => $bajaRow->motivo,
                        'usuario' => $usuarioNombre,
                        'usuario_id' => $bajaRow->usuario_registra,
                    ];
                }
            }

            return response()->json([
                'producto' => [
                    'codigo' => $asignacion->codigo ?? '',
                    'nombre' => $asignacion->nombre ?? '',
                    'descripcion' => $asignacion->descripcion ?? '',
                    'tipo' => $asignacion->tipo ?? '',
                    'unidad_medida' => $asignacion->unidad_medida ?? '',
                ],
                'asignacion' => [
                    'codigo_asignacion' => $asignacion->codigo_asignacion ?? '',
                    'nombre_area' => $asignacion->nombre_area ?? '',
                    'stock' => $asignacion->stock ?? 0,
                    'costo_total' => $asignacion->costo_total ?? 0,
                    'estado_dado_baja' => (int)($asignacion->estado_dado_baja ?? 0),
                ],
                'baja' => $baja,
                'kardex' => $kardex,
            ], 200);
        } catch (\Throwable $e) {
            Log::error('Error al obtener asignación de producto', [
                'id_asignacion' => $id_asignacion,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'message' => 'Error al obtener la asignación de producto',
            ], 500);
        }
    }

    /**
     * Genera PDF del kardex de una asignación de producto en formato horizontal.
     */
    public function generateKardexPdf($id_asignacion)
    {
        try {
            $fechaInicio = request()->query('fecha_inicio');
            $fechaFin = request()->query('fecha_fin');

            // Normalizar fechas a Y-m-d
            $normalizeDate = function ($date) {
                if (!$date) return null;
                if (strpos($date, '/') !== false) {
                    $parts = explode('/', $date);
                    if (count($parts) === 3) {
                        [$d, $m, $y] = $parts;
                        if (checkdate((int)$m, (int)$d, (int)$y)) {
                            return sprintf('%04d-%02d-%02d', $y, $m, $d);
                        }
                    }
                }
                $ts = strtotime($date);
                return $ts ? date('Y-m-d', $ts) : null;
            };

            $fechaInicio = $normalizeDate($fechaInicio);
            $fechaFin = $normalizeDate($fechaFin);

            // Obtener información de la asignación
            $asignacion = Asignacion_Producto::query()
                ->where('id_asignacion', $id_asignacion)
                ->leftJoin('i_producto as pr', 'pr.id_producto', '=', 'i_asignaciones_productos.producto_id')
                ->leftJoin('i_areas as a', 'a.id_area', '=', 'i_asignaciones_productos.area_id')
                ->select([
                    'i_asignaciones_productos.id_asignacion',
                    'i_asignaciones_productos.codigo',
                    'i_asignaciones_productos.stock',
                    'i_asignaciones_productos.costo_total',
                    'i_asignaciones_productos.estado_dado_baja',
                    'pr.nombre',
                    'pr.tipo',
                    'pr.unidad_medida',
                    'a.nombre as area_nombre',
                ])
                ->first();

            if (!$asignacion) {
                abort(404, 'Asignación no encontrada');
            }

            // Obtener kardex
            $kardexCompleto = $this->calcularKardex($id_asignacion);

            // Filtrar por fechas si se especifican
            $kardex = $kardexCompleto;
            if ($fechaInicio || $fechaFin) {
                $kardex = array_filter($kardexCompleto, function ($item) use ($fechaInicio, $fechaFin) {
                    $itemFecha = (new \DateTime($item['fecha']))->format('Y-m-d');
                    if ($fechaInicio && $itemFecha < $fechaInicio) return false;
                    if ($fechaFin && $itemFecha > $fechaFin) return false;
                    return true;
                });
                $kardex = array_values($kardex);
            }

            // Crear PDF en modo horizontal (landscape) con header/footer personalizados
            $pdf = new KardexPDF('L', 'mm', 'Letter'); // L = landscape
            $pdf->AliasNbPages();
            $pdf->SetMargins(10, 10, 10);
            $pdf->SetAutoPageBreak(true, 15);
            $pdf->AddPage();

            // Encabezado del reporte
            $pdf->SetFont('Arial', 'B', 11);
            $pdf->Cell(0, 6, utf8_decode('KARDEX'), 0, 1, 'C');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, utf8_decode('Reporte de Movimientos de Inventario'), 0, 1, 'C');

            // Mostrar rango de fechas aplicado, si existe filtro
            $rangoTxt = '';
            if ($fechaInicio && $fechaFin) {
                $rangoTxt = 'Del ' . date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
            } elseif ($fechaInicio) {
                $rangoTxt = 'Desde ' . date('d/m/Y', strtotime($fechaInicio));
            } elseif ($fechaFin) {
                $rangoTxt = 'Hasta ' . date('d/m/Y', strtotime($fechaFin));
            }

            if ($rangoTxt !== '') {
                $pdf->Cell(0, 4, utf8_decode($rangoTxt), 0, 1, 'C');
            }

            $pdf->Ln(3);

            // Información de la asignación en dos columnas
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(40, 4, utf8_decode('Código:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 4, utf8_decode($asignacion->codigo ?? '—'), 0, 0);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 4, utf8_decode('Producto:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, utf8_decode($asignacion->nombre ?? '—'), 0, 1);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(40, 4, utf8_decode('Tipo:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 4, utf8_decode($asignacion->tipo ?? '—'), 0, 0);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 4, utf8_decode('Área:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, utf8_decode($asignacion->area_nombre ?? '—'), 0, 1);

            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(40, 4, utf8_decode('Unidad Medida:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(35, 4, utf8_decode($asignacion->unidad_medida ?? '—'), 0, 0);
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(35, 4, utf8_decode('Stock Actual:'), 0, 0);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 4, number_format($asignacion->stock ?? 0, 2, '.', ','), 0, 1);

            // Estado de baja (si aplica)
            $baja = null;
            if ((int)($asignacion->estado_dado_baja ?? 0) === 1) {
                $bajaRow = Baja_Producto::query()
                    ->where('asignacion_id', $id_asignacion)
                    ->orderByDesc('fecha_baja')
                    ->orderByDesc('id_baja')
                    ->first();
                if ($bajaRow) {
                    $usuarioNombre = null;
                    if ($bajaRow->usuario_registra) {
                        $u = Usuario::find($bajaRow->usuario_registra);
                        $usuarioNombre = $u?->nombre_usuario;
                    }
                    $baja = [
                        'fecha_baja' => $bajaRow->fecha_baja,
                        'motivo' => $bajaRow->motivo,
                        'usuario' => $usuarioNombre,
                    ];
                }
            }

            if ($baja) {
                $pdf->Ln(3);
                $pdf->SetFont('Arial', 'B', 9);
                $pdf->SetTextColor(200, 0, 0);
                $pdf->Cell(0, 5, utf8_decode('ESTADO: DADO DE BAJA'), 0, 1, 'L');
                $pdf->SetTextColor(0, 0, 0);
                $pdf->SetFont('Arial', '', 9);
                if (!empty($baja['fecha_baja'])) {
                    $pdf->Cell(0, 4, utf8_decode('Fecha de baja: ' . date('d/m/Y', strtotime($baja['fecha_baja']))), 0, 1, 'L');
                }
                if (!empty($baja['motivo'])) {
                    $pdf->MultiCell(0, 4, utf8_decode('Motivo: ' . $baja['motivo']), 0, 'L');
                }
                if (!empty($baja['usuario'])) {
                    $pdf->Cell(0, 4, utf8_decode('Registrado por: ' . $baja['usuario']), 0, 1, 'L');
                }
                $pdf->Ln(3);
            }

            $pdf->Ln(4);

            // Encabezados de tabla con fondo gris
            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(220, 220, 220);
            
            // Ancho total disponible: 259mm (Letter landscape - márgenes 10mm)
            $colWidths = [
                'fecha' => 22,
                'tipo_doc' => 14,
                'documento' => 27,
                'detalle' => 48,
                'ingreso' => 19,
                'salida' => 19,
                'saldo' => 19,
                'costo_unitario' => 22,
                'ing_val' => 22,
                'sal_val' => 22,
                'saldo_val' => 25,
            ];

            $pdf->Cell($colWidths['fecha'], 5, utf8_decode('Fecha'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['tipo_doc'], 5, utf8_decode('T.Doc'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['documento'], 5, utf8_decode('Documento'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['detalle'], 5, utf8_decode('Detalle'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['ingreso'], 5, utf8_decode('Ing.'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['salida'], 5, utf8_decode('Sal.'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['saldo'], 5, utf8_decode('Saldo'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['costo_unitario'], 5, utf8_decode('C.Unit'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['ing_val'], 5, utf8_decode('Ing.Val'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['sal_val'], 5, utf8_decode('Sal.Val'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['saldo_val'], 5, utf8_decode('Saldo Val'), 1, 1, 'C', true);

            // Datos de kardex
            $pdf->SetFont('Arial', '', 8);
            $totalIngresos = 0;
            $totalSalidas = 0;
            $totalIngVal = 0;
            $totalSalVal = 0;

            foreach ($kardex as $item) {
                $fecha = (new \DateTime($item['fecha']))->format('d/m/Y');
                $ingreso = (float)($item['ingreso'] ?? 0);
                $salida = (float)($item['salida'] ?? 0);
                $saldo = (float)($item['saldo'] ?? 0);
                $costo_unitario = (float)($item['costo_unitario'] ?? 0);
                $ing_val = (float)($item['ing_val'] ?? 0);
                $sal_val = (float)($item['sal_val'] ?? 0);
                $saldo_val = (float)($item['saldo_val'] ?? 0);

                $totalIngresos += $ingreso;
                $totalSalidas += $salida;
                $totalIngVal += $ing_val;
                $totalSalVal += $sal_val;

                $pdf->Cell($colWidths['fecha'], 5, $fecha, 1, 0, 'L');
                $pdf->Cell($colWidths['tipo_doc'], 5, utf8_decode($item['tipo_doc'] ?? ''), 1, 0, 'C');
                $pdf->Cell($colWidths['documento'], 5, utf8_decode($item['documento'] ?? ''), 1, 0, 'L');
                
                $detalle = utf8_decode($item['detalle'] ?? '');
                if (strlen($detalle) > 18) $detalle = substr($detalle, 0, 18) . '...';
                $pdf->Cell($colWidths['detalle'], 5, $detalle, 1, 0, 'L');
                
                $pdf->Cell($colWidths['ingreso'], 5, number_format($ingreso, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['salida'], 5, number_format($salida, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['saldo'], 5, number_format($saldo, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['costo_unitario'], 5, number_format($costo_unitario, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['ing_val'], 5, number_format($ing_val, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['sal_val'], 5, number_format($sal_val, 2, '.', ','), 1, 0, 'R');
                $pdf->Cell($colWidths['saldo_val'], 5, number_format($saldo_val, 2, '.', ','), 1, 1, 'R');
            }

            // Fila de totales
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($colWidths['fecha'], 5, utf8_decode('TOTALES'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['tipo_doc'], 5, '', 1, 0, 'C', true);
            $pdf->Cell($colWidths['documento'], 5, '', 1, 0, 'C', true);
            $pdf->Cell($colWidths['detalle'], 5, '', 1, 0, 'C', true);
            $pdf->Cell($colWidths['ingreso'], 5, number_format($totalIngresos, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['salida'], 5, number_format($totalSalidas, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['saldo'], 5, '', 1, 0, 'R', true);
            $pdf->Cell($colWidths['costo_unitario'], 5, '', 1, 0, 'R', true);
            $pdf->Cell($colWidths['ing_val'], 5, number_format($totalIngVal, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['sal_val'], 5, number_format($totalSalVal, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['saldo_val'], 5, '', 1, 1, 'R', true);

            $pdf->Ln(8);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 4, utf8_decode('Generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'R');

            $filename = 'Kardex_' . str_pad($asignacion->id_asignacion, 6, '0', STR_PAD_LEFT) . '.pdf';

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al generar kardex PDF', [
                'id_asignacion' => $id_asignacion,
                'error' => $e->getMessage(),
            ]);
            abort(500, 'Error al generar PDF');
        }
    }

    /**
     * Reporte de Notas de Ingreso con filtros avanzados
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reporteNotasIngreso(Request $request)
    {
        try {
            // Obtener filtros del request
            $areaId = $request->input('area_id');
            $proveedorId = $request->input('proveedor_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $fechaFactura = $request->input('fecha_factura');
            $estado = $request->input('estado'); // 'activo', 'anulado', o null (todos)

            // Log para debug
            Log::info('Reporte de Notas de Ingreso - Filtros recibidos', [
                'area_id' => $areaId,
                'proveedor_id' => $proveedorId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'fecha_factura' => $fechaFactura,
                'estado' => $estado
            ]);

            // Query base - Solo ingresos, sin detalle de productos
            $query = DB::table('i_ingresos as ing')
                ->leftJoin('i_proveedores as prov', 'ing.proveedor_id', '=', 'prov.id_proveedores')
                ->leftJoin('i_anulacion_ingreso as anu', 'ing.id_ingreso', '=', 'anu.ingreso_id')
                ->select([
                    'ing.id_ingreso',
                    'ing.numero',
                    'ing.fecha_ingreso',
                    'ing.fecha_factura',
                    'ing.factura_numero',
                    'ing.estado',
                    'ing.importe',
                    'ing.persona_recibe',
                    'ing.persona_entrega',
                    'ing.Observaciones',
                    'prov.nombre as proveedor',
                    DB::raw('CASE WHEN anu.id_anulacion_ingreso IS NOT NULL THEN 1 ELSE 0 END as tiene_anulacion'),
                    'anu.fecha_anulacion',
                    'anu.motivo as motivo_anulacion'
                ])
                ->orderBy('ing.fecha_ingreso', 'DESC')
                ->orderBy('ing.numero', 'DESC');

            // Aplicar filtros
            if ($proveedorId) {
                $query->where('ing.proveedor_id', $proveedorId);
            }

            if ($fechaInicio) {
                $query->whereDate('ing.fecha_ingreso', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('ing.fecha_ingreso', '<=', $fechaFin);
            }

            if ($fechaFactura) {
                $query->whereDate('ing.fecha_factura', '=', $fechaFactura);
            }

            // Filtro por estado
            if ($estado === 'activo') {
                $query->where('ing.estado', 1);
            } elseif ($estado === 'anulado') {
                $query->where('ing.estado', 0);
            }

            $registros = $query->get();

            Log::info('Reporte de Notas de Ingreso - Registros obtenidos', [
                'total_registros' => $registros->count(),
                'sql' => $query->toSql()
            ]);

            // Formatear los registros para el frontend
            $reporte = $registros->map(function($item) {
                return [
                    'id_ingreso' => $item->id_ingreso,
                    'numero' => 'NI-' . str_pad($item->numero, 6, '0', STR_PAD_LEFT),
                    'fecha_ingreso' => $item->fecha_ingreso,
                    'fecha_factura' => $item->fecha_factura,
                    'factura_numero' => $item->factura_numero ?: 'S/F',
                    'proveedor' => $item->proveedor ?: 'Sin Proveedor',
                    'importe' => (float)$item->importe,
                    'persona_recibe' => $item->persona_recibe ?: '-',
                    'persona_entrega' => $item->persona_entrega ?: '-',
                    'observaciones' => $item->Observaciones ?: '-',
                    'estado' => $item->estado == 1 ? 'Activo' : 'Anulado',
                    'estado_valor' => (int)$item->estado,
                    'tiene_anulacion' => (bool)$item->tiene_anulacion,
                    'fecha_anulacion' => $item->fecha_anulacion,
                    'motivo_anulacion' => $item->motivo_anulacion
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $reporte,
                'total_registros' => $reporte->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de notas de ingreso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener lista de áreas para el filtro del reporte
     * 
     * @return JsonResponse
     */
    public function getAreasParaReporte()
    {
        try {
            $areas = Area::select('id_area', 'nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $areas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener áreas'
            ], 500);
        }
    }

    /**
     * Obtener lista de proveedores para el filtro del reporte
     * 
     * @return JsonResponse
     */
    public function getProveedoresParaReporte()
    {
        try {
            $proveedores = Proveedor::select('id_proveedores', 'nombre')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $proveedores
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener proveedores'
            ], 500);
        }
    }

    /**
     * Generar PDF del Reporte de Notas de Ingreso
     * 
     * @param Request $request
     * @return Response
     */
    public function reporteNotasIngresoPDF(Request $request)
    {
        try {
            // Obtener filtros del request
            $proveedorId = $request->input('proveedor_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');
            $fechaFactura = $request->input('fecha_factura');
            $estado = $request->input('estado');

            // Query base - misma lógica que reporteNotasIngreso
            $query = DB::table('i_ingresos as ing')
                ->leftJoin('i_proveedores as prov', 'ing.proveedor_id', '=', 'prov.id_proveedores')
                ->leftJoin('i_anulacion_ingreso as anu', 'ing.id_ingreso', '=', 'anu.ingreso_id')
                ->select([
                    'ing.id_ingreso',
                    'ing.numero',
                    'ing.fecha_ingreso',
                    'ing.fecha_factura',
                    'ing.factura_numero',
                    'ing.estado',
                    'ing.importe',
                    'ing.persona_recibe',
                    'ing.persona_entrega',
                    'ing.Observaciones',
                    'prov.nombre as proveedor',
                    DB::raw('CASE WHEN anu.id_anulacion_ingreso IS NOT NULL THEN 1 ELSE 0 END as tiene_anulacion'),
                    'anu.fecha_anulacion',
                    'anu.motivo as motivo_anulacion'
                ])
                ->orderBy('ing.fecha_ingreso', 'DESC')
                ->orderBy('ing.numero', 'DESC');

            // Aplicar filtros
            if ($proveedorId) {
                $query->where('ing.proveedor_id', $proveedorId);
            }

            if ($fechaInicio) {
                $query->whereDate('ing.fecha_ingreso', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('ing.fecha_ingreso', '<=', $fechaFin);
            }

            if ($fechaFactura) {
                $query->whereDate('ing.fecha_factura', '=', $fechaFactura);
            }

            if ($estado === 'activo') {
                $query->where('ing.estado', 1);
            } elseif ($estado === 'anulado') {
                $query->where('ing.estado', 0);
            }

            $registros = $query->get();

            // Crear PDF en formato horizontal (landscape)
            $pdf = new ReporteIngresosPDF('L', 'mm', 'Letter');
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetMargins(10, 10, 10);

            // Título
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, utf8_decode('REPORTE DE NOTAS DE INGRESO'), 0, 1, 'C');

            // Mostrar filtros aplicados
            $pdf->SetFont('Arial', '', 9);
            $filtrosTexto = [];
            
            if ($fechaInicio && $fechaFin) {
                $filtrosTexto[] = 'Del ' . date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
            } elseif ($fechaInicio) {
                $filtrosTexto[] = 'Desde ' . date('d/m/Y', strtotime($fechaInicio));
            } elseif ($fechaFin) {
                $filtrosTexto[] = 'Hasta ' . date('d/m/Y', strtotime($fechaFin));
            }

            if ($proveedorId) {
                $proveedor = DB::table('i_proveedores')->where('id_proveedores', $proveedorId)->first();
                if ($proveedor) {
                    $filtrosTexto[] = 'Proveedor: ' . $proveedor->nombre;
                }
            }

            if ($estado) {
                $filtrosTexto[] = 'Estado: ' . ucfirst($estado);
            }

            if (!empty($filtrosTexto)) {
                $pdf->Cell(0, 5, utf8_decode(implode(' | ', $filtrosTexto)), 0, 1, 'C');
            }

            $pdf->Ln(3);

            // Encabezado de tabla
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(200, 200, 200);
            
            // Anchos de columnas ajustados para llenar exactamente 259mm (ancho útil con márgenes de 1cm)
            $colWidths = [
                'numero' => 21,
                'fecha_ingreso' => 21,
                'fecha_factura' => 21,
                'factura' => 19,
                'proveedor' => 40,
                'importe' => 21,
                'recibe' => 33,
                'entrega' => 33,
                'observaciones' => 35,
                'estado' => 15,
            ];

            $pdf->Cell($colWidths['numero'], 6, utf8_decode('N° Ingreso'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['fecha_ingreso'], 6, utf8_decode('F. Ingreso'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['fecha_factura'], 6, utf8_decode('F. Factura'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['factura'], 6, utf8_decode('N° Factura'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['proveedor'], 6, 'Proveedor', 1, 0, 'C', true);
            $pdf->Cell($colWidths['importe'], 6, 'Importe', 1, 0, 'C', true);
            $pdf->Cell($colWidths['recibe'], 6, 'Recibe', 1, 0, 'C', true);
            $pdf->Cell($colWidths['entrega'], 6, 'Entrega', 1, 0, 'C', true);
            $pdf->Cell($colWidths['observaciones'], 6, 'Observaciones', 1, 0, 'C', true);
            $pdf->Cell($colWidths['estado'], 6, 'Estado', 1, 1, 'C', true);

            // Datos
            $pdf->SetFont('Arial', '', 7);
            $totalImporte = 0;

            foreach ($registros as $reg) {
                // Verificar si necesitamos nueva página
                if ($pdf->GetY() > 180) {
                    $pdf->AddPage();
                    // Re-dibujar encabezado
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->SetFillColor(200, 200, 200);
                    $pdf->Cell($colWidths['numero'], 6, utf8_decode('N° Ingreso'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['fecha_ingreso'], 6, utf8_decode('F. Ingreso'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['fecha_factura'], 6, utf8_decode('F. Factura'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['factura'], 6, utf8_decode('N° Factura'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['proveedor'], 6, 'Proveedor', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['importe'], 6, 'Importe', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['recibe'], 6, 'Recibe', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['entrega'], 6, 'Entrega', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['observaciones'], 6, 'Observaciones', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['estado'], 6, 'Estado', 1, 1, 'C', true);
                    $pdf->SetFont('Arial', '', 7);
                }

                $numero = 'NI-' . str_pad($reg->numero, 6, '0', STR_PAD_LEFT);
                $fechaIngreso = date('d/m/Y', strtotime($reg->fecha_ingreso));
                $fechaFactura = $reg->fecha_factura ? date('d/m/Y', strtotime($reg->fecha_factura)) : '-';
                $factura = $reg->factura_numero ?: 'S/F';
                $proveedor = $reg->proveedor ?: 'Sin Proveedor';
                $importe = number_format($reg->importe, 2, '.', ',');
                $recibe = $reg->persona_recibe ?: '-';
                $entrega = $reg->persona_entrega ?: '-';
                $observaciones = $reg->Observaciones ?: '-';
                $estadoTexto = $reg->estado == 1 ? 'Activo' : 'Anulado';

                // Color de fondo para anulados
                if ($reg->estado == 0) {
                    $pdf->SetFillColor(255, 230, 230);
                } else {
                    $pdf->SetFillColor(255, 255, 255);
                }

                $pdf->Cell($colWidths['numero'], 5, $numero, 1, 0, 'L', true);
                $pdf->Cell($colWidths['fecha_ingreso'], 5, $fechaIngreso, 1, 0, 'C', true);
                $pdf->Cell($colWidths['fecha_factura'], 5, $fechaFactura, 1, 0, 'C', true);
                $pdf->Cell($colWidths['factura'], 5, utf8_decode($factura), 1, 0, 'C', true);
                $pdf->Cell($colWidths['proveedor'], 5, utf8_decode(substr($proveedor, 0, 28)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['importe'], 5, 'Bs. ' . $importe, 1, 0, 'R', true);
                $pdf->Cell($colWidths['recibe'], 5, utf8_decode(substr($recibe, 0, 24)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['entrega'], 5, utf8_decode(substr($entrega, 0, 24)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['observaciones'], 5, utf8_decode(substr($observaciones, 0, 30)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['estado'], 5, utf8_decode($estadoTexto), 1, 1, 'C', true);

                $totalImporte += $reg->importe;
            }

            // Fila de totales
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(230, 230, 230);
            $pdf->Cell($colWidths['numero'] + $colWidths['fecha_ingreso'] + $colWidths['fecha_factura'] + $colWidths['factura'] + $colWidths['proveedor'], 6, 'TOTAL', 1, 0, 'R', true);
            $pdf->Cell($colWidths['importe'], 6, 'Bs. ' . number_format($totalImporte, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['recibe'] + $colWidths['entrega'] + $colWidths['observaciones'] + $colWidths['estado'], 6, '', 1, 1, 'C', true);

            $pdf->Ln(5);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 4, utf8_decode('Generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'R');
            $pdf->Cell(0, 4, utf8_decode('Total de registros: ' . count($registros)), 0, 1, 'R');

            $filename = 'Reporte_Notas_Ingreso_' . date('Ymd_His') . '.pdf';

            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar PDF del reporte de notas de ingreso', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de Movimientos de Inventario con filtros
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reporteMovimientos(Request $request)
    {
        try {
            // Obtener filtros del request
            $tipoMovimiento = $request->input('tipo_movimiento'); // 1 = Salida, 2 = Ingreso
            $areaId = $request->input('area_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            // Query base - solo movimientos con join a areas
            $query = DB::table('i_movimiento as mov')
                ->leftJoin('i_areas as area', DB::raw('CAST(mov.area AS UNSIGNED)'), '=', 'area.id_area')
                ->select([
                    'mov.id_movimiento',
                    'mov.codigo',
                    'mov.tipo',
                    'mov.fecha',
                    'mov.observaciones',
                    'mov.total',
                    'area.nombre as area_nombre'
                ])
                ->orderBy('mov.fecha', 'ASC')
                ->orderBy('mov.codigo', 'ASC');

            // Aplicar filtros
            if ($tipoMovimiento) {
                $query->where('mov.tipo', $tipoMovimiento);
            }

            if ($areaId) {
                $query->where('mov.area', $areaId);
            }

            if ($fechaInicio) {
                $query->whereDate('mov.fecha', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('mov.fecha', '<=', $fechaFin);
            }

            $registros = $query->get();

            // Formatear los registros para el frontend
            $reporte = $registros->map(function($item) {
                $tipo = $item->tipo == 1 ? 'Salida' : 'Ingreso';
                $prefijo = $item->tipo == 1 ? 'SA' : 'IA';
                
                return [
                    'id_movimiento' => $item->id_movimiento,
                    'codigo' => $prefijo . '-' . str_pad($item->codigo, 6, '0', STR_PAD_LEFT),
                    'tipo' => $tipo,
                    'tipo_valor' => (int)$item->tipo,
                    'fecha' => $item->fecha,
                    'area' => $item->area_nombre ?: 'Sin Área',
                    'observaciones' => $item->observaciones ?: '-',
                    'total' => (float)$item->total
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $reporte,
                'total_registros' => $reporte->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de movimientos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    public function reporteMovimientosPDF(Request $request)
    {
        try {
            // Obtener filtros del request
            $tipoMovimiento = $request->input('tipo_movimiento');
            $areaId = $request->input('area_id');
            $fechaInicio = $request->input('fecha_inicio');
            $fechaFin = $request->input('fecha_fin');

            // Query base - misma lógica que reporteMovimientos
            $query = DB::table('i_movimiento as mov')
                ->leftJoin('i_areas as area', DB::raw('CAST(mov.area AS UNSIGNED)'), '=', 'area.id_area')
                ->select([
                    'mov.id_movimiento',
                    'mov.codigo',
                    'mov.tipo',
                    'mov.fecha',
                    'mov.observaciones',
                    'mov.total',
                    'area.nombre as area_nombre'
                ])
                ->orderBy('mov.fecha', 'ASC')
                ->orderBy('mov.codigo', 'ASC');

            // Aplicar filtros
            if ($tipoMovimiento) {
                $query->where('mov.tipo', $tipoMovimiento);
            }

            if ($areaId) {
                $query->where('mov.area', $areaId);
            }

            if ($fechaInicio) {
                $query->whereDate('mov.fecha', '>=', $fechaInicio);
            }

            if ($fechaFin) {
                $query->whereDate('mov.fecha', '<=', $fechaFin);
            }

            $registros = $query->get();

            // Crear PDF en formato horizontal (landscape)
            $pdf = new ReporteMovimientosPDF('L', 'mm', 'Letter');
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetMargins(10, 10, 10);

            // Título
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, utf8_decode('REPORTE DE MOVIMIENTOS DE INVENTARIO'), 0, 1, 'C');

            // Mostrar filtros aplicados
            $pdf->SetFont('Arial', '', 9);
            $filtrosTexto = [];
            
            if ($fechaInicio && $fechaFin) {
                $filtrosTexto[] = 'Del ' . date('d/m/Y', strtotime($fechaInicio)) . ' al ' . date('d/m/Y', strtotime($fechaFin));
            } elseif ($fechaInicio) {
                $filtrosTexto[] = 'Desde ' . date('d/m/Y', strtotime($fechaInicio));
            } elseif ($fechaFin) {
                $filtrosTexto[] = 'Hasta ' . date('d/m/Y', strtotime($fechaFin));
            }

            if ($tipoMovimiento) {
                $tipoTexto = $tipoMovimiento == 1 ? 'Salida' : 'Ingreso';
                $filtrosTexto[] = 'Tipo: ' . $tipoTexto;
            }

            if ($areaId) {
                $area = DB::table('i_areas')->where('id_area', $areaId)->first();
                if ($area) {
                    $filtrosTexto[] = 'Area: ' . $area->nombre;
                }
            }

            if (!empty($filtrosTexto)) {
                $pdf->Cell(0, 5, utf8_decode(implode(' | ', $filtrosTexto)), 0, 1, 'C');
            }

            $pdf->Ln(3);

            // Encabezado de tabla
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(200, 200, 200);
            
            // Anchos de columnas - total 259mm
            $colWidths = [
                'fecha' => 25,
                'tipo' => 25,
                'codigo' => 30,
                'area' => 60,
                'observaciones' => 94,
                'total' => 25,
            ];

            $pdf->Cell($colWidths['fecha'], 6, 'Fecha', 1, 0, 'C', true);
            $pdf->Cell($colWidths['tipo'], 6, 'Tipo', 1, 0, 'C', true);
            $pdf->Cell($colWidths['codigo'], 6, utf8_decode('Código'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['area'], 6, utf8_decode('Área'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['observaciones'], 6, 'Observaciones', 1, 0, 'C', true);
            $pdf->Cell($colWidths['total'], 6, 'Total', 1, 1, 'C', true);

            // Datos
            $pdf->SetFont('Arial', '', 7);
            $totalGeneral = 0;

            foreach ($registros as $reg) {
                // Verificar si necesitamos nueva página
                if ($pdf->GetY() > 180) {
                    $pdf->AddPage();
                    // Re-dibujar encabezado
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->SetFillColor(200, 200, 200);
                    $pdf->Cell($colWidths['fecha'], 6, 'Fecha', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['tipo'], 6, 'Tipo', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['codigo'], 6, utf8_decode('Código'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['area'], 6, utf8_decode('Área'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['observaciones'], 6, 'Observaciones', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['total'], 6, 'Total', 1, 1, 'C', true);
                    $pdf->SetFont('Arial', '', 7);
                }

                $fecha = date('d/m/Y', strtotime($reg->fecha));
                $tipo = $reg->tipo == 1 ? 'Salida' : 'Ingreso';
                $prefijo = $reg->tipo == 1 ? 'SA' : 'IA';
                $codigo = $prefijo . '-' . str_pad($reg->codigo, 6, '0', STR_PAD_LEFT);
                $area = $reg->area_nombre ?: 'Sin Area';
                $observaciones = $reg->observaciones ?: '-';
                $total = number_format($reg->total, 2, '.', ',');

                // Color de fondo según tipo
                if ($reg->tipo == 1) {
                    $pdf->SetFillColor(255, 230, 230); // Rojo claro para salidas
                } else {
                    $pdf->SetFillColor(230, 255, 230); // Verde claro para ingresos
                }

                $pdf->Cell($colWidths['fecha'], 5, $fecha, 1, 0, 'C', true);
                $pdf->Cell($colWidths['tipo'], 5, $tipo, 1, 0, 'C', true);
                $pdf->Cell($colWidths['codigo'], 5, $codigo, 1, 0, 'L', true);
                $pdf->Cell($colWidths['area'], 5, utf8_decode(substr($area, 0, 35)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['observaciones'], 5, utf8_decode(substr($observaciones, 0, 60)), 1, 0, 'L', true);
                $pdf->Cell($colWidths['total'], 5, 'Bs. ' . $total, 1, 1, 'R', true);

                $totalGeneral += $reg->total;
            }

            // Fila de totales
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell($colWidths['fecha'] + $colWidths['tipo'] + $colWidths['codigo'] + $colWidths['area'] + $colWidths['observaciones'], 6, 'TOTAL GENERAL', 1, 0, 'R', true);
            $pdf->Cell($colWidths['total'], 6, 'Bs. ' . number_format($totalGeneral, 2, '.', ','), 1, 1, 'R', true);

            $pdf->Ln(5);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 4, utf8_decode('Generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'R');
            $pdf->Cell(0, 4, utf8_decode('Total de registros: ' . count($registros)), 0, 1, 'R');

            $filename = 'Reporte_Movimientos_' . date('Ymd_His') . '.pdf';

            // Salida del PDF
            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar PDF de movimientos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reporte de Productos Asignados con filtros
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function reporteProductos(Request $request)
    {
        try {
            // Obtener filtros del request
            $areaId = $request->input('area_id');
            $tipo = $request->input('tipo');
            $productoId = $request->input('producto_id');

            // Query base
            $query = DB::table('i_asignaciones_productos as ap')
                ->leftJoin('i_producto as prod', 'ap.producto_id', '=', 'prod.id_producto')
                ->leftJoin('i_areas as area', 'ap.area_id', '=', 'area.id_area')
                ->leftJoin('i_detalle_movimientos as dm', function($join) {
                    $join->on('dm.asignacion_id', '=', 'ap.id_asignacion')
                         ->whereRaw('dm.id_detalle_movimiento = (SELECT MAX(id_detalle_movimiento) FROM i_detalle_movimientos WHERE asignacion_id = ap.id_asignacion)');
                })
                ->leftJoin('i_movimiento as mov', 'mov.id_movimiento', '=', 'dm.movimiento_id')
                ->select([
                    'ap.id_asignacion',
                    'ap.codigo',
                    'ap.stock',
                    'ap.costo_total',
                    'ap.estado_dado_baja',
                    'ap.estado_movimiento',
                    'prod.nombre as producto_nombre',
                    'prod.descripcion',
                    'prod.tipo',
                    'prod.unidad_medida',
                    'area.nombre as area_nombre',
                    'mov.persona_recibe'
                ])
                ->orderBy('ap.codigo', 'ASC');

            // Aplicar filtros
            if ($areaId) {
                $query->where('ap.area_id', $areaId);
            }

            if ($tipo) {
                $query->where('prod.tipo', $tipo);
            }

            if ($productoId) {
                $query->where('ap.producto_id', $productoId);
            }

            $registros = $query->get();

            // Formatear los registros para el frontend
            $reporte = $registros->map(function($item) {
                // Determinar ubicación para activos fijos
                $ubicacion = null;
                $esActivoFijo = strtolower($item->tipo) === 'activo fijo';
                
                if ($esActivoFijo) {
                    $estadoMovimiento = (int)$item->estado_movimiento;
                    if ($estadoMovimiento === 0 || $estadoMovimiento === 1) {
                        $ubicacion = 'En Almacen';
                    } else {
                        $personaRecibe = $item->persona_recibe ?: 'No especificado';
                        $ubicacion = 'Fuera del Almacen - ' . $personaRecibe;
                    }
                }

                return [
                    'id_asignacion' => $item->id_asignacion,
                    'codigo' => $item->codigo,
                    'producto' => $item->producto_nombre ?: 'Sin Nombre',
                    'descripcion' => $item->descripcion ?: '-',
                    'tipo' => $item->tipo ?: '-',
                    'unidad_medida' => $item->unidad_medida ?: '-',
                    'area' => $item->area_nombre ?: 'Sin Área',
                    'stock' => (float)$item->stock,
                    'costo_total' => (float)$item->costo_total,
                    'estado' => $item->estado_dado_baja == 1 ? 'Dado de Baja' : 'Activo',
                    'estado_valor' => (int)$item->estado_dado_baja,
                    'ubicacion' => $ubicacion,
                    'es_activo_fijo' => $esActivoFijo
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $reporte,
                'total_registros' => $reporte->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar reporte de productos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener tipos de productos únicos para filtros
     */
    public function getTiposProducto()
    {
        try {
            $tipos = DB::table('i_producto')
                ->select('tipo')
                ->distinct()
                ->whereNotNull('tipo')
                ->where('tipo', '!=', '')
                ->orderBy('tipo')
                ->pluck('tipo');

            return response()->json([
                'success' => true,
                'data' => $tipos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener productos para filtros
     */
    public function getProductosParaReporte()
    {
        try {
            $productos = DB::table('i_producto')
                ->select('id_producto', 'nombre', 'tipo')
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $productos
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function reporteProductosPDF(Request $request)
    {
        try {
            // Obtener filtros del request
            $areaId = $request->input('area_id');
            $tipo = $request->input('tipo');
            $productoId = $request->input('producto_id');

            // Query base - misma lógica que reporteProductos
            $query = DB::table('i_asignaciones_productos as ap')
                ->leftJoin('i_producto as prod', 'ap.producto_id', '=', 'prod.id_producto')
                ->leftJoin('i_areas as area', 'ap.area_id', '=', 'area.id_area')
                ->leftJoin('i_detalle_movimientos as dm', function($join) {
                    $join->on('dm.asignacion_id', '=', 'ap.id_asignacion')
                         ->whereRaw('dm.id_detalle_movimiento = (SELECT MAX(id_detalle_movimiento) FROM i_detalle_movimientos WHERE asignacion_id = ap.id_asignacion)');
                })
                ->leftJoin('i_movimiento as mov', 'mov.id_movimiento', '=', 'dm.movimiento_id')
                ->select([
                    'ap.id_asignacion',
                    'ap.codigo',
                    'ap.stock',
                    'ap.costo_total',
                    'ap.estado_dado_baja',
                    'ap.estado_movimiento',
                    'prod.nombre as producto_nombre',
                    'prod.descripcion',
                    'prod.tipo',
                    'prod.unidad_medida',
                    'area.nombre as area_nombre',
                    'mov.persona_recibe'
                ])
                ->orderBy('ap.codigo', 'ASC');

            // Aplicar filtros
            if ($areaId) {
                $query->where('ap.area_id', $areaId);
            }

            if ($tipo) {
                $query->where('prod.tipo', $tipo);
            }

            if ($productoId) {
                $query->where('ap.producto_id', $productoId);
            }

            $registros = $query->get();

            // Crear PDF en formato horizontal (landscape)
            $pdf = new ReporteProductosPDF('L', 'mm', 'Letter');
            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetMargins(10, 10, 10);

            // Título
            $pdf->SetFont('Arial', 'B', 14);
            $pdf->Cell(0, 8, utf8_decode('REPORTE DE PRODUCTOS'), 0, 1, 'C');

            // Mostrar filtros aplicados
            $pdf->SetFont('Arial', '', 9);
            $filtrosTexto = [];
            
            if ($productoId) {
                $producto = DB::table('i_producto')->where('id_producto', $productoId)->first();
                if ($producto) {
                    $filtrosTexto[] = 'Producto: ' . $producto->nombre;
                }
            }

            if ($areaId) {
                $area = DB::table('i_areas')->where('id_area', $areaId)->first();
                if ($area) {
                    $filtrosTexto[] = 'Area: ' . $area->nombre;
                }
            }

            if ($tipo) {
                $filtrosTexto[] = 'Tipo: ' . $tipo;
            }

            if (!empty($filtrosTexto)) {
                $pdf->Cell(0, 5, utf8_decode(implode(' | ', $filtrosTexto)), 0, 1, 'C');
            }

            $pdf->Ln(3);

            // Encabezado de tabla
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(200, 200, 200);
            
            // Anchos de columnas - total 259mm
            $colWidths = [
                'codigo' => 25,
                'producto' => 50,
                'tipo' => 30,
                'unidad' => 25,
                'area' => 40,
                'stock' => 20,
                'costo' => 25,
                'estado' => 25,
                'ubicacion' => 19,
            ];

            $pdf->Cell($colWidths['codigo'], 6, utf8_decode('Código'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['producto'], 6, 'Producto', 1, 0, 'C', true);
            $pdf->Cell($colWidths['tipo'], 6, 'Tipo', 1, 0, 'C', true);
            $pdf->Cell($colWidths['area'], 6, utf8_decode('Área'), 1, 0, 'C', true);
            $pdf->Cell($colWidths['unidad'], 6, 'Unidad', 1, 0, 'C', true);
            $pdf->Cell($colWidths['stock'], 6, 'Stock', 1, 0, 'C', true);
            $pdf->Cell($colWidths['costo'], 6, 'Costo Total', 1, 0, 'C', true);
            $pdf->Cell($colWidths['estado'], 6, 'Estado', 1, 0, 'C', true);
            $pdf->Cell($colWidths['ubicacion'], 6, utf8_decode('Ubicación'), 1, 1, 'C', true);

            // Datos
            $pdf->SetFont('Arial', '', 7);
            $totalStock = 0;
            $totalCosto = 0;

            foreach ($registros as $reg) {
                // Verificar si necesitamos nueva página
                if ($pdf->GetY() > 180) {
                    $pdf->AddPage();
                    // Re-dibujar encabezado
                    $pdf->SetFont('Arial', 'B', 8);
                    $pdf->SetFillColor(200, 200, 200);
                    $pdf->Cell($colWidths['codigo'], 6, utf8_decode('Código'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['producto'], 6, 'Producto', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['tipo'], 6, 'Tipo', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['area'], 6, utf8_decode('Área'), 1, 0, 'C', true);
                    $pdf->Cell($colWidths['unidad'], 6, 'Unidad', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['stock'], 6, 'Stock', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['costo'], 6, 'Costo Total', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['estado'], 6, 'Estado', 1, 0, 'C', true);
                    $pdf->Cell($colWidths['ubicacion'], 6, utf8_decode('Ubicación'), 1, 1, 'C', true);
                    $pdf->SetFont('Arial', '', 7);
                }

                $codigo = $reg->codigo;
                $producto = $reg->producto_nombre ?: 'Sin Nombre';
                $tipo = $reg->tipo ?: '-';
                $unidad = $reg->unidad_medida ?: '-';
                $area = $reg->area_nombre ?: 'Sin Area';
                $stock = number_format($reg->stock, 2, '.', ',');
                $costo = number_format($reg->costo_total, 2, '.', ',');
                $estadoTexto = $reg->estado_dado_baja == 1 ? 'Dado de Baja' : 'Activo';

                // Determinar ubicación para activos fijos
                $ubicacion = '-';
                $esActivoFijo = strtolower($reg->tipo) === 'activo fijo';
                if ($esActivoFijo) {
                    $estadoMovimiento = (int)$reg->estado_movimiento;
                    if ($estadoMovimiento === 0 || $estadoMovimiento === 1) {
                        $ubicacion = 'Almacen';
                    } else {
                        $personaRecibe = $reg->persona_recibe ?: 'No especif.';
                        $ubicacion = "Fuera\n" . substr($personaRecibe, 0, 20);
                    }
                }

                // Color de fondo según estado
                if ($reg->estado_dado_baja == 1) {
                    $pdf->SetFillColor(255, 230, 230); // Rojo claro para dado de baja
                } else {
                    $pdf->SetFillColor(255, 255, 255); // Blanco para activos
                }

                // Calcular altura necesaria basada en si ubicación tiene salto de línea
                $alturaFila = strpos($ubicacion, "\n") !== false ? 10 : 5;

                // Guardar posición Y inicial
                $yInicial = $pdf->GetY();
                $xInicial = $pdf->GetX();

                // Dibujar TODAS las celdas con bordes y la altura calculada
                $pdf->Cell($colWidths['codigo'], $alturaFila, '', 1, 0, 'L', true);
                $pdf->Cell($colWidths['producto'], $alturaFila, '', 1, 0, 'L', true);
                $pdf->Cell($colWidths['tipo'], $alturaFila, '', 1, 0, 'L', true);
                $pdf->Cell($colWidths['area'], $alturaFila, '', 1, 0, 'L', true);
                $pdf->Cell($colWidths['unidad'], $alturaFila, '', 1, 0, 'C', true);
                $pdf->Cell($colWidths['stock'], $alturaFila, '', 1, 0, 'R', true);
                $pdf->Cell($colWidths['costo'], $alturaFila, '', 1, 0, 'R', true);
                $pdf->Cell($colWidths['estado'], $alturaFila, '', 1, 0, 'C', true);
                $pdf->Cell($colWidths['ubicacion'], $alturaFila, '', 1, 1, 'C', true);

                // Ahora escribir el contenido sobre las celdas sin bordes
                $pdf->SetXY($xInicial, $yInicial);
                $pdf->Cell($colWidths['codigo'], $alturaFila, $codigo, 0, 0, 'L');
                
                $xProducto = $pdf->GetX();
                // Centrar verticalmente el producto si la fila tiene altura doble
                $yProducto = $alturaFila > 5 ? $yInicial + 2.5 : $yInicial;
                $pdf->SetXY($xProducto, $yProducto);
                $pdf->MultiCell($colWidths['producto'], 5, utf8_decode($producto), 0, 'L');
                
                $pdf->SetXY($xInicial + $colWidths['codigo'] + $colWidths['producto'], $yInicial);
                $pdf->Cell($colWidths['tipo'], $alturaFila, utf8_decode(substr($tipo, 0, 18)), 0, 0, 'L');
                $pdf->Cell($colWidths['area'], $alturaFila, utf8_decode(substr($area, 0, 24)), 0, 0, 'L');
                $pdf->Cell($colWidths['unidad'], $alturaFila, utf8_decode(substr($unidad, 0, 15)), 0, 0, 'C');
                $pdf->Cell($colWidths['stock'], $alturaFila, $stock, 0, 0, 'R');
                $pdf->Cell($colWidths['costo'], $alturaFila, 'Bs. ' . $costo, 0, 0, 'R');
                $pdf->Cell($colWidths['estado'], $alturaFila, utf8_decode($estadoTexto), 0, 0, 'C');
                
                $xUbicacion = $pdf->GetX();
                $pdf->SetXY($xUbicacion, $yInicial);
                $pdf->MultiCell($colWidths['ubicacion'], 5, utf8_decode($ubicacion), 0, 'C');

                // Posicionar para la siguiente fila
                $pdf->SetXY($xInicial, $yInicial + $alturaFila);

                $totalStock += $reg->stock;
                $totalCosto += $reg->costo_total;
            }

            // Fila de totales
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->SetFillColor(220, 220, 220);
            $pdf->Cell($colWidths['codigo'] + $colWidths['producto'] + $colWidths['tipo'] + $colWidths['unidad'] + $colWidths['area'], 6, 'TOTALES', 1, 0, 'R', true);
            $pdf->Cell($colWidths['stock'], 6, number_format($totalStock, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['costo'], 6, 'Bs. ' . number_format($totalCosto, 2, '.', ','), 1, 0, 'R', true);
            $pdf->Cell($colWidths['estado'] + $colWidths['ubicacion'], 6, '', 1, 1, 'C', true);

            $pdf->Ln(5);
            $pdf->SetFont('Arial', '', 8);
            $pdf->Cell(0, 4, utf8_decode('Generado el: ' . date('d/m/Y H:i:s')), 0, 1, 'R');
            $pdf->Cell(0, 4, utf8_decode('Total de registros: ' . count($registros)), 0, 1, 'R');

            $filename = 'Reporte_Productos_' . date('Ymd_His') . '.pdf';

            // Salida del PDF
            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);

        } catch (\Exception $e) {
            Log::error('Error al generar PDF de productos', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }
}


