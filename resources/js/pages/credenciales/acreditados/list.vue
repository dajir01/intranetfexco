<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useSafePagination } from '@/composables/useSafePagination'
import { useCan } from '@/composables/useCan'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Acreditados',
    ability: 'credenciales.empresas.ver',
  },
})

const route = useRoute()
const can = useCan()
const eventos = ref([])
const eventoSeleccionado = ref(null)
const acreditados = ref([])
const totalAcreditados = ref(0)
const resumenPorTipo = ref([])
const loadingEventos = ref(false)
const loadingAcreditados = ref(false)
const error = ref('')
const search = ref('')
const modalidadFiltro = ref('Todos')
const tipoFiltro = ref('Todos')
const page = ref(1)
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const itemsPerPage = ref(defaultItemsPerPage)
const credencialesSeleccionadas = ref([])
let busquedaTimer = null
const snackbarAccion = ref({ show: false, color: 'success', text: '' })
const dialogVerAcreditado = ref(false)
const acreditadoSeleccionado = ref(null)
const dialogHistorial = ref(false)
const historialEventos = ref([])
const cargandoHistorial = ref(false)
const errorHistorial = ref('')
const dialogModalidad = ref(false)
const modalidadNueva = ref(1)
const dialogQr = ref(false)
const dialogEstado = ref(false)
const actualizandoHabilitacionAcreditacion = ref(false)
const dialogInhabilitar = ref(false)
const accionEstado = ref('')
const estadoNuevo = ref(null)
const motivoEstado = ref('')
const motivoOperacion = ref('')
const observacionesOperacion = ref('')
const guardandoOperacion = ref(false)
const errorOperacion = ref('')
const dialogEditar = ref(false)
const editando = ref(null)
const guardandoEdicion = ref(false)
const errorEdicion = ref('')
const archivoFoto = ref(null)
const inputFoto = ref(null)
const editFotoPreview = ref('')
const editCodigoPais = ref('591')
const editNumeroCelular = ref('')
const codigosPais = [
  { title: 'Bolivia (+591)', value: '591' },
  { title: 'Argentina (+54)', value: '54' },
  { title: 'Brasil (+55)', value: '55' },
  { title: 'Chile (+56)', value: '56' },
  { title: 'Perú (+51)', value: '51' },
  { title: 'México (+52)', value: '52' },
  { title: 'España (+34)', value: '34' },
  { title: 'Estados Unidos (+1)', value: '1' },
]

