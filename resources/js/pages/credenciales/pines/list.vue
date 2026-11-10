<script setup>
import { computed, onMounted, ref } from 'vue'
import { useCan } from '@/composables/useCan'
import { useSafePagination } from '@/composables/useSafePagination'

const can = useCan()

definePage({
  meta: {
    requiresAuth: true,
    title: 'PINs de acreditación',
    ability: 'credenciales.pines.ver',
  },
})

const eventos = ref([])
const evento = ref(null)
const empresas = ref([])
const pines = ref([])
const resumenPines = ref({})
const loading = ref(false)
const loadingEmpresas = ref(false)
const error = ref('')
const search = ref('')
const estado = ref('Todos')
const tipo = ref('Todos')
const empresaSearch = ref('')
const dialogGenerar = ref(false)
const empresaSeleccionada = ref(null)
const tipoGenerar = ref(null)
const contratoGenerar = ref(null)
const cantidad = ref(1)
const generando = ref(false)
const mensaje = ref('')
const dialogEnviar = ref(false)
const pinEnviar = ref(null)
const empresaEnviar = ref(null)
const tipoEnviar = ref('')
const cantidadPinesEnviar = ref(0)
const envioAgrupado = ref(false)
const correoEnviar = ref('')
const enviando = ref(false)
const mensajeEnvio = ref('')
const dialogHistorialEnvios = ref(false)
const historialEnvios = ref([])
const cargandoHistorialEnvios = ref(false)
const errorHistorialEnvios = ref('')
const buscarHistorialEnvios = ref('')
const filtroTipoEnvio = ref('Todos')
const paginaHistorialEnvios = ref(1)
const totalHistorialEnvios = ref(0)
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const itemsPorPaginaHistorialEnvios = ref(defaultItemsPerPage)
const dialogHistorialGeneraciones = ref(false)
const historialGeneraciones = ref([])
const cargandoHistorialGeneraciones = ref(false)
const errorHistorialGeneraciones = ref('')
const empresaHistorialGeneraciones = ref(null)
const paginaHistorialGeneraciones = ref(1)
const totalHistorialGeneraciones = ref(0)
const itemsPorPaginaHistorialGeneraciones = ref(10)
const paginaPines = ref(1)
const itemsPorPaginaPines = ref(defaultItemsPerPage)
const totalPines = ref(0)
const paginaEmpresas = ref(1)
const itemsPorPaginaEmpresas = ref(defaultItemsPerPage)

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
const opcionesEstado = [
  { title: 'Todos', value: 'Todos' },
  { title: 'Disponible', value: 'disponible' },
  { title: 'Enviado', value: 'enviado' },
  { title: 'Usado', value: 'usado' },
  { title: 'Revocado', value: 'revocado' },
]
const headersPines = [
  { title: 'PIN', key: 'pin', sortable: false },
  { title: 'Institución/Empresa', key: 'empresa', sortable: false },
  { title: 'Tipo', key: 'tipo_credencial', sortable: false },
  { title: 'Modalidad', key: 'modalidad', sortable: false },
  { title: 'Estado', key: 'estado', sortable: false },
  { title: 'Destinatario', key: 'destinatario', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false },
]
const headersEmpresas = [
  { title: 'Institución/Empresa', key: 'nombre_empresa', sortable: false },
  { title: 'Tipo', key: 'tipo_credencial', sortable: false },
  { title: 'Asignadas', key: 'credenciales_asignadas', sortable: false },
  { title: 'Acreditadas', key: 'credenciales_utilizadas', sortable: false },
  { title: 'PINs generados', key: 'pines_generados', sortable: false },
  { title: 'Acción', key: 'actions', sortable: false },
]
const headersHistorial = [
  { title: 'PIN', key: 'pin', sortable: false },
  { title: 'Institución/Empresa', key: 'empresa', sortable: false },
  { title: 'Tipo', key: 'tipo_credencial', sortable: false },
  { title: 'Correo', key: 'correo', sortable: false },
  { title: 'Envío', key: 'tipo_envio', sortable: false },
  { title: 'Usuario', key: 'usuario', sortable: false },
  { title: 'Fecha', key: 'fecha', sortable: false },
]
const headersGeneraciones = [
  { title: 'Tipo de credencial', key: 'tipo_credencial', sortable: false },
  { title: 'PINs creados', key: 'cantidad', sortable: false },
  { title: 'Usuario', key: 'usuario', sortable: false },
  { title: 'Fecha', key: 'fecha', sortable: false },
]

