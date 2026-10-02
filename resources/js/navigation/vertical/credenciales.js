export default [
  { heading: 'Credenciales' },
  {
    title: 'Empresas',
    icon: { icon: 'tabler-building' },
    to: 'credenciales-empresas-list',
    meta: {
      ability: 'credenciales.empresas.ver',
    },
  },
  {
    title: 'Acreditados',
    icon: { icon: 'tabler-users' },
    to: 'credenciales-acreditados-list',
    meta: {
      ability: 'credenciales.empresas.ver',
    },
  },
  {
    title: 'PINs de acreditación',
    icon: { icon: 'tabler-key' },
    to: 'credenciales-pines-list',
    meta: {
      ability: 'credenciales.pines.ver',
    },
  },
  {
    title: 'Tipos de Credenciales',
    icon: { icon: 'tabler-id-badge-2' },
    to: 'credenciales-tipos-list',
    meta: {
      ability: 'credenciales.empresas.ver',
    },
  },
]