const headers = [
  { title: '', key: 'select', sortable: false, width: 52 },
  { title: 'Acreditado', key: 'acreditado', sortable: false },
  { title: 'Empresa / Institución', key: 'empresa', sortable: false },
  { title: 'Teléfono', key: 'celular' },
  { title: 'Tipo de credencial', key: 'tipo_credencial' },
  { title: 'Estado', key: 'estado_credencial' },
  { title: 'Impresiones / Descargas', key: 'salida', sortable: false, align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

const modalidadesFiltro = [
  { title: 'Todas las modalidades', value: 'Todos' },
  { title: 'Física', value: 'Física' },
  { title: 'Digital', value: 'Digital' },
]
const tiposFiltro = [
  { title: 'Todos los tipos', value: 'Todos' },
  { title: 'Expositor', value: 'Expositor' },
  { title: 'Oficial', value: 'Oficial' },
  { title: 'Prensa', value: 'Prensa' },
  { title: 'Servicios', value: 'Servicios' },
  { title: 'Negocios', value: 'Negocios' },
]

const urlFoto = foto => {
  if (!foto) return ''
  const filename = String(foto).split('/').pop()
  if (!/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i.test(filename)) return ''

  return `/credenciales/fotos/${encodeURIComponent(filename)}`
}

const estadoColor = estado => ({
  Activo: 'success',
  Anulado: 'warning',
  Inhabilitado: 'error',
  Eliminado: 'secondary',
}[estado] || 'secondary')

const tipoCredencialColor = tipo => ({
  Expositor: 'success',
  Oficial: 'info',
  Prensa: 'secondary',
  Servicios: 'warning',
  Negocios: 'primary',
}[String(tipo ?? '').trim()] || 'primary')

const tipoCredencialIcon = tipo => ({
  Expositor: 'tabler-users',
  Oficial: 'tabler-id-badge-2',
  Prensa: 'tabler-news',
  Servicios: 'tabler-tools',
  Negocios: 'tabler-briefcase',
}[String(tipo ?? '').trim()] || 'tabler-id-badge-2')

const abrirDetalle = acreditado => {
  if (!can('credenciales.acreditados.ver')) return

  acreditadoSeleccionado.value = acreditado
  dialogVerAcreditado.value = true
}

const cerrarDetalle = () => {
  dialogVerAcreditado.value = false
  acreditadoSeleccionado.value = null
}

const abrirHistorial = async acreditado => {
  dialogHistorial.value = true
  historialEventos.value = []
  errorHistorial.value = ''
  cargandoHistorial.value = true

  try {
    const response = await fetch(`/credenciales/acreditados/${acreditado.id_credencial}/historial?id_feria=${eventoSeleccionado.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial.')
    historialEventos.value = payload.eventos || []
  } catch (err) {
    errorHistorial.value = err.message || 'No se pudo cargar el historial.'
  } finally {
    cargandoHistorial.value = false
  }
}

const cerrarHistorial = () => {
  dialogHistorial.value = false
  historialEventos.value = []
}

const formatoFechaHistorial = fecha => fecha
  ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(fecha).replace(' ', 'T')))
  : 'Fecha no disponible'

const ejecutarOperacion = async (url, body, method = 'POST') => {
  guardandoOperacion.value = true
  errorOperacion.value = ''

  try {
    const response = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo completar la operación.')

    dialogModalidad.value = false
    dialogQr.value = false
    dialogEstado.value = false
    dialogInhabilitar.value = false
    await cargarAcreditados()
  } catch (err) {
    errorOperacion.value = err.message || 'No se pudo completar la operación.'
  } finally {
    guardandoOperacion.value = false
  }
}

const abrirModalidad = acreditado => {
  acreditadoSeleccionado.value = acreditado
  modalidadNueva.value = acreditado.modalidad_credencial === 'Digital' ? 0 : 1
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogModalidad.value = true
}

const guardarModalidad = () => ejecutarOperacion(
  `/credenciales/acreditados/${acreditadoSeleccionado.value.id_credencial}/modalidad`,
  { modalidad: modalidadNueva.value, observaciones: observacionesOperacion.value },
  'PATCH',
)

const abrirQr = acreditado => {
  acreditadoSeleccionado.value = acreditado
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogQr.value = true
}

const guardarQr = () => ejecutarOperacion(
  `/credenciales/acreditados/${acreditadoSeleccionado.value.id_credencial}/renovar-qr`,
  { observaciones: observacionesOperacion.value },
)

const abrirEstado = (acreditado, accion, estado) => {
  acreditadoSeleccionado.value = acreditado
  accionEstado.value = accion
  estadoNuevo.value = estado
  motivoEstado.value = ''
  errorOperacion.value = ''
  dialogEstado.value = true
}

const abrirInhabilitar = acreditado => {
  acreditadoSeleccionado.value = acreditado
  motivoOperacion.value = ''
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogInhabilitar.value = true
}

const confirmarInhabilitacion = () => {
  if (!motivoOperacion.value) {
    errorOperacion.value = 'Seleccione un motivo.'

    return
  }

  ejecutarOperacion('/credenciales/acreditados/inhabilitar', {
    credenciales: [acreditadoSeleccionado.value.id_credencial],
    motivo: motivoOperacion.value,
    observaciones: observacionesOperacion.value,
  })
}

const guardarEstado = () => {
  if (!motivoEstado.value.trim()) {
    errorOperacion.value = 'Debe indicar el motivo de la operación.'

    return
  }

  if (accionEstado.value === 'eliminar')
    return ejecutarOperacion(`/credenciales/acreditados/${acreditadoSeleccionado.value.id_credencial}/eliminar`, { motivo: motivoEstado.value.trim() }, 'DELETE')

  if (accionEstado.value === 'habilitar')
    return ejecutarOperacion('/credenciales/acreditados/habilitar', { credenciales: [acreditadoSeleccionado.value.id_credencial], observaciones: motivoEstado.value.trim() })

  if (accionEstado.value === 'inhabilitar')
    return ejecutarOperacion('/credenciales/acreditados/inhabilitar', { credenciales: [acreditadoSeleccionado.value.id_credencial], motivo: motivoEstado.value.trim() })

  return ejecutarOperacion(`/credenciales/acreditados/${acreditadoSeleccionado.value.id_credencial}/estado`, { estado: estadoNuevo.value, motivo: motivoEstado.value.trim() }, 'PATCH')
}

const abrirEditar = acreditado => {
  editando.value = { ...acreditado }
  archivoFoto.value = null
  editFotoPreview.value = urlFoto(acreditado.foto)
  const digitos = String(acreditado.celular || '').replace(/\D/g, '')
  editCodigoPais.value = digitos.length === 8 ? '591' : (codigosPais.find(codigo => digitos.startsWith(codigo.value))?.value || '591')
  editNumeroCelular.value = digitos.length === 8 ? digitos : digitos.replace(new RegExp(`^${editCodigoPais.value}`), '')
  errorEdicion.value = ''
  dialogEditar.value = true
}

const seleccionarFoto = event => {
  archivoFoto.value = event?.target?.files?.[0] || event?.[0] || event || null
  if (archivoFoto.value) editFotoPreview.value = URL.createObjectURL(archivoFoto.value)
}

const abrirSelectorFoto = () => inputFoto.value?.click()

const guardarEdicion = async () => {
  if (!editando.value?.nombre?.trim() || !editando.value?.ci?.trim()) {
    errorEdicion.value = 'El nombre y el CI son obligatorios.'

    return
  }

  guardandoEdicion.value = true
  errorEdicion.value = ''
  try {
    const formData = new FormData()

    formData.append('_method', 'PATCH')
    formData.append('ci', editando.value.ci.trim())
    formData.append('tipo_documento', editando.value.tipo_documento || '')
    formData.append('nombre', editando.value.nombre.trim())
    formData.append('email', editando.value.email || '')
    formData.append('celular', editNumeroCelular.value ? `+${editCodigoPais.value}${editNumeroCelular.value}` : '')
    if (archivoFoto.value) formData.append('foto', archivoFoto.value)

    const response = await fetch(`/credenciales/acreditados/${editando.value.id_credencial}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: formData,
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo actualizar el acreditado.')
    dialogEditar.value = false
    await cargarAcreditados()
  } catch (err) {
    errorEdicion.value = err.message || 'No se pudo actualizar el acreditado.'
  } finally {
    guardandoEdicion.value = false
  }
}

const formatoFechaSalida = fecha => fecha
  ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(String(fecha).replace(' ', 'T')))
  : 'Fecha no disponible'

const abrirSalida = async acreditado => {
  const url = acreditado.modalidad_credencial === 'Digital'
    ? `/credenciales/acreditados/${acreditado.id_credencial}/descargar-digital`
    : `/credenciales/acreditados/${acreditado.id_credencial}/imprimir-fisica`

  if (acreditado.modalidad_credencial !== 'Digital') {
    window.open(url, '_blank', 'noopener')
    sincronizarAcreditados(2, 700)
    return
  }

  try {
    const response = await fetch(url, {
      headers: { Accept: 'image/png', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const contentType = response.headers.get('content-type') || ''
    if (!response.ok || response.redirected || !contentType.includes('image/png')) {
      throw new Error('La sesión expiró o el servidor no devolvió una imagen PNG válida.')
    }

    const firmaPng = [137, 80, 78, 71, 13, 10, 26, 10]
    const bytesRespuesta = new Uint8Array(await response.arrayBuffer())
    let inicioPng = -1
    for (let indice = 0; indice <= bytesRespuesta.length - firmaPng.length; indice++) {
      if (firmaPng.every((byte, offset) => bytesRespuesta[indice + offset] === byte)) {
        inicioPng = indice
        break
      }
    }

    if (inicioPng < 0) {
      const muestra = Array.from(bytesRespuesta.slice(0, 16)).map(byte => byte.toString(16).padStart(2, '0')).join(' ')
      console.error('Respuesta inválida al descargar credencial digital', {
        status: response.status,
        contentType,
        bytes: bytesRespuesta.length,
        muestra,
      })
      throw new Error('El archivo recibido no es una imagen PNG válida.')
    }

    const blob = new Blob([bytesRespuesta.slice(inicioPng)], { type: 'image/png' })
    const enlace = document.createElement('a')
    const urlBlob = URL.createObjectURL(blob)
    enlace.href = urlBlob
    enlace.download = `credencial-${acreditado.nombre || acreditado.id_credencial}.png`
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    setTimeout(() => URL.revokeObjectURL(urlBlob), 1000)
    sincronizarAcreditados(2, 700)
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudo descargar la credencial digital.' }
  }
}

const exportarAcreditados = () => {
  if (!eventoSeleccionado.value) return

  const parametros = new URLSearchParams()
  if (search.value.trim()) parametros.set('search', search.value.trim())
  if (modalidadFiltro.value !== 'Todos') parametros.set('modalidad', modalidadFiltro.value)
  if (tipoFiltro.value !== 'Todos') parametros.set('tipo_credencial', tipoFiltro.value)

  window.open(`/credenciales/eventos/${eventoSeleccionado.value}/acreditados/exportar?${parametros.toString()}`, '_blank', 'noopener')
}

const cargarEventos = async () => {
  loadingEventos.value = true
  error.value = ''

  try {
    const response = await fetch('/credenciales/eventos', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los eventos')

    eventos.value = payload.data || []

    const feriaDesdeQuery = Number(route.query.feria)
    if (feriaDesdeQuery && eventos.value.some(evento => Number(evento.id_feria) === feriaDesdeQuery))
      eventoSeleccionado.value = feriaDesdeQuery
  } catch (err) {
    error.value = err.message || 'No se pudieron cargar los eventos'
  } finally {
    loadingEventos.value = false
  }
}

const cambiarHabilitacionAcreditacion = async () => {
  const feria = feriaSeleccionadaInfo.value
  if (!feria || !can('credenciales.acreditados.editar') || actualizandoHabilitacionAcreditacion.value) return

  actualizandoHabilitacionAcreditacion.value = true
  error.value = ''

  try {
    const response = await fetch(`/credenciales/eventos/${feria.id_feria}/acreditacion-empresa`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ habilitada: !feria.acreditacion_empresa_habilitada }),
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo actualizar la habilitación de acreditaciones.')

    const acreditacionHabilitada = Boolean(payload.data?.acreditacion_empresa_habilitada)

    eventos.value = eventos.value.map(evento => {
      if (Number(evento.id_feria) !== Number(feria.id_feria)) return evento

      return {
        ...evento,
        // eslint-disable-next-line camelcase
        acreditacion_empresa_habilitada: acreditacionHabilitada,
      }
    })
    snackbarAccion.value = {
      show: true,
      color: 'success',
      text: payload.data?.acreditacion_empresa_habilitada
        ? 'Las acreditaciones de empresas quedaron habilitadas en Plataforma.'
        : 'Las acreditaciones de empresas quedaron cerradas en Plataforma.',
    }
  } catch (exception) {
    error.value = exception.message || 'No se pudo actualizar la habilitación de acreditaciones.'
  } finally {
    actualizandoHabilitacionAcreditacion.value = false
  }
}

const cargarAcreditados = async () => {
  if (!eventoSeleccionado.value) {
    acreditados.value = []
    totalAcreditados.value = 0
    resumenPorTipo.value = []
    credencialesSeleccionadas.value = []

    return
  }

  loadingAcreditados.value = true
  error.value = ''

  try {
    const parametros = new URLSearchParams({
      page: String(page.value),
      per_page: String(itemsPerPage.value),
    })
    if (search.value.trim()) parametros.set('search', search.value.trim())
    if (modalidadFiltro.value !== 'Todos') parametros.set('modalidad', modalidadFiltro.value)
    if (tipoFiltro.value !== 'Todos') parametros.set('tipo_credencial', tipoFiltro.value)

    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/acreditados?${parametros.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los acreditados')
    acreditados.value = payload.data || []
    totalAcreditados.value = payload.meta?.total || 0
    resumenPorTipo.value = payload.meta?.resumen_por_tipo || []
    limpiarSeleccionFueraDeResultados()
  } catch (err) {
    error.value = err.message || 'No se pudieron cargar los acreditados'
  } finally {
    loadingAcreditados.value = false
  }
}

const sincronizarAcreditados = async (intentos = 1, espera = 0) => {
  try {
    for (let intento = 0; intento < intentos; intento++) {
      if (espera) await new Promise(resolve => setTimeout(resolve, espera))
      await cargarAcreditados()
    }
  } catch {}
}

const cambiarEvento = () => {
  page.value = 1
  credencialesSeleccionadas.value = []
  cargarAcreditados()
}

const acreditadosSeleccionables = computed(() => acreditados.value.filter(acreditado => acreditado.estado_credencial_codigo !== 3))
const todosSeleccionados = computed(() => acreditadosSeleccionables.value.length > 0
  && acreditadosSeleccionables.value.every(acreditado => credencialesSeleccionadas.value.includes(acreditado.id_credencial)))
const seleccionados = computed(() => acreditados.value.filter(acreditado => credencialesSeleccionadas.value.includes(acreditado.id_credencial)))
const seleccionadosDigitales = computed(() => seleccionados.value.filter(acreditado => acreditado.modalidad_credencial === 'Digital'))
const seleccionadosFisicos = computed(() => seleccionados.value.filter(acreditado => acreditado.modalidad_credencial !== 'Digital'))

const alternarSeleccionTodos = seleccionado => {
  const ids = acreditadosSeleccionables.value.map(acreditado => acreditado.id_credencial)
  const modalidades = new Set(acreditadosSeleccionables.value.map(acreditado => acreditado.modalidad_credencial))

  if (seleccionado && modalidades.size > 1) {
    snackbarAccion.value = { show: true, color: 'warning', text: 'No puede seleccionar credenciales físicas y digitales al mismo tiempo. Filtre una sola modalidad.' }

    return
  }

  if (seleccionado && credencialesSeleccionadas.value.length) {
    const modalidadActual = seleccionados.value[0]?.modalidad_credencial
    const modalidadNueva = acreditadosSeleccionables.value.find(acreditado => acreditado.modalidad_credencial !== modalidadActual)
    if (modalidadNueva) {
      snackbarAccion.value = { show: true, color: 'warning', text: 'No puede seleccionar credenciales físicas y digitales al mismo tiempo.' }

      return
    }
  }

  credencialesSeleccionadas.value = seleccionado
    ? Array.from(new Set([...credencialesSeleccionadas.value, ...ids]))
    : credencialesSeleccionadas.value.filter(id => !ids.includes(id))
}

const alternarSeleccion = (acreditado, seleccionado) => {
  if (!seleccionado) {
    credencialesSeleccionadas.value = credencialesSeleccionadas.value.filter(id => id !== acreditado.id_credencial)

    return
  }

  const modalidadActual = seleccionados.value[0]?.modalidad_credencial
  if (modalidadActual && modalidadActual !== acreditado.modalidad_credencial) {
    snackbarAccion.value = { show: true, color: 'warning', text: 'No puede seleccionar credenciales físicas y digitales al mismo tiempo.' }

    return
  }

  credencialesSeleccionadas.value = Array.from(new Set([...credencialesSeleccionadas.value, acreditado.id_credencial]))
}

const limpiarSeleccionFueraDeResultados = () => {
  const ids = new Set(acreditados.value.map(acreditado => acreditado.id_credencial))
  credencialesSeleccionadas.value = credencialesSeleccionadas.value.filter(id => ids.has(id))
}

const enviarSeleccionados = async () => {
  if (!seleccionadosDigitales.value.length) return

  try {
    const response = await fetch('/credenciales/enviar-digitales', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({ credenciales: seleccionadosDigitales.value.map(acreditado => acreditado.id_credencial) }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudieron enviar las credenciales.')

    credencialesSeleccionadas.value = []
    snackbarAccion.value = { show: true, color: 'success', text: payload.message }
    sincronizarAcreditados(8, 1000)
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudieron enviar las credenciales.' }
  }
}

const descargarSeleccionados = async () => {
  if (!seleccionadosDigitales.value.length) return

  try {
    const response = await fetch('/credenciales/descargar-digitales', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        credenciales: seleccionadosDigitales.value.map(acreditado => acreditado.id_credencial),
        archivo: true,
      }),
    })
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}))
      throw new Error(payload.message || 'No se pudieron descargar las credenciales.')
    }

    const bytesRespuesta = new Uint8Array(await response.arrayBuffer())
    const firmaZip = [0x50, 0x4b, 0x03, 0x04]
    let inicioZip = -1
    for (let indice = 0; indice <= bytesRespuesta.length - firmaZip.length; indice++) {
      if (firmaZip.every((byte, offset) => bytesRespuesta[indice + offset] === byte)) {
        inicioZip = indice
        break
      }
    }

    if (inicioZip < 0) {
      throw new Error('El servidor no devolvió un archivo ZIP válido.')
    }

    const archivo = new Blob([bytesRespuesta.slice(inicioZip)], { type: 'application/zip' })
    const urlBlob = URL.createObjectURL(archivo)
    const enlace = document.createElement('a')
    enlace.href = urlBlob
    enlace.download = payload.filename || 'credenciales-digitales.zip'
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    setTimeout(() => URL.revokeObjectURL(urlBlob), 1000)
    await sincronizarAcreditados()
    snackbarAccion.value = { show: true, color: 'success', text: 'Credenciales digitales descargadas correctamente.' }
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudieron descargar las credenciales.' }
  }
}

const imprimirSeleccionados = () => {
  if (!seleccionadosFisicos.value.length) return

  const formulario = document.createElement('form')
  formulario.method = 'POST'
  formulario.action = '/credenciales/imprimir-fisicas'
  formulario.target = '_blank'
  formulario.classList.add('d-none')
  const csrf = document.createElement('input')
  csrf.type = 'hidden'
  csrf.name = '_token'
  csrf.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  formulario.appendChild(csrf)
  seleccionadosFisicos.value.forEach(acreditado => {
    const campo = document.createElement('input')
    campo.type = 'hidden'
    campo.name = 'credenciales[]'
    campo.value = acreditado.id_credencial
    formulario.appendChild(campo)
  })
  document.body.appendChild(formulario)
  formulario.submit()
  formulario.remove()
  sincronizarAcreditados(2, 700)
}

const feriaSeleccionadaInfo = computed(() => eventos.value.find(evento => Number(evento.id_feria) === Number(eventoSeleccionado.value)))

watch(search, () => {
  clearTimeout(busquedaTimer)
  busquedaTimer = setTimeout(() => {
    page.value = 1
    credencialesSeleccionadas.value = []
    cargarAcreditados()
  }, 350)
})

watch([modalidadFiltro, tipoFiltro], () => {
  page.value = 1
  credencialesSeleccionadas.value = []
  cargarAcreditados()
})

watch(itemsPerPage, () => {
  page.value = 1
  cargarAcreditados()
})

watch(page, () => {
  cargarAcreditados()
})

onMounted(cargarEventos)
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              Acreditados
            </h4>
          </div>
        </div>

        <VRow class="align-center">
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              v-model="eventoSeleccionado"
              :items="eventos"
              :loading="loadingEventos"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Seleccionar Evento / Feria"
              placeholder="Elige una feria para ver los acreditados"
              clearable
              @update:model-value="cambiarEvento"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" />
              </template>
            </VSelect>
          </VCol>

          <VCol
            v-if="feriaSeleccionadaInfo"
            cols="12"
            md="6"
          >
            <VAlert
              type="info"
              variant="tonal"
              class="mb-0"
            >
              <strong>{{ feriaSeleccionadaInfo.nombre_feria }}</strong>
              <div class="d-flex align-center justify-space-between flex-wrap gap-3 mt-2">
                <div class="text-caption">
                  Total acreditados: {{ totalAcreditados }}
                </div>
                <div
                  v-if="can('credenciales.acreditados.editar')"
                  class="d-flex align-center gap-2"
                >
                  <VChip
                    size="small"
                    :color="feriaSeleccionadaInfo.acreditacion_empresa_habilitada ? 'success' : 'secondary'"
                    variant="tonal"
                  >
                    Acreditación en Plataforma: {{ feriaSeleccionadaInfo.acreditacion_empresa_habilitada ? 'Habilitada' : 'Cerrada' }}
                  </VChip>
                  <VBtn
                    size="small"
                    :color="feriaSeleccionadaInfo.acreditacion_empresa_habilitada ? 'error' : 'success'"
                    variant="tonal"
                    :loading="actualizandoHabilitacionAcreditacion"
                    @click="cambiarHabilitacionAcreditacion"
                  >
                    {{ feriaSeleccionadaInfo.acreditacion_empresa_habilitada ? 'Cerrar' : 'Habilitar' }}
                  </VBtn>
                </div>
              </div>
            </VAlert>
          </VCol>
        </VRow>

        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mt-4"
          closable
          @click:close="error = ''"
        >
          {{ error }}
        </VAlert>
      </VCardText>
    </VCard>

    <VRow v-if="eventoSeleccionado && resumenPorTipo.length" class="mb-6">
      <VCol
        v-for="resumen in resumenPorTipo"
        :key="resumen.tipo"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard
          class="acreditados-tipo-estadistica"
          :style="`border-block-end-color: rgba(var(--v-theme-${tipoCredencialColor(resumen.tipo)}), 0.38)`"
        >
          <VCardText>
            <div class="d-flex align-center gap-4 mb-2">
              <VAvatar variant="tonal" :color="tipoCredencialColor(resumen.tipo)" rounded>
                <VIcon :icon="tipoCredencialIcon(resumen.tipo)" size="28" />
              </VAvatar>
              <h4 class="text-h4">{{ resumen.acreditadas }}</h4>
            </div>
            <div class="text-body-1 mb-2">{{ resumen.tipo }}</div>
            <div class="text-body-2 text-medium-emphasis">
              Digital: <strong>{{ resumen.digitales }}</strong>
              <span class="mx-1">|</span>
              Física: <strong>{{ resumen.fisicas }}</strong>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard
      v-if="eventoSeleccionado"
      id="credenciales-acreditados-list"
    >
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Mostrar</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = sanitizeItemsPerPage($event)"
            />
          </div>
        </div>

        <div class="d-flex align-center gap-2 flex-wrap">
          <VSelect
            v-model="modalidadFiltro"
            :items="modalidadesFiltro"
            item-title="title"
            item-value="value"
            label="Modalidad"
            variant="outlined"
            density="comfortable"
            hide-details
            style="min-inline-size: 190px;"
          />
          <VSelect
            v-model="tipoFiltro"
            :items="tiposFiltro"
            item-title="title"
            item-value="value"
            label="Tipo de credencial"
            variant="outlined"
            density="comfortable"
            hide-details
            style="min-inline-size: 190px;"
          />
          <VTooltip
            v-if="can('credenciales.acreditados.exportar')"
            location="top"
          >
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="small"
                variant="text"
                color="success"
                @click="exportarAcreditados"
              >
                <VIcon icon="tabler-file-spreadsheet" />
              </VBtn>
            </template>
            <span>Exportar acreditados a Excel</span>
          </VTooltip>
          <VTooltip
            v-if="can('credenciales.salida_fisica.imprimir_lote')"
            location="top"
          >
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="small"
                :variant="seleccionadosFisicos.length ? 'flat' : 'text'"
                color="secondary"
                :disabled="!seleccionadosFisicos.length"
                :class="{ 'lote-action-button-active': seleccionadosFisicos.length }"
                @click="imprimirSeleccionados"
              >
                <VIcon icon="tabler-printer" />
                <span
                  v-if="seleccionadosFisicos.length"
                  class="lote-action-count"
                >
                  {{ seleccionadosFisicos.length }}
                </span>
              </VBtn>
            </template>
            <span>Imprimir seleccionados</span>
          </VTooltip>
          <VTooltip
            v-if="can('credenciales.salida_digital.descargar_lote')"
            location="top"
          >
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="small"
                :variant="seleccionadosDigitales.length ? 'flat' : 'text'"
                color="primary"
                :disabled="!seleccionadosDigitales.length"
                :class="{ 'lote-action-button-active': seleccionadosDigitales.length }"
                @click="descargarSeleccionados"
              >
                <VIcon icon="tabler-download" />
                <span
                  v-if="seleccionadosDigitales.length"
                  class="lote-action-count"
                >
                  {{ seleccionadosDigitales.length }}
                </span>
              </VBtn>
            </template>
            <span>Descargar seleccionados</span>
          </VTooltip>
          <VTooltip
            v-if="can('credenciales.seleccionados.enviar')"
            location="top"
          >
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="small"
                :variant="seleccionadosDigitales.length ? 'flat' : 'text'"
                color="info"
                :disabled="!seleccionadosDigitales.length"
                :class="{ 'lote-action-button-active': seleccionadosDigitales.length }"
                @click="enviarSeleccionados"
              >
                <VIcon icon="tabler-mail-forward" />
                <span
                  v-if="seleccionadosDigitales.length"
                  class="lote-action-count"
                >
                  {{ seleccionadosDigitales.length }}
                </span>
              </VBtn>
            </template>
            <span>Enviar seleccionados</span>
          </VTooltip>
          <AppTextField
            v-model="search"
            placeholder="Buscar empresa, CI, nombre o CI de persona..."
            append-inner-icon="tabler-search"
            single-line
            hide-details
            outlined
            style="min-inline-size: 280px;"
          />
        </div>
      </VCardText>

      <VDivider />

      <VCardText class="pt-4">
        <div
          v-if="loadingAcreditados"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">
            Cargando acreditados...
          </p>
        </div>

        <VAlert
          v-else-if="!acreditados.length"
          type="info"
          variant="tonal"
        >
          {{ search ? 'No hay acreditados que coincidan con la búsqueda.' : 'No hay acreditados para este evento.' }}
        </VAlert>

        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :items-length="totalAcreditados"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="acreditados"
          :loading="loadingAcreditados"
          item-value="id_credencial"
          class="text-no-wrap"
        >
          <template #header.select>
            <VCheckbox
              :model-value="todosSeleccionados"
              :indeterminate="credencialesSeleccionadas.length > 0 && !todosSeleccionados"
              hide-details
              density="compact"
              @update:model-value="alternarSeleccionTodos"
            />
          </template>

          <template #item.select="{ item }">
            <VCheckbox
              :model-value="credencialesSeleccionadas.includes(item.id_credencial)"
              :disabled="item.estado_credencial_codigo === 3"
              hide-details
              density="compact"
              @update:model-value="alternarSeleccion(item, $event)"
            />
          </template>

          <template #item.acreditado="{ item }">
            <div class="d-flex align-center gap-3 py-1">
              <VAvatar
                size="48"
                rounded="sm"
                color="secondary"
                variant="tonal"
              >
                <VImg
                  v-if="urlFoto(item.foto)"
                  :src="urlFoto(item.foto)"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-user"
                />
              </VAvatar>
              <div>
                <div class="font-weight-medium">
                  {{ item.nombre || 'Sin nombre' }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  CI: {{ item.ci || 'Sin datos' }}
                  <template v-if="item.exp">
                    {{ item.exp }}
                  </template>
                </div>
              </div>
            </div>
          </template>

          <template #item.empresa="{ item }">
            <div>
              <div class="font-weight-medium">
                {{ item.nombre_empresa || 'Sin empresa' }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.tipo_credencial }}
              </div>
            </div>
          </template>

          <template #item.celular="{ item }">
            {{ item.celular || 'Sin datos' }}
          </template>

          <template #item.tipo_credencial="{ item }">
            <VChip
              size="small"
              variant="tonal"
              :color="tipoCredencialColor(item.tipo_credencial)"
            >
              {{ item.tipo_credencial }} · {{ item.modalidad_credencial }}
            </VChip>
          </template>

          <template #item.estado_credencial="{ item }">
            <VChip
              size="small"
              variant="tonal"
              :color="estadoColor(item.estado_credencial)"
            >
              {{ item.estado_credencial }}
            </VChip>
          </template>

          <template #item.salida="{ item }">
            <template v-if="item.salida">
              <VChip
                size="small"
                variant="tonal"
                color="primary"
              >
                {{ item.salida.cantidad }}x
              </VChip>
              <div class="text-caption text-medium-emphasis mt-1">
                {{ formatoFechaSalida(item.salida.ultima_fecha) }}
              </div>
            </template>
            <span v-else>—</span>
          </template>

          <template #item.actions="{ item }">
            <VTooltip
              v-if="item.estado_credencial_codigo !== 3 && ((item.modalidad_credencial === 'Digital' && can('credenciales.salida_digital.descargar')) || (item.modalidad_credencial !== 'Digital' && can('credenciales.salida_fisica.imprimir')))"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  :color="item.modalidad_credencial === 'Digital' ? 'primary' : 'secondary'"
                  @click="abrirSalida(item)"
                >
                  <VIcon :icon="item.modalidad_credencial === 'Digital' ? 'tabler-download' : 'tabler-printer'" />
                </VBtn>
              </template>
              <span>{{ item.modalidad_credencial === 'Digital' ? 'Descargar credencial digital' : 'Imprimir credencial física' }}</span>
            </VTooltip>
            <VTooltip
              v-if="can('credenciales.acreditados.ver')"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  color="primary"
                  @click="abrirDetalle(item)"
                >
                  <VIcon icon="tabler-eye" />
                </VBtn>
              </template>
              <span>Ver acreditado</span>
            </VTooltip>
            <VTooltip v-if="can('credenciales.historial.ver')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="info" @click="abrirHistorial(item)"><VIcon icon="tabler-clipboard-list" /></VBtn>
              </template>
              <span>Ver historial</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 1 && can('credenciales.modalidad.cambiar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="secondary" @click="abrirModalidad(item)"><VIcon icon="tabler-repeat" /></VBtn>
              </template>
              <span>Cambiar tipo de credencial</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 1 && can('credenciales.estado.inhabilitar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="warning" @click="abrirInhabilitar(item)"><VIcon icon="tabler-user-off" /></VBtn>
              </template>
              <span>Inhabilitar credencial</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 1 && can('credenciales.qr.renovar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="success" @click="abrirQr(item)"><VIcon icon="tabler-qrcode" /></VBtn>
              </template>
              <span>Generar nuevo QR</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo !== 3 && can('credenciales.acreditados.editar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="warning" @click="abrirEditar(item)"><VIcon icon="tabler-pencil" /></VBtn>
              </template>
              <span>Editar acreditado</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 1 && can('credenciales.estado.anular')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="error" @click="abrirEstado(item, 'estado', 2)"><VIcon icon="tabler-trash" /></VBtn>
              </template>
              <span>Anular credencial</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 0 && can('credenciales.estado.habilitar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="success" @click="abrirEstado(item, 'habilitar', 1)"><VIcon icon="tabler-check" /></VBtn>
              </template>
              <span>Habilitar y conservar QR</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo === 2 && can('credenciales.estado.habilitar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="success" @click="abrirEstado(item, 'estado', 1)"><VIcon icon="tabler-check" /></VBtn>
              </template>
              <span>Reactivar con nuevo QR</span>
            </VTooltip>
            <VTooltip v-if="item.estado_credencial_codigo !== 3 && can('credenciales.registro.eliminar')" location="top">
              <template #activator="{ props }">
                <VBtn v-bind="props" icon size="small" variant="text" color="error" @click="abrirEstado(item, 'eliminar', null)"><VIcon icon="tabler-trash-x" /></VBtn>
              </template>
              <span>Eliminar registro</span>
            </VTooltip>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialogVerAcreditado"
      max-width="640"
      scrollable
    >
      <VCard
        v-if="acreditadoSeleccionado"
        class="acreditacion-dialog-card"
      >
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              icon="tabler-user"
              size="24"
            />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
              Datos del acreditado
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              Información registrada para la credencial
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            size="small"
            @click="cerrarDetalle"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="acreditacion-dialog-body pa-5 pt-4">
          <div class="ver-acreditado-contenido">
            <div class="d-flex flex-column align-center text-center mb-5">
              <VAvatar
                size="140"
                rounded="circle"
                color="secondary"
                variant="tonal"
              >
                <VImg
                  v-if="urlFoto(acreditadoSeleccionado.foto)"
                  :src="urlFoto(acreditadoSeleccionado.foto)"
                  cover
                />
                <VIcon
                  v-else
                  icon="tabler-user"
                  size="44"
                />
              </VAvatar>
              <p class="text-body-2 text-medium-emphasis mt-3 mb-0">
                Fotografía
              </p>
            </div>
            <div class="ver-acreditado-datos-grid">
              <div class="conflicto-acreditado-linea ver-acreditado-dato-principal">
                <span>Nombre completo</span>
                <strong>{{ acreditadoSeleccionado.nombre || 'Sin datos' }}</strong>
              </div>
              <div class="conflicto-acreditado-linea">
                <span>Carnet de identidad</span>
                <strong>{{ acreditadoSeleccionado.ci || 'Sin datos' }}</strong>
              </div>
              <div class="conflicto-acreditado-linea">
                <span>Documento</span>
                <strong>{{ acreditadoSeleccionado.tipo_documento || 'Sin datos' }}</strong>
              </div>
              <div class="conflicto-acreditado-linea">
                <span>Celular</span>
                <strong>{{ acreditadoSeleccionado.celular || 'Sin datos' }}</strong>
              </div>
              <div class="conflicto-acreditado-linea">
                <span>Correo electrónico</span>
                <strong>{{ acreditadoSeleccionado.email || 'Sin datos' }}</strong>
              </div>
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="cerrarDetalle"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogHistorial"
      max-width="1100"
      width="95vw"
      scrollable
    >
      <VCard class="acreditacion-dialog-card historial-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon icon="tabler-clipboard-list" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
              Historial de credencial
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              Creación y cambios de estado registrados
            </div>
          </div>
          <VSpacer /><VBtn
            icon
            variant="tonal"
            size="small"
            @click="cerrarHistorial"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorHistorial"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorHistorial }}
          </VAlert>
          <div
            v-if="cargandoHistorial"
            class="d-flex justify-center py-8"
          >
            <VProgressCircular
              indeterminate
              color="primary"
            />
          </div>
          <VAlert
            v-else-if="!historialEventos.length"
            type="info"
            variant="tonal"
          >
            No hay movimientos registrados para esta credencial.
          </VAlert>
          <template v-else>
            <section
              v-for="(evento, indiceEvento) in historialEventos"
              :key="evento.id_feria"
              class="historial-evento-bloque"
            >
              <div
                v-if="indiceEvento"
                class="historial-evento-divider"
              >
                <VDivider />
              </div>
              <div class="historial-evento-titulo">
                <VIcon
                  icon="tabler-calendar-event"
                  size="20"
                  color="primary"
                />
                <div>
                  <div class="text-caption text-medium-emphasis">Evento / feria</div>
                  <div class="text-h6 font-weight-bold">{{ evento.nombre }}</div>
                </div>
              </div>
              <VTable
                density="comfortable"
                class="historial-acciones-table border rounded-lg"
              >
                <thead>
                  <tr class="bg-primary-container">
                    <th class="pa-3">Acción</th>
                    <th class="pa-3">Tipo</th>
                    <th class="pa-3">Estado anterior</th>
                    <th class="pa-3">Estado nuevo</th>
                    <th class="pa-3">Motivo</th>
                    <th class="pa-3">Usuario</th>
                    <th class="pa-3">Fecha</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="registro in evento.acciones"
                    :key="registro.id"
                    class="border-b"
                  >
                    <td class="pa-3"><VChip size="small" variant="tonal" :color="registro.es_descarga ? 'info' : 'primary'">{{ registro.accion_label }}</VChip></td>
                    <td class="pa-3"><span v-if="registro.tipo_anterior || registro.tipo_nuevo">{{ registro.tipo_anterior || '—' }} → {{ registro.tipo_nuevo || '—' }}</span><span v-else>—</span><div v-if="registro.version_qr_anterior || registro.version_qr_nueva" class="text-caption text-medium-emphasis">QR v{{ registro.version_qr_anterior || '—' }} → v{{ registro.version_qr_nueva || '—' }}</div></td>
                    <td class="pa-3">{{ registro.estado_anterior }}</td>
                    <td class="pa-3"><VChip v-if="!registro.es_descarga" size="small" variant="tonal" :color="estadoColor(registro.estado_nuevo)">{{ registro.estado_nuevo }}</VChip><span v-else>—</span></td>
                    <td class="pa-3 historial-motivo">{{ registro.motivo }}</td>
                    <td class="pa-3">{{ registro.usuario }}</td>
                    <td class="pa-3 text-no-wrap">{{ formatoFechaHistorial(registro.fecha) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </section>
          </template>
        </VCardText>
        <VDivider /><VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="cerrarHistorial"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogInhabilitar"
      max-width="560"
      persistent
    >
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              icon="tabler-user-off"
              size="24"
            />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
              Inhabilitar credencial
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              El código QR se conservará, pero no podrá utilizarse.
            </div>
          </div>
          <VSpacer />
          <VBtn
            icon
            variant="tonal"
            size="small"
            @click="dialogInhabilitar = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorOperacion"
            type="error"
            density="compact"
            variant="tonal"
            class="mb-4"
          >
            {{ errorOperacion }}
          </VAlert>
          <VAlert
            type="info"
            density="compact"
            variant="tonal"
            class="mb-4"
          >
            El código QR se conservará, pero será rechazado mientras la credencial esté inhabilitada.
          </VAlert>
          <VTextField
            :model-value="acreditadoSeleccionado?.nombre"
            label="Acreditado"
            readonly
            variant="outlined"
            class="mb-4"
          />
          <VSelect
            v-model="motivoOperacion"
            label="Motivo *"
            :items="['Mal uso', 'Falta de pago']"
            variant="outlined"
            class="mb-4"
          />
          <VTextarea
            v-model="observacionesOperacion"
            label="Observaciones"
            rows="3"
            auto-grow
            maxlength="2000"
            counter="2000"
            variant="outlined"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogInhabilitar = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            :loading="guardandoOperacion"
            :disabled="!motivoOperacion"
            @click="confirmarInhabilitacion"
          >
            Inhabilitar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogModalidad"
      max-width="560"
      persistent
    >
      <VCard
        v-if="acreditadoSeleccionado"
        class="acreditacion-dialog-card"
      >
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              icon="tabler-repeat"
              size="24"
            />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
            Cambiar tipo de credencial
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              El cambio renovará el código QR y quedará registrado.
            </div>
          </div>
          <VSpacer /><VBtn
            icon
            variant="tonal"
            size="small"
            @click="dialogModalidad = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider /><VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorOperacion"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorOperacion }}
          </VAlert>
          <VTextField
            :model-value="acreditadoSeleccionado.nombre"
            label="Acreditado"
            readonly
            variant="outlined"
            class="mb-4"
          />
          <VTextField
            :model-value="acreditadoSeleccionado.modalidad_credencial"
            label="Tipo actual"
            variant="outlined"
            readonly
            class="mb-4"
          />
          <VSelect
            v-model="modalidadNueva"
            label="Nuevo tipo"
            :items="[{ title: 'Física', value: 0 }, { title: 'Digital', value: 1 }]"
            variant="outlined"
            class="mb-4"
          />
          <VTextarea
            v-model="observacionesOperacion"
            label="Observaciones"
            rows="3"
            auto-grow
            variant="outlined"
          />
        </VCardText><VDivider /><VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogModalidad = false"
          >
            Cancelar
          </VBtn><VBtn
            color="primary"
            :loading="guardandoOperacion"
            @click="guardarModalidad"
          >
            Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogQr"
      max-width="560"
      persistent
    >
      <VCard
        v-if="acreditadoSeleccionado"
        class="acreditacion-dialog-card"
      >
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              icon="tabler-refresh"
              size="24"
            />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
            Generar nuevo código QR
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              El QR anterior quedará revocado permanentemente.
            </div>
          </div>
          <VSpacer /><VBtn
            icon
            variant="tonal"
            size="small"
            @click="dialogQr = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider /><VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorOperacion"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorOperacion }}
          </VAlert>
          <VTextField
            :model-value="acreditadoSeleccionado.nombre"
            label="Acreditado"
            readonly
            variant="outlined"
            class="mb-4"
          />
          <VAlert
            type="warning"
            density="compact"
            variant="tonal"
            class="mb-4"
          >
            Use esta acción si sospecha que el QR fue compartido o comprometido.
          </VAlert>
          <VTextarea
            v-model="observacionesOperacion"
            label="Observaciones"
            rows="3"
            auto-grow
            variant="outlined"
          />
        </VCardText><VDivider /><VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogQr = false"
          >
            Cancelar
          </VBtn><VBtn
            color="success"
            :loading="guardandoOperacion"
            @click="guardarQr"
          >
            Generar nuevo QR
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogEstado"
      max-width="560"
      persistent
    >
      <VCard
        v-if="acreditadoSeleccionado"
        class="acreditacion-dialog-card"
      >
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              :icon="accionEstado === 'eliminar' ? 'tabler-trash-x' : (estadoNuevo === 2 ? 'tabler-alert-triangle' : (estadoNuevo === 1 ? 'tabler-check' : 'tabler-ban'))"
              size="24"
            />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
            {{ accionEstado === 'eliminar' ? 'Eliminar registro' : accionEstado === 'inhabilitar' ? 'Inhabilitar credencial' : estadoNuevo === 2 ? 'Anular credencial' : 'Habilitar credencial' }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">
              Esta acción quedará registrada en el historial
            </div>
          </div>
          <VSpacer /><VBtn
            icon
            variant="tonal"
            size="small"
            @click="dialogEstado = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider /><VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorOperacion"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorOperacion }}
          </VAlert>
          <p class="text-body-1 mb-4">
            ¿Desea {{ accionEstado === 'eliminar' ? 'eliminar' : accionEstado === 'inhabilitar' ? 'inhabilitar' : estadoNuevo === 2 ? 'anular' : 'habilitar' }} este registro?
          </p>
          <VTextField
            :model-value="acreditadoSeleccionado.nombre"
            label="Acreditado"
            readonly
            variant="outlined"
            class="mb-4"
          />
          <VAlert
            v-if="estadoNuevo === 2"
            type="warning"
            density="compact"
            variant="tonal"
            class="mb-4"
          >
            El QR actual quedará revocado definitivamente. Al reactivar esta credencial se generará un QR nuevo.
          </VAlert>
          <VSelect
            v-if="estadoNuevo === 2"
            v-model="motivoEstado"
            label="Motivo de anulación *"
            :items="['Robo', 'Extravío', 'Daño irreversible', 'Error administrativo']"
            variant="outlined"
            class="mb-4"
          />
          <VTextarea
            v-else
            v-model="motivoEstado"
            label="Motivo *"
            rows="4"
            auto-grow
            maxlength="2000"
            variant="outlined"
          />
        </VCardText><VDivider /><VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogEstado = false"
          >
            Cancelar
          </VBtn><VBtn
            color="error"
            :loading="guardandoOperacion"
            :disabled="!motivoEstado.trim()"
            @click="guardarEstado"
          >
            Confirmar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialogEditar"
      max-width="640"
      persistent
    >
      <VCard
        v-if="editando"
        class="acreditacion-dialog-card acreditacion-dialog-tall"
      >
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon
              icon="tabler-pencil"
              size="24"
            />
          </div><div class="text-h5 font-weight-bold">
            <div>Editar acreditado</div>
            <div class="text-caption text-medium-emphasis mt-1">
              Actualice los datos personales del acreditado
            </div>
          </div><VSpacer /><VBtn
            icon
            variant="tonal"
            size="small"
            @click="dialogEditar = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider /><VCardText class="acreditacion-dialog-body pa-5">
          <VAlert
            v-if="errorEdicion"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorEdicion }}
          </VAlert>
          <div class="acreditacion-section-title mb-3">
            <VIcon
              icon="tabler-id-badge-2"
              size="18"
              class="me-2"
            />
            Datos del acreditado
          </div>
          <VRow>
            <VCol cols="12">
              <VRow dense>
                <VCol cols="12" md="6">
                  <VTextField v-model="editando.ci" label="Carnet de identidad *" variant="outlined" density="comfortable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VSelect v-model="editando.tipo_documento" label="Documento *" :items="['Cédula de identidad', 'Pasaporte', 'Documento extranjero']" variant="outlined" density="comfortable" />
                </VCol>
                <VCol cols="12">
                  <VTextField v-model="editando.nombre" label="Nombre completo *" variant="outlined" density="comfortable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VSelect v-model="editCodigoPais" label="Código de país" :items="codigosPais" variant="outlined" density="comfortable" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="editNumeroCelular" label="Número de celular (opcional)" variant="outlined" density="comfortable" inputmode="numeric" maxlength="14" @update:model-value="editNumeroCelular = String($event).replace(/\D/g, '')" />
                </VCol>
                <VCol cols="12" md="6">
                  <VTextField v-model="editando.email" label="Correo electrónico (opcional)" type="email" variant="outlined" density="comfortable" />
                </VCol>
              </VRow>
            </VCol>
            <VCol cols="12" class="d-flex align-center justify-center gap-4 pt-4">
              <input ref="inputFoto" class="d-none" type="file" accept="image/png,image/jpeg" @change="seleccionarFoto">
              <button type="button" class="acreditacion-photo-picker" @click="abrirSelectorFoto">
                <VImg v-if="editFotoPreview" :src="editFotoPreview" cover class="acreditacion-photo-preview" />
                <VIcon v-else icon="tabler-camera" size="40" />
              </button>
              <div>
                <p class="text-body-1 font-weight-medium mb-1">Fotografía *</p>
                <p class="text-body-2 text-medium-emphasis mb-2">JPG o PNG, máximo 2 MB. Las fotos mayores se reducen automáticamente.</p>
                <VBtn size="small" variant="tonal" color="primary" prepend-icon="tabler-upload" @click="abrirSelectorFoto">Cargar fotografía</VBtn>
              </div>
            </VCol>
          </VRow>
        </VCardText><VDivider /><VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn
            color="secondary"
            variant="tonal"
            @click="dialogEditar = false"
          >
            Cancelar
          </VBtn><VBtn
            color="primary"
            :loading="guardandoEdicion"
            @click="guardarEdicion"
          >
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VCard v-if="!eventoSeleccionado">
      <VCardText class="text-center py-12">
        <VIcon
          icon="tabler-id-badge-2"
          size="64"
          color="disabled"
          class="mb-4"
        />
        <h5 class="text-h5 mb-2">
          Selecciona una Feria
        </h5>
        <p class="text-body-1 text-medium-emphasis">
          Elige un evento/feria del selector superior para visualizar sus acreditados
        </p>
      </VCardText>
    </VCard>

    <VSnackbar
      v-model="snackbarAccion.show"
      :color="snackbarAccion.color"
      location="top end"
    >
      {{ snackbarAccion.text }}
      <template #actions>
        <VBtn
          variant="text"
          @click="snackbarAccion.show = false"
        >
          Cerrar
        </VBtn>
      </template>
    </VSnackbar>
  </div>
</template>

<style scoped>
.acreditados-tipo-estadistica {
  border-block-end: 2px solid;
  transition: box-shadow 0.1s ease-out, border-block-end-width 0.1s ease-out;
}

.acreditados-tipo-estadistica:hover {
  border-block-end-width: 3px;
  box-shadow: 0 5px 14px rgba(var(--v-theme-on-surface), 0.12);
}

.ver-acreditado-contenido {
  inline-size: min(100%, 560px);
  margin-inline: auto;
}

.ver-acreditado-datos-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 22px 36px;
  padding-block-start: 8px;
}

.ver-acreditado-dato-principal {
  grid-column: 1 / -1;
}

.conflicto-acreditado-linea {
  text-align: center;
}

.conflicto-acreditado-linea span {
  display: block;
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}

.conflicto-acreditado-linea strong {
  display: block;
  margin-block-start: 4px;
  color: rgba(var(--v-theme-on-surface), 0.88);
  font-size: 1.05rem;
}

.acreditacion-dialog-card {
  overflow: hidden;
  border-radius: 16px;
}

.acreditacion-dialog-tall {
  display: flex;
  flex-direction: column;
  max-height: 88vh;
}

.acreditacion-photo-picker {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 140px;
  height: 140px;
  padding: 0;
  overflow: hidden;
  border: 2px dashed rgba(var(--v-theme-on-surface), 0.48);
  border-radius: 50%;
  background: rgba(var(--v-theme-on-surface), 0.02);
  color: rgba(var(--v-theme-on-surface), 0.7);
  cursor: pointer;
}

.acreditacion-photo-preview {
  width: 100%;
  height: 100%;
}

.lote-action-button-active {
  position: relative;
  box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.18);
  transform: translateY(-1px);
}

