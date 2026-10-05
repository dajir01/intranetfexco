<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\HorarioBiometricoController;
use App\Http\Controllers\FeriaController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InventarioController;
use App\Http\Controllers\PabellonController;
use App\Http\Controllers\StandController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\ContratoController;
use App\Http\Controllers\PagosController;
use App\Http\Controllers\ImprimirController;
use App\Http\Controllers\LocalidadController;
use App\Http\Controllers\NotificacionAdminController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReporteFeriaController;
use App\Http\Controllers\NoticiaController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ControlVentasController;
use App\Http\Controllers\PresupuestoController;
use App\Http\Controllers\SolicitudController;
use App\Http\Controllers\ModeloContratoController;
use App\Http\Controllers\CorrespondenciaController;
use App\Http\Controllers\NotificationConfigurationController;
use App\Http\Controllers\CredencialController;
use App\Http\Controllers\CredencialPinController;
use App\Http\Controllers\ReglamentoFeriaController;

Route::middleware(['auth', 'ability:reports.download'])->get('/reporte-biometrico/exportar', [\App\Http\Controllers\ReporteBiometricoController::class, 'export']);
// API para obtener datos del reporte biométrico
Route::middleware(['auth', 'ability:reports.view'])->get('/api/reporte-biometrico', [\App\Http\Controllers\ReporteBiometricoController::class, 'index']);
Route::middleware(['auth', 'ability:reports.view'])->get('/api/reporte-biometrico/nombres', [\App\Http\Controllers\ReporteBiometricoController::class, 'nombres']);
// Obtener bloqueos temporales de stands para una feria
Route::get('/stands/bloqueos-temporales', [\App\Http\Controllers\StandController::class, 'bloqueosTemporales'])->middleware(['auth', 'ability:contratos.create']);

Route::middleware(['guest', 'throttle:5,1'])->post('/login', [AuthController::class, 'login']);
Route::middleware('auth')->post('/logout', [AuthController::class, 'logout']);
Route::get('/me', [AuthController::class, 'me']);
Route::get('/csrf-token', function () {
    return response()->json([
        'token' => csrf_token(),
    ]);
});

