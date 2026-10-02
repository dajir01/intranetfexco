export default [
  { heading: 'Gestión de Contratos' },
  {
    title: 'Contratos',
    icon: { icon: 'tabler-file' },
    to: 'contrato-list',
    meta: {
      ability: 'contratos.view',
    },
  },
  {
    title: 'Pagos',
    icon: { icon: 'tabler-cash' },
    to: 'pagos-list',
    meta: {
      ability: 'pagos.view',
    },
  },
]
