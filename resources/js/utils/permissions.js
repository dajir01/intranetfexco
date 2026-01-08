const ROLE_GROUPS = {
  full: ['sistemas', 'administracion', 'gerencia'],
  almacen: ['almacen'],
  auditoria: ['auditoria'],
}

export const ABILITIES = {
  'products.view': ['full', 'almacen', 'auditoria'],
  'products.create': ['full'],
  'products.update': ['full'],
  'products.baja': ['full'],

  'assignments.upsert': ['full', 'almacen'],
  'assignments.delete': ['full'],

  'areas.view': ['full', 'almacen', 'auditoria'],
  'providers.view': ['full', 'almacen'],
  'providers.create': ['full', 'almacen'],

  'ingresos.view': ['full', 'almacen', 'auditoria'],
  'ingresos.create': ['full', 'almacen'],
  'ingresos.update': ['full'],
  'ingresos.cancel': ['full'],

  'movimientos.view': ['full', 'almacen', 'auditoria'],
  'movimientos.create': ['full', 'almacen'],

  'reports.view': ['full', 'almacen', 'auditoria'],
  'reports.download': ['full', 'almacen', 'auditoria'],
}

const normalizeArea = area => String(area || '').trim().toLowerCase()

export const resolveRole = area => {
  const normalized = normalizeArea(area)

  return Object.entries(ROLE_GROUPS).find(([, areas]) => areas.includes(normalized))?.[0] || null
}

export const canUser = (user, ability) => {
  const role = resolveRole(user?.area)
  if (!role)
    return false

  if (role === 'full')
    return true

  const allowed = ABILITIES[ability] || []

  return allowed.includes(role)
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

export const roleFromUser = user => resolveRole(user?.area)
