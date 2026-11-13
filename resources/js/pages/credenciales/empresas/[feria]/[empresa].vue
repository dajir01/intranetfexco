<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCan } from '@/composables/useCan'
import { useSafePagination } from '@/composables/useSafePagination'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Detalle de acreditación',
    ability: 'credenciales.empresas.ver',
  },
})

const route = useRoute()
const router = useRouter()
const can = useCan()
const feriaId = computed(() => Number(route.params.feria))
const empresaId = computed(() => Number(route.params.empresa))

const detalle = ref(null)
const loading = ref(false)
const error = ref('')
const empresaPassword = ref('')
const empresaPasswordMessage = ref('')
const showingEmpresaPassword = ref(false)
const loadingEmpresaPassword = ref(false)
const resumenSalidas = ref({})
const dialogAcreditar = ref(false)
const registroAcreditar = ref(null)
const tiposExpandidos = ref([])
const acreditadoSeleccionado = ref(null)
const dialogVerAcreditado = ref(false)
const expositorConflicto = ref(null)
const dialogConflictoCredencial = ref(false)
const dialogCambiarEstado = ref(false)
const credencialEstadoSeleccionada = ref(null)
const motivoEstado = ref('')
const guardandoEstado = ref(false)
const errorEstado = ref('')
const dialogModalidad = ref(false)
const dialogInhabilitar = ref(false)
const dialogHabilitar = ref(false)
const dialogReemplazar = ref(false)
const credencialesOperacion = ref([])
const modalidadNueva = ref(1)
const motivoOperacion = ref('')
const observacionesOperacion = ref('')
const guardandoOperacion = ref(false)
const errorOperacion = ref('')
const dialogAdicionales = ref(false)
const adicionalesRegistro = ref(null)
const modoAdicionales = ref('adicion')
const cantidadAdicionales = ref(null)
const motivoAdicionales = ref('')
const guardandoAdicionales = ref(false)
const errorAdicionales = ref('')
const avisoCantidadAdicionales = ref('')
const dialogHistorialAdicionales = ref(false)
const historialAdicionales = ref([])
const historialAdicionalesRegistro = ref(null)
const cargandoHistorialAdicionales = ref(false)
const errorHistorialAdicionales = ref('')
const dialogHistorial = ref(false)
const historialEventos = ref([])
const historialAcreditadoActual = ref(null)
const historialSoloEventoActual = ref(false)
const cargandoHistorial = ref(false)
const errorHistorial = ref('')
const historialAcreditadosEmpresa = ref([])
const cargandoHistorialEmpresa = ref(false)
const buscarHistorialAcreditados = ref('')
const paginaHistorial = ref(1)
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const itemsPorPaginaHistorial = ref(defaultItemsPerPage)
const buscarAcreditadosPorTipo = ref({})
const paginaAcreditadosEvento = ref({})
const itemsPorPaginaAcreditados = ref(defaultItemsPerPage)
const modoEdicionAcreditado = ref(false)
const idCredencialEditando = ref(null)
const camposAcreditadoHabilitados = ref(false)
const buscandoExpositor = ref(false)
const resultadoBusquedaCi = ref(null)
const credencialesEvento = ref([])
const codigosPais = [
  { title: 'Bolivia (+591)', value: '591' },
  { title: 'Argentina (+54)', value: '54' },
  { title: 'Brasil (+55)', value: '55' },
  { title: 'Chile (+56)', value: '56' },
  { title: 'Colombia (+57)', value: '57' },
  { title: 'Perú (+51)', value: '51' },
  { title: 'México (+52)', value: '52' },
  { title: 'España (+34)', value: '34' },
  { title: 'Estados Unidos (+1)', value: '1' },
]
const formularioAcreditado = ref({
  id_expositor: null,
  ci: '',
  tipo_documento: '',
  nombre: '',
  email: '',
  codigoPais: '591',
  numeroCelular: '',
  celular: '',
  foto: null,
})
const vistaPreviaFoto = ref('')
const inputFoto = ref(null)
const procesandoFoto = ref(false)
const errorFoto = ref('')
const guardandoAcreditacion = ref(false)
const errorGuardarAcreditacion = ref('')
let busquedaCiTimer = null
let busquedaCiAbortController = null
let actualizacionTimer = null

