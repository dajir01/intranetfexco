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
        meta: { ability: 'movimientos.view' },
        children: [
          {
            title: 'Salida',
            icon: { icon: 'tabler-arrow-bar-to-left' },
            to: 'inventario-movimiento-salida-producto',
            meta: { ability: 'movimientos.create' },
          },
          {
            title: 'Ingreso',
            icon: { icon: 'tabler-arrow-bar-to-right' },
            to: 'inventario-movimiento-ingreso-producto',
            meta: { ability: 'movimientos.create' },
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
