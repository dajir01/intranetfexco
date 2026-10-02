export default [
  { heading: 'Correspondencia' },
  {
    title: 'Correspondencia',
    icon: { icon: 'tabler-mail' },
    to: 'correspondencia-list',
    meta: { ability: 'correspondencia.ver' },
  },
  {
    title: 'Recibidas',
    icon: { icon: 'tabler-inbox' },
    to: 'correspondencia-recibidas',
    meta: { ability: 'correspondencia.recibidas' },
  },
  {
    title: 'Remitidas',
    icon: { icon: 'tabler-send' },
    to: 'correspondencia-remitidas',
    meta: { ability: 'correspondencia.ver' },
  },
]
