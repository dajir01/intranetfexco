const ROLE_GROUPS = {
  admin: ['sistemas', 'administracion'],
  full: ['gerencia'],
  almacen: ['almacen'],
  auditoria: ['auditoria'],
}

export const ABILITIES = {
  'products.view': ['admin', 'full', 'almacen', 'auditoria'],
  'products.create': ['admin', 'full'],
  'products.update': ['admin', 'full'],
  'products.baja': ['admin', 'full'],

  'assignments.upsert': ['admin', 'full', 'almacen'],
  'assignments.delete': ['admin', 'full'],

  'areas.view': ['admin', 'full', 'almacen', 'auditoria'],
  'providers.view': ['admin', 'full', 'almacen'],
  'providers.create': ['admin', 'full', 'almacen'],

  'ingresos.view': ['admin', 'full', 'almacen', 'auditoria'],
  'ingresos.create': ['admin', 'full', 'almacen'],
  'ingresos.update': ['admin', 'full'],
  'ingresos.cancel': ['admin', 'full'],

  'movimientos.view': ['admin', 'full', 'almacen', 'auditoria'],
  'movimientos.create': ['admin', 'full', 'almacen'],

  'reports.view': ['admin', 'full', 'almacen', 'auditoria'],
  'reports.download': ['admin', 'full', 'almacen', 'auditoria'],

  // Usuarios: solo Sistemas y Administración
  'users.view': ['admin'],
  'users.create': ['admin'],
  'users.update': ['admin'],

  // Ferias: solo Sistemas
  'ferias.view': ['admin'],
  'ferias.create': ['admin'],
  'ferias.update': ['admin'],
  'ferias.activate': ['admin'],
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
