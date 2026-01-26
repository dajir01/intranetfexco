import { useAuthStore } from '@/stores/auth'

/**
 * Composable para validar acceso al módulo Feria
 * El módulo Feria solo está disponible para usuarios del área "sistemas"
 * que corresponde al grupo de rol "admin"
 */
export function useFeriaAccess() {
  const auth = useAuthStore()

  /**
   * Verifica si el usuario actual puede acceder al módulo Feria
   * @returns {boolean} true si tiene acceso, false en caso contrario
   */
  const canAccessFeria = () => {
    return auth.can('ferias.view')
  }

  /**
   * Verifica si el usuario puede crear/modificar ferias
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canEditFeria = () => {
    return auth.can('ferias.create') && auth.can('ferias.update')
  }

  /**
   * Verifica si el usuario puede activar/desactivar ferias
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canManageFeria = () => {
    return auth.can('ferias.activate')
  }

  return {
    canAccessFeria,
    canEditFeria,
    canManageFeria,
  }
}
