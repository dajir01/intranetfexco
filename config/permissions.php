<?php

return [
    'role_groups' => [
        'full' => ['sistemas', 'administracion', 'gerencia'],
        'almacen' => ['almacen'],
        'auditoria' => ['auditoria'],
    ],

    // Mapa de habilidades -> grupos de rol permitidos. Los roles no listados no tienen acceso.
    'abilities' => [
        // Productos
        'products.view' => ['full', 'almacen', 'auditoria'],
        'products.create' => ['full'],
        'products.update' => ['full'],
        'products.baja' => ['full'],

        // Asignaciones de producto
        'assignments.upsert' => ['full', 'almacen'],
        'assignments.delete' => ['full'],

        // Áreas y catálogos
        'areas.view' => ['full', 'almacen', 'auditoria'],
        'providers.view' => ['full', 'almacen'],
        'providers.create' => ['full', 'almacen'],

        // Ingresos
        'ingresos.view' => ['full', 'almacen', 'auditoria'],
        'ingresos.create' => ['full', 'almacen'],
        'ingresos.update' => ['full'],
        'ingresos.cancel' => ['full'],

        // Movimientos (salidas/ingresos de almacén)
        'movimientos.view' => ['full', 'almacen', 'auditoria'],
        'movimientos.create' => ['full', 'almacen'],

        // Reportes y PDFs
        'reports.view' => ['full', 'almacen', 'auditoria'],
        'reports.download' => ['full', 'almacen', 'auditoria'],
    ],
];
