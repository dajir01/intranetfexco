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
  {
    title: 'Control de Accesos',
    icon: { icon: 'tabler-door-enter' },
    to: { name: 'controlaccesos-list' },
    meta: {
      ability: 'controlaccesos.ingresos.ver',
    },
  },
  {
    title: 'Operadores de Acceso',
    icon: { icon: 'tabler-user-shield' },
    to: { name: 'controlaccesos-operadores' },
    meta: {
      ability: 'controlaccesos.operadores.ver',
    },
  },
]
