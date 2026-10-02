export default [
  { heading: 'Almacen' },
  {
    title: 'Inventario',
    icon: { icon: 'tabler-forklift' },
    meta: { ability: 'ingresos.view' },
    children: [
      {
        title: 'Nota de Ingreso',
        icon: { icon: 'tabler-address-book' },
        meta: { ability: 'ingresos.view' },
        children: [
          { title: 'Lista', to: 'inventario-ingreso', meta: { ability: 'ingresos.view' } },
          { title: 'Registrar Ingreso', to: 'inventario-ingreso-register', meta: { ability: 'ingresos.create' } },
          { title: 'Reporte', icon: { icon: 'tabler-clipboard-data' }, to: 'inventario-reporte', meta: { ability: 'reports.view' } },
        ],
      },
      {
        title: 'Movimientos',
        icon: { icon: 'tabler-refresh' },
        meta: { ability: 'movimientos.ver' },
        children: [
          {
            title: 'Consumibles',
            icon: { icon: 'tabler-package' },
            meta: { ability: 'movimientos.ver' },
            children: [
              {
                title: 'Ingreso de Almacén',
                icon: { icon: 'tabler-arrow-bar-to-right' },
                to: { name: 'inventario-movimiento-ingreso-producto', query: { modo: 'consumible_ingreso' } },
                meta: { ability: 'movimientos.ver' },
              },
              {
                title: 'Salida de Almacén',
                icon: { icon: 'tabler-arrow-bar-to-left' },
                to: { name: 'inventario-movimiento-salida-producto', query: { modo: 'consumible_salida' } },
                meta: { ability: 'movimientos.ver' },
              },
            ],
          },
          {
            title: 'Activos Fijos',
            icon: { icon: 'tabler-device-desktop' },
            meta: { ability: 'movimientos.ver' },
            children: [
              {
                title: 'Asignación de Activo',
                icon: { icon: 'tabler-arrow-bar-to-left' },
                to: { name: 'inventario-movimiento-salida-producto', query: { modo: 'activo_asignacion' } },
                meta: { ability: 'movimientos.ver' },
              },
              {
                title: 'Devolución de Activo',
                icon: { icon: 'tabler-arrow-bar-to-right' },
                to: { name: 'inventario-movimiento-ingreso-producto', query: { modo: 'activo_devolucion' } },
                meta: { ability: 'movimientos.ver' },
              },
            ],
          },
          {
            title: 'Reporte de Movimientos',
            icon: { icon: 'tabler-clipboard-data' },
            to: 'inventario-movimiento-reporte',
            meta: { ability: 'reports.view' },
          },
        ],
      },
    ],
  },
]
