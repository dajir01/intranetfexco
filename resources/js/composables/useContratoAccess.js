import { useAuthStore } from '@/stores/auth'

/**
 * Composable para validar acceso al módulo Contratos
 * El módulo Contratos solo está disponible para usuarios de áreas:
 * - Sistemas
 * - Administración
 * - Comercial
 */
export function useContratoAccess() {
  const auth = useAuthStore()

  /**
   * Verifica si el usuario puede acceder al módulo Contratos (listar)
   * @returns {boolean} true si tiene acceso, false en caso contrario
   */
  const canViewContratos = () => {
    return auth.can('contratos.view')
  }

  /**
   * Verifica si el usuario puede crear contratos (reservar)
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canCreateContrato = () => {
    return auth.can('contratos.create')
  }

  /**
   * Verifica si el usuario puede editar contratos (llenar datos)
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canEditContrato = () => {
    return auth.can('contratos.edit')
  }

  /**
   * Verifica si el usuario puede generar contratos
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canGenerateContrato = () => {
    return auth.can('contratos.generate')
  }

  /**
   * Verifica si el usuario puede imprimir contratos
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canPrintContrato = () => {
    return auth.can('contratos.print')
  }

  /**
   * Verifica si el usuario puede exportar contratos
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canExportContrato = () => {
    return auth.can('contratos.export')
  }

  /**
   * Verifica si el usuario puede eliminar contratos/reservas
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canDeleteContrato = () => {
    return auth.can('contratos.delete')
  }

  /**
   * Verifica si el usuario puede actualizar empresa de un contrato
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canUpdateEmpresa = () => {
    return auth.can('contratos.update-empresa')
  }

  /**
   * Verifica si el usuario puede actualizar stands de un contrato
   * @returns {boolean} true si tiene permiso, false en caso contrario
   */
  const canUpdateStands = () => {
    return auth.can('contratos.update-stands')
  }

  return {
    canViewContratos,
    canCreateContrato,
    canEditContrato,
    canGenerateContrato,
    canPrintContrato,
    canExportContrato,
    canDeleteContrato,
    canUpdateEmpresa,
    canUpdateStands,
  }
}