const cargarEventos = async () => {
  const response = await fetch('/credenciales/pines/eventos', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  const payload = await response.json()
  eventos.value = payload.data || []
}

const cargarEmpresas = async () => {
  if (!evento.value) return
  loadingEmpresas.value = true
  try {
    const response = await fetch(`/credenciales/eventos/${evento.value}/empresas`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const payload = await response.json()
    empresas.value = payload.data || []
  } finally {
    loadingEmpresas.value = false
  }
}

const cargarPines = async (mostrarCarga = true) => {
  if (!evento.value) return
  if (mostrarCarga) loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({ page: String(paginaPines.value), per_page: String(itemsPorPaginaPines.value) })
    if (estado.value !== 'Todos') params.set('estado', estado.value)
    if (tipo.value !== 'Todos') params.set('tipo_credencial', tipo.value)
    if (search.value.trim()) params.set('search', search.value.trim())
    const response = await fetch(`/credenciales/eventos/${evento.value}/pines?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los PINs.')
    pines.value = payload.data || []
    totalPines.value = Number(payload.meta?.total || 0)
  } catch (err) {
    error.value = err.message
  } finally {
    if (mostrarCarga) loading.value = false
  }
}

const cargarResumenPines = async () => {
  if (!evento.value) return
  const response = await fetch(`/credenciales/eventos/${evento.value}/pines/resumen`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  const payload = await response.json()
  resumenPines.value = payload.data || {}
}

const cargarHistorialEnvios = async () => {
  if (!evento.value) return
  cargandoHistorialEnvios.value = true
  errorHistorialEnvios.value = ''
  try {
    const params = new URLSearchParams({
      page: String(paginaHistorialEnvios.value),
      per_page: String(itemsPorPaginaHistorialEnvios.value),
    })
    if (buscarHistorialEnvios.value.trim()) params.set('search', buscarHistorialEnvios.value.trim())
    if (filtroTipoEnvio.value !== 'Todos') params.set('tipo_envio', filtroTipoEnvio.value)
    const response = await fetch(`/credenciales/eventos/${evento.value}/pines/envios?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial de envíos.')
    historialEnvios.value = payload.data || []
    totalHistorialEnvios.value = Number(payload.meta?.total || 0)
  } catch (err) {
    errorHistorialEnvios.value = err.message
  } finally {
    cargandoHistorialEnvios.value = false
  }
}

const abrirHistorialEnvios = async () => {
  if (!evento.value) return
  paginaHistorialEnvios.value = 1
  dialogHistorialEnvios.value = true
  await cargarHistorialEnvios()
}

const cambiarFiltroHistorialEnvios = () => {
  paginaHistorialEnvios.value = 1
  cargarHistorialEnvios()
}

const cambiarFiltroPines = () => {
  paginaPines.value = 1
  cargarPines(false)
}

const cambiarPaginaPines = () => cargarPines()

const actualizarOpcionesPines = options => {
  const nuevaPagina = Number(options.page || paginaPines.value)
  const nuevosItems = sanitizeItemsPerPage(options.itemsPerPage || itemsPorPaginaPines.value)
  if (nuevaPagina === paginaPines.value && nuevosItems === itemsPorPaginaPines.value) return
  paginaPines.value = nuevaPagina
  itemsPorPaginaPines.value = nuevosItems
  cargarPines()
}

const cambiarPaginacionEmpresas = () => {
  paginaEmpresas.value = 1
}

const actualizarOpcionesEmpresas = options => {
  if (options.page) paginaEmpresas.value = options.page
  if (options.itemsPerPage) itemsPorPaginaEmpresas.value = sanitizeItemsPerPage(options.itemsPerPage)
}

const actualizarOpcionesHistorial = options => {
  const nuevaPagina = Number(options.page || paginaHistorialEnvios.value)
  const nuevosItems = sanitizeItemsPerPage(options.itemsPerPage || itemsPorPaginaHistorialEnvios.value)
  if (nuevaPagina === paginaHistorialEnvios.value && nuevosItems === itemsPorPaginaHistorialEnvios.value) return
  paginaHistorialEnvios.value = nuevaPagina
  itemsPorPaginaHistorialEnvios.value = nuevosItems
  cargarHistorialEnvios()
}

const cargarHistorialGeneraciones = async () => {
  if (!evento.value || !empresaHistorialGeneraciones.value) return
  cargandoHistorialGeneraciones.value = true
  errorHistorialGeneraciones.value = ''
  try {
    const params = new URLSearchParams({
      page: String(paginaHistorialGeneraciones.value),
      per_page: String(itemsPorPaginaHistorialGeneraciones.value),
    })
    const response = await fetch(`/credenciales/eventos/${evento.value}/empresas/${empresaHistorialGeneraciones.value.id_empresa}/pines/generaciones?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudo cargar el historial de generación.')
    historialGeneraciones.value = payload.data || []
    totalHistorialGeneraciones.value = Number(payload.meta?.total || 0)
  } catch (err) {
    errorHistorialGeneraciones.value = err.message
  } finally {
    cargandoHistorialGeneraciones.value = false
  }
}

const abrirHistorialGeneraciones = async empresa => {
  empresaHistorialGeneraciones.value = empresa
  paginaHistorialGeneraciones.value = 1
  historialGeneraciones.value = []
  dialogHistorialGeneraciones.value = true
  await cargarHistorialGeneraciones()
}

const actualizarOpcionesGeneraciones = options => {
  const nuevaPagina = Number(options.page || paginaHistorialGeneraciones.value)
  const nuevosItems = sanitizeItemsPerPage(options.itemsPerPage || itemsPorPaginaHistorialGeneraciones.value)
  if (nuevaPagina === paginaHistorialGeneraciones.value && nuevosItems === itemsPorPaginaHistorialGeneraciones.value) return
  paginaHistorialGeneraciones.value = nuevaPagina
  itemsPorPaginaHistorialGeneraciones.value = nuevosItems
  cargarHistorialGeneraciones()
}

const seleccionarEvento = async () => {
  pines.value = []
  empresas.value = []
  paginaPines.value = 1
  paginaEmpresas.value = 1
  resumenPines.value = {}
  await Promise.all([cargarEmpresas(), cargarPines(), cargarResumenPines()])
}

const abrirGenerar = empresa => {
  empresaSeleccionada.value = empresa
  tipoGenerar.value = empresa.tipo_credencial || empresa.contratos?.[0]?.tipo_credencial || null
  contratoGenerar.value = empresa.contratos?.[0]?.id_contrato || null
  cantidad.value = 1
  mensaje.value = ''
  dialogGenerar.value = true
}

const tiposDisponibles = computed(() => {
  const registros = empresaSeleccionada.value?.contratos?.length ? empresaSeleccionada.value.contratos : [empresaSeleccionada.value]
  return [...new Set(registros.map(registro => registro?.tipo_credencial).filter(Boolean))]
})

const contratosDisponibles = computed(() => (empresaSeleccionada.value?.contratos || []).filter(contrato => contrato.tipo_credencial === tipoGenerar.value))

const cantidadDisponible = computed(() => Math.max(
  0,
  Number(empresaSeleccionada.value?.credenciales_asignadas || 0)
    - Number(empresaSeleccionada.value?.credenciales_utilizadas || 0)
    - Number(empresaSeleccionada.value?.pines_generados || 0),
))

const contratoDescripcion = computed(() => {
  const contratos = contratosDisponibles.value
  if (!contratos.length) return 'Sin contrato'
  const codigos = [...new Set(contratos.map(contrato => contrato.codigo_contrato).filter(Boolean))]
  return codigos.length === 1 ? codigos[0] : `${codigos.length} contratos asociados`
})

const empresasPorTipo = computed(() => empresas.value.flatMap(empresa => {
  const registros = empresa.contratos?.length ? empresa.contratos : [empresa]
  const agrupados = new Map()

  registros.forEach(registro => {
    const clave = String(registro.tipo_credencial || empresa.tipo_credencial || '').trim()
    if (!clave) return

    const actual = agrupados.get(clave) || {
      ...empresa,
      ...registro,
      nombre_empresa: empresa.nombre_empresa,
      tipo_credencial: clave,
      credenciales_asignadas: 0,
      credenciales_utilizadas: 0,
      contratos: [],
      pines_generados: Number(resumenPines.value[`${empresa.id_empresa}|${clave}`] || 0),
    }
    actual.credenciales_asignadas += Number(registro.credenciales_asignadas || 0)
    actual.credenciales_utilizadas += Number(registro.credenciales_utilizadas || 0)
    actual.contratos.push(registro)
    agrupados.set(clave, actual)
  })

  return [...agrupados.values()]
}))

const empresasPorTipoFiltradas = computed(() => {
  const termino = empresaSearch.value.trim().toLowerCase()
  if (!termino) return empresasPorTipo.value

  return empresasPorTipo.value.filter(empresa => [
    empresa.nombre_empresa,
    empresa.tipo_credencial,
    ...(empresa.contratos || []).map(contrato => contrato.codigo_contrato),
  ].some(valor => String(valor ?? '').toLowerCase().includes(termino)))
})

const empresasPaginadas = computed(() => {
  const inicio = (paginaEmpresas.value - 1) * itemsPorPaginaEmpresas.value
  return empresasPorTipoFiltradas.value.slice(inicio, inicio + itemsPorPaginaEmpresas.value)
})

const generar = async () => {
  if (!empresaSeleccionada.value || !tipoGenerar.value || Number(cantidad.value) < 1) return
  generando.value = true
  error.value = ''
  try {
    const response = await fetch(`/credenciales/eventos/${evento.value}/empresas/${empresaSeleccionada.value.id_empresa}/pines`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify({ id_contrato: contratoGenerar.value || null, tipo_credencial: tipoGenerar.value, cantidad: Number(cantidad.value) }),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudieron generar los PINs.')
    mensaje.value = payload.message
    dialogGenerar.value = false
    paginaPines.value = 1
    await Promise.all([cargarPines(), cargarResumenPines()])
  } catch (err) {
    error.value = err.message
  } finally {
    generando.value = false
  }
}

const exportar = () => {
  if (evento.value && can('credenciales.pines.exportar')) window.open(`/credenciales/eventos/${evento.value}/pines/exportar`, '_blank', 'noopener')
}

const enviarCorreo = async pin => {
  if (!can('credenciales.pines.enviar') || pin.estado === 'usado' || pin.estado === 'revocado') return
  pinEnviar.value = pin
  empresaEnviar.value = null
  envioAgrupado.value = false
  correoEnviar.value = pin.email_destinatario || ''
  mensajeEnvio.value = ''
  dialogEnviar.value = true
}

const enviarEmpresaCorreo = empresa => {
  if (!can('credenciales.pines.enviar') || !Number(empresa.pines_generados)) return
  pinEnviar.value = null
  empresaEnviar.value = empresa
  tipoEnviar.value = empresa.tipo_credencial
  cantidadPinesEnviar.value = empresa.pines_generados === null ? 0 : Number(empresa.pines_generados)
  envioAgrupado.value = true
  correoEnviar.value = ''
  mensajeEnvio.value = ''
  dialogEnviar.value = true
}

const confirmarEnvioCorreo = async () => {
  if ((!pinEnviar.value && !envioAgrupado.value) || !correoEnviar.value.trim()) return
  enviando.value = true
  mensajeEnvio.value = ''
  try {
    const endpoint = envioAgrupado.value
      ? `/credenciales/eventos/${evento.value}/empresas/${empresaEnviar.value.id_empresa}/pines/enviar`
      : `/credenciales/pines/${pinEnviar.value.id}/enviar`
    const body = envioAgrupado.value
      ? { tipo_credencial: tipoEnviar.value, destinatario: correoEnviar.value.trim() }
      : { canal: 'email', destinatario: correoEnviar.value.trim() }
    const response = await fetch(endpoint, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
      credentials: 'same-origin',
      body: JSON.stringify(body),
    })
    const payload = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo enviar el correo.')
    dialogEnviar.value = false
    mensajeEnvio.value = payload.message || 'PIN enviado correctamente.'
    mensaje.value = 'Mensaje enviado correctamente.'
    await cargarPines()
  } catch (err) {
    error.value = err.message
  } finally {
    enviando.value = false
  }
}

const imprimirDisponibles = () => {
  if (!evento.value || !can('credenciales.pines.imprimir')) return
  const form = document.createElement('form')
  form.method = 'POST'
  form.action = `/credenciales/eventos/${evento.value}/pines/imprimir`
  form.target = '_blank'
  const token = document.createElement('input')
  token.type = 'hidden'
  token.name = '_token'
  token.value = csrf()
  form.appendChild(token)
  document.body.appendChild(form)
  form.submit()
  form.remove()
}

const estadosColor = estadoActual => ({ disponible: 'success', enviado: 'info', usado: 'primary', revocado: 'error' }[estadoActual] || 'secondary')
const estadoLabel = estadoActual => estadoActual ? estadoActual.charAt(0).toUpperCase() + estadoActual.slice(1) : '—'

onMounted(cargarEventos)
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-5">
          <div>
            <h4 class="text-h4 mb-1">PINs de acreditación</h4>
            <p class="text-body-2 text-medium-emphasis mb-0">Administra los códigos de autoacreditación de todo el evento.</p>
          </div>
          <div class="d-flex align-center gap-2">
            <VBtn v-if="can('credenciales.pines.ver') && evento" variant="tonal" color="info" prepend-icon="tabler-history" title="Ver historial de envíos" @click="abrirHistorialEnvios">Historial de envíos</VBtn>
            <VBtn v-if="can('credenciales.pines.imprimir') && evento" icon variant="tonal" color="secondary" title="Imprimir PINs disponibles" @click="imprimirDisponibles"><VIcon icon="tabler-printer" /></VBtn>
            <VBtn v-if="can('credenciales.pines.exportar') && evento" icon variant="tonal" color="success" title="Exportar PINs" @click="exportar"><VIcon icon="tabler-file-spreadsheet" /></VBtn>
          </div>
        </div>
        <VSelect v-model="evento" :items="eventos" item-title="nombre_feria" item-value="id_feria" label="Evento / Feria" clearable @update:model-value="seleccionarEvento" />
        <VAlert v-if="error" type="error" variant="tonal" class="mt-4">{{ error }}</VAlert>
        <VAlert v-if="mensaje" type="success" variant="tonal" class="mt-4">{{ mensaje }}</VAlert>
      </VCardText>
    </VCard>

    <VCard v-if="evento">
      <VCardText class="d-flex align-center flex-wrap gap-3">
        <AppTextField
          v-model="search"
          placeholder="Buscar PIN, institución o destinatario..."
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
          style="min-inline-size: 280px;"
          @update:model-value="cambiarFiltroPines"
        />
        <VSelect v-model="estado" :items="opcionesEstado" label="Estado" hide-details style="min-inline-size: 150px" @update:model-value="cambiarFiltroPines" />
        <VSelect v-model="tipo" :items="['Todos', 'Expositor', 'Prensa', 'Oficial', 'Servicios', 'Negocios']" label="Tipo" hide-details style="min-inline-size: 150px" @update:model-value="cambiarFiltroPines" />
      </VCardText>
      <VDivider />
      <VCardText>
        <VProgressLinear v-if="loading" indeterminate color="primary" />
        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPorPaginaPines"
          v-model:page="paginaPines"
          :items-length="totalPines"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headersPines"
          :items="pines"
          :loading="loading"
          item-value="id"
          class="pins-table"
          @update:options="actualizarOpcionesPines"
        >
          <template #item.pin="{ item }"><strong class="text-primary">{{ item.pin }}</strong></template>
          <template #item.empresa="{ item }">
            <span class="pins-company-name">{{ item.empresa || '—' }}</span>
          </template>
          <template #item.estado="{ item }"><VChip size="small" :color="estadosColor(item.estado)" variant="tonal">{{ estadoLabel(item.estado) }}</VChip></template>
          <template #item.destinatario="{ item }">{{ item.nombre_destinatario || 'Sin asignar' }}</template>
          <template #item.actions="{ item }">
            <div class="pins-actions-cell">
              <VBtn v-if="can('credenciales.pines.enviar') && item.estado !== 'usado' && item.estado !== 'revocado'" color="info" size="small" variant="tonal" prepend-icon="tabler-mail-forward" class="text-none" @click="enviarCorreo(item)">Enviar PIN</VBtn>
            </div>
          </template>
          <template #no-data>No hay PINs generados para este filtro.</template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VCard v-if="evento" class="mt-6">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <span>Generar PINs por empresa</span>
        <AppTextField
          v-model="empresaSearch"
          placeholder="Buscar empresa o institución..."
          prepend-inner-icon="tabler-search"
          clearable
          hide-details
          style="min-inline-size: 280px;"
            @update:model-value="cambiarPaginacionEmpresas"
        />
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div v-if="loadingEmpresas" class="py-6 text-center">
          <VProgressCircular indeterminate color="primary" />
        </div>
        <VDataTableServer
          v-model:items-per-page="itemsPorPaginaEmpresas"
          v-model:page="paginaEmpresas"
          :items-length="empresasPorTipoFiltradas.length"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headersEmpresas"
          :items="empresasPaginadas"
          :loading="loadingEmpresas"
          item-value="id_empresa"
          class="pins-company-table"
          @update:options="actualizarOpcionesEmpresas"
        >
          <template #item.nombre_empresa="{ item }"><span class="pins-company-name">{{ item.nombre_empresa }}</span></template>
          <template #item.pines_generados="{ item }">{{ item.pines_generados }} / {{ item.credenciales_asignadas }}</template>
          <template #item.actions="{ item }">
            <div class="pins-actions-cell">
              <VBtn v-if="can('credenciales.pines.generar')" size="small" color="success" variant="flat" prepend-icon="tabler-key" class="text-none" :disabled="Number(item.pines_generados) + Number(item.credenciales_utilizadas) >= Number(item.credenciales_asignadas)" @click="abrirGenerar(item)">Generar PIN</VBtn>
              <VBtn v-if="can('credenciales.pines.enviar') && Number(item.pines_generados)" size="small" variant="tonal" color="info" prepend-icon="tabler-mail-forward" class="text-none" @click="enviarEmpresaCorreo(item)">Enviar PINs</VBtn>
              <VBtn v-if="can('credenciales.pines.ver') && Number(item.pines_generados)" size="small" variant="tonal" color="warning" prepend-icon="tabler-history" class="text-none" @click="abrirHistorialGeneraciones(item)">Historial</VBtn>
            </div>
          </template>
          <template #no-data>No hay empresas que coincidan con la búsqueda.</template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VCard v-else class="pins-empty-state">
      <VCardText class="d-flex flex-column align-center justify-center text-center py-16">
        <VIcon icon="tabler-file-description" size="56" color="secondary" class="mb-4" />
        <h5 class="text-h5 mb-2">Selecciona una Feria</h5>
        <p class="text-body-1 text-medium-emphasis mb-0">Elige un evento/feria del selector superior para visualizar y generar sus PINs.</p>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogGenerar" max-width="560" persistent scrollable>
      <VCard class="pago-dialog-card">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3"><VIcon icon="tabler-key" size="24" /></div>
          <div>
            <div class="text-h5 font-weight-bold">Generar PINs</div>
            <div class="text-caption text-medium-emphasis mt-1">Códigos de autoacreditación para {{ empresaSeleccionada?.nombre_empresa }}</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" :disabled="generando" @click="dialogGenerar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pago-dialog-body pa-5 pt-4">
          <div class="pins-section-title mb-3"><VIcon icon="tabler-settings" size="18" class="me-2" />Configuración de cupos</div>
          <VAlert type="info" variant="tonal" class="mb-4">
            <div class="d-flex flex-column gap-1">
              <div><strong>Tipo de credencial:</strong> {{ tipoGenerar || 'No definido' }}</div>
              <div><strong>Contrato:</strong> {{ contratoDescripcion }}</div>
            </div>
          </VAlert>
          <VAlert :type="cantidadDisponible > 0 ? 'success' : 'warning'" variant="tonal" class="mb-4">
            Esta asignación tiene <strong>{{ empresaSeleccionada?.credenciales_asignadas || 0 }} credenciales asignadas</strong>, <strong>{{ empresaSeleccionada?.credenciales_utilizadas || 0 }} ya registradas</strong> y <strong>{{ empresaSeleccionada?.pines_generados || 0 }} PINs generados</strong>.
            <template v-if="cantidadDisponible > 0">
              Puede generar hasta <strong>{{ cantidadDisponible }} PIN{{ cantidadDisponible === 1 ? '' : 's' }}</strong>.
              ¿Cuántos desea generar?
            </template>
            <template v-else>
              No hay cupos disponibles para generar nuevos PINs.
            </template>
          </VAlert>
          <VTextField
            v-model.number="cantidad"
            type="number"
            min="1"
            :max="cantidadDisponible"
            label="Cantidad de PINs a generar"
            variant="outlined"
            :disabled="cantidadDisponible === 0"
            @update:model-value="cantidad = Math.min(Math.max(1, Number($event) || 1), cantidadDisponible || 1)"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn variant="tonal" :disabled="generando" @click="dialogGenerar = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="generando" :disabled="cantidadDisponible === 0 || cantidad < 1 || cantidad > cantidadDisponible" @click="generar">Generar PINs</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogEnviar" max-width="560" persistent scrollable>
      <VCard class="pago-dialog-card">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3"><VIcon icon="tabler-mail-forward" size="24" /></div>
          <div>
            <div class="text-h5 font-weight-bold">{{ envioAgrupado ? 'Enviar PINs de la empresa' : 'Enviar PIN' }}</div>
              <div class="text-caption text-medium-emphasis mt-1">Envía {{ envioAgrupado ? 'todos los PINs disponibles de esta asignación' : 'el PIN de autoacreditación' }} por correo electrónico.</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" :disabled="enviando" @click="dialogEnviar = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pago-dialog-body pa-5 pt-4">
          <VAlert type="info" variant="tonal" class="mb-4">
            <template v-if="envioAgrupado">PINs a enviar: <strong>{{ cantidadPinesEnviar || 'todos los disponibles' }}</strong><br>Institución: {{ empresaEnviar?.nombre_empresa || 'Sin institución' }}<br>Tipo: {{ tipoEnviar }}</template>
            <template v-else>PIN: <strong>{{ pinEnviar?.pin }}</strong><br>Institución: {{ pinEnviar?.empresa || 'Sin institución' }}</template>
          </VAlert>
          <VTextField v-model="correoEnviar" label="Correo del destinatario" type="email" variant="outlined" autofocus />
        </VCardText>
        <VDivider />
        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn variant="tonal" :disabled="enviando" @click="dialogEnviar = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="enviando" :disabled="!correoEnviar.trim()" @click="confirmarEnvioCorreo">{{ envioAgrupado ? 'Enviar PINs' : 'Enviar PIN' }}</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHistorialEnvios" :width="$vuetify.display.smAndDown ? 'auto' : 1180" scrollable>
      <VCard class="pago-dialog-card pago-dialog-tall">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3"><VIcon icon="tabler-history" size="24" /></div>
          <div>
            <div class="text-h5 font-weight-bold">Historial de envíos de PINs</div>
            <div class="text-caption text-medium-emphasis mt-1">Consulta el correo, usuario, fecha y PIN de cada envío realizado.</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="dialogHistorialEnvios = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pago-dialog-body pa-5 pt-4">
          <div class="d-flex align-center flex-wrap gap-3 mb-4">
            <VTextField v-model="buscarHistorialEnvios" label="Buscar correo, empresa o usuario" clearable hide-details @update:model-value="cambiarFiltroHistorialEnvios" />
            <VSelect v-model="filtroTipoEnvio" :items="['Todos', 'individual', 'empresa']" label="Tipo de envío" hide-details style="min-inline-size: 170px" @update:model-value="cambiarFiltroHistorialEnvios" />
          </div>
          <VAlert v-if="errorHistorialEnvios" type="error" variant="tonal" class="mb-4">{{ errorHistorialEnvios }}</VAlert>
          <VProgressLinear v-if="cargandoHistorialEnvios" indeterminate color="primary" class="mb-3" />
          <VDataTableServer
            v-model:items-per-page="itemsPorPaginaHistorialEnvios"
            v-model:page="paginaHistorialEnvios"
            :items-length="totalHistorialEnvios"
            :items-per-page-options="itemsPerPageOptions"
            :headers="headersHistorial"
            :items="historialEnvios"
            :loading="cargandoHistorialEnvios"
            item-value="id"
            class="pins-history-table"
            @update:options="actualizarOpcionesHistorial"
          >
            <template #item.pin="{ item }"><strong class="text-primary">{{ item.pin }}</strong></template>
            <template #item.tipo_envio="{ item }"><VChip size="small" :color="item.tipo_envio === 'empresa' ? 'info' : 'secondary'" variant="tonal">{{ item.tipo_envio === 'empresa' ? `Empresa (${item.cantidad_pines})` : 'Individual' }}</VChip></template>
            <template #item.fecha="{ item }">{{ new Date(item.fecha).toLocaleString('es-BO') }}</template>
            <template #no-data>No hay envíos registrados para este evento.</template>
          </VDataTableServer>
        </VCardText>
        <VDivider />
        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn color="secondary" variant="flat" @click="dialogHistorialEnvios = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogHistorialGeneraciones" :width="$vuetify.display.smAndDown ? 'auto' : 820" scrollable>
      <VCard class="pago-dialog-card pago-dialog-tall">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3"><VIcon icon="tabler-key" size="24" /></div>
          <div>
            <div class="text-h5 font-weight-bold">Historial de generación</div>
            <div class="text-caption text-medium-emphasis mt-1">{{ empresaHistorialGeneraciones?.nombre_empresa || 'Empresa' }}</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="dialogHistorialGeneraciones = false"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>
        <VDivider />
        <VCardText class="pago-dialog-body pa-5 pt-4">
          <VAlert v-if="errorHistorialGeneraciones" type="error" variant="tonal" class="mb-4">{{ errorHistorialGeneraciones }}</VAlert>
          <VDataTableServer
            v-model:items-per-page="itemsPorPaginaHistorialGeneraciones"
            v-model:page="paginaHistorialGeneraciones"
            :items-length="totalHistorialGeneraciones"
            :items-per-page-options="itemsPerPageOptions"
            :headers="headersGeneraciones"
            :items="historialGeneraciones"
            :loading="cargandoHistorialGeneraciones"
            item-value="fecha"
            class="pins-generation-table"
            @update:options="actualizarOpcionesGeneraciones"
          >
            <template #item.cantidad="{ item }"><VChip color="primary" variant="tonal" size="small">{{ item.cantidad }}</VChip></template>
            <template #item.fecha="{ item }">{{ new Date(item.fecha).toLocaleString('es-BO') }}</template>
            <template #no-data>No hay generaciones registradas para esta empresa.</template>
          </VDataTableServer>
        </VCardText>
        <VDivider />
        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VBtn color="secondary" variant="flat" @click="dialogHistorialGeneraciones = false">Cerrar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.pago-dialog-card {
  overflow: hidden;
}

.pago-dialog-tall {
  max-height: min(88vh, 760px);
}

.pago-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.13), rgba(var(--v-theme-primary), 0.04));
}

.pago-dialog-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 44px;
  block-size: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.pago-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
}

.pago-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}

.pins-actions-cell {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
  inline-size: 176px;
  min-inline-size: 176px;
  padding-block: 4px;
}

.pins-actions-cell .v-btn {
  inline-size: 100%;
  justify-content: flex-start;
}

.pins-table :deep(.v-data-table__td),
.pins-company-table :deep(.v-data-table__td) {
  white-space: normal;
  vertical-align: middle;
}

.pins-table :deep(.v-data-table__td:nth-child(2)),
.pins-table :deep(.v-data-table__th:nth-child(2)) {
  max-inline-size: 300px;
  min-inline-size: 230px;
}

.pins-company-table :deep(.v-data-table__td:first-child),
.pins-company-table :deep(.v-data-table__th:first-child) {
  max-inline-size: 420px;
  min-inline-size: 280px;
}

.pins-company-name {
  display: -webkit-box;
  max-inline-size: 100%;
  overflow: hidden;
  overflow-wrap: anywhere;
  line-height: 1.35;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
}

.pins-history-table :deep(.v-data-table__td),
.pins-generation-table :deep(.v-data-table__td) {
  white-space: normal;
  vertical-align: middle;
  line-height: 1.35;
}

.pins-history-table :deep(.v-data-table__td:nth-child(2)),
.pins-history-table :deep(.v-data-table__th:nth-child(2)) {
  max-inline-size: 390px;
  min-inline-size: 260px;
}

.pins-history-table :deep(.v-data-table__td:nth-child(4)),
.pins-history-table :deep(.v-data-table__th:nth-child(4)) {
  max-inline-size: 220px;
  overflow-wrap: anywhere;
}

.pins-generation-table :deep(.v-data-table__td:first-child),
.pins-generation-table :deep(.v-data-table__th:first-child) {
  max-inline-size: 280px;
  min-inline-size: 190px;
}

.pins-company-table :deep(.v-data-table__td:last-child),
.pins-company-table :deep(.v-data-table__th:last-child) {
  inline-size: 200px;
  min-inline-size: 200px;
}

.pins-company-table :deep(.v-data-table__td:last-child) {
  vertical-align: top;
  padding-block: 8px;
}

.pins-empty-state {
  min-block-size: 220px;
}

.pins-dialog-card {
  overflow: hidden;
  border-radius: 16px;
}

.pins-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.pins-dialog-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  inline-size: 44px;
  block-size: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.pins-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
}

.pins-section-title {
  display: flex;
  align-items: center;
  color: rgba(var(--v-theme-on-surface), 0.7);
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
}

.pins-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}
</style>
