export default [
  { heading: 'Configuración' },
  {
    title: 'Auditoría de registros',
    icon: { icon: 'tabler-file-alert' },
    to: 'configuracion-logs',
    meta: {
      ability: 'auditoria.logs.view',
    },
  },
  {
    title: 'Notificaciones por correo',
    icon: { icon: 'tabler-mail' },
    to: 'configuracion-notificaciones',
    meta: {
      ability: 'notificaciones.ver',
    },
  },
]
