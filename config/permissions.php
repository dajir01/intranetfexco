<?php

return [
    'role_groups' => [
        'admin' => ['sistemas', 'administracion'],
        'full' => ['gerencia'],
        'almacen' => ['almacen'],
        'auditoria' => ['auditoria'],
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

        // Áreas y catálogos
        'areas.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'providers.view' => ['admin', 'full', 'almacen'],
        'providers.create' => ['admin', 'full', 'almacen'],

        // Ingresos
        'ingresos.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'ingresos.create' => ['admin', 'full', 'almacen'],
        'ingresos.update' => ['admin', 'full'],
        'ingresos.cancel' => ['admin', 'full'],

        // Movimientos (salidas/ingresos de almacén)
        'movimientos.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'movimientos.create' => ['admin', 'full', 'almacen'],

        // Reportes y PDFs
        'reports.view' => ['admin', 'full', 'almacen', 'auditoria'],
        'reports.download' => ['admin', 'full', 'almacen', 'auditoria'],

        // Usuarios (solo Sistemas y Administración)
        'users.view' => ['admin'],
        'users.create' => ['admin'],
        'users.update' => ['admin'],
    ],
];
