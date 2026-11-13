<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSafePagination } from '@/composables/useSafePagination'
import { useCan } from '@/composables/useCan'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Empresas con credenciales',
    ability: 'credenciales.empresas.ver',
  },
})

const eventos = ref([])
const eventoSeleccionado = ref(null)
const empresas = ref([])
const loadingEventos = ref(false)
const loadingEmpresas = ref(false)
const error = ref('')
const can = useCan()
const route = useRoute()
const router = useRouter()
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const search = ref('')
const tipoCredencialFiltro = ref('Todos')
const dialogAgregar = ref(false)
const buscarEmpresa = ref('')
const empresasEncontradas = ref([])
const loadingBusqueda = ref(false)
let busquedaTimer = null
let busquedaAbortController = null
const loadingTipos = ref(false)
const modoNuevaEmpresa = ref(false)
const empresaSeleccionada = ref(null)
const tiposCredencial = ref([])
const formularioAgregar = ref({
  nombre_empresa: '',
  nit: '',
  usuario: '',
  pass: '',
  tipo_credencial: null,
  tipo_credenciales: null,
  cantidad_credenciales: null,
})
const errorDialog = ref('')
const successDialog = ref('')
const dialogEliminar = ref(false)
const empresaEliminar = ref(null)
const eliminando = ref(false)
const visibilidadPlataformaActualizando = ref({})
const whatsappEnviandoEmpresaId = ref(null)
const emailEnviandoEmpresaId = ref(null)
const notificacionEnvio = ref('')
const dialogHistorialWhatsapp = ref(false)
const empresaHistorialWhatsapp = ref(null)
const historialWhatsapp = ref([])
const historialWhatsappTotal = ref(0)
const historialWhatsappPagina = ref(1)
const historialWhatsappUltimaPagina = ref(1)
const historialWhatsappCargando = ref(false)
const historialWhatsappError = ref('')
const dialogCopiarWhatsapp = ref(false)
const empresaCopiarWhatsapp = ref(null)
const mensajeWhatsappVistaPrevia = ref('')
const telefonoWhatsappVistaPrevia = ref('')
const preparandoCopiaWhatsapp = ref(false)
const copiandoMensajeWhatsapp = ref(false)
const mensajeWhatsappCopiado = ref(false)
const errorCopiarWhatsapp = ref('')

