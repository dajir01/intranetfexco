<?php

return [
    'role_groups' => [
        'admin' => ['sistemas', 'administracion'],
        'full' => ['gerencia'],
        'almacen' => ['almacen'],
        'auditoria' => ['auditoria'],
        'comercial_access' => ['sistemas', 'administracion', 'comercial'],
        'comunicacion' => ['comunicacion'],
    ],

    // Mapa de habilidades -> grupos de rol permitidos. Los roles no listados no tienen acceso.
    'abilities' => [
        // Productos
        'products.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'products.create' => ['admin', 'full'],
        'products.update' => ['admin', 'full'],
        'products.baja' => ['admin', 'full'],

        // Asignaciones de producto
        'assignments.upsert' => ['admin', 'full', 'almacen'],
        'assignments.delete' => ['admin', 'full'],

        // Areas y catalogos
        'areas.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'providers.view' => ['admin', 'full', 'almacen'],
        'providers.create' => ['admin', 'full', 'almacen'],

        // Ingresos
        'ingresos.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'ingresos.create' => ['admin', 'full', 'almacen'],
        'ingresos.update' => ['admin', 'full'],
        'ingresos.cancel' => ['admin', 'full'],

        // Movimientos (salidas/ingresos de almacen)
        'movimientos.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'movimientos.create' => ['admin', 'full', 'almacen'],

        // Reportes y PDFs
        'reports.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'reports.download' => ['admin', 'full', 'almacen', 'auditoria'],

        // Usuarios (solo Sistemas y Administracion)
        'users.view' => ['admin'],
        'users.create' => ['admin'],
        'users.update' => ['admin'],

        // Reporte Biometrico
        'biometrico.import' => ['admin'],

        // Feria (solo Sistemas)
        'ferias.view' => ['admin'],
        'ferias.create' => ['admin'],
        'ferias.update' => ['admin'],
        'ferias.activate' => ['admin'],
        'ferias.modelo_contrato.view' => ['admin'],
        'ferias.reglamentos.view' => ['admin'],
        'ferias.reglamentos.create' => ['admin'],
        'ferias.reglamentos.delete' => ['admin'],

        // Contratos (Sistemas, Administracion y Comercial)
        'contratos.view' => ['admin', 'comercial_access'],
        'contratos.create' => ['admin', 'comercial_access'],
        'contratos.edit' => ['admin', 'comercial_access'],
        'contratos.update' => ['admin', 'comercial_access'],
        'contratos.delete' => ['admin', 'comercial_access'],
        'contratos.generate' => ['admin', 'comercial_access'],
        'contratos.print' => ['admin', 'comercial_access'],
        'contratos.export' => ['admin', 'comercial_access'],
        'contratos.update-empresa' => ['admin', 'comercial_access'],
        'contratos.update-stands' => ['admin', 'comercial_access'],

        // Credenciales
        'credenciales.empresas.ver' => ['admin', 'comercial_access'],
        'credenciales.empresas.crear' => ['admin', 'comercial_access'],
        'credenciales.empresas.eliminar' => ['admin', 'comercial_access'],
        'credenciales.empresas.exportar' => ['admin', 'comercial_access'],
        'credenciales.tipos.editar' => ['admin', 'comercial_access'],
        'credenciales.pines.ver' => ['admin', 'comercial_access'],
        'credenciales.pines.generar' => ['admin', 'comercial_access'],
        'credenciales.pines.exportar' => ['admin', 'comercial_access'],
        'credenciales.pines.imprimir' => ['admin', 'comercial_access'],
        'credenciales.pines.enviar' => ['admin', 'comercial_access'],
        'credenciales.acreditados.ver' => ['admin', 'comercial_access'],
        'credenciales.acreditados.crear' => ['admin', 'comercial_access'],
        'credenciales.acreditados.editar' => ['admin', 'comercial_access'],
        'credenciales.acreditados.exportar' => ['admin', 'comercial_access'],
        'credenciales.historial.ver' => ['admin', 'comercial_access'],
        'credenciales.cupos.adicionar' => ['admin', 'comercial_access'],
        'credenciales.cupos.quitar' => ['admin', 'comercial_access'],
        'credenciales.cupos.historial' => ['admin', 'comercial_access'],
        'credenciales.modalidad.cambiar' => ['admin', 'comercial_access'],
        'credenciales.estado.inhabilitar' => ['admin', 'comercial_access'],
        'credenciales.estado.habilitar' => ['admin', 'comercial_access'],
        'credenciales.estado.anular' => ['admin', 'comercial_access'],
        'credenciales.qr.renovar' => ['admin', 'comercial_access'],
        'credenciales.registro.eliminar' => ['admin', 'comercial_access'],
        'credenciales.salida_digital.descargar' => ['admin', 'comercial_access'],
        'credenciales.salida_fisica.imprimir' => ['admin', 'comercial_access'],
        'credenciales.salida_digital.descargar_lote' => ['admin', 'comercial_access'],
        'credenciales.salida_fisica.imprimir_lote' => ['admin', 'comercial_access'],
        'credenciales.seleccionados.enviar' => ['admin', 'comercial_access'],
        'credenciales.seleccionados.inhabilitar' => ['admin', 'comercial_access'],
        'credenciales.seleccionados.habilitar' => ['admin', 'comercial_access'],

        // Pagos (Auditoria, Sistemas, Administracion y Comercial)
        'pagos.view' => ['admin', 'comercial_access', 'auditoria'],
        'pagos.export' => ['admin', 'comercial_access', 'auditoria'],
        'pagos.create' => ['admin', 'comercial_access'],
        'pagos.approve' => ['admin'],
        'pagos.reject' => ['admin'],
        'pagos.recalculate' => ['admin'],

        // Dashboard - Home
        'home.ferias.detalles' => [
            'roles' => ['admin', 'full', 'auditoria', 'comercial_access'],
            'areas' => ['sistemas', 'administracion', 'gerencia', 'auditoria', 'comercial'],
        ],

        // Reportes de Feria (sistemas, administracion, auditoria, gerencia, comercial)
        'reportes.ferias.view' => [
            'roles' => ['admin', 'full', 'auditoria', 'comercial_access'],
            'areas' => ['sistemas', 'administracion', 'gerencia', 'auditoria', 'comercial'],
        ],
        'reportes.ferias.download' => [
            'roles' => ['admin', 'full', 'auditoria', 'comercial_access'],
            'areas' => ['sistemas', 'administracion', 'gerencia', 'auditoria', 'comercial'],
        ],

        // Noticias (Sistemas, Gerencia y Comunicacion)
        'noticias.view' => [
            'areas' => ['sistemas', 'gerencia', 'comunicacion'],
        ],
        'noticias.create' => [
            'areas' => ['sistemas', 'gerencia', 'comunicacion'],
        ],
        'noticias.update' => [
            'areas' => ['sistemas', 'gerencia', 'comunicacion'],
        ],
        'noticias.delete' => [
            'areas' => ['sistemas', 'gerencia', 'comunicacion'],
        ],

        // Empresas
        'empresas.ver'      => ['admin', 'full', 'auditoria', 'comercial_access'],
        'empresas.detalle'  => ['admin', 'full', 'auditoria', 'comercial_access'],
        'empresas.ferias'   => ['admin', 'full', 'auditoria', 'comercial_access'],
        'empresas.historial'=> ['admin', 'auditoria'],

        // Control Ventas (solo Sistemas y Administracion)
        'controlventas.view' => ['admin'],
        'controlventas.edit_last' => ['admin'],
        'controlventas.delete_last' => ['admin'],
    ],
];