.lote-action-count {
  position: absolute;
  inset-block-start: -6px;
  inset-inline-end: -6px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-inline-size: 18px;
  block-size: 18px;
  padding-inline: 4px;
  border: 2px solid rgb(var(--v-theme-surface));
  border-radius: 999px;
  background: rgb(var(--v-theme-error));
  color: rgb(var(--v-theme-on-error));
  font-size: 0.68rem;
  font-weight: 700;
  line-height: 1;
}

.acreditacion-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.acreditacion-dialog-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.acreditacion-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
  flex: 1 1 auto;
  overflow-y: auto;
}

.acreditacion-section-title {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.acreditacion-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}

.historial-dialog-card {
  max-block-size: 88vh;
}

.historial-evento-bloque + .historial-evento-bloque {
  margin-block-start: 24px;
}

.historial-evento-divider {
  margin-block: 24px;
}

.historial-evento-titulo {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 16px;
  margin-block-end: 12px;
  border-inline-start: 4px solid rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.05);
}

.historial-acciones-table th,
.historial-acciones-table td {
  white-space: nowrap;
}

.historial-motivo {
  min-inline-size: 180px;
  max-inline-size: 260px;
  white-space: normal !important;
  overflow-wrap: anywhere;
}

@media (max-width: 600px) {
  .ver-acreditado-datos-grid {
    grid-template-columns: 1fr;
    gap: 18px;
  }

  .ver-acreditado-dato-principal {
    grid-column: auto;
  }
}
</style>
