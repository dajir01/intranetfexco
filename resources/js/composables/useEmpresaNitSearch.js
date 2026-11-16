import { onUnmounted, ref } from 'vue'
import axios, { isCancel } from 'axios'

const MIN_NIT_SEARCH_LENGTH = 5

export function useEmpresaNitSearch({
  endpoint,
  normalizeNit,
  onMatch,
  onReview,
  onError,
  debounce = 400,
}) {
  const buscandoNit = ref(false)
  const estadoBusquedaNit = ref('')
  let debounceTimer = null
  let activeController = null
  let requestVersion = 0

  const cancelarBusquedaEmpresa = ({ clearStatus = true } = {}) => {
    requestVersion += 1
    clearTimeout(debounceTimer)
    activeController?.abort()
    activeController = null
    buscandoNit.value = false

    if (clearStatus) estadoBusquedaNit.value = ''
  }

  const ejecutarBusqueda = async (nit, version) => {
    if (version !== requestVersion) return

    const controller = new AbortController()

    activeController = controller

    try {
      const response = await axios.get(endpoint(), {
        params: { nit },
        signal: controller.signal,
        timeout: 10000,
      })

      const payload = response.data

      if (!payload?.success || !payload.data || typeof payload.data.encontrada !== 'boolean') {
        throw new Error('El servidor devolvió una respuesta inválida al buscar el NIT.')
      }

      if (version !== requestVersion) return

      if (!payload.data.encontrada) {
        estadoBusquedaNit.value = 'No se encontró una empresa con este NIT. Puede continuar como empresa nueva.'

        return
      }

      if (payload.data.requiere_revision) {
        estadoBusquedaNit.value = 'Se encontraron registros duplicados que requieren revisión.'
        onReview?.()

        return
      }

      if (!payload.data.empresa) {
        throw new Error('La respuesta no contiene los datos de la empresa encontrada.')
      }

      estadoBusquedaNit.value = 'Empresa encontrada.'
      onMatch(payload.data)
    } catch (error) {
      if (version !== requestVersion || isCancel(error) || error?.code === 'ERR_CANCELED') return

      estadoBusquedaNit.value = 'No se pudo consultar el NIT.'
      onError(error)
    } finally {
      if (version === requestVersion) {
        activeController = null
        buscandoNit.value = false
      }
    }
  }

  const programarBusquedaEmpresa = value => {
    cancelarBusquedaEmpresa()

    const nit = normalizeNit(value)
    if (nit.length < MIN_NIT_SEARCH_LENGTH) return

    const version = requestVersion

    buscandoNit.value = true
    estadoBusquedaNit.value = 'Buscando empresa por NIT...'
    debounceTimer = setTimeout(() => ejecutarBusqueda(nit, version), debounce)
  }

  onUnmounted(() => cancelarBusquedaEmpresa())

  return {
    buscandoNit,
    estadoBusquedaNit,
    programarBusquedaEmpresa,
    cancelarBusquedaEmpresa,
  }
}