Route::middleware(['auth', 'ability:credenciales.empresas.ver'])->group(function () {
    Route::get('/credenciales/eventos', [CredencialController::class, 'eventos']);
    Route::get('/credenciales/empresas/buscar', [CredencialController::class, 'buscarEmpresas']);
    Route::get('/credenciales/expositores/buscar', [CredencialController::class, 'buscarExpositorPorCi']);
    Route::post('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/acreditados', [CredencialController::class, 'acreditarExpositor'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.acreditados.crear');
    Route::patch('/credenciales/acreditados/{idCredencial}', [CredencialController::class, 'actualizarExpositorAcreditado'])->whereNumber('idCredencial')->middleware('permission:credenciales.acreditados.editar');
    Route::patch('/credenciales/acreditados/{idCredencial}/estado', [CredencialController::class, 'cambiarEstadoCredencial'])->whereNumber('idCredencial');
    Route::patch('/credenciales/acreditados/{idCredencial}/modalidad', [CredencialController::class, 'cambiarModalidadCredencial'])->whereNumber('idCredencial')->middleware('permission:credenciales.modalidad.cambiar');
    Route::post('/credenciales/acreditados/inhabilitar', [CredencialController::class, 'inhabilitarCredenciales']);
    Route::post('/credenciales/acreditados/habilitar', [CredencialController::class, 'habilitarCredenciales']);
    Route::post('/credenciales/acreditados/{idCredencial}/reemplazar', [CredencialController::class, 'reemplazarCredencial'])->whereNumber('idCredencial')->middleware('permission:credenciales.qr.renovar');
    Route::post('/credenciales/acreditados/{idCredencial}/renovar-qr', [CredencialController::class, 'renovarQrCredencial'])->whereNumber('idCredencial')->middleware('permission:credenciales.qr.renovar');
    Route::post('/credenciales/validar-qr', [CredencialController::class, 'validarQrCredencial']);
    Route::post('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/adicionales', [CredencialController::class, 'agregarCredencialesAdicionales'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.cupos.adicionar');
    Route::post('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/adicionales/disminuir', [CredencialController::class, 'disminuirCredencialesAdicionales'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.cupos.quitar');
    Route::get('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/adicionales/historial', [CredencialController::class, 'historialCredencialesAdicionales'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.cupos.historial');
    Route::delete('/credenciales/acreditados/{idCredencial}/eliminar', [CredencialController::class, 'eliminarCredencial'])->whereNumber('idCredencial')->middleware('permission:credenciales.registro.eliminar');
    Route::get('/credenciales/acreditados/{idCredencial}/historial', [CredencialController::class, 'historialCredencial'])->whereNumber('idCredencial')->middleware('permission:credenciales.historial.ver');
    Route::get('/credenciales/empresas/{idEmpresa}/historial-acreditados', [CredencialController::class, 'historialAcreditadosEmpresa'])->whereNumber('idEmpresa')->middleware('permission:credenciales.historial.ver');
    Route::get('/credenciales/eventos/{idFeria}/empresas', [CredencialController::class, 'empresas'])->whereNumber('idFeria');
    Route::get('/credenciales/eventos/{idFeria}/empresas/exportar', [CredencialController::class, 'exportarEmpresasCredenciales'])->whereNumber('idFeria')->middleware('permission:credenciales.empresas.exportar');
    Route::get('/credenciales/eventos/{idFeria}/acreditados', [CredencialController::class, 'acreditadosPorEvento'])->whereNumber('idFeria');
    Route::get('/credenciales/eventos/{idFeria}/acreditados/exportar', [CredencialController::class, 'exportarAcreditados'])->whereNumber('idFeria')->middleware('permission:credenciales.acreditados.exportar');
    Route::get('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}', [CredencialController::class, 'detalleEmpresa'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.acreditados.ver');
    Route::get('/credenciales/eventos/{idFeria}/tipos', [CredencialController::class, 'tiposPorEvento'])->whereNumber('idFeria');
    Route::post('/credenciales/eventos/{idFeria}/tipos/{tipo}/diseño', [CredencialController::class, 'guardarDiseñoTipo'])
        ->whereNumber('idFeria')
        ->middleware('permission:credenciales.tipos.editar');
    Route::get('/credenciales/acreditados/{idCredencial}/descargar-digital', [CredencialController::class, 'descargarCredencialDigital'])->whereNumber('idCredencial')->middleware('permission:credenciales.salida_digital.descargar');
    Route::get('/credenciales/descargas/{archivo}', [CredencialController::class, 'descargarArchivoDigital'])
        ->where('archivo', '[A-Za-z0-9._-]+')
        ->middleware('permission:credenciales.salida_digital.descargar_lote')
        ->name('credenciales.descarga');
    Route::get('/credenciales/acreditados/{idCredencial}/imprimir-fisica', [CredencialController::class, 'imprimirCredencialFisica'])->whereNumber('idCredencial')->middleware('permission:credenciales.salida_fisica.imprimir');
    Route::post('/credenciales/descargar-digitales', [CredencialController::class, 'descargarCredencialesDigitales'])->middleware('permission:credenciales.salida_digital.descargar_lote');
    Route::post('/credenciales/enviar-digitales', [CredencialController::class, 'enviarCredencialesDigitales'])->middleware('permission:credenciales.seleccionados.enviar');
    Route::post('/credenciales/imprimir-fisicas', [CredencialController::class, 'imprimirCredencialesFisicas'])->middleware('permission:credenciales.salida_fisica.imprimir_lote');
    Route::get('/credenciales/acreditados/{idCredencial}/descargas', [CredencialController::class, 'historialDescargasDigitales'])->whereNumber('idCredencial')->middleware('permission:credenciales.historial.ver');
    Route::post('/credenciales/acreditados/resumen-salidas', [CredencialController::class, 'resumenSalidasCredenciales']);
    Route::post('/credenciales/empresas', [CredencialController::class, 'storeEmpresa'])->middleware('permission:credenciales.empresas.crear');
    Route::delete('/credenciales/empresas/vinculaciones/{id}', [CredencialController::class, 'destroyEmpresaVinculacion'])
        ->whereNumber('id')
        ->middleware('permission:credenciales.empresas.eliminar');
    Route::get('/credenciales/pines/eventos', [CredencialPinController::class, 'eventos'])->middleware('permission:credenciales.pines.ver');
    Route::get('/credenciales/eventos/{idFeria}/pines', [CredencialPinController::class, 'index'])->whereNumber('idFeria')->middleware('permission:credenciales.pines.ver');
    Route::get('/credenciales/eventos/{idFeria}/pines/envios', [CredencialPinController::class, 'historialEnvios'])->whereNumber('idFeria')->middleware('permission:credenciales.pines.ver');
    Route::get('/credenciales/eventos/{idFeria}/pines/resumen', [CredencialPinController::class, 'resumen'])->whereNumber('idFeria')->middleware('permission:credenciales.pines.ver');
    Route::get('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/pines/generaciones', [CredencialPinController::class, 'historialGeneraciones'])
        ->whereNumber(['idFeria', 'idEmpresa'])
        ->middleware('permission:credenciales.pines.ver');
    Route::post('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/pines', [CredencialPinController::class, 'generar'])->whereNumber(['idFeria', 'idEmpresa'])->middleware('permission:credenciales.pines.generar');
    Route::get('/credenciales/eventos/{idFeria}/pines/exportar', [CredencialPinController::class, 'exportar'])->whereNumber('idFeria')->middleware('permission:credenciales.pines.exportar');
    Route::post('/credenciales/eventos/{idFeria}/pines/imprimir', [CredencialPinController::class, 'imprimir'])->whereNumber('idFeria')->middleware('permission:credenciales.pines.imprimir');
    Route::post('/credenciales/pines/{idPin}/enviar', [CredencialPinController::class, 'enviar'])->whereNumber('idPin')->middleware('permission:credenciales.pines.enviar');
    Route::post('/credenciales/eventos/{idFeria}/empresas/{idEmpresa}/pines/enviar', [CredencialPinController::class, 'enviarEmpresa'])
        ->whereNumber(['idFeria', 'idEmpresa'])
        ->middleware('permission:credenciales.pines.enviar');
});

Route::get('/autoacreditacion', fn () => view('application'))->name('autoacreditacion');
Route::post('/api/autoacreditacion/validar-pin', [CredencialPinController::class, 'validarPublico'])->middleware('throttle:10,1');
Route::post('/api/autoacreditacion/registrar', [CredencialPinController::class, 'registrarPublico'])->middleware('throttle:10,1');

// Rutas públicas de Contratos (acceso sin autenticación para formularios públicos)
Route::get('/api/formulario/contrato/{id_contrato}/{clave}', [ContratoController::class, 'getContratoPublico'])
    ->whereNumber('id_contrato');
Route::post('/api/formulario/guardar/{id_contrato}/{clave}', [ContratoController::class, 'guardarFormularioPublico'])
    ->whereNumber('id_contrato');

// Rutas públicas para catálogos necesarios en el formulario público
Route::get('/api/paises', [LocalidadController::class, 'getPaises']);
Route::get('/api/ciudades/{idPais}', [LocalidadController::class, 'getCiudadesByPais'])->whereNumber('idPais');
Route::get('/api/autocomplete/paises', [LocalidadController::class, 'autocompletePaises']);
Route::get('/api/autocomplete/ciudades', [LocalidadController::class, 'autocompleteCiudades']);
Route::get('/api/paises/{id}/nombre', [LocalidadController::class, 'getPaisNombre'])->whereNumber('id');
Route::get('/api/ciudades/{id}/nombre', [LocalidadController::class, 'getCiudadNombre'])->whereNumber('id');
Route::get('/api/rubros', [LocalidadController::class, 'getRubros']);
Route::get('/api/subrubros', [LocalidadController::class, 'getSubrubros']);

// Inventario API (protegido por sesión)
Route::middleware('auth')->group(function () {
    // Bloqueo temporal de stands (tiempo real)
    Route::post('/stands/bloquear', [StandController::class, 'bloquearTemporal'])->middleware('ability:contratos.create');
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
    Route::get('/inventario/reporte/productos/excel', [InventarioController::class, 'reporteProductosExcel'])->middleware('ability:reports.download');
    Route::get('/inventario/reporte/productos/imprimir-codigos', [InventarioController::class, 'imprimirCodigos'])->middleware('ability:reports.download');

    // Usuario API
    Route::get('/usuarios', [UsuarioController::class, 'usuarios'])->middleware('ability:users.view');
    Route::post('/usuarios', [UsuarioController::class, 'store'])->middleware('ability:users.create');
    Route::get('/usuarios/{id}', [UsuarioController::class, 'show'])->middleware('ability:users.view');
    Route::patch('/usuarios/{id}', [UsuarioController::class, 'update'])->middleware('ability:users.update');
    Route::patch('/usuarios/{id}/estado', [UsuarioController::class, 'toggleEstado'])->middleware('ability:users.update');

    Route::get('/api/notificaciones/configuraciones', [NotificationConfigurationController::class, 'index'])->middleware('permission:notificaciones.ver');
    Route::get('/api/notificaciones/configuraciones/{eventKey}', [NotificationConfigurationController::class, 'show'])->middleware('permission:notificaciones.ver');
    Route::post('/api/notificaciones/configuraciones', [NotificationConfigurationController::class, 'store'])->middleware('permission:notificaciones.crear');
    Route::put('/api/notificaciones/configuraciones/{eventKey}', [NotificationConfigurationController::class, 'update'])->middleware('permission:notificaciones.editar');
    Route::patch('/api/notificaciones/configuraciones/{eventKey}/toggle', [NotificationConfigurationController::class, 'toggle'])->middleware('permission:notificaciones.editar');
    Route::get('/api/notificaciones/areas', [NotificationConfigurationController::class, 'areas'])->middleware('permission:notificaciones.ver');
    Route::get('/api/notificaciones/usuarios', [NotificationConfigurationController::class, 'users'])->middleware('permission:notificaciones.ver');

    // ACL granular por usuario (nuevo sistema con fallback legado)
    Route::get('/permissions', [PermissionController::class, 'index'])->middleware('permission:usuarios.gestionar_permisos');
    Route::get('/usuarios/{id}/permissions', [PermissionController::class, 'userPermissions'])->middleware('permission:usuarios.gestionar_permisos');
    Route::put('/usuarios/{id}/permissions', [PermissionController::class, 'syncUserPermissions'])->middleware('permission:usuarios.gestionar_permisos');

    // Feria API
    Route::middleware('ability:ferias.view')->group(function () {
        Route::get('/ferias', [FeriaController::class, 'index']);
        Route::get('/ferias/{id}', [FeriaController::class, 'show'])->whereNumber('id');
        Route::get('/modelos-contrato', [FeriaController::class, 'modelosContrato']);
    });
    Route::middleware('ability:ferias.create')->group(function () {
        Route::get('/ferias/modelo-contrato-activo', [FeriaController::class, 'modeloContratoActivo']);
        Route::post('/ferias', [FeriaController::class, 'store']);
        Route::post('/ferias/{feriaId}/reglamentos', [ReglamentoFeriaController::class, 'store'])->whereNumber('feriaId');
    });
    Route::middleware('ability:ferias.update')->group(function () {
        Route::patch('/ferias/{id}', [FeriaController::class, 'update'])->whereNumber('id');
        Route::patch('/ferias/{id}/activate', [FeriaController::class, 'activate'])->whereNumber('id');
        Route::patch('/ferias/{id}/deactivate', [FeriaController::class, 'deactivate'])->whereNumber('id');
    });

    Route::middleware('ability:ferias.reglamentos.view')->group(function () {
        Route::get('/reglamentos-feria/eventos', [ReglamentoFeriaController::class, 'ferias']);
        Route::get('/ferias/{feriaId}/reglamentos', [ReglamentoFeriaController::class, 'index'])->whereNumber('feriaId');
        Route::get('/reglamentos-feria/{id}/download', [ReglamentoFeriaController::class, 'download'])->whereNumber('id');
    });
    Route::middleware('ability:ferias.reglamentos.create')->post('/ferias/{feriaId}/reglamentos', [ReglamentoFeriaController::class, 'store'])->whereNumber('feriaId');
    Route::middleware('ability:ferias.reglamentos.delete')->delete('/reglamentos-feria/{id}', [ReglamentoFeriaController::class, 'destroy'])->whereNumber('id');

    // Modelo Contrato API
    Route::middleware('ability:ferias.modelo_contrato.view')->group(function () {
        Route::get('/modelo-contrato', [ModeloContratoController::class, 'index']);
        Route::post('/modelo-contrato', [ModeloContratoController::class, 'store']);
        Route::get('/modelo-contrato/{id}/download', [ModeloContratoController::class, 'download'])->whereNumber('id');
        Route::patch('/modelo-contrato/{id}/estado', [ModeloContratoController::class, 'updateEstado'])->whereNumber('id');
        Route::delete('/modelo-contrato/{id}', [ModeloContratoController::class, 'destroy'])->whereNumber('id');
    });

    // Pabellon API
    Route::middleware('ability:ferias.view')->group(function () {
        Route::get('/ferias/{feriaId}/pabellones', [PabellonController::class, 'index'])->whereNumber('feriaId');
        Route::get('/ferias/{feriaId}/pabellones/{id}', [PabellonController::class, 'show'])->whereNumber(['feriaId', 'id']);
    });
    Route::middleware('ability:ferias.create')->post('/ferias/{feriaId}/pabellones', [PabellonController::class, 'store'])->whereNumber('feriaId');
    Route::middleware('ability:ferias.update')->group(function () {
        Route::post('/ferias/{feriaId}/pabellones/{id}', [PabellonController::class, 'update'])->whereNumber(['feriaId', 'id']);
        Route::delete('/ferias/{feriaId}/pabellones/{id}', [PabellonController::class, 'destroy'])->whereNumber(['feriaId', 'id']);
    });

    // Stand API
    Route::middleware('ability:ferias.view')->group(function () {
        Route::get('/pabellones/{pabellonId}/stands', [StandController::class, 'index'])->whereNumber('pabellonId');
    });
    Route::middleware('ability:ferias.create')->post('/pabellones/{pabellonId}/stands', [StandController::class, 'store'])->whereNumber('pabellonId');
    Route::middleware('ability:ferias.update')->patch('/pabellones/{pabellonId}/stands/{id}', [StandController::class, 'update'])->whereNumber(['pabellonId', 'id']);
    Route::middleware('ability:ferias.update')->delete('/pabellones/{pabellonId}/stands/{id}', [StandController::class, 'destroy'])->whereNumber(['pabellonId', 'id']);

    // Límites de Credenciales API
    Route::middleware('ability:ferias.view')->group(function () {
        Route::get('/pabellones/{pabellonId}/limites-credenciales', [StandController::class, 'obtenerLimitesCredenciales'])->whereNumber('pabellonId');
    });
    Route::middleware('ability:ferias.create')->post('/pabellones/{pabellonId}/limites-credenciales', [StandController::class, 'guardarLimitesCredencialesMultiples'])->whereNumber('pabellonId');
    Route::middleware('ability:ferias.update')->group(function () {
        Route::delete('/pabellones/{pabellonId}/limites-credenciales/{id}', [StandController::class, 'eliminarLimiteCredencial'])->whereNumber(['pabellonId', 'id']);
        Route::post('/pabellones/{pabellonId}/limites-credenciales/eliminar', [StandController::class, 'eliminarLimiteCredencialPorCampos'])->whereNumber('pabellonId');
    });

    // Home/Dashboard API - Protegidas con autenticación
    Route::middleware('auth')->group(function () {
        Route::get('/dashboard/resumen-ferias', [HomeController::class, 'resumenTodasFerias'])->middleware('ability:home.ferias.detalles');
        Route::get('/dashboard/resumen/{idFeria}', [HomeController::class, 'resumenPorFeria'])->whereNumber('idFeria')->middleware('ability:home.ferias.detalles');
    });

    // Reportes de Feria - Solo roles autorizados (sistemas, administración, auditoría, gerencia, comercial)
    Route::middleware('ability:home.ferias.detalles')->group(function () {
        Route::get('/api/reporte-feria/{id}', [ReporteFeriaController::class, 'show'])->whereNumber('id');
    });
        Route::middleware('ability:home.ferias.detalles')->group(function () {
        Route::get('/api/reporte-feria/{id}/pdf', [ReporteFeriaController::class, 'downloadPDF'])->whereNumber('id');
    });

    // Contratos API - Protegidas con autenticación y roles (admin, comercial, full)
    Route::middleware('ability:contratos.view')->group(function () {
        Route::get('/contratos/ferias', [ContratoController::class, 'getFerias']);
        Route::get('/contratos/ferias/{idFeria}', [ContratoController::class, 'getContratosByFeria'])->whereNumber('idFeria');
        Route::get('/contratos/reporte/estado-ventas/{idFeria}', [ContratoController::class, 'estadoVentas'])->whereNumber('idFeria');
        Route::get('/contratos/empresas', [ContratoController::class, 'getEmpresas']);
        Route::get('/contratos/pabellones/{idFeria}', [ContratoController::class, 'getPabellonesByFeria'])->whereNumber('idFeria');
        Route::get('/contratos/stands/{idFeria}/{idPabellon}', [ContratoController::class, 'getStandsByPabellon'])->whereNumber(['idFeria', 'idPabellon']);
        Route::get('/contratos/{id}', [ContratoController::class, 'show'])->whereNumber('id');
    });

    Route::middleware('ability:contratos.export')->group(function () {
        Route::get('/contratos/exportar/{idFeria}', [ContratoController::class, 'exportarContratos'])->whereNumber('idFeria');
    });

    Route::middleware('ability:contratos.create')->group(function () {
        Route::post('/contratos/empresas', [ContratoController::class, 'storeEmpresa']);
        Route::post('/contratos/reserva', [ContratoController::class, 'guardarReserva']);
    });

    Route::middleware('ability:contratos.edit')->group(function () {
        Route::get('/contratos/{id}/cambiar-datos', [ContratoController::class, 'getDatosCambio'])->whereNumber('id');
        Route::put('/contratos/{id}/llenar', [ContratoController::class, 'guardarLlenado'])->whereNumber('id');
        Route::patch('/contratos/{id}/empresa', [ContratoController::class, 'actualizarEmpresa'])->whereNumber('id');
        Route::patch('/contratos/{id}/stands', [ContratoController::class, 'actualizarStands'])->whereNumber('id');
    });

    Route::middleware('ability:contratos.generate')->group(function () {
        Route::post('/contratos/{id}/generar', [ContratoController::class, 'generarContrato'])->whereNumber('id');
    });

    //PENDIENTE: Rutas de impresión - requiere ImprimirController
    Route::middleware('ability:contratos.print')->group(function () {
        Route::get('/contratos/{id}/imprimir', [ImprimirController::class, 'imprimirContrato'])->whereNumber('id');
        Route::get('/imprimir/adendum/{id}', [ImprimirController::class, 'adendum'])->name('imprimir.adendum')->whereNumber('id');
    });

    Route::middleware('ability:contratos.delete')->group(function () {
        Route::delete('/reservas/{id}', [ContratoController::class, 'eliminarReserva'])->whereNumber('id');
    });

    // Pagos API: cada operación se autoriza con su permiso granular.
    Route::middleware('ability:pagos.view')->group(function () {
        Route::get('/pagos/ferias', [PagosController::class, 'getFerias']);
        Route::get('/pagos/ferias/{idFeria}', [PagosController::class, 'getPagosbyFeria'])->whereNumber('idFeria');
    });

    Route::middleware('ability:pagos.attachments.view')
        ->get('/pagos/{id}/archivo', [PagosController::class, 'archivo'])
        ->whereNumber('id');
    Route::middleware('ability:pagos.receipts.view')
        ->get('/pagos/{id}/recibo', [PagosController::class, 'recibo'])
        ->whereNumber('id');

    Route::middleware('ability:pagos.export')->group(function () {
        Route::get('/pagos/exportar/{idFeria}', [PagosController::class, 'exportar'])->whereNumber('idFeria');
    });

    // Creación: Solo Sistemas y Administración / Comercial
    Route::middleware('ability:pagos.create')->post('/pagos', [PagosController::class, 'store']);

    // Aprobación: Solo Sistemas y Administración
    Route::middleware('ability:pagos.approve')->group(function () {
        Route::post('/pagos/{id}/aprobar', [PagosController::class, 'aprobar'])->whereNumber('id');
    });

    // Rechazo: Solo Sistemas y Administración
    Route::middleware('ability:pagos.reject')->post('/pagos/{id}/rechazar', [PagosController::class, 'rechazar'])->whereNumber('id');

    // Recálculo: Solo Sistemas y Administración
    Route::middleware('ability:pagos.recalculate')->post('/pagos/recalcular/{idFeria}', [PagosController::class, 'recalcularEstadosPago'])->whereNumber('idFeria');

    // Notificaciones de administración: disponibles para cualquier usuario autenticado
    Route::middleware('auth')->group(function () {
        Route::get('/notificaciones/admin', [NotificacionAdminController::class, 'index']);
        Route::patch('/notificaciones/admin/{id}/leer', [NotificacionAdminController::class, 'marcarLeida'])->whereNumber('id');
        Route::patch('/notificaciones/admin/{id}/no-leer', [NotificacionAdminController::class, 'marcarNoLeida'])->whereNumber('id');
        Route::patch('/notificaciones/admin/leer-todas', [NotificacionAdminController::class, 'marcarTodasLeidas']);
        Route::delete('/notificaciones/admin/{id}', [NotificacionAdminController::class, 'destroy'])->whereNumber('id');
    });



    // Reporte Biométrico - Importar
    Route::post('/api/reporte-biometrico/importar', [\App\Http\Controllers\ReporteBiometricoController::class, 'importar'])->middleware('ability:biometrico.import');

    // Horarios Biométricos - API
    Route::middleware(['auth', 'ability:biometrico.import'])->group(function () {
        Route::get('/api/horarios-biometrico', [HorarioBiometricoController::class, 'index']);
        Route::post('/api/horarios-biometrico', [HorarioBiometricoController::class, 'store']);
        Route::get('/api/horarios-biometrico/{id}', [HorarioBiometricoController::class, 'show']);
        Route::put('/api/horarios-biometrico/{id}', [HorarioBiometricoController::class, 'update']);
        Route::patch('/api/horarios-biometrico/{id}', [HorarioBiometricoController::class, 'update']);
        Route::delete('/api/horarios-biometrico/{id}', [HorarioBiometricoController::class, 'destroy']);
    });

        // Noticias - API protegidas con autenticación básica
        Route::get('/noticias/data', [NoticiaController::class, 'data'])->middleware('ability:noticias.view');
        // Control Ventas API
        Route::middleware('ability:controlventas.view')->group(function () {
            Route::get('/control-ventas/ferias', [ControlVentasController::class, 'getFerias']);
            Route::get('/control-ventas/list', [ControlVentasController::class, 'index']);
            Route::get('/control-ventas/entrada/{id}', [ControlVentasController::class, 'detail'])->whereNumber('id');
            Route::get('/control-ventas/{id}/rangos-disponibles', [ControlVentasController::class, 'getRangosDisponibles'])->whereNumber('id');
            Route::get('/control-ventas/detalle/{id}/constancia', [ControlVentasController::class, 'descargarConstancia'])->whereNumber('id');
            Route::get('/control-ventas/{id}', [ControlVentasController::class, 'show'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.export')->group(function () {
            Route::get('/control-ventas/entrada/{id}/exportar-excel', [ControlVentasController::class, 'exportarExcel'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.sell')->group(function () {
            Route::post('/control-ventas/{id}/detalle', [ControlVentasController::class, 'storeDetalle'])->whereNumber('id');
            Route::post('/control-ventas/{id}/reservar-rango', [ControlVentasController::class, 'reservarRango'])->whereNumber('id');
            Route::delete('/control-ventas/{id}/reservar-rango', [ControlVentasController::class, 'liberarReservaRango'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.edit_last')->group(function () {
            Route::put('/control-ventas/detalle/{id}/ultimo', [ControlVentasController::class, 'updateUltimoDetalle'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.delete_last')->group(function () {
            Route::delete('/control-ventas/detalle/{id}/ultimo', [ControlVentasController::class, 'deleteUltimoDetalle'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.create')->group(function () {
            Route::post('/control-ventas/store', [ControlVentasController::class, 'store']);
        });

        Route::middleware('ability:controlventas.update')->group(function () {
            Route::put('/control-ventas/{id}', [ControlVentasController::class, 'update'])->whereNumber('id');
        });

        Route::middleware('ability:controlventas.delete')->group(function () {
            Route::delete('/control-ventas/{id}', [ControlVentasController::class, 'destroy'])->whereNumber('id');
        });

            Route::post('/noticias', [NoticiaController::class, 'store'])->middleware('ability:noticias.create');
        Route::get('/noticias/{id}', [NoticiaController::class, 'show'])->whereNumber('id')->middleware('ability:noticias.view');
        Route::patch('/noticias/{id}', [NoticiaController::class, 'update'])->whereNumber('id')->middleware('ability:noticias.update');
        Route::delete('/noticias/{id}', [NoticiaController::class, 'destroy'])->whereNumber('id')->middleware('ability:noticias.delete');

        // Empresas API
        Route::middleware('ability:empresas.ver')->group(function () {
            Route::get('/api/empresas', [\App\Http\Controllers\EmpresaController::class, 'index']);
        });
        Route::middleware('ability:empresas.detalle')->group(function () {
            Route::get('/api/empresas/{id}', [\App\Http\Controllers\EmpresaController::class, 'show'])->whereNumber('id');
        });
        Route::middleware('ability:empresas.ferias')->group(function () {
            Route::get('/api/empresas/{id}/ferias', [\App\Http\Controllers\EmpresaController::class, 'ferias'])->whereNumber('id');
        });
        Route::middleware('ability:empresas.historial')->group(function () {
            Route::get('/api/empresas/{id}/historial', [\App\Http\Controllers\EmpresaController::class, 'historial'])->whereNumber('id');
        });

        // Presupuesto API
        Route::middleware('ability:presupuesto.ver')->group(function () {
            Route::get('/api/presupuesto', [PresupuestoController::class, 'index']);
            Route::get('/api/presupuesto/{id}', [PresupuestoController::class, 'show'])->whereNumber('id');
            Route::get('/api/presupuesto/{presupuestoId}/exportar-excel', [PresupuestoController::class, 'exportarExcel'])->whereNumber('presupuestoId');
            // Partidas
            Route::get('/api/presupuesto/{presupuestoId}/partidas', [PresupuestoController::class, 'partidasIndex'])->whereNumber('presupuestoId');
            // Cuentas
            Route::get('/api/presupuesto/{presupuestoId}/partidas/{partidaId}/cuentas', [PresupuestoController::class, 'cuentasIndex'])->whereNumber(['presupuestoId', 'partidaId']);
            Route::get('/api/presupuesto/{presupuestoId}/partidas/{partidaId}/cuentas/{cuentaId}/certificaciones', [PresupuestoController::class, 'cuentaCertificaciones'])->whereNumber(['presupuestoId', 'partidaId', 'cuentaId']);
        });
        Route::middleware('ability:presupuesto.crear')->post('/api/presupuesto', [PresupuestoController::class, 'store']);
        Route::middleware('ability:presupuesto.editar')->patch('/api/presupuesto/{id}', [PresupuestoController::class, 'update'])->whereNumber('id');
        Route::middleware('ability:presupuesto.eliminar')->delete('/api/presupuesto/{id}', [PresupuestoController::class, 'destroy'])->whereNumber('id');
        // Partidas CRUD
            Route::middleware('ability:partida.crear')->post('/api/presupuesto/{presupuestoId}/partidas', [PresupuestoController::class, 'partidaStore'])->whereNumber('presupuestoId');

            // Solicitudes API
            Route::get('/api/solicitudes/tipos-proceso', [SolicitudController::class, 'tiposProceso'])->middleware('ability:solicitudes.ver');
            Route::get('/api/solicitudes/escalas', [SolicitudController::class, 'escalas'])->middleware('ability:solicitudes.ver');
            Route::middleware('ability:solicitudes.ver')->group(function () {
                Route::get('/api/solicitudes', [SolicitudController::class, 'index']);
                Route::get('/api/solicitudes/{id}', [SolicitudController::class, 'show'])->whereNumber('id');
            });
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/detalle', [SolicitudController::class, 'detalle'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/descargar-solicitud', [SolicitudController::class, 'descargarSolicitudAdquisicion'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/solicitudes/{id}/imprimir', [SolicitudController::class, 'descargarSolicitudAdquisicion'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/solicitud-pago', [SolicitudController::class, 'descargarSolicitudPago'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/tabla-comparativa-cotizaciones', [SolicitudController::class, 'getTablaComparativaCotizaciones'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/tabla-comparativa-cotizaciones/descargar', [SolicitudController::class, 'descargarTablaComparativaCotizaciones'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/pago-proceso', [SolicitudController::class, 'getPagoProceso'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/rendicion-fondos', [SolicitudController::class, 'getRendicionFondos'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/rendicion-fondos/descargar', [SolicitudController::class, 'descargarRendicionFondosPdf'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/solicitud-pago/descargar', [SolicitudController::class, 'descargarSolicitudPago'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/proveedores-cotizacion', [SolicitudController::class, 'getProveedoresCotizacion']);
            Route::middleware('ability:solicitudes.recibidas')->group(function () {
                Route::get('/api/solicitudes/recibidas', [SolicitudController::class, 'recibidas']);
                Route::get('/api/solicitudes/remitidas', [SolicitudController::class, 'remitidas']);
            });
            Route::middleware('ability:solicitudes.crear')->post('/api/solicitudes', [SolicitudController::class, 'store']);
            Route::middleware('ability:solicitudes.editar')->patch('/api/solicitudes/{id}', [SolicitudController::class, 'update'])->whereNumber('id');
            Route::middleware('ability:solicitudes.editar')->post('/api/solicitudes/{id}/tabla-comparativa-cotizaciones', [SolicitudController::class, 'guardarTablaComparativaCotizaciones'])->whereNumber('id');
            Route::middleware('ability:solicitudes.editar')->post('/api/solicitudes/{id}/pago-proceso', [SolicitudController::class, 'guardarPagoProceso'])->whereNumber('id');
            Route::middleware('ability:solicitudes.editar')->post('/api/solicitudes/{id}/rendicion-fondos', [SolicitudController::class, 'guardarRendicionFondos'])->whereNumber('id');
            Route::middleware('ability:solicitudes.derivar')->post('/api/solicitudes/{id}/derivar-administracion', [SolicitudController::class, 'derivarAdministracion'])->whereNumber('id');
            Route::middleware('ability:solicitudes.derivar')->post('/api/solicitudes/{id}/derivaciones', [SolicitudController::class, 'derivarAMultiplesAreas'])->whereNumber('id');
            Route::middleware('ability:solicitudes.aprobar')->post('/api/solicitudes/{id}/aprobar', [SolicitudController::class, 'aprobar'])->whereNumber('id');
            Route::middleware('ability:solicitudes.aprobar')->post('/api/solicitudes/{id}/rechazar', [SolicitudController::class, 'rechazar'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->post('/api/solicitudes/{id}/estado-contrato', [SolicitudController::class, 'actualizarEstadoContrato'])->whereNumber('id');
            Route::middleware('ability:solicitudes.aprobar')->post('/api/solicitudes/{id}/estado-cheque', [SolicitudController::class, 'actualizarEstadoCheque'])->whereNumber('id');
            Route::middleware('ability:solicitudes.devolver')->post('/api/solicitudes/{id}/devolver', [SolicitudController::class, 'devolver'])->whereNumber('id');
            Route::middleware('ability:solicitudes.aprobar')->post('/api/solicitudes/{id}/avanzar-flujo', [SolicitudController::class, 'avanzarFlujo'])->whereNumber('id');
            Route::middleware('ability:solicitudes.aprobar')->post('/api/solicitudes/{id}/finalizar', [SolicitudController::class, 'finalizar'])->whereNumber('id');
            Route::middleware('ability:solicitudes.editar')->post('/api/solicitudes/{id}/archivos', [SolicitudController::class, 'cargarArchivos'])->whereNumber('id');
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/archivos/{archivoId}/descargar', [SolicitudController::class, 'descargarArchivo'])->whereNumber(['id', 'archivoId']);
            Route::middleware('ability:solicitudes.editar')->delete('/api/solicitudes/{id}/archivos/{archivoId}', [SolicitudController::class, 'eliminarArchivo'])->whereNumber(['id', 'archivoId']);
            Route::middleware('ability:solicitudes.detalle')->get('/api/solicitudes/{id}/timeline', [SolicitudController::class, 'timeline'])->whereNumber('id');
            Route::middleware('ability:solicitudes.certificacion')->post('/api/solicitudes/{id}/certificacion-inicial', [SolicitudController::class, 'certificacionInicial'])->whereNumber('id');
            
            // Certificación Presupuestaria API
            Route::middleware('ability:solicitudes.certificacion_presupuestaria')->group(function () {
                Route::get('/api/solicitudes/presupuesto/partidas', [SolicitudController::class, 'getPartidas']);
                Route::get('/api/solicitudes/presupuesto/partidas/{idPartida}/cuentas', [SolicitudController::class, 'getCuentasByPartida'])->whereNumber('idPartida');
                Route::post('/api/solicitudes/{id}/certificacion-presupuestaria', [SolicitudController::class, 'certificacionPresupuestaria'])->whereNumber('id');
                Route::put('/api/solicitudes/{id}/certificacion-presupuestaria', [SolicitudController::class, 'actualizarCertificacionPresupuestaria'])->whereNumber('id');
                Route::post('/api/solicitudes/{id}/aprobar-certificacion', [SolicitudController::class, 'aprobarCertificacion'])->whereNumber('id');
                Route::get('/api/solicitudes/{id}/certificaciones-presupuestarias', [SolicitudController::class, 'getCertificacionesPresupuestarias'])->whereNumber('id');
                // Descarga PDF de Certificación Presupuestaria
                Route::middleware('ability:solicitudes.certificacion_presupuestaria')->get('/api/solicitudes/{id}/certificacion-presupuestaria/descargar', [SolicitudController::class, 'descargarCertificacionPresupuestaria'])->whereNumber('id');
            });
            
            Route::middleware('ability:solicitudes.eliminar')->delete('/api/solicitudes/{id}', [SolicitudController::class, 'destroy'])->whereNumber('id');

            // Correspondencia API
            Route::middleware('permission:correspondencia.ver')->group(function () {
                Route::get('/api/correspondencias/areas', [CorrespondenciaController::class, 'areas']);
                Route::get('/api/correspondencias/instrucciones', [CorrespondenciaController::class, 'instrucciones']);
                Route::get('/api/correspondencias/proximo-guia', [CorrespondenciaController::class, 'proximoGuia']);
                Route::get('/api/correspondencias/remitidas', [CorrespondenciaController::class, 'remitidas']);
            });
            Route::get('/api/correspondencias/years', [CorrespondenciaController::class, 'years'])
                ->middleware('permission:correspondencia.ver,correspondencia.recibidas');
            Route::middleware('permission:correspondencia.ver')->group(function () {
                Route::get('/api/correspondencias', [CorrespondenciaController::class, 'index']);
            });
            Route::get('/api/correspondencias/{id}', [CorrespondenciaController::class, 'show'])
                ->whereNumber('id')
                ->middleware('permission:correspondencia.ver,correspondencia.recibidas');
            Route::middleware('permission:correspondencia.recibidas')->group(function () {
                Route::get('/api/correspondencias/recibidas', [CorrespondenciaController::class, 'recibidas']);
                Route::patch('/api/correspondencias/destinos/{id}/recibir', [CorrespondenciaController::class, 'marcarRecibida'])->whereNumber('id');
            });
            Route::middleware('permission:correspondencia.crear')->post('/api/correspondencias', [CorrespondenciaController::class, 'store']);
            Route::middleware('permission:correspondencia.editar')->patch('/api/correspondencias/{id}', [CorrespondenciaController::class, 'update'])->whereNumber('id');
            Route::middleware('permission:correspondencia.eliminar')->delete('/api/correspondencias/{id}', [CorrespondenciaController::class, 'destroy'])->whereNumber('id');
            Route::middleware('permission:correspondencia.responder')->post('/api/correspondencias/destinos/{id}/respuestas', [CorrespondenciaController::class, 'responder'])->whereNumber('id');
            Route::middleware('permission:correspondencia.archivos')->group(function () {
                Route::delete('/api/correspondencias/{id}/archivos/{archivoId}', [CorrespondenciaController::class, 'eliminarArchivo'])->whereNumber(['id', 'archivoId']);
                Route::get('/api/correspondencias/{id}/archivos/{archivoId}/descargar', [CorrespondenciaController::class, 'descargarArchivo'])->whereNumber(['id', 'archivoId']);
            });
            Route::middleware('permission:correspondencia.historial')->get('/api/correspondencias/{id}/timeline', [CorrespondenciaController::class, 'timeline'])->whereNumber('id');
        Route::middleware('ability:partida.editar')->patch('/api/presupuesto/{presupuestoId}/partidas/{partidaId}', [PresupuestoController::class, 'partidaUpdate'])->whereNumber(['presupuestoId', 'partidaId']);
        Route::middleware('ability:partida.eliminar')->delete('/api/presupuesto/{presupuestoId}/partidas/{partidaId}', [PresupuestoController::class, 'partidaDestroy'])->whereNumber(['presupuestoId', 'partidaId']);
        // Cuentas CRUD
        Route::middleware('ability:cuenta.crear')->post('/api/presupuesto/{presupuestoId}/partidas/{partidaId}/cuentas', [PresupuestoController::class, 'cuentaStore'])->whereNumber(['presupuestoId', 'partidaId']);
        Route::middleware('ability:cuenta.editar')->patch('/api/presupuesto/{presupuestoId}/partidas/{partidaId}/cuentas/{cuentaId}', [PresupuestoController::class, 'cuentaUpdate'])->whereNumber(['presupuestoId', 'partidaId', 'cuentaId']);
        Route::middleware('ability:cuenta.eliminar')->delete('/api/presupuesto/{presupuestoId}/partidas/{partidaId}/cuentas/{cuentaId}', [PresupuestoController::class, 'cuentaDestroy'])->whereNumber(['presupuestoId', 'partidaId', 'cuentaId']);
        // Importar Excel
        Route::middleware('ability:presupuesto.importar_excel')->post('/api/presupuesto/{presupuestoId}/importar-excel', [PresupuestoController::class, 'importarExcel'])->whereNumber('presupuestoId');
    });

// Rutas de vistas SPA - Ruta raíz de noticias que carga la SPA
Route::middleware(['auth', 'ability:noticias.view'])->get('/noticia', [NoticiaController::class, 'index']);
Route::middleware(['auth', 'ability:noticias.view'])->get('/noticia/{any}', [NoticiaController::class, 'index'])->where('any', '.*');

Route::get('{any?}', function () {
    return view('application');
})->where('any', '.*');
