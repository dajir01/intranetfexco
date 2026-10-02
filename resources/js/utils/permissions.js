const LEGACY_TO_GRANULAR = {
  'products.view': 'productos.ver',
  'products.create': 'productos.crear',
  'products.update': 'productos.editar',
  'products.baja': 'productos.eliminar',
  'assignments.upsert': 'asignaciones.editar',
  'assignments.delete': 'asignaciones.eliminar',
  'areas.view': 'areas.ver',
  'providers.view': 'proveedores.ver',
  'providers.create': 'proveedores.crear',
  'ingresos.view': 'ingresos.ver',
  'ingresos.create': 'ingresos.crear',
  'ingresos.update': 'ingresos.editar',
  'ingresos.cancel': 'ingresos.eliminar',
  'movimientos.view': 'movimientos.ver',
  'movimientos.create': 'movimientos.crear',
  'reports.view': 'reportes.ver',
  'reports.download': 'reportes.descargar',
  'users.view': 'usuarios.ver',
  'users.create': 'usuarios.crear',
  'users.update': 'usuarios.editar',
  'ferias.view': 'ferias.ver',
  'ferias.create': 'ferias.crear',
  'ferias.update': 'ferias.editar',
  'ferias.activate': 'ferias.activar',
  'ferias.reglamentos.view': 'ferias_reglamentos.ver',
  'ferias.reglamentos.create': 'ferias_reglamentos.crear',
  'ferias.reglamentos.delete': 'ferias_reglamentos.eliminar',
  'contratos.view': 'contratos.ver',
  'contratos.create': 'contratos.crear',
  'contratos.edit': 'contratos.editar',
  'contratos.update': 'contratos.editar',
  'contratos.delete': 'contratos.eliminar',
  'contratos.generate': 'contratos.generar',
  'contratos.print': 'contratos.imprimir',
  'contratos.export': 'contratos.exportar',
  'contratos.update-empresa': 'contratos.editar',
  'contratos.update-stands': 'contratos.editar',
  'pagos.view': 'pagos.ver',
  'pagos.export': 'pagos.exportar',
  'pagos.create': 'pagos.crear',
  'pagos.approve': 'pagos.aprobar',
  'pagos.reject': 'pagos.rechazar',
  'pagos.recalculate': 'pagos.recalcular',
  'reportes.ferias.view': 'reportes.ver',
  'reportes.ferias.download': 'reportes.descargar',
  'biometrico.view': 'biometrico.ver',
  'biometrico.import': 'biometrico.importar',
  'home.ferias.detalles': 'home.ferias.detalles',
  'noticias.view': 'noticias.ver',
  'noticias.create': 'noticias.crear',
  'noticias.update': 'noticias.editar',
  'noticias.delete': 'noticias.eliminar',
  'notificaciones.view': 'notificaciones.ver',
  'notificaciones.create': 'notificaciones.crear',
  'notificaciones.update': 'notificaciones.editar',
  'notificaciones.delete': 'notificaciones.eliminar',
  'controlventas.view': 'controlventas.ver',
  'controlventas.create': 'controlventas.crear',
  'controlventas.update': 'controlventas.editar',
  'controlventas.delete': 'controlventas.eliminar',
  'controlventas.sell': 'controlventas.registrar',
  'controlventas.edit_last': 'controlventas.editar_ultimo',
  'controlventas.delete_last': 'controlventas.eliminar_ultimo',
  'controlventas.export': 'controlventas.exportar',
}

const GRANULAR_TO_LEGACY = Object.fromEntries(
  Object.entries(LEGACY_TO_GRANULAR).map(([legacy, granular]) => [granular, legacy]),
)

export const canUser = (user, ability) => {
  const aliases = [ability, LEGACY_TO_GRANULAR[ability], GRANULAR_TO_LEGACY[ability]]
    .filter(Boolean)

  const directPermissions = user?.permissions

  if (directPermissions && typeof directPermissions === 'object' && !Array.isArray(directPermissions)) {
    for (const alias of aliases) {
      if (Object.prototype.hasOwnProperty.call(directPermissions, alias))
        return !!directPermissions[alias]
    }
  }

  const allowedList = user?.permissions_allowed
  if (Array.isArray(allowedList)) {
    if (aliases.some(alias => allowedList.includes(alias)))
      return true
  }

  const granularPermissions = user?.granular_permissions
  if (Array.isArray(granularPermissions)) {
    if (aliases.some(alias => granularPermissions.includes(alias)))
      return true
  }

  return false
}

export const roleLabel = role => {
  switch (role) {
  case 'full':
    return 'Acceso total'
  case 'almacen':
    return 'Almacén'
  case 'auditoria':
    return 'Auditoría (solo lectura)'
  default:
    return 'Sin permisos'
  }
}

export const roleFromUser = user => user?.role || null
