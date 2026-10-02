export default [
  { heading: 'Solicitudes' },
  {
    title: 'Solicitudes',
    icon: { icon: 'tabler-clipboard-list' },
    to: 'solicitudes-list',
    meta: {
      ability: 'solicitudes.ver',
    },
  },
  {
    title: 'Solicitudes Recibidas',
    icon: { icon: 'tabler-inbox' },
    to: 'solicitudes-recibidas',
    meta: {
      ability: 'solicitudes.recibidas',
    },
  },
  {
    title: 'Solicitudes Remitidas',
    icon: { icon: 'tabler-send' },
    to: 'solicitudes-remitidas',
    meta: {
      ability: 'solicitudes.recibidas',
    },
  },
]
