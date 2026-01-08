export default [
  {
    title: 'Productos',
    icon: { icon: 'tabler-tags' },
    meta: { ability: 'products.view' },
    children: [
      {
        title: 'Lista de Productos',
        icon: { icon: 'tabler-package' },
        to: 'producto-lista',
        meta: { ability: 'products.view' },
      },
      {
        title: 'Reporte Producto',
        icon: { icon: 'tabler-clipboard-data' },
        to: 'producto-reporte',
        meta: { ability: 'reports.view' },
      },
    ],
  },
]