const headers = [
  { title: 'Empresa', key: 'nombre_empresa' },
  { title: 'Tipo', key: 'tipo_credencial' },
  { title: 'Código', key: 'codigo_contrato' },
  { title: 'Acreditados', key: 'credenciales_asignadas', align: 'center' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const tipoCredencialColorMap = {
  Expositor: 'success',
  Oficial: 'info',
  Prensa: 'secondary',
  Servicios: 'warning',
  Negocios: 'deep-purple',
}

const tipoCredencialIconMap = {
  Expositor: 'tabler-users',
  Oficial: 'tabler-id-badge-2',
  Prensa: 'tabler-news',
  Servicios: 'tabler-tools',
  Negocios: 'tabler-briefcase',
}

const getTipoCredencialColor = tipo => tipoCredencialColorMap[String(tipo ?? '').trim()] || 'default'
const getTipoCredencialEstadisticaColor = tipo => ({
  Expositor: 'success',
  Oficial: 'info',
  Prensa: 'secondary',
  Servicios: 'warning',
  Negocios: 'primary',
}[String(tipo ?? '').trim()] || 'primary')
const getTipoCredencialIcon = tipo => tipoCredencialIconMap[String(tipo ?? '').trim()] || 'tabler-id-badge-2'
const getCodigoContratoColor = codigo => (String(codigo ?? '').trim() === 'Sin contrato' ? 'deep-orange' : 'teal')
const tiposCredencialFiltroOpciones = [
  { title: 'Todos', value: 'Todos' },
  { title: 'Expositor', value: 'Expositor' },
  { title: 'Oficial', value: 'Oficial' },
  { title: 'Prensa', value: 'Prensa' },
  { title: 'Servicios', value: 'Servicios' },
  { title: 'Negocios', value: 'Negocios' },
]

const cargarEventos = async () => {
  loadingEventos.value = true
  error.value = ''

  try {
    const response = await fetch('/credenciales/eventos', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los eventos')
    eventos.value = payload.data || []

    const feria = Number(route.query.feria)
    if (feria && eventos.value.some(evento => Number(evento.id_feria) === feria)) {
      eventoSeleccionado.value = feria
      await Promise.all([cargarEmpresas(), cargarTiposCredencial()])
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loadingEventos.value = false
  }
}

const cargarEmpresas = async () => {
  empresas.value = []
  page.value = 1
  if (!eventoSeleccionado.value) return

  loadingEmpresas.value = true
  error.value = ''

  try {
    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/empresas`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar las empresas')
    empresas.value = payload.data || []
  } catch (err) {
    error.value = err.message
  } finally {
    loadingEmpresas.value = false
  }
}

const cargarTiposCredencial = async () => {
  tiposCredencial.value = []
  if (!eventoSeleccionado.value) return

  try {
    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/tipos`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los tipos de credencial.')
    tiposCredencial.value = payload.data || []
  } catch (err) {
    error.value = err.message || 'No se pudieron cargar los tipos de credencial.'
  }
}

const cambiarEvento = async () => {
  page.value = 1
  await Promise.all([cargarEmpresas(), cargarTiposCredencial()])
}

const limpiarFormularioAgregar = () => {
  buscarEmpresa.value = ''
  empresasEncontradas.value = []
  empresaSeleccionada.value = null
  modoNuevaEmpresa.value = false
  errorDialog.value = ''
  successDialog.value = ''
  formularioAgregar.value = {
    nombre_empresa: '', nit: '', usuario: '', pass: '',
    tipo_credencial: null,
    tipo_credenciales: null,
    cantidad_credenciales: null,
  }
}

const generarPass = () => {
  const caracteres = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*+-'
  const aleatorios = crypto.getRandomValues(new Uint8Array(24))

  return Array.from(aleatorios, valor => caracteres[valor % caracteres.length]).join('')
}

const generarUsuario = nombre => {
  const base = String(nombre || 'empresa')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '')
    .slice(0, 10) || 'empresa'

  return base
}

const prepararAccesoNuevaEmpresa = () => {
  const nombre = formularioAgregar.value.nombre_empresa
  formularioAgregar.value.usuario = formularioAgregar.value.nit?.trim()
    ? ''
    : generarUsuario(nombre)
  formularioAgregar.value.pass ||= generarPass()
}

const abrirDialogAgregar = async () => {
  limpiarFormularioAgregar()
  if (eventoSoloDigital.value) formularioAgregar.value.tipo_credenciales = 1
  dialogAgregar.value = true
  loadingTipos.value = true

  try {
    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/tipos`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los tipos de credencial')
    tiposCredencial.value = payload.data || []
  } catch (err) {
    errorDialog.value = err.message
  } finally {
    loadingTipos.value = false
  }
}

const buscarEmpresas = value => {
  const query = String(value || '').trim()
  clearTimeout(busquedaTimer)

  if (busquedaAbortController) {
    busquedaAbortController.abort()
    busquedaAbortController = null
  }

  if (query.length < 2) {
    empresasEncontradas.value = []
    loadingBusqueda.value = false
    return
  }

  busquedaTimer = setTimeout(async () => {
    busquedaAbortController = new AbortController()
    loadingBusqueda.value = true

    try {
      const response = await fetch(`/credenciales/empresas/buscar?q=${encodeURIComponent(query)}`, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal: busquedaAbortController.signal,
      })
      const rawBody = await response.text()
      let payload

      try {
        payload = rawBody ? JSON.parse(rawBody) : null
      } catch {
        throw new Error('El servidor devolvió una respuesta inválida al buscar empresas.')
      }

      if (!response.ok) throw new Error(payload?.message || 'No se pudieron buscar empresas')
      empresasEncontradas.value = payload?.data || []
    } catch (err) {
      if (err.name !== 'AbortError') errorDialog.value = err.message
    } finally {
      loadingBusqueda.value = false
      busquedaAbortController = null
    }
  }, 250)
}

const seleccionarEmpresa = empresa => {
  if (!empresa) {
    empresaSeleccionada.value = null
    modoNuevaEmpresa.value = false
    return
  }

  if (typeof empresa === 'string') {
    empresaSeleccionada.value = empresa
    modoNuevaEmpresa.value = true
    formularioAgregar.value = {
      ...formularioAgregar.value,
      nombre_empresa: empresa,
      nit: '', usuario: generarUsuario(empresa), pass: generarPass(),
    }

    return
  }

  empresaSeleccionada.value = empresa
  modoNuevaEmpresa.value = false
  formularioAgregar.value = {
    ...formularioAgregar.value,
    nombre_empresa: empresa.nombre_empresa || '',
    nit: empresa.nit || '',
    usuario: empresa.usuario || (!empresa.nit ? generarUsuario(empresa.nombre_empresa) : ''),
    pass: '',
  }
}

const esEmpresaNueva = computed(() => typeof empresaSeleccionada.value === 'string' && empresaSeleccionada.value.trim() !== '')

const validarFormularioAgregar = () => {
  if (!eventoSeleccionado.value) return 'Seleccione una feria.'
  if (!empresaSeleccionada.value && !modoNuevaEmpresa.value) return 'Busque una empresa o registre una nueva.'
  if (!String(formularioAgregar.value.nombre_empresa || '').trim()) return 'El nombre de la empresa es obligatorio.'
  if (modoNuevaEmpresa.value) prepararAccesoNuevaEmpresa()
  if (!formularioAgregar.value.nit && !formularioAgregar.value.usuario) return 'Debe existir un NIT o un usuario de acceso.'
  if (modoNuevaEmpresa.value && !formularioAgregar.value.pass) return 'La contraseña de acceso es obligatoria.'
  if (!formularioAgregar.value.tipo_credencial) return 'Seleccione un tipo de credencial.'
  if (!eventoSoloDigital.value && ![0, 1].includes(Number(formularioAgregar.value.tipo_credenciales))) return 'Seleccione si la credencial será Física o Digital.'
  if (!Number.isInteger(Number(formularioAgregar.value.cantidad_credenciales)) || Number(formularioAgregar.value.cantidad_credenciales) <= 0) return 'La cantidad debe ser un número entero mayor a 0.'
  return ''
}

const obtenerCsrfToken = async () => {
  const response = await fetch('/csrf-token', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })
  const payload = await response.json()
  const meta = document.querySelector('meta[name="csrf-token"]')

  if (payload?.token && meta)
    meta.setAttribute('content', payload.token)

  return payload?.token || meta?.getAttribute('content') || ''
}

const guardarAgregarEmpresa = () => {
  errorDialog.value = validarFormularioAgregar()
  successDialog.value = ''
  if (errorDialog.value) return

  guardarEmpresa()
}

const guardarEmpresa = async () => {
  try {
    const body = JSON.stringify({
        id_feria: eventoSeleccionado.value,
        id_empresa: typeof empresaSeleccionada.value === 'object' ? empresaSeleccionada.value?.id_empresa : null,
        nombre_empresa: formularioAgregar.value.nombre_empresa,
        nit: formularioAgregar.value.nit || null,
        usuario: formularioAgregar.value.usuario || null,
        pass: formularioAgregar.value.pass || null,
        tipo_credencial: formularioAgregar.value.tipo_credencial,
        tipo_credenciales: eventoSoloDigital.value ? 1 : Number(formularioAgregar.value.tipo_credenciales),
        cantidad_credenciales: Number(formularioAgregar.value.cantidad_credenciales),
    })
    let csrfToken = await obtenerCsrfToken()
    let response = await fetch('/credenciales/empresas', {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
      body,
    })

    if (response.status === 419) {
      csrfToken = await obtenerCsrfToken()
      response = await fetch('/credenciales/empresas', {
        method: 'POST',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': csrfToken,
        },
        credentials: 'same-origin',
        body,
      })
    }

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo guardar la empresa')
    successDialog.value = modoNuevaEmpresa.value
      ? `${payload.message} La contraseña temporal permanece visible en este formulario; compártala por un canal seguro.`
      : payload.message
    await cargarEmpresas()
  } catch (err) {
    errorDialog.value = err.message
  }
}

watch(dialogAgregar, abierto => {
  if (!abierto) limpiarFormularioAgregar()
})

const abrirDialogEliminar = (empresa, vinculacion) => {
  if (
    !vinculacion?.id_vinculacion
    || vinculacion.id_contrato !== null
    || Number(vinculacion.credenciales_utilizadas ?? 0) !== 0
    || !can('credenciales.empresas.eliminar')
  ) return

  empresaEliminar.value = { ...empresa, ...vinculacion, nombre_empresa: empresa.nombre_empresa }
  dialogEliminar.value = true
}

const eliminarVinculacion = async () => {
  if (!empresaEliminar.value?.id_vinculacion || eliminando.value) return

  eliminando.value = true
  try {
    const csrfToken = await obtenerCsrfToken()
    const response = await fetch(`/credenciales/empresas/vinculaciones/${empresaEliminar.value.id_vinculacion}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo eliminar la vinculación')
    dialogEliminar.value = false
    empresaEliminar.value = null
    await cargarEmpresas()
  } catch (err) {
    error.value = err.message
  } finally {
    eliminando.value = false
  }
}

const cambiarVisibilidadPlataforma = async (vinculacion, visible) => {
  const id = Number(vinculacion?.id_vinculacion)
  if (
    !id
    || vinculacion.id_contrato !== null
    || typeof visible !== 'boolean'
    || !can('credenciales.empresas.plataforma')
    || visibilidadPlataformaActualizando.value[id]
  ) return

  visibilidadPlataformaActualizando.value = { ...visibilidadPlataformaActualizando.value, [id]: true }

  try {
    const csrfToken = await obtenerCsrfToken()
    const response = await fetch(`/credenciales/empresas/vinculaciones/${id}/visibilidad-plataforma`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
      body: JSON.stringify({ visible_en_plataforma: visible }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo actualizar la visibilidad en Plataforma')

    vinculacion.visible_en_plataforma = Boolean(payload.data?.visible_en_plataforma)
    notificacionEnvio.value = payload.message
  } catch (err) {
    error.value = err.message
  } finally {
    const pendientes = { ...visibilidadPlataformaActualizando.value }
    delete pendientes[id]
    visibilidadPlataformaActualizando.value = pendientes
  }
}

const crearMensajeWhatsapp = datos => [
  `Hola, te estamos contactando para que puedas ingresar al Sistema de Acreditación del evento ${datos.nombre_feria}.`,
  `- Nombre Empresa: ${datos.nombre_empresa}`,
  `- ${datos.tipo_usuario || 'Usuario'}: ${datos.usuario}`,
  `- Contraseña: ${datos.password}`,
  `- Cantidad de credenciales: ${datos.cantidad_credenciales}`,
  '- Link: https://acreditacion.fexco.com.bo/',
  'Si tienes alguna duda, no dudes en escribirme.',
].join('\n')

const copiarTextoAlPortapapeles = async texto => {
  try {
    await navigator.clipboard.writeText(texto)

    return true
  } catch {
    let copiado = false
    let textarea

    try {
      textarea = document.createElement('textarea')
      textarea.value = texto
      textarea.setAttribute('readonly', '')
      textarea.style.position = 'absolute'
      textarea.style.left = '-9999px'
      document.body.appendChild(textarea)
      textarea.select()
      copiado = document.execCommand('copy')
    } catch {
      copiado = false
    } finally {
      textarea?.remove()
    }

    return copiado
  }
}

const enviarAccesosWhatsapp = async item => {
  if (!eventoSeleccionado.value || !item?.id_empresa || whatsappEnviandoEmpresaId.value) return

  const ventanaWhatsapp = window.open('', '_blank')
  if (!ventanaWhatsapp) {
    error.value = 'El navegador bloqueó la ventana de WhatsApp. Permite las ventanas emergentes e inténtalo nuevamente.'

    return
  }

  ventanaWhatsapp.opener = null
  whatsappEnviandoEmpresaId.value = item.id_empresa
  error.value = ''

  try {
    let csrfToken = await obtenerCsrfToken()

    const url = `/credenciales/eventos/${eventoSeleccionado.value}/empresas/${item.id_empresa}/whatsapp`

    const opciones = {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    }

    let response = await fetch(url, opciones)

    if (response.status === 419) {
      csrfToken = await obtenerCsrfToken()
      opciones.headers['X-CSRF-TOKEN'] = csrfToken
      response = await fetch(url, opciones)
    }

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) throw new Error(payload.message || 'No se pudo preparar el mensaje de WhatsApp.')

    const datos = payload.data
    const mensaje = crearMensajeWhatsapp(datos)

    const enlaceWhatsapp = `https://wa.me/${datos.telefono}?text=${encodeURIComponent(mensaje)}`

    ventanaWhatsapp.location.href = enlaceWhatsapp

    item.whatsappEnvios = Number(item.whatsappEnvios || 0) + 1
  } catch (err) {
    ventanaWhatsapp.close()
    error.value = err.message || 'No se pudo iniciar WhatsApp.'
  } finally {
    whatsappEnviandoEmpresaId.value = null
  }
}

const enviarAccesosEmail = async item => {
  if (!eventoSeleccionado.value || !item?.id_empresa || emailEnviandoEmpresaId.value) return

  emailEnviandoEmpresaId.value = item.id_empresa
  error.value = ''
  notificacionEnvio.value = ''

  try {
    let csrfToken = await obtenerCsrfToken()

    const url = `/credenciales/eventos/${eventoSeleccionado.value}/empresas/${item.id_empresa}/email`

    const opciones = {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    }

    let response = await fetch(url, opciones)

    if (response.status === 419) {
      csrfToken = await obtenerCsrfToken()
      opciones.headers['X-CSRF-TOKEN'] = csrfToken
      response = await fetch(url, opciones)
    }

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || 'No se pudo enviar el correo.')

    item.emailEnvios = Number(item.emailEnvios || 0) + 1
    notificacionEnvio.value = 'El servicio de correo aceptó el mensaje. Esto no confirma que haya llegado a la bandeja del destinatario.'

    if (dialogHistorialWhatsapp.value && empresaHistorialWhatsapp.value?.id_empresa === item.id_empresa) {
      await cargarHistorialWhatsapp(item, historialWhatsappPagina.value)
    }
  } catch (err) {
    error.value = err.message || 'No se pudo enviar el correo.'
  } finally {
    emailEnviandoEmpresaId.value = null
  }
}

const abrirDialogCopiarWhatsapp = async item => {
  if (!eventoSeleccionado.value || !item?.id_empresa || preparandoCopiaWhatsapp.value) return

  empresaCopiarWhatsapp.value = item
  mensajeWhatsappVistaPrevia.value = ''
  telefonoWhatsappVistaPrevia.value = ''
  mensajeWhatsappCopiado.value = false
  errorCopiarWhatsapp.value = ''
  dialogCopiarWhatsapp.value = true
  preparandoCopiaWhatsapp.value = true

  try {
    const csrfToken = await obtenerCsrfToken()

    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/empresas/${item.id_empresa}/whatsapp/preparar-copia`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) throw new Error(payload.message || 'No se pudo preparar el mensaje para copiar.')

    mensajeWhatsappVistaPrevia.value = crearMensajeWhatsapp(payload.data)
    telefonoWhatsappVistaPrevia.value = payload.data.telefono
  } catch (err) {
    errorCopiarWhatsapp.value = err.message || 'No se pudo preparar el mensaje para copiar.'
  } finally {
    preparandoCopiaWhatsapp.value = false
  }
}

const copiarMensajeWhatsapp = async () => {
  if (!empresaCopiarWhatsapp.value || !mensajeWhatsappVistaPrevia.value || copiandoMensajeWhatsapp.value) return

  copiandoMensajeWhatsapp.value = true
  errorCopiarWhatsapp.value = ''

  try {
    const copiado = await copiarTextoAlPortapapeles(mensajeWhatsappVistaPrevia.value)
    if (!copiado) throw new Error('El navegador no pudo copiar el mensaje.')
  } catch (err) {
    errorCopiarWhatsapp.value = 'No se pudo copiar el mensaje. Selecciónalo y cópialo manualmente.'
    copiandoMensajeWhatsapp.value = false

    return
  }

  try {
    let csrfToken = await obtenerCsrfToken()

    const url = `/credenciales/eventos/${eventoSeleccionado.value}/empresas/${empresaCopiarWhatsapp.value.id_empresa}/whatsapp/copiar`

    const opciones = {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
    }

    let response = await fetch(url, opciones)

    if (response.status === 419) {
      csrfToken = await obtenerCsrfToken()
      opciones.headers['X-CSRF-TOKEN'] = csrfToken
      response = await fetch(url, opciones)
    }

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(payload.message || 'El mensaje se copió, pero no se pudo registrar la acción en el historial.')
    }

    mensajeWhatsappCopiado.value = true
    empresaCopiarWhatsapp.value.whatsappEnvios = Number(empresaCopiarWhatsapp.value.whatsappEnvios || 0) + 1
  } catch (err) {
    errorCopiarWhatsapp.value = `El mensaje se copió al portapapeles, pero no se pudo guardar en el historial: ${err.message || 'error desconocido'}.`
  } finally {
    copiandoMensajeWhatsapp.value = false
  }
}

const cargarHistorialWhatsapp = async (item, page = 1) => {
  if (!eventoSeleccionado.value || !item?.id_empresa) return

  historialWhatsappCargando.value = true
  historialWhatsappError.value = ''

  try {
    const parametros = new URLSearchParams({ page: String(page) })

    parametros.set('per_page', '50')

    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/empresas/${item.id_empresa}/envios/historial?${parametros}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const payload = await response.json().catch(() => ({}))

    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial de envíos.')

    historialWhatsapp.value = payload.data || []
    historialWhatsappTotal.value = Number(payload.meta?.total || 0)
    historialWhatsappPagina.value = Number(payload.meta?.current_page || 1)
    historialWhatsappUltimaPagina.value = Number(payload.meta?.last_page || 1)
  } catch (err) {
    historialWhatsappError.value = err.message || 'No se pudo cargar el historial de envíos.'
  } finally {
    historialWhatsappCargando.value = false
  }
}

const abrirHistorialWhatsapp = async item => {
  empresaHistorialWhatsapp.value = item
  historialWhatsapp.value = []
  historialWhatsappTotal.value = 0

  historialWhatsappPagina.value = 1
  historialWhatsappUltimaPagina.value = 1
  dialogHistorialWhatsapp.value = true
  await cargarHistorialWhatsapp(item)
}

const formatoFechaWhatsapp = fecha => {
  if (!fecha) return '—'

  const valor = new Date(fecha)

  return Number.isNaN(valor.getTime()) ? fecha : valor.toLocaleString('es-BO')
}

const etiquetaAccionEnvio = envio => {
  if (envio.accion === 'copiado') return 'Mensaje copiado'
  if (envio.accion === 'abierto') return 'WhatsApp abierto'
  if (envio.accion === 'procesando') return 'En proceso'
  if (envio.accion === 'enviado') return 'Correo aceptado para envío'
  if (envio.accion === 'error') return 'Error de envío'

  return envio.accion
}

const verDetalle = item => {
  if (!eventoSeleccionado.value || !item?.id_empresa) return

  router.push(`/credenciales/empresas/${eventoSeleccionado.value}/${item.id_empresa}`)
}

const exportarEmpresasCredenciales = () => {
  if (!eventoSeleccionado.value) return

  const parametros = new URLSearchParams()
  if (tipoCredencialFiltro.value !== 'Todos') parametros.set('tipo_credencial', tipoCredencialFiltro.value)

  window.open(`/credenciales/eventos/${eventoSeleccionado.value}/empresas/exportar?${parametros.toString()}`, '_blank', 'noopener')
}

const empresasFiltradas = computed(() => {
  const term = search.value.trim().toLowerCase()
  const tipoFiltro = tipoCredencialFiltro.value

  return empresas.value.filter(item => {
    const coincideTipo = tipoFiltro === 'Todos'
      || String(item.tipo_credencial ?? '').trim() === String(tipoFiltro).trim()
      || item.contratos?.some(contrato => String(contrato.tipo_credencial ?? '').trim() === String(tipoFiltro).trim())

    if (!coincideTipo) return false

    if (!term) return true

    return [
      item.nombre_empresa,
      item.codigo_contrato,
      item.tipo_credencial,
    ].some(value => String(value ?? '').toLowerCase().includes(term))
  })
})

const resumenAcreditacion = computed(() => {
  const stats = { completa: 0, parcial: 0, ninguna: 0 }

  for (const item of empresasFiltradas.value) {
    const usadas = Number(item.credenciales_utilizadas ?? 0)
    const asignadas = Number(item.credenciales_asignadas ?? 0)

    if (asignadas > 0 && usadas >= asignadas) {
      stats.completa++
    }
    else if (asignadas > 0 && usadas > 0 && usadas < asignadas) {
      stats.parcial++
    }
    else {
      stats.ninguna++
    }
  }

  return stats
})

const resumenPorTipoCredencial = computed(() => tiposCredencial.value.map(tipo => {
  const nombreTipo = tipo.value || tipo.tipo_credencial || tipo.title
  const registros = empresas.value.flatMap(empresa => empresa.contratos?.length ? empresa.contratos : [empresa])
    .filter(registro => String(registro.tipo_credencial || '').trim() === String(nombreTipo || '').trim())
  const asignadas = registros.reduce((total, registro) => total + Number(registro.credenciales_asignadas || 0), 0)
  const acreditadas = registros.reduce((total, registro) => total + Number(registro.credenciales_utilizadas || 0), 0)

  return {
    tipo: nombreTipo,
    color: getTipoCredencialEstadisticaColor(nombreTipo),
    icon: getTipoCredencialIcon(nombreTipo),
    asignadas,
    acreditadas,
    disponibles: Math.max(0, asignadas - acreditadas),
  }
}))

const feriaSeleccionadaInfo = computed(() => {
  if (!eventoSeleccionado.value) return null

  return eventos.value.find(feria => Number(feria.id_feria) === Number(eventoSeleccionado.value)) || null
})
const eventoSoloDigital = computed(() => Number(eventoSeleccionado.value) === 19)

const empresasPaginadas = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value

  return empresasFiltradas.value.slice(start, start + itemsPerPage.value)
})

const tieneEmpresasFiltradas = computed(() => empresasFiltradas.value.length > 0)

watch([search, tipoCredencialFiltro], () => {
  page.value = 1
})

onMounted(cargarEventos)
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">Gestión de Credenciales</h4>
          </div>
        </div>

        <VRow class="align-center">
          <VCol cols="12" md="6">
            <VSelect
              v-model="eventoSeleccionado"
              :items="eventos"
              :loading="loadingEventos"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Seleccionar Evento / Feria"
              placeholder="Elige una feria para ver las empresas"
              clearable
              @update:model-value="cambiarEvento"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" />
              </template>
            </VSelect>
          </VCol>

          <VCol v-if="feriaSeleccionadaInfo" cols="12" md="6">
            <VAlert
              type="info"
              variant="tonal"
              class="mb-0"
            >
              <div class="d-flex align-center gap-2">
                <div>
                  <strong>{{ feriaSeleccionadaInfo.nombre_feria }}</strong>
                  <div class="text-caption">
                    Acreditadas 100%: {{ resumenAcreditacion.completa }} | Parcial: {{ resumenAcreditacion.parcial }} | Ninguna: {{ resumenAcreditacion.ninguna }}
                  </div>
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
        <VAlert
          v-if="notificacionEnvio"
          type="success"
          variant="tonal"
          class="mt-4"
          closable
          @click:close="notificacionEnvio = ''"
        >
          {{ notificacionEnvio }}
        </VAlert>
      </VCardText>
    </VCard>

    <VRow v-if="eventoSeleccionado && resumenPorTipoCredencial.length" class="mb-6">
      <VCol
        v-for="resumen in resumenPorTipoCredencial"
        :key="resumen.tipo"
        cols="12"
        sm="6"
        md="4"
        lg="3"
      >
        <VCard
          class="credencial-tipo-estadistica"
          :style="`border-block-end-color: rgba(var(--v-theme-${resumen.color}), 0.38)`"
        >
          <VCardText>
            <div class="d-flex align-center gap-4 mb-2">
              <VAvatar variant="tonal" :color="resumen.color" rounded>
                <VIcon :icon="resumen.icon" size="28" />
              </VAvatar>
              <h4 class="text-h4">{{ resumen.acreditadas }}</h4>
            </div>
            <div class="text-body-1 mb-2">{{ resumen.tipo }}</div>
            <div class="d-flex gap-2 align-center text-body-2">
              <span><strong>{{ resumen.asignadas }}</strong> asignadas</span>
              <span class="text-disabled">|</span>
              <span class="text-success"><strong>{{ resumen.disponibles }}</strong> disponibles</span>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VCard v-if="eventoSeleccionado" id="credenciales-empresas-list">
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

        <div class="d-flex align-center flex-wrap gap-4">
          <div class="credenciales-list-filter">
            <AppTextField
              v-model="search"
              placeholder="Buscar empresa o contrato..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              outlined
            />
          </div>
          <div class="credenciales-list-filter-type">
            <VSelect
              v-model="tipoCredencialFiltro"
              :items="tiposCredencialFiltroOpciones"
              item-title="title"
              item-value="value"
              label="Tipo"
              variant="outlined"
              density="comfortable"
              hide-details
              style="min-inline-size: 170px;"
            />
          </div>
          <VTooltip
            v-if="can('credenciales.empresas.exportar')"
            location="top"
          >
            <template #activator="{ props }">
              <VBtn
                v-bind="props"
                icon
                size="small"
                variant="text"
                color="success"
                @click="exportarEmpresasCredenciales"
              >
                <VIcon icon="tabler-file-spreadsheet" />
              </VBtn>
            </template>
            <span>Exportar empresas y credenciales a Excel</span>
          </VTooltip>
          <VBtn v-if="can('credenciales.empresas.crear')" color="primary" variant="flat" prepend-icon="tabler-plus" @click="abrirDialogAgregar">
            Agregar Empresa
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VCardText class="pt-4">
        <h5 class="text-h5 mb-4">Empresas con contratos</h5>

        <div v-if="loadingEmpresas" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" size="64" />
          <p class="text-body-1 mt-4">Cargando empresas...</p>
        </div>

        <VAlert v-else-if="!tieneEmpresasFiltradas" type="info" variant="tonal">
          No hay empresas con contrato para este evento.
        </VAlert>

        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :items-length="empresasFiltradas.length"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="empresasPaginadas"
          :loading="loadingEmpresas"
          item-value="id_contrato"
          class="text-no-wrap"
        >
          <template #item.nombre_empresa="{ item }">
            <div class="empresa-cell">
              <div class="font-weight-medium">{{ item.nombre_empresa || '—' }}</div>
              <div
                v-for="contrato in item.contratos"
                :key="`ubicacion-${contrato.id_contrato}-${contrato.tipo_credencial}-${contrato.codigo_contrato}`"
                class="empresa-ubicacion"
              >
                <span v-if="contrato.pabellon && contrato.stands?.length">
                  {{ contrato.pabellon }} | Stand: {{ contrato.stands.join(', ') }}
                </span>
                <span v-else>Sin ubicación</span>
              </div>
            </div>
          </template>
          <template #item.tipo_credencial="{ item }">
            <div class="credenciales-tipos-columna">
              <div
                v-for="contrato in item.contratos"
                :key="`tipo-${contrato.id_vinculacion ?? contrato.id_contrato}-${contrato.tipo_credencial}`"
                class="credencial-tipo-plataforma"
              >
                <VChip
                  :color="getTipoCredencialColor(contrato.tipo_credencial)"
                  variant="tonal"
                  size="small"
                >
                  {{ contrato.tipo_credencial }}
                </VChip>
                <div class="d-flex align-center gap-1">
                  <VTooltip
                    v-if="contrato.id_vinculacion && contrato.id_contrato === null && can('credenciales.empresas.plataforma')"
                    location="top"
                  >
                    <template #activator="{ props }">
                      <VSwitch
                        v-bind="props"
                        :model-value="Boolean(contrato.visible_en_plataforma)"
                        :loading="visibilidadPlataformaActualizando[contrato.id_vinculacion]"
                        :disabled="!!visibilidadPlataformaActualizando[contrato.id_vinculacion]"
                        color="success"
                        density="compact"
                        hide-details
                        label="Plataforma"
                        :aria-label="`${contrato.visible_en_plataforma ? 'Ocultar' : 'Mostrar'} ${contrato.tipo_credencial} en Plataforma`"
                        @update:model-value="cambiarVisibilidadPlataforma(contrato, $event)"
                      />
                    </template>
                    <span>{{ contrato.visible_en_plataforma ? `Ocultar ${contrato.tipo_credencial} en Plataforma` : `Mostrar ${contrato.tipo_credencial} en Plataforma` }}</span>
                  </VTooltip>
                  <VTooltip
                    v-if="contrato.id_vinculacion && contrato.id_contrato === null && Number(contrato.credenciales_utilizadas ?? 0) === 0 && can('credenciales.empresas.eliminar')"
                    location="top"
                  >
                    <template #activator="{ props }">
                      <VBtn
                        v-bind="props"
                        icon
                        variant="text"
                        size="small"
                        color="error"
                        :aria-label="`Eliminar vínculo ${contrato.tipo_credencial} de ${item.nombre_empresa}`"
                        @click="abrirDialogEliminar(item, contrato)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </template>
                    <span>Eliminar vínculo de {{ contrato.tipo_credencial }}</span>
                  </VTooltip>
                </div>
              </div>
              <VChip v-if="item.contratos.length === 0" :color="getTipoCredencialColor(item.tipo_credencial)" variant="tonal" size="small">
                {{ item.tipo_credencial }}
              </VChip>
            </div>
          </template>
          <template #item.codigo_contrato="{ item }">
            <div class="credenciales-codigos-columna">
              <VChip
                v-for="contrato in item.contratos"
                :key="`codigo-${contrato.id_contrato}-${contrato.tipo_credencial}-${contrato.codigo_contrato}`"
                :color="getCodigoContratoColor(contrato.codigo_contrato)"
                variant="tonal"
                size="small"
              >
                {{ contrato.codigo_contrato }}
              </VChip>
              <span v-if="item.contratos.length === 0">Sin contrato</span>
            </div>
          </template>
          <template #item.credenciales_asignadas="{ item }">
            <div class="credenciales-acreditados-columna">
              <span v-for="contrato in item.contratos" :key="`acreditados-${contrato.id_contrato}-${contrato.tipo_credencial}-${contrato.codigo_contrato}`">
                {{ contrato.credenciales_utilizadas ?? 0 }} / {{ contrato.credenciales_asignadas ?? 0 }}
              </span>
              <span v-if="item.contratos.length === 0">
                {{ item.credenciales_utilizadas ?? 0 }} / {{ item.credenciales_asignadas ?? 0 }}
              </span>
            </div>
          </template>
          <template #item.actions="{ item }">
            <div class="d-flex gap-1">
              <VTooltip location="top">
                <template #activator="{ props }">
                  <VBtn v-bind="props" icon variant="text" size="small" color="primary" @click="verDetalle(item)">
                    <VIcon icon="tabler-eye" />
                  </VBtn>
                </template>
                <span>Ver empresa y contrato</span>
              </VTooltip>
              <VTooltip v-if="can('credenciales.empresas.whatsapp.enviar')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="text"
                    size="small"
                    color="success"
                    :loading="whatsappEnviandoEmpresaId === item.id_empresa"
                    :disabled="!!whatsappEnviandoEmpresaId"
                    :aria-label="`Enviar accesos por WhatsApp a ${item.nombre_empresa}`"
                    @click="enviarAccesosWhatsapp(item)"
                  >
                    <VIcon icon="tabler-brand-whatsapp" />
                  </VBtn>
                </template>
                <span>Preparar accesos en WhatsApp</span>
              </VTooltip>
              <VTooltip v-if="can('credenciales.empresas.email.enviar')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="text"
                    size="small"
                    color="primary"
                    :loading="emailEnviandoEmpresaId === item.id_empresa"
                    :disabled="!!emailEnviandoEmpresaId"
                    :aria-label="`Enviar accesos por correo a ${item.nombre_empresa}`"
                    @click="enviarAccesosEmail(item)"
                  >
                    <VIcon icon="tabler-mail-forward" />
                  </VBtn>
                </template>
                <span>Enviar accesos al correo del contacto</span>
              </VTooltip>
              <VTooltip v-if="can('credenciales.empresas.whatsapp.enviar')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="text"
                    size="small"
                    color="secondary"
                    :loading="preparandoCopiaWhatsapp && empresaCopiarWhatsapp?.id_empresa === item.id_empresa"
                    :disabled="preparandoCopiaWhatsapp || copiandoMensajeWhatsapp"
                    :aria-label="`Copiar mensaje de WhatsApp para ${item.nombre_empresa}`"
                    @click="abrirDialogCopiarWhatsapp(item)"
                  >
                    <VIcon icon="tabler-copy" />
                  </VBtn>
                </template>
                <span>Ver y copiar mensaje de WhatsApp</span>
              </VTooltip>
              <VTooltip v-if="can('credenciales.empresas.whatsapp.ver') || can('credenciales.empresas.email.ver')" location="top">
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    icon
                    variant="text"
                    size="small"
                    color="info"
                    :aria-label="`Ver historial de envíos de ${item.nombre_empresa}`"
                    @click="abrirHistorialWhatsapp(item)"
                  >
                    <VBadge
                      :content="Number(item.whatsappEnvios || 0) + Number(item.emailEnvios || 0)"
                      :model-value="Number(item.whatsappEnvios || 0) + Number(item.emailEnvios || 0) > 0"
                      color="info"
                    >
                      <VIcon icon="tabler-history" />
                    </VBadge>
                  </VBtn>
                </template>
                <span>Historial: WhatsApp {{ item.whatsappEnvios || 0 }} acciones · Correo {{ item.emailEnvios || 0 }} envíos</span>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VCard v-else>
      <VCardText class="text-center py-12">
        <VIcon icon="tabler-id-badge-2" size="64" color="disabled" class="mb-4" />
        <h5 class="text-h5 mb-2">Selecciona una Feria</h5>
        <p class="text-body-1 text-medium-emphasis">
          Elige un evento/feria del selector superior para visualizar sus empresas
        </p>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogCopiarWhatsapp" max-width="680" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-copy" color="secondary" />
          <span>Copiar mensaje para WhatsApp</span>
          <VSpacer />
          <VBtn icon variant="text" size="small" @click="dialogCopiarWhatsapp = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText>
          <div class="text-subtitle-1 font-weight-medium mb-1">{{ empresaCopiarWhatsapp?.nombre_empresa }}</div>
          <div v-if="telefonoWhatsappVistaPrevia" class="text-body-2 mb-4">
            <strong>Contacto:</strong> +{{ telefonoWhatsappVistaPrevia }}
          </div>

          <VAlert type="info" variant="tonal" class="mb-4">
            El número tiene formato válido, pero el sistema no puede comprobar si está registrado en WhatsApp. Al copiar, se registrará la acción; esto no confirma que el mensaje se haya enviado.
          </VAlert>
          <VAlert v-if="errorCopiarWhatsapp" type="error" variant="tonal" class="mb-4">
            {{ errorCopiarWhatsapp }}
          </VAlert>
          <VAlert v-if="mensajeWhatsappCopiado" type="success" variant="tonal" class="mb-4">
            Mensaje copiado y acción registrada en el historial. Pégalo en WhatsApp para enviarlo.
          </VAlert>

          <div v-if="preparandoCopiaWhatsapp" class="text-center py-6">
            <VProgressCircular indeterminate color="primary" />
            <div class="text-body-2 mt-3">Preparando el mensaje...</div>
          </div>
          <VTextarea
            v-else-if="mensajeWhatsappVistaPrevia"
            :model-value="mensajeWhatsappVistaPrevia"
            label="Vista previa del mensaje"
            rows="9"
            readonly
            auto-grow
            hide-details
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="text" @click="dialogCopiarWhatsapp = false">Cerrar</VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            prepend-icon="tabler-copy"
            :loading="copiandoMensajeWhatsapp"
            :disabled="!mensajeWhatsappVistaPrevia || preparandoCopiaWhatsapp"
            @click="copiarMensajeWhatsapp"
          >
            {{ mensajeWhatsappCopiado ? 'Copiar nuevamente' : 'Copiar mensaje' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogAgregar" max-width="760" scrollable>
      <VCard class="credencial-dialog-card credencial-dialog-tall">
        <VCardTitle class="credencial-dialog-header d-flex align-center pa-5">
          <div class="credencial-dialog-icon me-3">
            <VIcon icon="tabler-building-plus" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Agregar Empresa</div>
            <div class="text-caption text-medium-emphasis mt-1">Configura la empresa y sus credenciales para el evento</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="dialogAgregar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="credencial-dialog-body pa-5">
          <VAlert v-if="errorDialog" type="error" variant="tonal" class="mb-4">{{ errorDialog }}</VAlert>
          <VAlert v-if="successDialog" type="success" variant="tonal" class="mb-4">{{ successDialog }}</VAlert>

          <div class="credencial-section-title mb-3">
            <VIcon icon="tabler-building-search" size="20" class="me-2" />
            Empresa
          </div>
          <VCombobox
            v-model="empresaSeleccionada"
            v-model:search="buscarEmpresa"
            :items="empresasEncontradas"
            item-title="nombre_empresa"
            item-value="id_empresa"
            return-object
            label="Buscar empresa existente o escribir nueva"
            placeholder="Escribe o selecciona el nombre de la empresa..."
            :loading="loadingBusqueda"
            clearable
            no-data-text="No se encontraron empresas"
            @update:search="buscarEmpresas"
            @update:model-value="seleccionarEmpresa"
          >
            <template #prepend-inner>
              <VIcon icon="tabler-building" />
            </template>
            <template #item="{ props, item }">
              <VListItem v-bind="props" :title="item.raw.nombre_empresa">
                <template #subtitle>
                  <div class="text-caption">
                    <span v-if="item.raw.nombre_responsable">Responsable: {{ item.raw.nombre_responsable }}</span>
                    <span v-if="item.raw.nombre_gerente" class="ml-2">| Gerente: {{ item.raw.nombre_gerente }}</span>
                  </div>
                </template>
              </VListItem>
            </template>
          </VCombobox>

          <div class="text-caption text-medium-emphasis mt-2">
            <VIcon icon="tabler-info-circle" size="small" />
            Puedes seleccionar una empresa existente o escribir el nombre de una nueva empresa
          </div>

          <VAlert v-if="empresaSeleccionada" :type="esEmpresaNueva ? 'info' : 'success'" variant="tonal" class="mt-4">
            <div class="d-flex align-center gap-2">
              <VIcon :icon="esEmpresaNueva ? 'tabler-plus' : 'tabler-check'" />
              <div>
                <strong>{{ esEmpresaNueva ? 'Nueva empresa' : 'Empresa existente' }}</strong>
                <div v-if="esEmpresaNueva" class="text-caption">
                  Se creará automáticamente: "{{ empresaSeleccionada }}"
                </div>
              </div>
            </div>
          </VAlert>

          <VRow v-if="modoNuevaEmpresa || empresaSeleccionada" class="mt-2">
                    <VCol cols="12" md="6"><VTextField v-model="formularioAgregar.nombre_empresa" label="Nombre / Razón social" :readonly="!!empresaSeleccionada" /></VCol>
                    <VCol cols="12" md="6">
                      <VTextField
                        v-model="formularioAgregar.nit"
                        label="NIT / identificador de acceso"
                        :readonly="!!empresaSeleccionada"
                        hint="Si no existe NIT, se utilizará un usuario generado."
                        persistent-hint
                      />
                    </VCol>
                    <VCol cols="12" md="6"><VTextField v-model="formularioAgregar.usuario" label="Usuario de acceso" readonly /></VCol>
                    <VCol v-if="modoNuevaEmpresa" cols="12" md="6"><VTextField v-model="formularioAgregar.pass" label="Contraseña temporal de acceso" readonly /></VCol>
          </VRow>

          <VDivider class="my-5" />
          <div class="credencial-section-title mb-3">
            <VIcon icon="tabler-id-badge-2" size="20" class="me-2" />
            Credenciales del evento
          </div>
          <VRow>
            <VCol cols="12" md="4"><VSelect v-model="formularioAgregar.tipo_credencial" :items="tiposCredencial" item-title="title" item-value="value" label="Tipo de credencial" placeholder="Seleccionar tipo de credencial" :loading="loadingTipos" :disabled="!tiposCredencial.length" /></VCol>
            <VCol v-if="eventoSoloDigital" cols="12" md="4"><VTextField model-value="Digital" label="Modalidad" readonly /></VCol>
            <VCol v-else cols="12" md="4"><VSelect v-model="formularioAgregar.tipo_credenciales" :items="[{ value: 0, title: 'Física' }, { value: 1, title: 'Digital' }]" label="Modalidad" placeholder="Seleccionar modalidad" /></VCol>
            <VCol cols="12" md="4"><VTextField v-model.number="formularioAgregar.cantidad_credenciales" label="Cantidad de credenciales" type="number" min="1" step="1" /></VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="credencial-dialog-actions pa-5 d-flex justify-end gap-2">
          <VSpacer />
          <VBtn color="secondary" variant="flat" @click="dialogAgregar = false">{{ successDialog ? 'Cerrar' : 'Cancelar' }}</VBtn>
          <VBtn v-if="can('credenciales.empresas.crear')" color="primary" variant="flat" :disabled="!!successDialog" @click="guardarAgregarEmpresa">Guardar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHistorialWhatsapp" max-width="760" scrollable>
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-history" color="info" />
          <span>Historial de envíos</span>
          <VSpacer />
          <VBtn icon variant="text" size="small" @click="dialogHistorialWhatsapp = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText>
          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-medium">{{ empresaHistorialWhatsapp?.nombre_empresa }}</div>
            <div class="text-body-2 text-medium-emphasis">
              {{ historialWhatsappTotal }} registro{{ historialWhatsappTotal === 1 ? '' : 's' }}
            </div>
          </div>

          <VAlert v-if="historialWhatsappError" type="error" variant="tonal" class="mb-4">
            {{ historialWhatsappError }}
          </VAlert>

          <div v-if="historialWhatsappCargando" class="text-center py-6">
            <VProgressCircular indeterminate color="primary" />
          </div>
          <VAlert v-else-if="!historialWhatsappError && historialWhatsapp.length === 0" type="info" variant="tonal">
            Aún no hay envíos ni acciones de WhatsApp registrados para esta empresa en el evento.
          </VAlert>
          <VTable v-else-if="historialWhatsapp.length">
            <thead>
              <tr>
                <th>Fecha y hora</th>
                <th>Canal</th>
                <th>Acción</th>
                <th>Destinatario</th>
                <th>Usuario</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="envio in historialWhatsapp" :key="envio.id">
                <td class="text-no-wrap">{{ formatoFechaWhatsapp(envio.fecha) }}</td>
                <td>{{ envio.canal === 'email' ? 'Correo' : 'WhatsApp' }}</td>
                <td>{{ etiquetaAccionEnvio(envio) }}</td>
                <td class="text-no-wrap">{{ envio.destinatario }}</td>
                <td>{{ envio.usuario }}</td>
              </tr>
            </tbody>
          </VTable>
          <VPagination
            v-if="historialWhatsappUltimaPagina > 1"
            :model-value="historialWhatsappPagina"
            :length="historialWhatsappUltimaPagina"
            :disabled="historialWhatsappCargando"
            class="mt-4"
            @update:model-value="cargarHistorialWhatsapp(empresaHistorialWhatsapp, $event)"
          />
        </VCardText>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogEliminar" max-width="500">
      <VCard class="credencial-dialog-card">
        <VCardTitle class="credencial-dialog-header d-flex align-center pa-5">
          <div class="credencial-dialog-icon me-3 text-error">
            <VIcon icon="tabler-trash" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Eliminar vinculación</div>
            <div class="text-caption text-medium-emphasis mt-1">Esta acción quitará la empresa de la feria</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="dialogEliminar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="credencial-dialog-body pa-5">
          <VAlert type="warning" variant="tonal" class="mb-4">
            ¿Deseas eliminar la vinculación de <strong>{{ empresaEliminar?.nombre_empresa || 'esta empresa' }}</strong> para el tipo <strong>{{ empresaEliminar?.tipo_credencial }}</strong> en el evento seleccionado?
          </VAlert>
          <p class="text-body-2 text-medium-emphasis mb-0">La empresa y sus datos generales no serán eliminados.</p>
        </VCardText>
        <VDivider />
        <VCardActions class="credencial-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn color="secondary" variant="flat" :disabled="eliminando" @click="dialogEliminar = false">Cancelar</VBtn>
          <VBtn color="error" variant="flat" :loading="eliminando" @click="eliminarVinculacion">
            <VIcon icon="tabler-trash" start />
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.credencial-dialog-card {
  overflow: hidden;
  border-radius: 16px;
}

.credencial-dialog-tall {
  display: flex;
  flex-direction: column;
  min-height: min(680px, 82vh);
  max-height: 88vh;
}

.credencial-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.credencial-dialog-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.credencial-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
  flex: 1 1 auto;
  overflow-y: auto;
}

.credencial-section-title {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.credencial-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}

.credenciales-list-filter {
  inline-size: 18rem;
  max-inline-size: 100%;
}

.credencial-tipo-estadistica {
  border-block-end: 2px solid;
  transition: box-shadow 0.1s ease-out, border-block-end-width 0.1s ease-out;
}

.credencial-tipo-estadistica:hover {
  border-block-end-width: 3px;
  box-shadow: 0 5px 14px rgba(var(--v-theme-on-surface), 0.12);
}

.empresa-cell {
  min-inline-size: 220px;
  white-space: normal;
  overflow-wrap: break-word;
  line-height: 1.35;
}

.empresa-ubicacion {
  color: rgba(var(--v-theme-on-surface), 0.58);
  font-size: 0.75rem;
  line-height: 1.3;
  margin-block-start: 0.2rem;
}

.credenciales-codigos-columna,
.credenciales-acreditados-columna,
.credenciales-tipos-columna {
  display: flex;
  flex-direction: column;
  gap: 8px;
  padding: 6px;
}

.credenciales-codigos-columna {
  align-items: flex-start;
}

.credenciales-acreditados-columna {
  align-items: center;
}

.credenciales-tipos-columna {
  align-items: flex-start;
}

.credencial-tipo-plataforma {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  inline-size: 100%;
  min-inline-size: 180px;
}

@media (max-width: 600px) {
  .credencial-dialog-header,
  .credencial-dialog-body,
  .credencial-dialog-actions {
    padding: 16px !important;
  }

  .credenciales-list-filter {
    inline-size: 100%;
  }
}
</style>
