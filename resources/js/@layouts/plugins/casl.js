import { useAbility } from '@casl/vue'

/**
 * Returns ability result if ACL is configured or else just return true
 * We should allow passing string | undefined to can because for admin ability we omit defining action & subject
 *
 * Useful if you don't know if ACL is configured or not
 * Used in @core files to handle absence of ACL without errors
 *
 * @param {string} action CASL Actions // https://casl.js.org/v4/en/guide/intro#basics
 * @param {string} subject CASL Subject // https://casl.js.org/v4/en/guide/intro#basics
 */
export const can = (action, subject) => {
  const vm = getCurrentInstance()
  if (!vm)
    return false
  const localCan = vm.proxy && '$can' in vm.proxy
    
  return localCan ? vm.proxy?.$can(action, subject) : true
}

/**
 * Check if user can view item based on it's ability
 * Based on item's action and subject & Hide group if all of it's children are hidden
 * @param {object} item navigation object item
 */
export const canViewNavMenuGroup = item => {
  // Helper para verificar permisos de un item individual
  const canViewItem = (i) => {
    // Si tiene action/subject (formato legacy CASL)
    if (i.action || i.subject) {
      return can(i.action, i.subject)
    }
    // Si tiene meta.ability (formato custom del proyecto)
    if (i.meta?.ability) {
      return can(i.meta.ability, null)
    }
    // Sin permisos definidos, mostrar por defecto
    return true
  }

  const hasAnyVisibleChild = item.children.some(canViewItem)

  // Si el grupo no tiene action/subject ni ability, solo verificar hijos
  if (!(item.action && item.subject) && !item.meta?.ability)
    return hasAnyVisibleChild
  
  // Verificar permiso del grupo y que tenga al menos un hijo visible
  return canViewItem(item) && hasAnyVisibleChild
}
export const canNavigate = to => {
  const ability = useAbility()

  // Get the most specific route (last one in the matched array)
  const targetRoute = to.matched[to.matched.length - 1]

  // If the target route has specific permissions, check those first
  if (targetRoute?.meta?.action && targetRoute?.meta?.subject)
    return ability.can(targetRoute.meta.action, targetRoute.meta.subject)

  // If no specific permissions, fall back to checking if any parent route allows access
    
  return to.matched.some(route => ability.can(route.meta.action, route.meta.subject))
}