const cargarDetalle = async (mostrarCarga = true) => {
  if (!can('credenciales.empresas.ver')) {
    router.replace('/')
    return
  }

  if (mostrarCarga) loading.value = true
  if (mostrarCarga) error.value = ''

  try {
    const response = await fetch(`/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json().catch(() => null)

    if (!response.ok) throw new Error(payload?.message || 'No se pudo cargar el detalle de acreditación.')
    detalle.value = payload?.data || null
    await cargarResumenSalidas()
    if (mostrarCarga) tiposExpandidos.value = tiposCredencial.value.map(tipo => tipo.clave)
  } catch (err) {
    if (mostrarCarga) error.value = err.message || 'No se pudo cargar el detalle de acreditación.'
  } finally {
    if (mostrarCarga) loading.value = false
  }
}

const cargarResumenSalidas = async () => {
  const ids = [
    ...(detalle.value?.contratos || []),
    ...(detalle.value?.credenciales_directas || []),
  ].flatMap(registro => (registro.acreditados || []).map(acreditado => acreditado.id_credencial))
  if (!ids.length) return

  const response = await fetch('/credenciales/acreditados/resumen-salidas', {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    },
    credentials: 'same-origin',
    body: JSON.stringify({ credenciales: ids }),
  })
  const payload = await response.json().catch(() => ({}))
  if (response.ok) resumenSalidas.value = payload.data || {}
}

const toggleEmpresaPassword = async () => {
  if (showingEmpresaPassword.value) {
    empresaPassword.value = ''
    empresaPasswordMessage.value = ''
    showingEmpresaPassword.value = false

    return
  }

  loadingEmpresaPassword.value = true
  empresaPasswordMessage.value = ''
  try {
    const response = await fetch(`/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}/credencial-acceso`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok)
      throw new Error(payload.message || 'No se pudo consultar la contraseña de la empresa.')

    if (!payload.data?.available) {
      empresaPasswordMessage.value = payload.data?.message || 'No se puede mostrar la contraseña.'

      return
    }

    empresaPassword.value = payload.data.password
    showingEmpresaPassword.value = true
  }
  catch (exception) {
    empresaPasswordMessage.value = exception.message || 'No se pudo consultar la contraseña de la empresa.'
  }
  finally {
    loadingEmpresaPassword.value = false
  }
}

const sincronizarSalidas = async (intentos = 1, espera = 0) => {
  try {
    for (let intento = 0; intento < intentos; intento++) {
      if (espera) await new Promise(resolve => setTimeout(resolve, espera))
      await cargarResumenSalidas()

      if (dialogHistorial.value && historialAcreditadoActual.value) {
        await abrirHistorial(historialAcreditadoActual.value, historialSoloEventoActual.value)
      }
    }
  } catch {}
}

const cargarHistorialAcreditadosEmpresa = async () => {
  cargandoHistorialEmpresa.value = true
  try {
    const response = await fetch(`/credenciales/empresas/${empresaId.value}/historial-acreditados`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial de acreditados.')
    historialAcreditadosEmpresa.value = payload.data || []
  } catch (err) {
    error.value = err.message || 'No se pudo cargar el historial de acreditados.'
  } finally {
    cargandoHistorialEmpresa.value = false
  }
}

const historialAcreditadosFiltrados = computed(() => {
  const termino = buscarHistorialAcreditados.value.trim().toLowerCase()
  if (!termino) return historialAcreditadosEmpresa.value

  return historialAcreditadosEmpresa.value.filter(acreditado => [
    acreditado.nombre,
    acreditado.ci,
  ].some(valor => String(valor || '').toLowerCase().includes(termino)))
})

const historialAcreditadosPaginados = computed(() => {
  const inicio = (paginaHistorial.value - 1) * itemsPorPaginaHistorial.value

  return historialAcreditadosFiltrados.value.slice(inicio, inicio + itemsPorPaginaHistorial.value)
})

const paginasHistorial = computed(() => Math.max(1, Math.ceil(historialAcreditadosFiltrados.value.length / itemsPorPaginaHistorial.value)))

watch([buscarHistorialAcreditados, itemsPorPaginaHistorial], () => {
  paginaHistorial.value = 1
})

const acreditadosFiltradosPorTipo = tipo => {
  const termino = String(buscarAcreditadosPorTipo.value[tipo.clave] || '').trim().toLowerCase()
  const acreditados = tipo.registros.flatMap(registro => registro.acreditados || [])

  if (!termino) return acreditados

  return acreditados.filter(acreditado => [acreditado.nombre, acreditado.ci]
    .some(valor => String(valor || '').toLowerCase().includes(termino)))
}

const actualizarBusquedaTipo = (tipo, valor) => {
  buscarAcreditadosPorTipo.value[tipo] = valor
  paginaAcreditadosEvento.value[tipo] = 1
}

const acreditadosPaginadosPorTipo = tipo => {
  const pagina = paginaAcreditadosEvento.value[tipo.clave] || 1
  const inicio = (pagina - 1) * itemsPorPaginaAcreditados.value

  return acreditadosFiltradosPorTipo(tipo).slice(inicio, inicio + itemsPorPaginaAcreditados.value)
}

const paginasAcreditadosPorTipo = tipo => Math.max(1, Math.ceil(acreditadosFiltradosPorTipo(tipo).length / itemsPorPaginaAcreditados.value))

watch(itemsPorPaginaAcreditados, () => {
  paginaAcreditadosEvento.value = {}
})

const credencialesSeleccionadas = ref([])

const estaTodoSeleccionado = tipo => {
  const lista = acreditadosFiltradosPorTipo(tipo)
  if (!lista.length) return false
  return lista.every(a => credencialesSeleccionadas.value.includes(a.id_credencial))
}

const estaParcialSeleccionado = tipo => {
  const lista = acreditadosFiltradosPorTipo(tipo)
  if (!lista.length) return false
  const seleccionados = lista.filter(a => credencialesSeleccionadas.value.includes(a.id_credencial))
  return seleccionados.length > 0 && seleccionados.length < lista.length
}

const modalidadSeleccionadaTipo = tipo => seleccionadosPorTipo(tipo)[0]?.modalidad_credencial || null

const puedeSeleccionarAcreditado = (tipo, acreditado) => {
  const modalidadActual = modalidadSeleccionadaTipo(tipo)
  return !modalidadActual || modalidadActual === acreditado.modalidad_credencial
}

const toggleSeleccionAcreditado = (tipo, acreditado, val) => {
  if (val) {
    if (!puedeSeleccionarAcreditado(tipo, acreditado)) {
      snackbarAccion.value = { show: true, color: 'warning', text: 'No se pueden seleccionar credenciales Física y Digital al mismo tiempo. Deben ser del mismo tipo.' }
      return
    }
    credencialesSeleccionadas.value = Array.from(new Set([...credencialesSeleccionadas.value, acreditado.id_credencial]))
  } else {
    credencialesSeleccionadas.value = credencialesSeleccionadas.value.filter(id => id !== acreditado.id_credencial)
  }
}

const toggleSeleccionarTodo = (tipo, val) => {
  const lista = acreditadosFiltradosPorTipo(tipo)
  if (val) {
    const modalidadObjetivo = modalidadSeleccionadaTipo(tipo) || lista[0]?.modalidad_credencial
    const ids = lista.filter(a => a.modalidad_credencial === modalidadObjetivo).map(a => a.id_credencial)
    credencialesSeleccionadas.value = Array.from(new Set([...credencialesSeleccionadas.value, ...ids]))
  } else {
    const ids = lista.map(a => a.id_credencial)
    credencialesSeleccionadas.value = credencialesSeleccionadas.value.filter(id => !ids.includes(id))
  }
}

const seleccionadosPorTipo = tipo => {
  const lista = acreditadosFiltradosPorTipo(tipo)
  return lista.filter(a => credencialesSeleccionadas.value.includes(a.id_credencial))
}

const todosSeleccionadosTienenEstado = (tipo, estado) => {
  const seleccionados = seleccionadosPorTipo(tipo)
  return seleccionados.length > 0 && seleccionados.every(acreditado => acreditado.estado_credencial_codigo === estado)
}

const esDigitalTipo = tipo => {
  const seleccionados = seleccionadosPorTipo(tipo)
  if (seleccionados.length) {
    const modalidad = seleccionados[0]?.modalidad_credencial
    if (modalidad) return modalidad === 'Digital'
  }
  const reg = registroUnicoTipo(tipo)
  if (reg?.modalidad_credencial) return reg.modalidad_credencial === 'Digital'
  return false
}

const snackbarAccion = ref({ show: false, color: 'success', text: '' })

const enviarSeleccionados = async tipo => {
  const seleccionados = seleccionadosPorTipo(tipo)
  if (!seleccionados.length) return

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
      body: JSON.stringify({ credenciales: seleccionados.map(acreditado => acreditado.id_credencial) }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudieron enviar las credenciales digitales.')

    credencialesSeleccionadas.value = []
    snackbarAccion.value = { show: true, color: 'success', text: payload.message }
    sincronizarSalidas(8, 1000)
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudieron enviar las credenciales digitales.' }
  }
}

const descargarSeleccionados = async tipo => {
  const seleccionados = seleccionadosPorTipo(tipo)
  if (!seleccionados.length) return

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
        credenciales: seleccionados.map(acreditado => acreditado.id_credencial),
        archivo: true,
      }),
    })
    if (!response.ok) {
      const payload = await response.json().catch(() => ({}))
      throw new Error(payload.message || 'No se pudieron generar las credenciales digitales.')
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
    enlace.download = 'credenciales-digitales.zip'
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    setTimeout(() => URL.revokeObjectURL(urlBlob), 1000)
    await sincronizarSalidas()
    snackbarAccion.value = { show: true, color: 'success', text: 'Archivo ZIP de credenciales digitales descargado correctamente.' }
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudieron descargar las credenciales.' }
  }
}

const descargarCredencialIndividual = async acreditado => {
  try {
    const response = await fetch(`/credenciales/acreditados/${acreditado.id_credencial}/descargar-digital`, {
      headers: { Accept: 'image/png', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const contentType = response.headers.get('content-type') || ''
    if (!response.ok || response.redirected || !contentType.includes('image/png')) {
      throw new Error('La sesión expiró o el servidor no devolvió una imagen PNG válida.')
    }

    const bytesRespuesta = new Uint8Array(await response.arrayBuffer())
    const firmaPng = [137, 80, 78, 71, 13, 10, 26, 10]
    let inicioPng = -1
    for (let indice = 0; indice <= bytesRespuesta.length - firmaPng.length; indice++) {
      if (firmaPng.every((byte, offset) => bytesRespuesta[indice + offset] === byte)) {
        inicioPng = indice
        break
      }
    }

    if (inicioPng < 0) throw new Error('El servidor no devolvió una imagen PNG válida.')

    const archivo = new Blob([bytesRespuesta.slice(inicioPng)], { type: 'image/png' })
    const urlBlob = URL.createObjectURL(archivo)
    const enlace = document.createElement('a')
    enlace.href = urlBlob
    enlace.download = `credencial-${acreditado.nombre || acreditado.id_credencial}.png`
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    setTimeout(() => URL.revokeObjectURL(urlBlob), 1000)
    sincronizarSalidas(2, 700)
  } catch (err) {
    snackbarAccion.value = { show: true, color: 'error', text: err.message || 'No se pudo descargar la credencial digital.' }
  }
}

const abrirCambioModalidad = acreditado => {
  credencialesOperacion.value = [acreditado]
  modalidadNueva.value = acreditado.modalidad_credencial === 'Digital' ? 0 : 1
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogModalidad.value = true
}

const abrirInhabilitar = acreditados => {
  credencialesOperacion.value = acreditados
  motivoOperacion.value = ''
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogInhabilitar.value = true
}

const abrirHabilitar = acreditados => {
  credencialesOperacion.value = acreditados
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogHabilitar.value = true
}

const abrirReemplazo = acreditado => {
  credencialesOperacion.value = [acreditado]
  observacionesOperacion.value = ''
  errorOperacion.value = ''
  dialogReemplazar.value = true
}

const ejecutarOperacion = async (url, body, cerrar, method = 'POST') => {
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
    cerrar()
    credencialesSeleccionadas.value = []
    await cargarDetalle()
    snackbarAccion.value = { show: true, color: 'success', text: payload.message }
  } catch (err) {
    errorOperacion.value = err.message || 'No se pudo completar la operación.'
  } finally {
    guardandoOperacion.value = false
  }
}

const guardarCambioModalidad = () => ejecutarOperacion(
  `/credenciales/acreditados/${credencialesOperacion.value[0].id_credencial}/modalidad`,
  { modalidad: modalidadNueva.value, observaciones: observacionesOperacion.value },
  () => { dialogModalidad.value = false },
  'PATCH',
)

const confirmarInhabilitacion = () => ejecutarOperacion(
  '/credenciales/acreditados/inhabilitar',
  { credenciales: credencialesOperacion.value.map(item => item.id_credencial), motivo: motivoOperacion.value, observaciones: observacionesOperacion.value },
  () => { dialogInhabilitar.value = false },
)

const confirmarHabilitacion = () => ejecutarOperacion(
  '/credenciales/acreditados/habilitar',
  { credenciales: credencialesOperacion.value.map(item => item.id_credencial), observaciones: observacionesOperacion.value },
  () => { dialogHabilitar.value = false },
)

const confirmarReemplazo = () => ejecutarOperacion(
  `/credenciales/acreditados/${credencialesOperacion.value[0].id_credencial}/renovar-qr`,
  { observaciones: observacionesOperacion.value },
  () => { dialogReemplazar.value = false },
)

const imprimirCredencialIndividual = acreditado => {
  window.open(`/credenciales/acreditados/${acreditado.id_credencial}/imprimir-fisica`, '_blank', 'noopener')
  sincronizarSalidas(2, 700)
}

const imprimirSeleccionados = tipo => {
  const seleccionados = seleccionadosPorTipo(tipo)
  if (!seleccionados.length) return

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
  seleccionados.forEach(acreditado => {
    const campo = document.createElement('input')
    campo.type = 'hidden'
    campo.name = 'credenciales[]'
    campo.value = acreditado.id_credencial
    formulario.appendChild(campo)
  })
  document.body.appendChild(formulario)
  formulario.submit()
  formulario.remove()
  sincronizarSalidas(2, 700)
}

const resumenSalida = acreditado => resumenSalidas.value[acreditado.id_credencial]

const credenciales = computed(() => detalle.value?.credenciales || { asignadas: 0, utilizadas: 0, disponibles: 0 })
const disponibilidadColor = computed(() => Number(credenciales.value.disponibles) > 0 ? 'success' : 'error')
const tiposCredencial = computed(() => {
  const registros = [
    ...(detalle.value?.contratos || []),
    ...(detalle.value?.credenciales_directas || []),
  ]

  return registros.map((registro, indice) => ({
    clave: `${registro.id_contrato || 'directa'}-${registro.id_vinculacion || indice}`,
    tipo: registro.tipo_credencial || 'Sin tipo definido',
    registros: [registro],
    credenciales_asignadas: Number(registro.credenciales_asignadas || 0),
    credenciales_utilizadas: Number(registro.credenciales_utilizadas || 0),
    credenciales_disponibles: Number(registro.credenciales_disponibles || 0),
  }))
})

const estadoContrato = estado => ({
  2: 'Reservado',
  3: 'Confirmado',
})[Number(estado)] || 'Sin estado definido'
const estadoCredencialColor = estado => ({
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

const modalidadCredencialColor = modalidad => modalidad === 'Digital' ? 'info' : 'secondary'

const tipoExpandido = tipo => tiposExpandidos.value.includes(tipo)
const registroUnicoTipo = tipo => tipo?.registros?.length === 1 ? tipo.registros[0] ?? null : null
const puedeGuardarAcreditacion = computed(() => {
  const celularCorrecto = !numeroCelularNormalizado.value || celularValido.value
  if (credencialesEvento.value.length) return false

  if (modoEdicionAcreditado.value) {
    return Boolean(
      !procesandoFoto.value
      && formularioAcreditado.value.ci.trim()
      && formularioAcreditado.value.tipo_documento
      && formularioAcreditado.value.nombre.trim()
      && celularCorrecto
    )
  }

  if (buscandoExpositor.value || procesandoFoto.value || !resultadoBusquedaCi.value || resultadoBusquedaCi.value.tipo === 'error') return false
  if (!formularioAcreditado.value.ci.trim()) return false
  if (formularioAcreditado.value.id_expositor) {
    return Boolean(
      formularioAcreditado.value.tipo_documento
      && formularioAcreditado.value.nombre.trim()
      && celularCorrecto
    )
  }

  return Boolean(
    formularioAcreditado.value.tipo_documento
    && formularioAcreditado.value.nombre.trim()
    && formularioAcreditado.value.foto
    && celularCorrecto
  )
})
const alternarTipo = tipo => {
  const index = tiposExpandidos.value.indexOf(tipo)

  if (index === -1) tiposExpandidos.value.push(tipo)
  else tiposExpandidos.value.splice(index, 1)
}

const abrirAcreditacion = registro => {
  if (Number(registro.credenciales_disponibles) === 0) return

  registroAcreditar.value = registro
  modoEdicionAcreditado.value = false
  idCredencialEditando.value = null
  formularioAcreditado.value = {
    id_expositor: null, ci: '', tipo_documento: '', nombre: '', email: '', codigoPais: '591', numeroCelular: '', celular: '', foto: null,
  }
  camposAcreditadoHabilitados.value = false
  buscandoExpositor.value = false
  resultadoBusquedaCi.value = null
  credencialesEvento.value = []
  expositorConflicto.value = null
  dialogConflictoCredencial.value = false
  vistaPreviaFoto.value = ''
  errorFoto.value = ''
  errorGuardarAcreditacion.value = ''
  dialogAcreditar.value = true
}

const abrirEditarAcreditado = acreditado => {
  if (vistaPreviaFoto.value.startsWith('blob:')) URL.revokeObjectURL(vistaPreviaFoto.value)

  modoEdicionAcreditado.value = true
  idCredencialEditando.value = acreditado.id_credencial
  formularioAcreditado.value = {
    id_expositor: acreditado.id_expositor,
    ci: acreditado.ci || '',
    tipo_documento: acreditado.tipo_documento || '',
    nombre: acreditado.nombre || '',
    email: acreditado.email || '',
    ...separarCelular(acreditado.celular),
    celular: acreditado.celular || '',
    foto: null,
  }
  camposAcreditadoHabilitados.value = true
  buscandoExpositor.value = false
  resultadoBusquedaCi.value = null
  vistaPreviaFoto.value = urlFoto(acreditado.foto)
  errorFoto.value = ''
  errorGuardarAcreditacion.value = ''
  acreditadoSeleccionado.value = null
  dialogAcreditar.value = true
}

const separarCelular = celular => {
  const digitos = String(celular || '').replace(/\D/g, '')
  if (digitos.length === 8) return { codigoPais: '591', numeroCelular: digitos }

  const codigo = codigosPais
    .map(opcion => opcion.value)
    .sort((a, b) => b.length - a.length)
    .find(opcion => digitos.startsWith(opcion))

  if (codigo && digitos.slice(codigo.length).length >= 7)
    return { codigoPais: codigo, numeroCelular: digitos.slice(codigo.length) }

  return { codigoPais: '591', numeroCelular: digitos }
}

const numeroCelularNormalizado = computed(() => String(formularioAcreditado.value.numeroCelular || '').replace(/\D/g, ''))
const celularValido = computed(() => {
  const numero = numeroCelularNormalizado.value
  if (formularioAcreditado.value.codigoPais === '591') return /^\d{8}$/.test(numero)

  return /^\d{7,14}$/.test(numero)
})
const celularFormateado = computed(() => `+${formularioAcreditado.value.codigoPais}${numeroCelularNormalizado.value}`)

const abrirVerAcreditado = acreditado => {
  acreditadoSeleccionado.value = acreditado
  dialogVerAcreditado.value = true
}

const cerrarVerAcreditado = () => {
  dialogVerAcreditado.value = false
  acreditadoSeleccionado.value = null
}

const cerrarConflictoCredencial = () => {
  dialogConflictoCredencial.value = false
  expositorConflicto.value = null
}

const abrirAdicionales = (registro, modo = 'adicion') => {
  adicionalesRegistro.value = registro
  modoAdicionales.value = modo
  cantidadAdicionales.value = null
  motivoAdicionales.value = ''
  errorAdicionales.value = ''
  avisoCantidadAdicionales.value = ''
  dialogAdicionales.value = true
}

const maximoDisminucionCredenciales = registro => {
  if (!registro) return 0
  const disponibles = Number(registro.credenciales_disponibles || 0)

  if (registro.id_contrato)
    return Math.min(disponibles, Math.max(0, Number(registro.credenciales_adicionales || 0)))

  return Math.min(disponibles, Math.max(0, Number(registro.credenciales_asignadas || 0) - 1))
}

const actualizarCantidadAdicionales = valor => {
  avisoCantidadAdicionales.value = ''
  const numero = Number.parseInt(String(valor || '').replace(/\D/g, ''), 10)
  if (Number.isNaN(numero)) {
    cantidadAdicionales.value = null
    return
  }

  const maximo = modoAdicionales.value === 'disminucion'
    ? maximoDisminucionCredenciales(adicionalesRegistro.value)
    : Number.POSITIVE_INFINITY

  if (numero > maximo) avisoCantidadAdicionales.value = `No puede ser mayor al permitido (${maximo}).`

  cantidadAdicionales.value = Math.max(1, Math.min(numero, maximo))
}

const cerrarAdicionales = () => {
  dialogAdicionales.value = false
  adicionalesRegistro.value = null
  modoAdicionales.value = 'adicion'
  cantidadAdicionales.value = null
  motivoAdicionales.value = ''
  errorAdicionales.value = ''
  avisoCantidadAdicionales.value = ''
}

const guardarAdicionales = async () => {
  if (!adicionalesRegistro.value || !Number.isInteger(Number(cantidadAdicionales.value)) || Number(cantidadAdicionales.value) < 1 || !motivoAdicionales.value.trim()) {
    errorAdicionales.value = 'Indique una cantidad válida y el motivo.'
    return
  }

  if (modoAdicionales.value === 'disminucion' && Number(cantidadAdicionales.value) > maximoDisminucionCredenciales(adicionalesRegistro.value)) {
    errorAdicionales.value = `No se puede disminuir esa cantidad. Máximo permitido: ${maximoDisminucionCredenciales(adicionalesRegistro.value)}.`
    return
  }

  guardandoAdicionales.value = true
  errorAdicionales.value = ''
  try {
    const url = modoAdicionales.value === 'disminucion'
      ? `/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}/adicionales/disminuir`
      : `/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}/adicionales`
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        id_contrato: adicionalesRegistro.value.id_contrato || null,
        id_vinculacion: adicionalesRegistro.value.id_vinculacion || null,
        cantidad: Number(cantidadAdicionales.value),
        motivo: motivoAdicionales.value.trim(),
      }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo registrar el movimiento de credenciales.')

    cerrarAdicionales()
    await cargarDetalle()
  } catch (err) {
    errorAdicionales.value = err.message || 'No se pudo registrar el movimiento de credenciales.'
  } finally {
    guardandoAdicionales.value = false
  }
}

const abrirHistorialAdicionales = async registro => {
  historialAdicionalesRegistro.value = registro
  historialAdicionales.value = []
  errorHistorialAdicionales.value = ''
  cargandoHistorialAdicionales.value = true
  dialogHistorialAdicionales.value = true

  try {
    const params = new URLSearchParams()
    if (registro.id_contrato) params.set('id_contrato', registro.id_contrato)
    if (registro.id_vinculacion) params.set('id_vinculacion', registro.id_vinculacion)
    const response = await fetch(`/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}/adicionales/historial?${params.toString()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial de movimientos.')
    historialAdicionales.value = payload.data || []
  } catch (err) {
    errorHistorialAdicionales.value = err.message || 'No se pudo cargar el historial de movimientos.'
  } finally {
    cargandoHistorialAdicionales.value = false
  }
}

