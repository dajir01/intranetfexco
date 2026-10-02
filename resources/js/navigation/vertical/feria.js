export default [
  { heading: 'Gestión de Ferias' },
  {
    title: 'Ferias',
    icon: { icon: 'tabler-calendar-event' },
    to: 'feria-list',
    meta: {
      ability: 'ferias.view',
    },
  },
  {
    title: 'Modelo Contrato de Ferias',
    icon: { icon: 'tabler-calendar-event' },
    to: 'feria-modelo-contrato-list',
    meta: {
      ability: 'ferias.modelo_contrato.view',
    },
  },
  {
    title: 'Reglamentos de Ferias',
    icon: { icon: 'tabler-file-text' },
    to: 'feria-reglamentos-list',
    meta: {
      ability: 'ferias.reglamentos.view',
    },
  },
]
