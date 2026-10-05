<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Catálogo de permisos granulares
    |--------------------------------------------------------------------------
    |
    | Formato recomendado: modulo.accion
    | Ejemplo: productos.ver, productos.crear, productos.editar, productos.eliminar
    |
    */
    'permissions' => [
        // Usuarios
        ['module' => 'usuarios', 'action' => 'ver'],
        ['module' => 'usuarios', 'action' => 'crear'],
        ['module' => 'usuarios', 'action' => 'editar'],
        ['module' => 'usuarios', 'action' => 'gestionar_permisos'],

        // Productos e inventario
        ['module' => 'productos', 'action' => 'ver'],
        ['module' => 'productos', 'action' => 'crear'],
        ['module' => 'productos', 'action' => 'editar'],
        ['module' => 'productos', 'action' => 'eliminar'],

        ['module' => 'asignaciones', 'action' => 'ver'],
        ['module' => 'asignaciones', 'action' => 'crear'],
        ['module' => 'asignaciones', 'action' => 'editar'],
        ['module' => 'asignaciones', 'action' => 'eliminar'],

        ['module' => 'areas', 'action' => 'ver'],

        ['module' => 'proveedores', 'action' => 'ver'],
        ['module' => 'proveedores', 'action' => 'crear'],
        ['module' => 'proveedores', 'action' => 'editar'],
        ['module' => 'proveedores', 'action' => 'eliminar'],

        ['module' => 'ingresos', 'action' => 'ver'],
        ['module' => 'ingresos', 'action' => 'crear'],
        ['module' => 'ingresos', 'action' => 'editar'],
        ['module' => 'ingresos', 'action' => 'eliminar'],

        ['module' => 'movimientos', 'action' => 'ver'],
        ['module' => 'movimientos', 'action' => 'crear'],
        ['module' => 'movimientos', 'action' => 'editar'],
        ['module' => 'movimientos', 'action' => 'eliminar'],

        ['module' => 'reportes', 'action' => 'ver'],
        ['module' => 'reportes', 'action' => 'descargar'],

        // Ferias / contratos / pagos
        ['module' => 'ferias', 'action' => 'ver'],
        ['module' => 'ferias', 'action' => 'crear'],
        ['module' => 'ferias', 'action' => 'editar'],
        ['module' => 'ferias', 'action' => 'activar'],
        ['module' => 'ferias_modelo_contrato', 'action' => 'ver'],
        ['module' => 'ferias_reglamentos', 'action' => 'ver'],
        ['module' => 'ferias_reglamentos', 'action' => 'crear'],
        ['module' => 'ferias_reglamentos', 'action' => 'eliminar'],

        ['module' => 'contratos', 'action' => 'ver'],
        ['module' => 'contratos', 'action' => 'crear'],
        ['module' => 'contratos', 'action' => 'editar'],
        ['module' => 'contratos', 'action' => 'eliminar'],
        ['module' => 'contratos', 'action' => 'generar'],
        ['module' => 'contratos', 'action' => 'imprimir'],
        ['module' => 'contratos', 'action' => 'exportar'],

        ['module' => 'pagos', 'action' => 'ver'],
        ['module' => 'pagos', 'action' => 'ver_comprobantes'],
        ['module' => 'pagos', 'action' => 'ver_recibos'],
        ['module' => 'pagos', 'action' => 'crear'],
        ['module' => 'pagos', 'action' => 'aprobar'],
        ['module' => 'pagos', 'action' => 'rechazar'],
        ['module' => 'pagos', 'action' => 'recalcular'],
        ['module' => 'pagos', 'action' => 'exportar'],

        // Módulos específicos
        ['module' => 'noticias', 'action' => 'ver'],
        ['module' => 'noticias', 'action' => 'crear'],
        ['module' => 'noticias', 'action' => 'editar'],
        ['module' => 'noticias', 'action' => 'eliminar'],

        ['module' => 'notificaciones', 'action' => 'ver'],
        ['module' => 'notificaciones', 'action' => 'crear'],
        ['module' => 'notificaciones', 'action' => 'editar'],

        ['module' => 'biometrico', 'action' => 'ver'],
        ['module' => 'biometrico', 'action' => 'importar'],

        // Dashboard - Home
        ['module' => 'home.ferias', 'action' => 'detalles'],

        ['module' => 'controlventas', 'action' => 'ver'],
        ['module' => 'controlventas', 'action' => 'crear'],
        ['module' => 'controlventas', 'action' => 'editar'],
        ['module' => 'controlventas', 'action' => 'eliminar'],
        ['module' => 'controlventas', 'action' => 'registrar'],
        ['module' => 'controlventas', 'action' => 'editar_ultimo'],
        ['module' => 'controlventas', 'action' => 'eliminar_ultimo'],
        ['module' => 'controlventas', 'action' => 'exportar'],

        // Empresas
        ['module' => 'empresas', 'action' => 'ver'],
        ['module' => 'empresas', 'action' => 'detalle'],
        ['module' => 'empresas', 'action' => 'ferias'],
        ['module' => 'empresas', 'action' => 'historial'],

        // Correspondencia
        ['module' => 'correspondencia', 'action' => 'ver'],
        ['module' => 'correspondencia', 'action' => 'crear'],
        ['module' => 'correspondencia', 'action' => 'editar'],
        ['module' => 'correspondencia', 'action' => 'eliminar'],
        ['module' => 'correspondencia', 'action' => 'derivar'],
        ['module' => 'correspondencia', 'action' => 'recibidas'],
        ['module' => 'correspondencia', 'action' => 'responder'],
        ['module' => 'correspondencia', 'action' => 'respuestas'],
        ['module' => 'correspondencia', 'action' => 'archivos'],
        ['module' => 'correspondencia', 'action' => 'historial'],

        // Solicitudes / procesos administrativos
        ['module' => 'solicitudes', 'action' => 'ver'],
        ['module' => 'solicitudes', 'action' => 'crear'],
        ['module' => 'solicitudes', 'action' => 'editar'],
        ['module' => 'solicitudes', 'action' => 'eliminar'],
        ['module' => 'solicitudes', 'action' => 'detalle'],
        ['module' => 'solicitudes', 'action' => 'recibidas'],
        ['module' => 'solicitudes', 'action' => 'derivar'],
        ['module' => 'solicitudes', 'action' => 'aprobar'],
        ['module' => 'solicitudes', 'action' => 'devolver'],
        ['module' => 'solicitudes', 'action' => 'certificacion'],
        ['module' => 'solicitudes', 'action' => 'certificacion_presupuestaria'],

        // Presupuestos
        ['module' => 'presupuesto', 'action' => 'ver'],
        ['module' => 'presupuesto', 'action' => 'crear'],
        ['module' => 'presupuesto', 'action' => 'editar'],
        ['module' => 'presupuesto', 'action' => 'eliminar'],
        ['module' => 'presupuesto', 'action' => 'importar_excel'],

        ['module' => 'partida', 'action' => 'crear'],
        ['module' => 'partida', 'action' => 'editar'],
        ['module' => 'partida', 'action' => 'eliminar'],

        ['module' => 'cuenta', 'action' => 'crear'],
        ['module' => 'cuenta', 'action' => 'editar'],
        ['module' => 'cuenta', 'action' => 'eliminar'],

        // Credenciales
        ['module' => 'credenciales.empresas', 'action' => 'ver'],
        ['module' => 'credenciales.empresas', 'action' => 'crear'],
        ['module' => 'credenciales.empresas', 'action' => 'eliminar'],
        ['module' => 'credenciales.empresas', 'action' => 'exportar'],
        ['module' => 'credenciales.tipos', 'action' => 'editar'],
        ['module' => 'credenciales.pines', 'action' => 'ver'],
        ['module' => 'credenciales.pines', 'action' => 'generar'],
        ['module' => 'credenciales.pines', 'action' => 'exportar'],
        ['module' => 'credenciales.pines', 'action' => 'imprimir'],
        ['module' => 'credenciales.pines', 'action' => 'enviar'],
        ['module' => 'credenciales.acreditados', 'action' => 'ver'],
        ['module' => 'credenciales.acreditados', 'action' => 'crear'],
        ['module' => 'credenciales.acreditados', 'action' => 'editar'],
        ['module' => 'credenciales.acreditados', 'action' => 'exportar'],
        ['module' => 'credenciales.historial', 'action' => 'ver'],
        ['module' => 'credenciales.cupos', 'action' => 'adicionar'],
        ['module' => 'credenciales.cupos', 'action' => 'quitar'],
        ['module' => 'credenciales.cupos', 'action' => 'historial'],
        ['module' => 'credenciales.modalidad', 'action' => 'cambiar'],
        ['module' => 'credenciales.estado', 'action' => 'inhabilitar'],
        ['module' => 'credenciales.estado', 'action' => 'habilitar'],
        ['module' => 'credenciales.estado', 'action' => 'anular'],
        ['module' => 'credenciales.qr', 'action' => 'renovar'],
        ['module' => 'credenciales.registro', 'action' => 'eliminar'],
        ['module' => 'credenciales.salida_digital', 'action' => 'descargar'],
        ['module' => 'credenciales.salida_fisica', 'action' => 'imprimir'],
        ['module' => 'credenciales.salida_digital', 'action' => 'descargar_lote'],
        ['module' => 'credenciales.salida_fisica', 'action' => 'imprimir_lote'],
        ['module' => 'credenciales.seleccionados', 'action' => 'enviar'],
        ['module' => 'credenciales.seleccionados', 'action' => 'inhabilitar'],
        ['module' => 'credenciales.seleccionados', 'action' => 'habilitar'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compatibilidad con abilities legadas
    |--------------------------------------------------------------------------
    |
    | Este mapa permite coexistencia temporal:
    | - Rutas/controladores antiguos que siguen usando abilities legadas
    | - Nuevo sistema granular por usuario
    |
    */
    'legacy_to_granular' => [
        'products.view' => 'productos.ver',
        'products.create' => 'productos.crear',
        'products.update' => 'productos.editar',
        'products.baja' => 'productos.eliminar',

        'assignments.upsert' => 'asignaciones.editar',
        'assignments.delete' => 'asignaciones.eliminar',

        'areas.view' => 'areas.ver',

        'providers.view' => 'proveedores.ver',
        'providers.create' => 'proveedores.crear',

        'ingresos.view' => 'ingresos.ver',
        'ingresos.create' => 'ingresos.crear',
        'ingresos.update' => 'ingresos.editar',
        'ingresos.cancel' => 'ingresos.eliminar',

        'movimientos.view' => 'movimientos.ver',
        'movimientos.create' => 'movimientos.crear',

        'reports.view' => 'reportes.ver',
        'reports.download' => 'reportes.descargar',

        'users.view' => 'usuarios.ver',
        'users.create' => 'usuarios.crear',
        'users.update' => 'usuarios.editar',
        'users.manage-permissions' => 'usuarios.gestionar_permisos',

        'ferias.view' => 'ferias.ver',
        'ferias.create' => 'ferias.crear',
        'ferias.update' => 'ferias.editar',
        'ferias.activate' => 'ferias.activar',
        'ferias.modelo_contrato.view' => 'ferias_modelo_contrato.ver',
        'ferias.reglamentos.view' => 'ferias_reglamentos.ver',
        'ferias.reglamentos.create' => 'ferias_reglamentos.crear',
        'ferias.reglamentos.delete' => 'ferias_reglamentos.eliminar',

        'contratos.view' => 'contratos.ver',
        'contratos.create' => 'contratos.crear',
        'contratos.edit' => 'contratos.editar',
        'contratos.update' => 'contratos.editar',
        'contratos.delete' => 'contratos.eliminar',
        'contratos.generate' => 'contratos.generar',
        'contratos.print' => 'contratos.imprimir',
        'contratos.export' => 'contratos.exportar',
        'contratos.update-empresa' => 'contratos.editar',
        'contratos.update-stands' => 'contratos.editar',

        'pagos.view' => 'pagos.ver',
        'pagos.attachments.view' => 'pagos.ver_comprobantes',
        'pagos.receipts.view' => 'pagos.ver_recibos',
        'pagos.export' => 'pagos.exportar',
        'pagos.create' => 'pagos.crear',
        'pagos.approve' => 'pagos.aprobar',
        'pagos.reject' => 'pagos.rechazar',
        'pagos.recalculate' => 'pagos.recalcular',

        'reportes.ferias.view' => 'reportes.ver',
        'reportes.ferias.download' => 'reportes.descargar',

        'noticias.view' => 'noticias.ver',
        'noticias.create' => 'noticias.crear',
        'noticias.update' => 'noticias.editar',
        'noticias.delete' => 'noticias.eliminar',

        'notificaciones.view' => 'notificaciones.ver',
        'notificaciones.create' => 'notificaciones.crear',
        'notificaciones.update' => 'notificaciones.editar',
        'notificaciones.delete' => 'notificaciones.eliminar',

        'biometrico.view' => 'biometrico.ver',
        'biometrico.import' => 'biometrico.importar',

        'home.ferias.detalles' => 'home.ferias.detalles',

        'controlventas.view' => 'controlventas.ver',
        'controlventas.create' => 'controlventas.crear',
        'controlventas.update' => 'controlventas.editar',
        'controlventas.delete' => 'controlventas.eliminar',
        'controlventas.sell' => 'controlventas.registrar',
        'controlventas.edit_last' => 'controlventas.editar_ultimo',
        'controlventas.delete_last' => 'controlventas.eliminar_ultimo',
        'controlventas.export' => 'controlventas.exportar',

        'empresas.ver'       => 'empresas.ver',
        'empresas.detalle'   => 'empresas.detalle',
        'empresas.ferias'    => 'empresas.ferias',
        'empresas.historial' => 'empresas.historial',
    ],
];