const cerrarHistorialAdicionales = () => {
  dialogHistorialAdicionales.value = false
  historialAdicionales.value = []
  historialAdicionalesRegistro.value = null
  errorHistorialAdicionales.value = ''
}

const abrirCambioEstado = (acreditado, estado) => {
  credencialEstadoSeleccionada.value = { ...acreditado, accion: 'cambio_estado', estadoNuevo: estado }
  motivoEstado.value = ''
  errorEstado.value = ''
  dialogCambiarEstado.value = true
}

const abrirEliminarCredencial = acreditado => {
  credencialEstadoSeleccionada.value = { ...acreditado, accion: 'eliminacion' }
  motivoEstado.value = ''
  errorEstado.value = ''
  dialogCambiarEstado.value = true
}

const cerrarCambioEstado = () => {
  dialogCambiarEstado.value = false
  credencialEstadoSeleccionada.value = null
  motivoEstado.value = ''
  errorEstado.value = ''
}

const guardarCambioEstado = async () => {
  if (!credencialEstadoSeleccionada.value || !motivoEstado.value.trim()) {
    errorEstado.value = 'Debe indicar el motivo del cambio de estado.'
    return
  }

  guardandoEstado.value = true
  errorEstado.value = ''
  try {
    const esEliminacion = credencialEstadoSeleccionada.value.accion === 'eliminacion'
    const response = await fetch(esEliminacion
      ? `/credenciales/acreditados/${credencialEstadoSeleccionada.value.id_credencial}/eliminar`
      : `/credenciales/acreditados/${credencialEstadoSeleccionada.value.id_credencial}/estado`, {
      method: esEliminacion ? 'DELETE' : 'PATCH',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(esEliminacion
        ? { motivo: motivoEstado.value.trim() }
        : { estado: credencialEstadoSeleccionada.value.estadoNuevo, motivo: motivoEstado.value.trim() }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo cambiar el estado.')

    cerrarCambioEstado()
    await cargarDetalle()
  } catch (err) {
    errorEstado.value = err.message || 'No se pudo cambiar el estado.'
  } finally {
    guardandoEstado.value = false
  }
}

const abrirHistorial = async (acreditado, soloEventoActual = false) => {
  historialAcreditadoActual.value = acreditado
  historialSoloEventoActual.value = soloEventoActual
  dialogHistorial.value = true
  historialEventos.value = []
  errorHistorial.value = ''
  cargandoHistorial.value = true

  try {
    const parametros = soloEventoActual ? `?id_feria=${feriaId.value}` : ''
    const response = await fetch(`/credenciales/acreditados/${acreditado.id_credencial}/historial${parametros}`, {
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
  historialAcreditadoActual.value = null
  historialSoloEventoActual.value = false
  historialEventos.value = []
  errorHistorial.value = ''
}

const formatoFechaHistorial = fecha => fecha
  ? new Intl.DateTimeFormat('es-BO', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(fecha.replace(' ', 'T')))
  : 'Fecha no disponible'

const cargarImagen = archivo => new Promise((resolve, reject) => {
  const url = URL.createObjectURL(archivo)
  const image = new Image()

  image.onload = () => {
    URL.revokeObjectURL(url)
    resolve(image)
  }
  image.onerror = () => {
    URL.revokeObjectURL(url)
    reject(new Error('No se pudo procesar la fotografía seleccionada.'))
  }
  image.src = url
})

const crearImagenReducida = (canvas, quality) => new Promise(resolve => {
  canvas.toBlob(resolve, 'image/jpeg', quality)
})

const reducirFotografia = async archivo => {
  const limiteBytes = 2 * 1024 * 1024
  if (archivo.size <= limiteBytes) return archivo

  const dimensionMaxima = 1920
  const image = await cargarImagen(archivo)
  let escala = Math.min(1, dimensionMaxima / Math.max(image.width, image.height))
  let quality = 0.92
  let resultado = null

  for (let intento = 0; intento < 10; intento++) {
    const canvas = document.createElement('canvas')
    canvas.width = Math.max(1, Math.round(image.width * escala))
    canvas.height = Math.max(1, Math.round(image.height * escala))
    canvas.getContext('2d')?.drawImage(image, 0, 0, canvas.width, canvas.height)
    resultado = await crearImagenReducida(canvas, quality)

    if (resultado && resultado.size <= limiteBytes) break

    if (quality > 0.7) quality -= 0.05
    else escala *= 0.85
  }

  if (!resultado || resultado.size > limiteBytes) {
    throw new Error('La fotografía no pudo reducirse a menos de 2 MB. Seleccione otra imagen.')
  }

  return new File([resultado], `${archivo.name.replace(/\.[^.]+$/, '') || 'acreditado'}.jpg`, {
    type: 'image/jpeg',
    lastModified: Date.now(),
  })
}

const seleccionarFoto = async archivo => {
  errorFoto.value = ''
  if (!archivo) {
    formularioAcreditado.value.foto = null
    if (vistaPreviaFoto.value.startsWith('blob:')) URL.revokeObjectURL(vistaPreviaFoto.value)
    vistaPreviaFoto.value = ''
    return
  }

  if (!['image/jpeg', 'image/png'].includes(archivo.type)) {
    errorFoto.value = 'Seleccione una fotografía JPG o PNG.'
    return
  }

  procesandoFoto.value = true
  try {
    const fotografiaReducida = await reducirFotografia(archivo)

    if (vistaPreviaFoto.value.startsWith('blob:')) URL.revokeObjectURL(vistaPreviaFoto.value)
    formularioAcreditado.value.foto = fotografiaReducida
    vistaPreviaFoto.value = URL.createObjectURL(fotografiaReducida)
  } catch (err) {
    errorFoto.value = err.message || 'No se pudo preparar la fotografía.'
  } finally {
    procesandoFoto.value = false
  }
}

const abrirSelectorFoto = () => {
  if (camposAcreditadoHabilitados.value && !procesandoFoto.value) inputFoto.value?.click()
}
const cambiarFoto = event => seleccionarFoto(event.target.files?.[0] || null)

const guardarAcreditacion = async () => {
  if (!puedeGuardarAcreditacion.value || (!modoEdicionAcreditado.value && !registroAcreditar.value)) return

  guardandoAcreditacion.value = true
  errorGuardarAcreditacion.value = ''
  try {
    const formData = new FormData()
    if (modoEdicionAcreditado.value) formData.append('_method', 'PATCH')
    else {
      formData.append('id_contrato', registroAcreditar.value.id_contrato || '')
      formData.append('tipo_credencial', registroAcreditar.value.tipo_credencial)
    }
    formData.append('ci', formularioAcreditado.value.ci.trim())
    formData.append('tipo_documento', formularioAcreditado.value.tipo_documento || '')
    formData.append('nombre', formularioAcreditado.value.nombre.trim())
    formData.append('email', formularioAcreditado.value.email.trim())
    formData.append('celular', numeroCelularNormalizado.value ? celularFormateado.value : '')
    if (formularioAcreditado.value.foto) formData.append('foto', formularioAcreditado.value.foto)

    const url = modoEdicionAcreditado.value
      ? `/credenciales/acreditados/${idCredencialEditando.value}`
      : `/credenciales/eventos/${feriaId.value}/empresas/${empresaId.value}/acreditados`
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: formData,
    })
    const payload = await response.json().catch(() => null)
    if (!response.ok) {
      throw new Error(payload?.message || Object.values(payload?.errors || {}).flat().join(' ') || 'No se pudo guardar los datos del acreditado.')
    }

    dialogAcreditar.value = false
    await cargarDetalle()
    await cargarHistorialAcreditadosEmpresa()
  } catch (err) {
    errorGuardarAcreditacion.value = err.message || 'No se pudo guardar los datos del acreditado.'
  } finally {
    guardandoAcreditacion.value = false
  }
}

const programarBusquedaExpositor = () => {
  clearTimeout(busquedaCiTimer)
  errorFoto.value = ''
  const ci = formularioAcreditado.value.ci.trim()

  if (busquedaCiAbortController) {
    busquedaCiAbortController.abort()
    busquedaCiAbortController = null
  }

  if (!ci) {
    camposAcreditadoHabilitados.value = false
    resultadoBusquedaCi.value = null
    credencialesEvento.value = []
    formularioAcreditado.value = {
      id_expositor: null, ci: '', tipo_documento: '', nombre: '', email: '', codigoPais: '591', numeroCelular: '', celular: '', foto: null,
    }
    vistaPreviaFoto.value = ''
    return
  }

  busquedaCiTimer = setTimeout(buscarExpositorPorCi, 350)
}

const buscarExpositorPorCi = async () => {
  const ci = formularioAcreditado.value.ci.trim()
  if (!ci) return

  busquedaCiAbortController = new AbortController()
  buscandoExpositor.value = true

  try {
    const response = await fetch(`/credenciales/expositores/buscar?ci=${encodeURIComponent(ci)}&id_feria=${feriaId.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
      signal: busquedaCiAbortController.signal,
    })
    const payload = await response.json().catch(() => null)
    if (!response.ok) throw new Error(payload?.message || 'No se pudo buscar el expositor.')

    const expositor = payload?.data
    if (expositor) {
      formularioAcreditado.value = {
        id_expositor: expositor.id_expositor,
        ci: expositor.ci || ci,
        tipo_documento: expositor.tipo_documento || '',
        nombre: expositor.nombre || '',
        email: expositor.email || '',
        ...separarCelular(expositor.celular),
        celular: expositor.celular || '',
        foto: null,
      }
      credencialesEvento.value = expositor.credenciales_evento || []
      vistaPreviaFoto.value = urlFoto(expositor.foto)
      camposAcreditadoHabilitados.value = true
      if (credencialesEvento.value.length) {
        expositorConflicto.value = {
          ...expositor,
          credencial: credencialesEvento.value[0],
        }
        dialogAcreditar.value = false
        dialogConflictoCredencial.value = true
        return
      }
      resultadoBusquedaCi.value = {
        tipo: 'success',
        texto: 'Datos encontrados. Puede modificar la información, excepto el carnet de identidad.',
      }
    } else {
      formularioAcreditado.value = {
        id_expositor: null, ci, tipo_documento: '', nombre: '', email: '', codigoPais: '591', numeroCelular: '', celular: '', foto: null,
      }
      vistaPreviaFoto.value = ''
      camposAcreditadoHabilitados.value = true
      credencialesEvento.value = []
      resultadoBusquedaCi.value = {
        tipo: 'warning',
        texto: 'Datos no encontrados. Complete los campos para registrar un nuevo expositor.',
      }
    }
  } catch (err) {
    if (err.name !== 'AbortError') {
      camposAcreditadoHabilitados.value = false
      resultadoBusquedaCi.value = {
        tipo: 'error',
        texto: err.message || 'No se pudo buscar el expositor.',
      }
      error.value = err.message || 'No se pudo buscar el expositor.'
    }
  } finally {
    buscandoExpositor.value = false
    busquedaCiAbortController = null
  }
}

const urlFoto = foto => {
  if (!foto) return ''
  const filename = String(foto).split('/').pop()
  if (!/^acreditado_[a-f0-9-]+\.(jpg|jpeg|png)$/i.test(filename)) return ''

  return `/credenciales/fotos/${encodeURIComponent(filename)}`
}

onMounted(async () => {
  await cargarDetalle()
  await cargarHistorialAcreditadosEmpresa()
  actualizacionTimer = window.setInterval(() => {
    if (!loading.value && !document.hidden) cargarDetalle(false)
  }, 5000)
})

onBeforeUnmount(() => {
  if (actualizacionTimer) window.clearInterval(actualizacionTimer)
  empresaPassword.value = ''
})
</script>

<template>
  <section>
    <VCard class="mb-5">
      <VCardTitle class="d-flex align-center">
        <VBtn
          icon="tabler-arrow-left"
          variant="text"
          @click="router.push({ path: '/credenciales/empresas/list', query: { feria: String(feriaId) } })"
        />
        <span class="ml-2">Detalle de acreditación</span>
      </VCardTitle>
    </VCard>

    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" />

    <VAlert v-else-if="error" type="error" closable @click:close="error = ''">
      {{ error }}
    </VAlert>

    <template v-else-if="detalle">
      <VCard class="mb-5">
        <VCardText>
          <VRow align="center">
            <VCol cols="12" md="7">
              <div class="d-flex align-center gap-3">
                <VAvatar color="primary" variant="tonal" size="52" rounded>
                  <VIcon icon="tabler-building" size="28" />
                </VAvatar>
                <div>
                  <p class="text-h6 mb-1">{{ detalle.empresa.nombre_empresa }}</p>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip color="primary" size="small" label>{{ detalle.evento.nombre_feria }}</VChip>
                    <VChip size="small" variant="tonal" label>
                      {{ detalle.contratos.length }} contrato{{ detalle.contratos.length === 1 ? '' : 's' }}
                    </VChip>
                  </div>
                  <div class="d-flex flex-wrap gap-3 mt-3 text-body-2">
                    <span><strong>NIT:</strong> {{ detalle.empresa.nit || 'No registrado' }}</span>
                    <span><strong>Usuario:</strong> {{ detalle.empresa.usuario || 'No registrado' }}</span>
                    <span
                      v-if="can('users.view')"
                      class="d-inline-flex align-center gap-1"
                    >
                      <strong>Contraseña:</strong>
                      <template v-if="showingEmpresaPassword">
                        <code>{{ empresaPassword }}</code>
                        <VBtn
                          size="x-small"
                          variant="text"
                          :disabled="loadingEmpresaPassword"
                          aria-label="Ocultar contraseña"
                          @click="toggleEmpresaPassword"
                        >
                          <VIcon icon="tabler-eye-off" />
                        </VBtn>
                      </template>
                      <VBtn
                        v-else
                        size="x-small"
                        variant="text"
                        :loading="loadingEmpresaPassword"
                        :disabled="loadingEmpresaPassword"
                        aria-label="Mostrar contraseña"
                        @click="toggleEmpresaPassword"
                      >
                        <VIcon icon="tabler-eye" />
                      </VBtn>
                    </span>
                  </div>
                  <VAlert
                    v-if="empresaPasswordMessage && can('users.view')"
                    type="info"
                    variant="tonal"
                    density="compact"
                    class="mt-2"
                  >
                    {{ empresaPasswordMessage }}
                  </VAlert>
                </div>
              </div>
            </VCol>
            <VCol cols="12" md="5">
              <div class="d-flex justify-md-end gap-5 flex-wrap">
                <div class="text-center">
                  <p class="text-caption text-disabled mb-0">Asignadas</p>
                  <p class="text-h6 mb-0">{{ credenciales.asignadas }}</p>
                </div>
                <div class="text-center">
                  <p class="text-caption text-disabled mb-0">Acreditadas</p>
                  <p class="text-h6 text-primary mb-0">{{ credenciales.utilizadas }}</p>
                </div>
                <div class="text-center">
                  <p class="text-caption text-disabled mb-0">Disponibles</p>
                  <p :class="`text-h6 text-${disponibilidadColor} mb-0`">{{ credenciales.disponibles }}</p>
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <div class="d-flex justify-space-between align-center flex-wrap gap-4 mt-6 mb-4">
        <p class="text-h6 mb-0">Tipos de credencial</p>
      </div>

      <VCard v-if="tiposCredencial.length === 0" variant="tonal" class="text-center py-8">
        <VIcon icon="tabler-id-badge-off" size="40" class="text-disabled mb-2" />
        <p class="mb-0 text-medium-emphasis">No hay tipos de credencial configurados para esta empresa.</p>
      </VCard>

      <div v-else class="d-flex flex-column gap-3">
        <VCard v-for="tipo in tiposCredencial" :key="tipo.clave" class="tipo-credencial-card">
          <VCardText class="pa-4">
            <div class="d-flex align-center gap-3 flex-wrap">
              <VBtn icon size="small" variant="text" @click="alternarTipo(tipo.clave)">
                <VIcon :icon="tipoExpandido(tipo.clave) ? 'tabler-chevron-up' : 'tabler-chevron-down'" />
              </VBtn>

              <div class="grow">
                <div class="d-flex align-center gap-2 flex-wrap">
                  <VChip :color="tipoCredencialColor(tipo.tipo)" size="small" label>{{ tipo.tipo }}</VChip>
                  <span v-if="registroUnicoTipo(tipo)" class="text-body-1 font-weight-medium text-high-emphasis">
                    {{ registroUnicoTipo(tipo).codigo_contrato }}
                  </span>
                  <VChip v-if="registroUnicoTipo(tipo)?.modalidad_credencial" size="small" variant="tonal" :color="modalidadCredencialColor(registroUnicoTipo(tipo).modalidad_credencial)">
                    {{ registroUnicoTipo(tipo).modalidad_credencial }}
                  </VChip>
                  <template v-if="!registroUnicoTipo(tipo)">
                    <span class="text-body-1 font-weight-medium text-high-emphasis">{{ tipo.registros.length }} contratos</span>
                    <template v-for="registro in tipo.registros" :key="`modalidad-${tipo.tipo}-${registro.id_contrato || registro.id_vinculacion}`">
                      <VChip
                        v-if="registro.modalidad_credencial"
                        size="small"
                        variant="tonal"
                        :color="modalidadCredencialColor(registro.modalidad_credencial)"
                      >
                        {{ registro.modalidad_credencial }}
                      </VChip>
                    </template>
                  </template>
                </div>
                <div class="d-flex align-center gap-3 mt-1 flex-wrap">
                  <span class="text-caption text-disabled">Asignadas: <strong class="text-medium-emphasis">{{ tipo.credenciales_asignadas }}</strong></span>
                  <span class="text-caption text-disabled">Acreditadas: <strong class="text-medium-emphasis">{{ tipo.credenciales_utilizadas }}</strong></span>
                  <span class="text-caption text-disabled">Disponibles: <strong class="text-success">{{ tipo.credenciales_disponibles }}</strong></span>
                </div>
                <div class="mt-1">
                  <span v-if="registroUnicoTipo(tipo)?.id_contrato" class="text-caption text-disabled">
                    {{ registroUnicoTipo(tipo).pabellon || 'Sin pabellón' }} | Stand: {{ registroUnicoTipo(tipo).stands.length ? registroUnicoTipo(tipo).stands.join(', ') : 'Sin stand' }}
                    <template v-if="registroUnicoTipo(tipo).metraje_total"> | {{ registroUnicoTipo(tipo).metraje_total }} m²</template>
                  </span>
                  <span v-else-if="registroUnicoTipo(tipo)" class="text-caption text-disabled">Configuración directa del evento</span>
                </div>
              </div>

              <AppTextField
                :model-value="buscarAcreditadosPorTipo[tipo.clave] || ''"
                placeholder="Buscar por nombre o CI..."
                append-inner-icon="tabler-search"
                single-line
                hide-details
                clearable
                class="acreditados-tipo-buscador"
                @update:model-value="actualizarBusquedaTipo(tipo.clave, $event)"
              />

              <VTooltip v-if="registroUnicoTipo(tipo) && can('credenciales.cupos.adicionar')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="flat"
                    size="small"
                    color="success"
                    rounded="circle"
                    :disabled="Number(registroUnicoTipo(tipo).credenciales_disponibles) === 0"
                    @click="abrirAdicionales(registroUnicoTipo(tipo))"
                  >
                    <VIcon icon="tabler-user-plus" />
                  </VBtn>
                </template>
                <span>Adicionar credenciales</span>
              </VTooltip>

              <VTooltip v-if="registroUnicoTipo(tipo) && can('credenciales.cupos.quitar')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="flat"
                    size="small"
                    color="error"
                    rounded="circle"
                    :disabled="maximoDisminucionCredenciales(registroUnicoTipo(tipo)) === 0"
                    @click="abrirAdicionales(registroUnicoTipo(tipo), 'disminucion')"
                  >
                    <VIcon icon="tabler-user-minus" />
                  </VBtn>
                </template>
                <span>Quitar credenciales</span>
              </VTooltip>

              <VTooltip v-if="registroUnicoTipo(tipo) && can('credenciales.cupos.historial')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="flat"
                    size="small"
                    color="info"
                    rounded="circle"
                    @click="abrirHistorialAdicionales(registroUnicoTipo(tipo))"
                  >
                    <VIcon icon="tabler-list-details" />
                  </VBtn>
                </template>
                <span>Historial de aumentos y disminuciones</span>
              </VTooltip>

              <VBtn
                v-if="registroUnicoTipo(tipo) && can('credenciales.acreditados.crear')"
                class="ms-3"
                size="small"
                color="primary"
                prepend-icon="tabler-user-plus"
                :disabled="Number(registroUnicoTipo(tipo).credenciales_disponibles) === 0"
                @click="abrirAcreditacion(registroUnicoTipo(tipo))"
              >
                Agregar acreditado
              </VBtn>
            </div>
          </VCardText>
          <VExpandTransition>
            <div v-if="tipoExpandido(tipo.clave)">
              <VDivider />
              <div v-if="seleccionadosPorTipo(tipo).length > 0" class="d-flex align-center justify-space-between flex-wrap gap-2 pa-3 px-4 bg-light-primary rounded my-2 mx-3">
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-checkbox" color="primary" />
                  <span class="text-body-2 font-weight-medium">
                    {{ seleccionadosPorTipo(tipo).length }} {{ seleccionadosPorTipo(tipo).length === 1 ? 'credencial seleccionada' : 'credenciales seleccionadas' }}
                  </span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VBtn
                    v-if="todosSeleccionadosTienenEstado(tipo, 0) && can('credenciales.seleccionados.habilitar')"
                    color="success"
                    size="small"
                    variant="flat"
                    prepend-icon="tabler-check"
                    @click="abrirHabilitar(seleccionadosPorTipo(tipo))"
                  >
                    Habilitar seleccionadas
                  </VBtn>
                  <VBtn
                    v-else-if="todosSeleccionadosTienenEstado(tipo, 1) && can('credenciales.seleccionados.inhabilitar')"
                    color="error"
                    size="small"
                    variant="flat"
                    prepend-icon="tabler-user-off"
                    @click="abrirInhabilitar(seleccionadosPorTipo(tipo))"
                  >
                    Inhabilitar seleccionadas
                  </VBtn>
                  <VBtn
                    v-if="esDigitalTipo(tipo) && can('credenciales.seleccionados.enviar')"
                    color="info"
                    size="small"
                    variant="flat"
                    prepend-icon="tabler-send"
                    @click="enviarSeleccionados(tipo)"
                  >
                    Enviar Credencial
                  </VBtn>
                  <VBtn
                    v-if="esDigitalTipo(tipo) ? can('credenciales.salida_digital.descargar_lote') : can('credenciales.salida_fisica.imprimir_lote')"
                    color="primary"
                    size="small"
                    variant="flat"
                    :prepend-icon="esDigitalTipo(tipo) ? 'tabler-file-zip' : 'tabler-printer'"
                    @click="esDigitalTipo(tipo) ? descargarSeleccionados(tipo) : imprimirSeleccionados(tipo)"
                  >
                    {{ esDigitalTipo(tipo) ? 'Descargar seleccionadas' : 'Imprimir seleccionadas' }}
                  </VBtn>
                </div>
              </div>
              <VTable density="compact" class="contrato-credencial-table">
                <thead>
                  <tr>
                    <th class="text-center" style="width: 40px;">
                      <VCheckbox
                        :model-value="estaTodoSeleccionado(tipo)"
                        :indeterminate="estaParcialSeleccionado(tipo)"
                        hide-details
                        density="compact"
                        @update:model-value="toggleSeleccionarTodo(tipo, $event)"
                      />
                    </th>
                    <th>ACREDITADO</th>
                    <th>CELULAR</th>
                    <th>CORREO</th>
                    <th>TIPO DOCUMENTO</th>
                    <th>TIPO</th>
                    <th>ESTADO</th>
                    <th class="text-center">IMPRESIONES / DESCARGAS</th>
                    <th class="text-end">ACCIONES</th>
                  </tr>
                </thead>
                <tbody>
                  <template v-if="acreditadosFiltradosPorTipo(tipo).length">
                    <tr v-for="acreditado in acreditadosPaginadosPorTipo(tipo)" :key="acreditado.id_credencial">
                      <td class="text-center" style="width: 40px;">
                        <VCheckbox
                          :model-value="credencialesSeleccionadas.includes(acreditado.id_credencial)"
                          :disabled="!credencialesSeleccionadas.includes(acreditado.id_credencial) && !puedeSeleccionarAcreditado(tipo, acreditado)"
                          hide-details
                          density="compact"
                          @update:model-value="val => toggleSeleccionAcreditado(tipo, acreditado, val)"
                        />
                      </td>
                      <td>
                        <div class="d-flex align-center gap-3">
                          <VAvatar size="40" rounded="sm" color="secondary" variant="tonal" class="foto-acreditado-avatar">
                            <VImg v-if="urlFoto(acreditado.foto)" :src="urlFoto(acreditado.foto)" cover />
                            <VIcon v-else icon="tabler-user" />
                          </VAvatar>
                          <div>
                            <div class="font-weight-medium">{{ acreditado.nombre }}</div>
                            <div class="text-caption text-medium-emphasis">CI: {{ acreditado.ci }}<template v-if="acreditado.exp"> {{ acreditado.exp }}</template></div>
                          </div>
                        </div>
                      </td>
                      <td>{{ acreditado.celular || 'Sin datos' }}</td>
                      <td>{{ acreditado.email || 'Sin datos' }}</td>
                      <td>{{ acreditado.tipo_documento || 'Sin datos' }}</td>
                      <td>
                        <VChip size="small" variant="tonal" :color="modalidadCredencialColor(acreditado.modalidad_credencial || 'Digital')">
                          {{ acreditado.modalidad_credencial || 'Digital' }}
                        </VChip>
                      </td>
                      <td>
                        <VChip size="small" variant="tonal" :color="estadoCredencialColor(acreditado.estado_credencial)">
                          {{ acreditado.estado_credencial }}
                        </VChip>
                      </td>
                      <td class="text-center">
                        <template v-if="resumenSalida(acreditado)">
                          <VChip size="small" variant="tonal" :color="acreditado.modalidad_credencial === 'Digital' ? 'primary' : 'secondary'">
                            {{ resumenSalida(acreditado).cantidad }}x
                          </VChip>
                          <div class="text-caption text-medium-emphasis mt-1">{{ formatoFechaHistorial(resumenSalida(acreditado).ultima_fecha) }}</div>
                        </template>
                        <span v-else>—</span>
                      </td>
                      <td class="text-end">
                        <VTooltip v-if="acreditado.modalidad_credencial === 'Digital' && can('credenciales.salida_digital.descargar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="primary" @click="descargarCredencialIndividual(acreditado)">
                              <VIcon icon="tabler-download" />
                            </VBtn>
                          </template>
                          <span>Descargar credencial digital</span>
                        </VTooltip>
                        <VTooltip v-else-if="can('credenciales.salida_fisica.imprimir')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="secondary" @click="imprimirCredencialIndividual(acreditado)">
                              <VIcon icon="tabler-printer" />
                            </VBtn>
                          </template>
                          <span>Imprimir credencial física</span>
                        </VTooltip>
                        <VTooltip v-if="can('credenciales.acreditados.ver')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="primary" @click="abrirVerAcreditado(acreditado)">
                              <VIcon icon="tabler-eye" />
                            </VBtn>
                          </template>
                          <span>Ver acreditado</span>
                        </VTooltip>
                        <VTooltip v-if="can('credenciales.historial.ver')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="info" @click="abrirHistorial(acreditado, true)">
                              <VIcon icon="tabler-clipboard-list" />
                            </VBtn>
                          </template>
                          <span>Ver historial</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo === 1 && can('credenciales.modalidad.cambiar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="secondary" @click="abrirCambioModalidad(acreditado)">
                              <VIcon icon="tabler-repeat" />
                            </VBtn>
                          </template>
                          <span>Cambiar tipo de credencial</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo === 1 && can('credenciales.estado.inhabilitar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="warning" @click="abrirInhabilitar([acreditado])">
                              <VIcon icon="tabler-user-off" />
                            </VBtn>
                          </template>
                          <span>Inhabilitar credencial</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo === 1 && can('credenciales.qr.renovar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="success" @click="abrirReemplazo(acreditado)">
                              <VIcon icon="tabler-qrcode" />
                            </VBtn>
                          </template>
                          <span>Generar nuevo QR</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo !== 3 && can('credenciales.acreditados.editar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="warning" @click="abrirEditarAcreditado(acreditado)">
                              <VIcon icon="tabler-pencil" />
                            </VBtn>
                          </template>
                          <span>Editar acreditado</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo === 1 && can('credenciales.estado.anular')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="error" @click="abrirCambioEstado(acreditado, 2)">
                              <VIcon icon="tabler-trash" />
                            </VBtn>
                          </template>
                          <span>Anular credencial</span>
                        </VTooltip>
                        <VTooltip v-if="[0, 2].includes(acreditado.estado_credencial_codigo) && can('credenciales.estado.habilitar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="success" @click="abrirCambioEstado(acreditado, 1)">
                              <VIcon icon="tabler-check" />
                            </VBtn>
                          </template>
                          <span>{{ acreditado.estado_credencial_codigo === 2 ? 'Reactivar con nuevo QR' : 'Habilitar y conservar QR' }}</span>
                        </VTooltip>
                        <VTooltip v-if="acreditado.estado_credencial_codigo !== 3 && can('credenciales.registro.eliminar')" location="top">
                          <template #activator="{ props }">
                            <VBtn v-bind="props" icon size="small" variant="text" color="error" @click="abrirEliminarCredencial(acreditado)">
                              <VIcon icon="tabler-trash-x" />
                            </VBtn>
                          </template>
                          <span>Eliminar registro</span>
                        </VTooltip>
                      </td>
                    </tr>
                  </template>
                  <tr v-else>
                    <td colspan="9" class="text-center text-medium-emphasis py-4">
                      {{ buscarAcreditadosPorTipo[tipo.clave]?.trim() ? 'No se encontraron acreditados.' : 'Sin datos' }}
                    </td>
                  </tr>
                </tbody>
              </VTable>
              <div v-if="acreditadosFiltradosPorTipo(tipo).length" class="acreditados-evento-paginacion d-flex align-center justify-space-between flex-wrap gap-4 mt-4">
                <div class="d-flex align-center gap-2 text-body-2 text-medium-emphasis">
                  <span>Elementos por página:</span>
                  <AppSelect
                    :model-value="itemsPorPaginaAcreditados"
                    :items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
                    style="inline-size: 5.5rem;"
                    @update:model-value="itemsPorPaginaAcreditados = sanitizeItemsPerPage($event)"
                  />
                </div>
                <VPagination
                        :model-value="paginaAcreditadosEvento[tipo.clave] || 1"
                  :length="paginasAcreditadosPorTipo(tipo)"
                  :total-visible="5"
                  density="comfortable"
                        @update:model-value="paginaAcreditadosEvento[tipo.clave] = $event"
                />
              </div>
            </div>
          </VExpandTransition>
        </VCard>
      </div>
    </template>

    <VCard v-if="detalle" class="mt-6">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4 pa-5">
        <div class="d-flex align-center">
          <VIcon icon="tabler-archive" color="primary" class="me-3" />
          <div>
            <div class="text-h5">Historial de acreditados</div>
            <div class="text-caption text-medium-emphasis">Expositores registrados por esta empresa en todos los eventos</div>
          </div>
        </div>
        <AppTextField
          v-model="buscarHistorialAcreditados"
          placeholder="Buscar por nombre o CI..."
          append-inner-icon="tabler-search"
          single-line
          hide-details
          clearable
          class="historial-acreditados-buscador"
        />
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div v-if="cargandoHistorialEmpresa" class="d-flex justify-center py-8">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <VAlert v-else-if="!historialAcreditadosFiltrados.length" type="info" variant="tonal">
          {{ buscarHistorialAcreditados.trim() ? 'No se encontraron acreditados con ese nombre o CI.' : 'No hay acreditados históricos para esta empresa.' }}
        </VAlert>
        <VTable v-else density="compact" class="contrato-credencial-table border rounded-lg">
          <thead>
            <tr class="bg-primary-container">
              <th class="pa-3 text-center">Foto</th>
              <th class="pa-3">Nombre del expositor</th>
              <th class="pa-3">CI</th>
              <th class="pa-3">Celular</th>
              <th class="pa-3">Correo</th>
              <th class="pa-3">Tipo documento</th>
              <th class="pa-3 text-end">Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="acreditado in historialAcreditadosPaginados" :key="`historico-${acreditado.id_credencial}`" class="border-b">
              <td class="pa-3 foto-acreditado-celda"><VAvatar size="48" rounded="sm" color="secondary" variant="tonal" class="foto-acreditado-avatar"><VImg v-if="urlFoto(acreditado.foto)" :src="urlFoto(acreditado.foto)" cover /><VIcon v-else icon="tabler-user" /></VAvatar></td>
              <td class="pa-3">{{ acreditado.nombre }}</td>
              <td class="pa-3">{{ acreditado.ci }}<template v-if="acreditado.exp"> {{ acreditado.exp }}</template></td>
              <td class="pa-3">{{ acreditado.celular || 'Sin datos' }}</td>
              <td class="pa-3">{{ acreditado.email || 'Sin datos' }}</td>
              <td class="pa-3">{{ acreditado.tipo_documento || 'Sin datos' }}</td>
              <td class="pa-3 text-end">
                <VTooltip location="top"><template #activator="{ props }"><VBtn v-bind="props" icon size="small" variant="text" color="primary" @click="abrirVerAcreditado(acreditado)"><VIcon icon="tabler-eye" /></VBtn></template><span>Ver acreditado</span></VTooltip>
                <VTooltip location="top"><template #activator="{ props }"><VBtn v-bind="props" icon size="small" variant="text" color="info" @click="abrirHistorial(acreditado)"><VIcon icon="tabler-clipboard-list" /></VBtn></template><span>Ver historial</span></VTooltip>
              </td>
            </tr>
          </tbody>
        </VTable>
        <div v-if="historialAcreditadosFiltrados.length" class="historial-paginacion d-flex align-center justify-space-between flex-wrap gap-4 mt-4">
          <div class="d-flex align-center gap-2 text-body-2 text-medium-emphasis">
            <span>Elementos por página:</span>
            <AppSelect
              :model-value="itemsPorPaginaHistorial"
              :items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPorPaginaHistorial = sanitizeItemsPerPage($event)"
            />
          </div>
          <VPagination
            v-model="paginaHistorial"
            :length="paginasHistorial"
            :total-visible="5"
            density="comfortable"
          />
        </div>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogAdicionales" max-width="560" persistent>
      <VCard v-if="adicionalesRegistro" class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon :icon="modoAdicionales === 'disminucion' ? 'tabler-user-minus' : 'tabler-user-plus'" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">{{ modoAdicionales === 'disminucion' ? 'Quitar credenciales' : 'Adicionar credenciales' }}</div>
            <div class="text-caption text-medium-emphasis mt-1">{{ adicionalesRegistro.codigo_contrato || 'Sin contrato' }}</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarAdicionales"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorAdicionales" type="error" variant="tonal" class="mb-4">{{ errorAdicionales }}</VAlert>
          <div class="text-body-1 mb-4">
            {{ modoAdicionales === 'disminucion' ? `Indique cuántas credenciales desea quitar. Puede quitar hasta ${maximoDisminucionCredenciales(adicionalesRegistro)}.` : 'Indique la cantidad adicional que desea asignar y el motivo.' }}
          </div>
          <VTextField :model-value="cantidadAdicionales" label="Cantidad de credenciales *" type="number" min="1" :max="modoAdicionales === 'disminucion' ? maximoDisminucionCredenciales(adicionalesRegistro) : undefined" step="1" variant="outlined" :error-messages="avisoCantidadAdicionales" class="mb-4" @update:model-value="actualizarCantidadAdicionales" />
          <VTextarea v-model="motivoAdicionales" :label="modoAdicionales === 'disminucion' ? 'Motivo de la disminución *' : 'Motivo de la adición *'" :placeholder="modoAdicionales === 'disminucion' ? 'Describa por qué se quitan credenciales...' : 'Describa por qué se adicionan credenciales...'" rows="4" auto-grow maxlength="2000" counter="2000" variant="outlined" />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn color="secondary" variant="tonal" @click="cerrarAdicionales">Cancelar</VBtn>
          <VBtn :color="modoAdicionales === 'disminucion' ? 'error' : 'success'" :loading="guardandoAdicionales" :disabled="!cantidadAdicionales || !motivoAdicionales.trim() || (modoAdicionales === 'disminucion' && Number(cantidadAdicionales) > maximoDisminucionCredenciales(adicionalesRegistro))" @click="guardarAdicionales">
            {{ modoAdicionales === 'disminucion' ? 'Quitar' : 'Adicionar' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHistorialAdicionales" max-width="820" scrollable>
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon icon="tabler-list-details" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Historial de cupos</div>
            <div class="text-caption text-medium-emphasis mt-1">Adiciones y disminuciones de credenciales</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarHistorialAdicionales"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorHistorialAdicionales" type="error" variant="tonal" class="mb-4">{{ errorHistorialAdicionales }}</VAlert>
          <div v-if="cargandoHistorialAdicionales" class="d-flex justify-center py-8"><VProgressCircular indeterminate color="primary" /></div>
          <VAlert v-else-if="!historialAdicionales.length" type="info" variant="tonal">No hay movimientos registrados.</VAlert>
          <VTable v-else density="comfortable" class="historial-cupos-table border rounded-lg">
            <thead>
              <tr class="bg-primary-container">
                <th class="pa-3">Acción</th>
                <th class="pa-3">Cantidad</th>
                <th class="pa-3">Motivo</th>
                <th class="pa-3">Usuario</th>
                <th class="pa-3">Fecha</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="movimiento in historialAdicionales" :key="movimiento.id" class="border-b">
                <td class="pa-3"><VChip size="small" variant="tonal" :color="movimiento.accion === 'Disminución' ? 'error' : 'success'">{{ movimiento.accion }}</VChip></td>
                <td class="pa-3">{{ movimiento.cantidad }}</td>
                <td class="pa-3 historial-motivo">{{ movimiento.motivo || 'Sin motivo' }}</td>
                <td class="pa-3">{{ movimiento.usuario }}</td>
                <td class="pa-3 text-no-wrap">{{ formatoFechaHistorial(movimiento.fecha) }}</td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end">
          <VBtn color="secondary" variant="tonal" @click="cerrarHistorialAdicionales">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogAcreditar" max-width="640" scrollable>
      <VCard class="acreditacion-dialog-card acreditacion-dialog-tall">
            <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
              <div class="acreditacion-dialog-icon me-3">
                <VIcon :icon="modoEdicionAcreditado ? 'tabler-pencil' : 'tabler-user-plus'" size="24" />
              </div>
              <div>
                <div class="text-h5 font-weight-bold">{{ modoEdicionAcreditado ? 'Editar acreditado' : 'Agregar acreditado' }}</div>
                <div class="text-caption text-medium-emphasis mt-1">
                  <template v-if="modoEdicionAcreditado">Actualice los datos personales del acreditado</template>
                  <template v-else>{{ detalle?.empresa?.nombre_empresa }} | {{ registroAcreditar?.tipo_credencial }} | {{ registroAcreditar?.codigo_contrato }}</template>
                </div>
              </div>
              <VSpacer />
              <VBtn icon variant="tonal" size="small" @click="dialogAcreditar = false">
                <VIcon icon="tabler-x" />
              </VBtn>
        </VCardTitle>

            <VDivider />

            <VCardText class="acreditacion-dialog-body pa-5 pt-4">
              <VAlert v-if="errorGuardarAcreditacion" type="error" density="compact" variant="tonal" class="mb-4">
                {{ errorGuardarAcreditacion }}
              </VAlert>
              <div class="acreditacion-section-title mb-3">
                <VIcon icon="tabler-id-badge-2" size="18" class="me-2" />
                Datos del acreditado
              </div>

              <VAlert v-if="resultadoBusquedaCi" :type="resultadoBusquedaCi.tipo" density="compact" variant="tonal" class="mb-4">
                {{ resultadoBusquedaCi.texto }}
              </VAlert>
              <VRow>
                <VCol cols="12">
                  <VRow dense>
                    <VCol cols="12" md="6">
                      <VTextField v-model="formularioAcreditado.ci" label="Carnet de identidad *" variant="outlined" density="comfortable" :loading="buscandoExpositor" :readonly="Boolean(formularioAcreditado.id_expositor)" @update:model-value="!modoEdicionAcreditado && programarBusquedaExpositor()" />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VSelect
                        v-model="formularioAcreditado.tipo_documento"
                        label="Documento *"
                        placeholder="Seleccione un documento"
                        :items="['Cédula de identidad', 'Pasaporte', 'Documento extranjero']"
                        variant="outlined"
                        density="comfortable"
                        :disabled="!camposAcreditadoHabilitados"
                      />
                    </VCol>
                    <VCol cols="12">
                      <VTextField v-model="formularioAcreditado.nombre" label="Nombre completo *" variant="outlined" density="comfortable" :disabled="!camposAcreditadoHabilitados" />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VSelect
                        v-model="formularioAcreditado.codigoPais"
                        label="Código de país"
                        :items="codigosPais"
                        variant="outlined"
                        density="comfortable"
                        :disabled="!camposAcreditadoHabilitados"
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="formularioAcreditado.numeroCelular"
                        label="Número de celular (opcional)"
                        variant="outlined"
                        density="comfortable"
                        inputmode="numeric"
                        :maxlength="formularioAcreditado.codigoPais === '591' ? 8 : 14"
                        :disabled="!camposAcreditadoHabilitados"
                        @update:model-value="formularioAcreditado.numeroCelular = String($event).replace(/\D/g, '')"
                      />
                      <div class="text-caption mt-1" :class="celularValido || !numeroCelularNormalizado ? 'text-medium-emphasis' : 'text-error'">
                        {{ formularioAcreditado.codigoPais === '591' ? 'Bolivia requiere 8 dígitos.' : 'Ingrese entre 7 y 14 dígitos.' }}
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextField v-model="formularioAcreditado.email" label="Correo electrónico (opcional)" type="email" variant="outlined" density="comfortable" :disabled="!camposAcreditadoHabilitados" />
                    </VCol>
                  </VRow>
                </VCol>
                <VCol cols="12" class="d-flex align-center justify-center gap-4 pt-4">
                  <input ref="inputFoto" class="d-none" type="file" accept="image/png,image/jpeg" @change="cambiarFoto">
                  <button type="button" class="acreditacion-photo-picker" :disabled="!camposAcreditadoHabilitados || procesandoFoto" @click="abrirSelectorFoto">
                    <VImg v-if="vistaPreviaFoto" :src="vistaPreviaFoto" class="acreditacion-photo-preview" />
                    <VProgressCircular v-if="procesandoFoto" indeterminate color="primary" size="36" />
                    <VIcon v-else-if="!vistaPreviaFoto" icon="tabler-camera" size="40" />
                  </button>
                  <div>
                    <p class="text-body-1 font-weight-medium mb-1">Fotografía *</p>
                    <p class="text-body-2 text-medium-emphasis mb-2">JPG o PNG, máximo 2 MB. Las fotos mayores se reducen automáticamente.</p>
                    <VBtn size="small" variant="tonal" color="primary" prepend-icon="tabler-upload" :loading="procesandoFoto" :disabled="!camposAcreditadoHabilitados || procesandoFoto" @click="abrirSelectorFoto">Cargar fotografía</VBtn>
                  </div>
                </VCol>
                <VCol v-if="errorFoto" cols="12">
                  <VAlert type="error" density="compact" variant="tonal">{{ errorFoto }}</VAlert>
                </VCol>
              </VRow>
            </VCardText>

            <VDivider />

            <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
              <VBtn color="secondary" variant="tonal" @click="dialogAcreditar = false">Cerrar</VBtn>
              <VBtn color="primary" :loading="guardandoAcreditacion" :disabled="!puedeGuardarAcreditacion" @click="guardarAcreditacion">{{ modoEdicionAcreditado ? 'Guardar cambios' : 'Acreditar expositor' }}</VBtn>
            </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogConflictoCredencial" max-width="620" persistent>
      <VCard v-if="expositorConflicto" class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon icon="tabler-id-badge-off" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Expositor ya acreditado</div>
            <div class="text-caption text-medium-emphasis mt-1">No puede recibir otra credencial en esta feria</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarConflictoCredencial">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="acreditacion-dialog-body pa-5">
          <div class="conflicto-acreditado-contenido">
            <div class="d-flex flex-column align-center text-center mb-5">
            <VAvatar size="132" rounded="circle" color="secondary" variant="tonal">
              <VImg v-if="urlFoto(expositorConflicto.foto)" :src="urlFoto(expositorConflicto.foto)" cover />
              <VIcon v-else icon="tabler-user" size="44" />
            </VAvatar>
            </div>
            <div class="conflicto-acreditado-linea mb-4">
              <span>Nombre completo</span>
              <strong>{{ expositorConflicto.nombre || 'Sin datos' }}</strong>
            </div>
            <div class="conflicto-acreditado-linea mb-5">
              <span>Carnet de identidad</span>
              <strong>{{ expositorConflicto.ci || 'Sin datos' }}</strong>
            </div>
            <VAlert type="warning" variant="tonal" class="conflicto-acreditado-alert">
              <div class="font-weight-medium">Credencial asignada en esta feria</div>
              <div class="mt-1">Empresa: <strong>{{ expositorConflicto.credencial.empresa }}</strong></div>
              <div>Tipo de credencial: <strong>{{ expositorConflicto.credencial.tipo }}</strong></div>
            </VAlert>
          </div>
        </VCardText>

        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end">
          <VBtn color="secondary" variant="tonal" @click="cerrarConflictoCredencial">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogVerAcreditado" max-width="640" scrollable>
      <VCard v-if="acreditadoSeleccionado" class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon icon="tabler-user" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Datos del acreditado</div>
            <div class="text-caption text-medium-emphasis mt-1">Información registrada para la credencial</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarVerAcreditado">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="acreditacion-dialog-body pa-5 pt-4">
          <div class="ver-acreditado-contenido">
            <div class="d-flex flex-column align-center text-center mb-5">
              <VAvatar size="140" rounded="circle" color="secondary" variant="tonal">
                <VImg v-if="urlFoto(acreditadoSeleccionado.foto)" :src="urlFoto(acreditadoSeleccionado.foto)" cover />
                <VIcon v-else icon="tabler-user" size="44" />
              </VAvatar>
              <p class="text-body-2 text-medium-emphasis mt-3 mb-0">Fotografía</p>
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
          <VBtn color="secondary" variant="tonal" @click="cerrarVerAcreditado">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogModalidad" max-width="560" persistent>
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3"><VIcon icon="tabler-repeat" size="24" /></div>
          <div><div class="text-h5 font-weight-bold">Cambiar tipo de credencial</div><div class="text-caption text-medium-emphasis mt-1">El cambio renovará el código QR y quedará registrado.</div></div>
          <VSpacer /><VBtn icon variant="tonal" size="small" @click="dialogModalidad = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorOperacion" type="error" density="compact" variant="tonal" class="mb-4">{{ errorOperacion }}</VAlert>
          <VTextField :model-value="credencialesOperacion[0]?.nombre" label="Acreditado" variant="outlined" readonly class="mb-4" />
          <VTextField :model-value="credencialesOperacion[0]?.modalidad_credencial" label="Tipo actual" variant="outlined" readonly class="mb-4" />
          <VSelect v-model="modalidadNueva" label="Nuevo tipo" :items="[{ title: 'Física', value: 0 }, { title: 'Digital', value: 1 }]" variant="outlined" class="mb-4" />
          <VTextarea v-model="observacionesOperacion" label="Observaciones" rows="3" auto-grow maxlength="2000" counter="2000" variant="outlined" />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2"><VBtn color="secondary" variant="tonal" @click="dialogModalidad = false">Cancelar</VBtn><VBtn color="primary" :loading="guardandoOperacion" @click="guardarCambioModalidad">Confirmar cambio</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogInhabilitar" max-width="560" persistent>
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3"><VIcon icon="tabler-user-off" size="24" /></div>
          <div><div class="text-h5 font-weight-bold">Inhabilitar credenciales</div><div class="text-caption text-medium-emphasis mt-1">{{ credencialesOperacion.length }} credencial{{ credencialesOperacion.length === 1 ? '' : 'es' }} seleccionada{{ credencialesOperacion.length === 1 ? '' : 's' }}.</div></div>
          <VSpacer /><VBtn icon variant="tonal" size="small" @click="dialogInhabilitar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorOperacion" type="error" density="compact" variant="tonal" class="mb-4">{{ errorOperacion }}</VAlert>
          <VAlert type="info" density="compact" variant="tonal" class="mb-4">El código QR se conservará, pero será rechazado mientras la credencial esté inhabilitada.</VAlert>
          <VSelect v-model="motivoOperacion" label="Motivo *" :items="['Mal uso', 'Falta de pago']" variant="outlined" class="mb-4" />
          <VTextarea v-model="observacionesOperacion" label="Observaciones" rows="3" auto-grow maxlength="2000" counter="2000" variant="outlined" />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2"><VBtn color="secondary" variant="tonal" @click="dialogInhabilitar = false">Cancelar</VBtn><VBtn color="error" :loading="guardandoOperacion" :disabled="!motivoOperacion" @click="confirmarInhabilitacion">Inhabilitar</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHabilitar" max-width="560" persistent>
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3"><VIcon icon="tabler-check" size="24" /></div>
          <div><div class="text-h5 font-weight-bold">Habilitar credenciales</div><div class="text-caption text-medium-emphasis mt-1">{{ credencialesOperacion.length }} credencial{{ credencialesOperacion.length === 1 ? '' : 'es' }} seleccionada{{ credencialesOperacion.length === 1 ? '' : 's' }}.</div></div>
          <VSpacer /><VBtn icon variant="tonal" size="small" @click="dialogHabilitar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorOperacion" type="error" density="compact" variant="tonal" class="mb-4">{{ errorOperacion }}</VAlert>
          <VAlert type="success" density="compact" variant="tonal" class="mb-4">Las credenciales se activarán nuevamente y conservarán sus códigos QR actuales.</VAlert>
          <VTextarea v-model="observacionesOperacion" label="Observaciones" rows="3" auto-grow maxlength="2000" counter="2000" variant="outlined" />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2"><VBtn color="secondary" variant="tonal" @click="dialogHabilitar = false">Cancelar</VBtn><VBtn color="success" :loading="guardandoOperacion" @click="confirmarHabilitacion">Habilitar</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogReemplazar" max-width="560" persistent>
      <VCard class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3"><VIcon icon="tabler-refresh" size="24" /></div>
          <div><div class="text-h5 font-weight-bold">Generar nuevo código QR</div><div class="text-caption text-medium-emphasis mt-1">El QR anterior quedará revocado permanentemente.</div></div>
          <VSpacer /><VBtn icon variant="tonal" size="small" @click="dialogReemplazar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorOperacion" type="error" density="compact" variant="tonal" class="mb-4">{{ errorOperacion }}</VAlert>
          <VTextField :model-value="credencialesOperacion[0]?.nombre" label="Acreditado" variant="outlined" readonly class="mb-4" />
          <VAlert type="warning" density="compact" variant="tonal" class="mb-4">Use esta acción si sospecha que el QR fue compartido o comprometido.</VAlert>
          <VTextarea v-model="observacionesOperacion" label="Observaciones" rows="3" auto-grow maxlength="2000" counter="2000" variant="outlined" />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2"><VBtn color="secondary" variant="tonal" @click="dialogReemplazar = false">Cancelar</VBtn><VBtn color="success" :loading="guardandoOperacion" @click="confirmarReemplazo">Generar nuevo QR</VBtn></VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogCambiarEstado" max-width="560" persistent>
      <VCard v-if="credencialEstadoSeleccionada" class="acreditacion-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon :icon="credencialEstadoSeleccionada.accion === 'eliminacion' ? 'tabler-trash-x' : (credencialEstadoSeleccionada.estadoNuevo === 2 ? 'tabler-alert-triangle' : (credencialEstadoSeleccionada.estadoNuevo === 1 ? 'tabler-check' : 'tabler-ban'))" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">
              {{ credencialEstadoSeleccionada.accion === 'eliminacion' ? 'Eliminar registro' : (credencialEstadoSeleccionada.estadoNuevo === 2 ? 'Anular credencial' : (credencialEstadoSeleccionada.estadoNuevo === 1 ? 'Habilitar credencial' : 'Inhabilitar credencial')) }}
            </div>
            <div class="text-caption text-medium-emphasis mt-1">Esta acción quedará registrada en el historial</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarCambioEstado">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorEstado" type="error" density="compact" variant="tonal" class="mb-4">
            {{ errorEstado }}
          </VAlert>
          <p class="text-body-1 mb-4">
            ¿Desea {{ credencialEstadoSeleccionada.accion === 'eliminacion' ? 'eliminar' : (credencialEstadoSeleccionada.estadoNuevo === 2 ? 'anular' : (credencialEstadoSeleccionada.estadoNuevo === 1 ? 'habilitar' : 'inhabilitar')) }} este registro?
          </p>
          <VTextField :model-value="credencialEstadoSeleccionada.nombre" label="Acreditado" readonly variant="outlined" class="mb-4" />
          <VAlert v-if="credencialEstadoSeleccionada.estadoNuevo === 2" type="warning" density="compact" variant="tonal" class="mb-4">
            El QR actual quedará revocado definitivamente. Al reactivar esta credencial se generará un QR nuevo.
          </VAlert>
          <VSelect
            v-if="credencialEstadoSeleccionada.estadoNuevo === 2"
            v-model="motivoEstado"
            label="Motivo de anulación *"
            :items="['Robo', 'Extravío', 'Daño irreversible', 'Error administrativo']"
            variant="outlined"
            class="mb-4"
          />
          <VTextarea
            v-else
            v-model="motivoEstado"
            label="Descripción del motivo *"
            placeholder="Indique por qué se realiza este cambio..."
            rows="4"
            auto-grow
            counter="2000"
            maxlength="2000"
            variant="outlined"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn color="secondary" variant="tonal" @click="cerrarCambioEstado">Cancelar</VBtn>
          <VBtn
            :color="credencialEstadoSeleccionada.accion === 'eliminacion' || credencialEstadoSeleccionada.estadoNuevo === 2 ? 'error' : (credencialEstadoSeleccionada.estadoNuevo === 1 ? 'success' : 'warning')"
            :loading="guardandoEstado"
            :disabled="!motivoEstado.trim()"
            @click="guardarCambioEstado"
          >
            {{ credencialEstadoSeleccionada.accion === 'eliminacion' ? 'Eliminar' : (credencialEstadoSeleccionada.estadoNuevo === 2 ? 'Anular' : (credencialEstadoSeleccionada.estadoNuevo === 1 ? 'Habilitar' : 'Inhabilitar')) }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHistorial" max-width="1100" width="95vw" scrollable>
      <VCard class="acreditacion-dialog-card historial-dialog-card">
        <VCardTitle class="acreditacion-dialog-header d-flex align-center pa-5">
          <div class="acreditacion-dialog-icon me-3">
            <VIcon icon="tabler-clipboard-list" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Historial de credencial</div>
            <div class="text-caption text-medium-emphasis mt-1">Creación y cambios de estado registrados</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarHistorial">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="acreditacion-dialog-body pa-5">
          <VAlert v-if="errorHistorial" type="error" variant="tonal" class="mb-4">{{ errorHistorial }}</VAlert>
          <div v-if="cargandoHistorial" class="d-flex justify-center py-8">
            <VProgressCircular indeterminate color="primary" />
          </div>
          <VAlert v-else-if="!historialEventos.length" type="info" variant="tonal">
            No hay movimientos registrados para esta credencial.
          </VAlert>
          <template v-else>
            <section v-for="(evento, indiceEvento) in historialEventos" :key="evento.id_feria" class="historial-evento-bloque">
              <div v-if="indiceEvento" class="historial-evento-divider"><VDivider /></div>
              <div class="historial-evento-titulo">
                <VIcon icon="tabler-calendar-event" size="20" color="primary" />
                <div>
                  <div class="text-caption text-medium-emphasis">Evento / feria</div>
                  <div class="text-h6 font-weight-bold">{{ evento.nombre }}</div>
                </div>
              </div>
              <VTable density="comfortable" class="historial-acciones-table border rounded-lg">
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
                  <tr v-for="registro in evento.acciones" :key="registro.id" class="border-b">
                    <td class="pa-3"><VChip size="small" variant="tonal" :color="registro.es_descarga ? 'info' : 'primary'">{{ registro.accion_label }}</VChip></td>
                    <td class="pa-3"><span v-if="registro.tipo_anterior || registro.tipo_nuevo">{{ registro.tipo_anterior || '—' }} → {{ registro.tipo_nuevo || '—' }}</span><span v-else>—</span><div v-if="registro.version_qr_anterior || registro.version_qr_nueva" class="text-caption text-medium-emphasis">QR v{{ registro.version_qr_anterior || '—' }} → v{{ registro.version_qr_nueva || '—' }}</div></td>
                    <td class="pa-3">{{ registro.estado_anterior }}</td>
                    <td class="pa-3"><VChip v-if="!registro.es_descarga" size="small" variant="tonal" :color="estadoCredencialColor(registro.estado_nuevo)">{{ registro.estado_nuevo }}</VChip><span v-else>—</span></td>
                    <td class="pa-3 historial-motivo">{{ registro.motivo }}</td>
                    <td class="pa-3">{{ registro.usuario }}</td>
                    <td class="pa-3 text-no-wrap">{{ formatoFechaHistorial(registro.fecha) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </section>
          </template>
        </VCardText>
        <VDivider />
        <VCardActions class="acreditacion-dialog-actions pa-5 d-flex justify-end">
          <VBtn color="secondary" variant="tonal" @click="cerrarHistorial">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbarAccion.show"
      :color="snackbarAccion.color"
      location="top end"
    >
      {{ snackbarAccion.text }}
      <template #actions>
        <VBtn variant="text" @click="snackbarAccion.show = false">
          Cerrar
        </VBtn>
      </template>
    </VSnackbar>
  </section>
</template>

<style scoped>
.contrato-credencial-table th {
  white-space: nowrap;
}

.contrato-credencial-table td {
  white-space: nowrap;
  padding-block: 14px;
}

.contrato-credencial-table .foto-acreditado-celda {
  inline-size: 92px;
  padding-inline: 16px;
  text-align: center;
  vertical-align: middle;
}

.foto-acreditado-avatar {
  display: inline-flex;
  margin-block: 5px;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(var(--v-theme-on-surface), 0.12);
}

.historial-acciones-table {
  min-inline-size: 0;
  inline-size: 100%;
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

.historial-acreditados-buscador {
  inline-size: 19rem !important;
  flex: 0 0 19rem !important;
  max-inline-size: 100%;
  margin-inline-start: 1.5rem;
  margin-inline-end: auto;
}

.acreditados-tipo-buscador {
  inline-size: 19rem !important;
  width: 19rem !important;
  flex: 0 0 19rem !important;
  max-inline-size: 19rem !important;
  max-width: 19rem !important;
  margin-inline-start: auto;
}

.acreditados-evento-paginacion {
  border-block-start: 1px solid rgba(var(--v-theme-on-surface), 0.08);
  padding-block-start: 12px;
}

@media (max-width: 700px) {
  .historial-acreditados-buscador {
    inline-size: 100%;
    flex-basis: 100%;
  }

  .acreditados-tipo-buscador {
    inline-size: 100%;
    flex-basis: 100%;
  }
}

.historial-acciones-table th {
  white-space: nowrap;
}

.historial-acciones-table td {
  vertical-align: middle;
}

.historial-motivo {
  min-inline-size: 180px;
  max-inline-size: 260px;
  white-space: normal;
  overflow-wrap: anywhere;
}

.historial-dialog-card {
  max-block-size: 88vh;
}

.acreditado-dato {
  min-block-size: 58px;
  padding: 10px 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 6px;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.acreditado-dato span {
  display: block;
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.75rem;
  line-height: 1.2;
}

.acreditado-dato strong {
  display: block;
  margin-block-start: 5px;
  color: rgba(var(--v-theme-on-surface), 0.88);
  font-size: 0.95rem;
  font-weight: 500;
  overflow-wrap: anywhere;
}

.conflicto-acreditado-contenido {
  inline-size: min(100%, 560px);
  margin-inline: auto;
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

.conflicto-acreditado-alert {
  text-align: center;
  border: 1px solid rgba(var(--v-theme-warning), 0.2);
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

.acreditacion-dialog-card {
  overflow: hidden;
  border-radius: 16px;
}

.acreditacion-dialog-tall {
  display: flex;
  flex-direction: column;
  max-height: 88vh;
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

.acreditacion-photo-picker:disabled {
  cursor: not-allowed;
  opacity: 0.55;
}

.acreditacion-photo-preview {
  width: 100%;
  height: 100%;
}

@media (max-width: 960px) {
  .contrato-credencial-table {
    overflow-x: auto;
  }
}

@media (max-width: 640px) {
  .acreditacion-dialog-header,
  .acreditacion-dialog-body,
  .acreditacion-dialog-actions {
    padding: 16px !important;
  }
}
</style>
