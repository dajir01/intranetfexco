<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\InventarioController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->post('/login', [AuthController::class, 'login']);
Route::middleware('auth')->post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
});

// Inventario API (protegido por sesión)
Route::middleware('auth')->group(function () {
    Route::get('/inventario/productos', [InventarioController::class, 'productos'])->middleware('ability:products.view');
    Route::get('/inventario/productos/{id}', [InventarioController::class, 'getProducto'])->whereNumber('id')->middleware('ability:products.view');
    Route::post('/inventario/productos', [InventarioController::class, 'storeProducto'])->middleware('ability:products.create');
    Route::patch('/inventario/productos/{producto}', [InventarioController::class, 'updateProducto'])->middleware('ability:products.update');
    Route::post('/inventario/productos/baja', [InventarioController::class, 'bajaProducto'])->middleware('ability:products.baja');
    Route::post('/inventario/productos/{id}/editar', [InventarioController::class, 'editarProductoBasico'])->whereNumber('id')->middleware('ability:products.update');
    Route::get('/inventario/asignaciones-productos', [InventarioController::class, 'getAsignacionesProductos'])->middleware('ability:products.view');
    Route::get('/inventario/asignaciones-productos/disponibles', [InventarioController::class, 'getAsignacionesDisponiblesAutocomplete'])->middleware('ability:products.view');
    Route::post('/inventario/asignaciones-productos', [InventarioController::class, 'assignOrFetchAsignacionProducto'])->middleware('ability:assignments.upsert');
    Route::delete('/inventario/asignaciones-productos', [InventarioController::class, 'deleteAsignacionProducto'])->middleware('ability:assignments.delete');
    Route::get('/inventario/areas', [InventarioController::class, 'areas'])->middleware('ability:areas.view');
    Route::get('/inventario/areas/{id}', [InventarioController::class, 'getArea'])->middleware('ability:areas.view');
    Route::get('/inventario/areas/nombre/{name}', [InventarioController::class, 'getAreaByName'])->middleware('ability:areas.view');
    Route::get('/inventario/movimientos/ultimo-salida', [InventarioController::class, 'ultimoCodigoSalida'])->middleware('ability:movimientos.create');
    Route::get('/inventario/movimientos/salida/next-numero', [InventarioController::class, 'nextSalidaNumero'])->middleware('ability:movimientos.create');
    Route::get('/inventario/movimientos/salida/{id}/pdf', [InventarioController::class, 'generateSalidaPdf'])->whereNumber('id')->middleware('ability:reports.download');
    Route::get('/inventario/movimientos/salida', [InventarioController::class, 'getSalidas'])->middleware('ability:movimientos.view');
    Route::get('/inventario/movimientos/salida/{id}', [InventarioController::class, 'showSalida'])->whereNumber('id')->middleware('ability:movimientos.view');
    Route::post('/inventario/movimientos/salida', [InventarioController::class, 'storeSalida'])->middleware('ability:movimientos.create');
    Route::get('/inventario/movimientos/ultimo-ingreso', [InventarioController::class, 'ultimoCodigoIngresoMovimiento'])->middleware('ability:movimientos.create');
    Route::get('/inventario/movimientos/ingreso/next-numero', [InventarioController::class, 'nextIngresoMovimientoNumero'])->middleware('ability:movimientos.create');
    Route::get('/inventario/movimientos/ingreso-almacen/{id}/pdf', [InventarioController::class, 'generateIngresoAlmacenPdf'])->whereNumber('id')->middleware('ability:reports.download');
    Route::get('/inventario/movimientos/ingreso', [InventarioController::class, 'getIngresosMovimiento'])->middleware('ability:movimientos.view');
    Route::get('/inventario/movimientos/ingreso/{id}', [InventarioController::class, 'showIngresoMovimiento'])->whereNumber('id')->middleware('ability:movimientos.view');
    Route::post('/inventario/movimientos/ingreso-almacen', [InventarioController::class, 'storeIngresoAlmacen'])->middleware('ability:movimientos.create');
    Route::get('/inventario/ingresos', [InventarioController::class, 'ingresos'])->middleware('ability:ingresos.view');
    // Colocar la ruta específica antes de la genérica para evitar captura de 'next-numero' por {id}
    Route::get('/inventario/ingresos/next-numero', [InventarioController::class, 'nextIngresoNumero'])->middleware('ability:ingresos.create');
    Route::get('/inventario/ingresos/{id}/pdf', [InventarioController::class, 'generateIngresoPdf'])->whereNumber('id')->middleware('ability:reports.download');
    Route::get('/inventario/ingresos/{id}', [InventarioController::class, 'showIngreso'])->whereNumber('id')->middleware('ability:ingresos.view');
    Route::patch('/inventario/ingresos/{id}', [InventarioController::class, 'updateIngreso'])->whereNumber('id')->middleware('ability:ingresos.update');
    Route::patch('/inventario/ingresos/{id}/detalles', [InventarioController::class, 'updateIngresoDetalles'])->whereNumber('id')->middleware('ability:ingresos.update');
    Route::get('/inventario/edisingreso/{id}', [InventarioController::class, 'edisingreso'])->whereNumber('id')->middleware('ability:ingresos.update');
    Route::post('/inventario/ingresos', [InventarioController::class, 'storeIngreso'])->middleware('ability:ingresos.create');
    Route::post('/inventario/ingresos/preview', [InventarioController::class, 'previewIngreso'])->middleware('ability:ingresos.create');
    Route::post('/inventario/anularIngreso', [InventarioController::class, 'anularIngreso'])->middleware('ability:ingresos.cancel');
    Route::get('/inventario/proveedores', [InventarioController::class, 'proveedores'])->middleware('ability:providers.view');
    Route::post('/inventario/proveedores', [InventarioController::class, 'storeProveedor'])->middleware('ability:providers.create');
    Route::get('/asignacion-producto/{id_asignacion}', [InventarioController::class, 'getAsignacionProducto'])->middleware('ability:products.view');
    Route::get('/asignacion-producto/{id_asignacion}/kardex-pdf', [InventarioController::class, 'generateKardexPdf'])->whereNumber('id_asignacion')->middleware('ability:reports.download');
    
    // Rutas para el reporte de notas de ingreso
    Route::get('/inventario/reporte/notas-ingreso', [InventarioController::class, 'reporteNotasIngreso'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/notas-ingreso/pdf', [InventarioController::class, 'reporteNotasIngresoPDF'])->middleware('ability:reports.download');
    Route::get('/inventario/reporte/areas', [InventarioController::class, 'getAreasParaReporte'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/proveedores', [InventarioController::class, 'getProveedoresParaReporte'])->middleware('ability:reports.view');
    
    // Rutas para el reporte de movimientos de inventario
    Route::get('/inventario/reporte/movimientos', [InventarioController::class, 'reporteMovimientos'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/movimientos/pdf', [InventarioController::class, 'reporteMovimientosPDF'])->middleware('ability:reports.download');
    
    // Rutas para el reporte de productos
    Route::get('/inventario/reporte/productos', [InventarioController::class, 'reporteProductos'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/tipos-producto', [InventarioController::class, 'getTiposProducto'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/productos-lista', [InventarioController::class, 'getProductosParaReporte'])->middleware('ability:reports.view');
    Route::get('/inventario/reporte/productos/pdf', [InventarioController::class, 'reporteProductosPDF'])->middleware('ability:reports.download');
});

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
