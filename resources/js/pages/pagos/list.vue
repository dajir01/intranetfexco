<script setup>
import { watchDebounced } from '@vueuse/core'
import { ref, computed, onMounted, onUnmounted, watch, reactive } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useSafePagination } from '@/composables/useSafePagination'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Estado
const loading = ref(false)
const loadingPagos = ref(false)
const ferias = ref([])
const feriaSeleccionada = ref(null)
const empresas = ref([])
const feriaInfo = ref(null)
const error = ref(null)
const canalPagosActual = ref(null)
const refrescandoPorEvento = ref(false)

// Estado de tabla y filtros
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy = ref([{ key: 'empresa', order: 'asc' }])
const search = ref('')

// Estado del dialog Subir Pago
const dialogPago = ref(false)
const empresaSeleccionada = ref({})
const dialogVerPago = ref(false)
const pagoSeleccionado = ref(null)
const dialogVerPagoAprobado = ref(false)
const pagoAprobadoSeleccionado = ref(null)
const tiposPago = ref([
  { value: 0, title: 'Depósito' },
  { value: 1, title: 'Efectivo' },
  { value: 2, title: 'Cheque' },
])
const formPago = reactive({
  id_empresa: null,
  id_feria: null,
  monto: null,
  tipo_pago: 0,
  generar_recibo: false,
  foto: null,
})

// Headers de la tabla
const headers = [
  { title: 'Empresa', key: 'nombre_empresa', sortable: true },
  { title: 'Contrato(s)', key: 'contratos', sortable: false },
  { title: 'Precio Total', key: 'total_contratado', sortable: true },
  { title: 'Pagos Aprobados', key: 'pagos_aprobados', sortable: false },
  { title: 'Pagos Pendientes de Aprobación', key: 'pagos_pendientes', sortable: false },
  { title: 'Saldo por Pagar', key: 'saldo', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false },
]

// Cargar ferias
const cargarFerias = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch('/pagos/ferias', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const json = await response.json()
    
    if (json.success) {
      ferias.value = json.data
    } else {
      error.value = json.message || 'Error al cargar las ferias'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar las ferias'
  } finally {
    loading.value = false
  }
}

// Cargar pagos cuando se selecciona una feria
const cargarPagos = async () => {
  if (!feriaSeleccionada.value) {
    empresas.value = []
    feriaInfo.value = null
    return
  }
  
  loadingPagos.value = true
  error.value = null
  
  try {
    const response = await fetch(`/pagos/ferias/${feriaSeleccionada.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const json = await response.json()
    
    if (json.success) {
      empresas.value = json.data.empresas
      feriaInfo.value = json.data.feria
    } else {
      error.value = json.message || 'Error al cargar los pagos'
      empresas.value = []
      feriaInfo.value = null
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar los pagos'
    empresas.value = []
    feriaInfo.value = null
  } finally {
    loadingPagos.value = false
  }
}

const manejarActualizacion = async (event) => {
  if (!feriaSeleccionada.value || !event?.id_feria)
    return

  if (Number(event.id_feria) !== Number(feriaSeleccionada.value))
    return

  if (refrescandoPorEvento.value)
    return

  refrescandoPorEvento.value = true

  try {
    await cargarPagos()
  } finally {
    refrescandoPorEvento.value = false
  }
}

const limpiarSuscripcionPagos = () => {
  if (!window.Echo || !canalPagosActual.value)
    return

  window.Echo.leaveChannel(`private-${canalPagosActual.value}`)
  canalPagosActual.value = null
}

const suscribirCanalPagos = () => {
  if (!window.Echo || !feriaSeleccionada.value) {
    limpiarSuscripcionPagos()
    return
  }

  const nuevoCanal = `pagos.feria.${feriaSeleccionada.value}`

  if (canalPagosActual.value === nuevoCanal)
    return

  limpiarSuscripcionPagos()

  canalPagosActual.value = nuevoCanal

  window.Echo
    .private(nuevoCanal)
    .listen('.RegistroActualizado', manejarActualizacion)
}

// Formatear precio en bolivianos
const formatoPrecio = precio => {
  if (!precio && precio !== 0) return 'N/A'
  
  return new Intl.NumberFormat('es-BO', {
    style: 'currency',
    currency: 'BOB',
    minimumFractionDigits: 2,
  }).format(precio)
}

// Formatear solo número sin símbolo
const formatoNumero = numero => {
  if (!numero && numero !== 0) return '0.00'
  
  return new Intl.NumberFormat('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(numero)
}

// Computadas
const normalizeText = value => String(value ?? '').toLowerCase()

const getSortableValue = (item, key) => {
  switch (key) {
    case 'nombre_empresa':
      return String(item.nombre_empresa ?? '')
    case 'total_contratado':
      return item.total_contratado ?? 0
    case 'saldo':
      return item.saldo ?? 0
    default:
      return ''
  }
}

const empresasFiltradas = computed(() => {
  const term = normalizeText(search.value)
  if (!term) return empresas.value

  return empresas.value.filter(e => {
    const valores = [
      e.nombre_empresa,
      e.nit,
      ...e.contratos.map(c => c.codigo_contrato), // Incluir búsqueda en contratos
    ]

    return valores.some(v => normalizeText(String(v)).includes(term))
  })
})

const totalFiltrado = computed(() => empresasFiltradas.value.length)

const empresasOrdenadas = computed(() => {
  const items = [...empresasFiltradas.value]
  const sort = sortBy.value?.[0]
  if (!sort?.key) return items

  const dir = sort.order === 'desc' ? -1 : 1
  return items.sort((a, b) => {
    const aVal = getSortableValue(a, sort.key)
    const bVal = getSortableValue(b, sort.key)
    
    if (typeof aVal === 'number' && typeof bVal === 'number') {
      return (aVal - bVal) * dir
    }
    
    return String(aVal).localeCompare(String(bVal), 'es', { sensitivity: 'base' }) * dir
  })
})

const empresasPaginadas = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return empresasOrdenadas.value.slice(start, end)
})

const tieneEmpresasFiltradas = computed(() => totalFiltrado.value > 0)

const tituloTabla = computed(() => {
  if (!feriaInfo.value) return 'Pagos'
  
  return `Pagos - ${feriaInfo.value.nombre}`
})

// Resumen de pagos
const resumenFeria = computed(() => {
  const items = empresas.value || []
  let pagadas = 0
  let parciales = 0
  let pendientes = 0

  for (const e of items) {
    if (e.estado_pago === 'pagado') {
      pagadas++
    } else if (e.estado_pago === 'parcial') {
      parciales++
    } else {
      pendientes++
    }
  }

  return { pagadas, parciales, pendientes }
})

const getCsrfToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')
  return token ? token.getAttribute('content') : ''
}

const getPagoExtension = pago => {
  const extension = pago?.extension ? String(pago.extension).toLowerCase() : 'jpg'
  return extension
}

const getPagoArchivoUrl = pago => `/pagos/${pago.id}/archivo`

const esImagenExtension = extension => {
  const ext = String(extension || '').toLowerCase()
  return ['jpg', 'jpeg', 'png', 'bmp'].includes(ext)
}

const getTipoPagoLabel = (tipoPago) => {
  const tipo = Number(tipoPago)
  if (tipo === 1) return 'Efectivo'
  if (tipo === 2) return 'Cheque'
  if (tipo === 0) return 'Depósito'
  return 'No definido'
}

// Función para imprimir contrato
const imprimirContrato = (idContrato) => {
  const url = `/contratos/${idContrato}/imprimir`
  window.open(url, '_blank')
  snackbar.value = { 
    show: true, 
    color: 'success', 
    text: 'Abriendo impresión del contrato...' 
  }
}

// Función para abrir dialog de pago
const abrirDialogPago = (empresa) => {
  empresaSeleccionada.value = empresa
  formPago.id_empresa = empresa.id_empresa
  formPago.id_feria = feriaSeleccionada.value
  formPago.monto = null
  formPago.tipo_pago = 0
  formPago.generar_recibo = false
  formPago.foto = null
  dialogPago.value = true
}

// Función para cerrar dialog
const cerrarDialogPago = () => {
  dialogPago.value = false
}

const verPago = pago => {
  pagoSeleccionado.value = pago
  dialogVerPago.value = true
}

const cerrarVerPago = () => {
  dialogVerPago.value = false
}

const verPagoAprobado = pago => {
  pagoAprobadoSeleccionado.value = pago
  dialogVerPagoAprobado.value = true
}

const cerrarVerPagoAprobado = () => {
  dialogVerPagoAprobado.value = false
}

const aprobarPago = async (pago) => {
  try {
    const response = await fetch(`/pagos/${pago.id}/aprobar`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    if (!response.ok) {
      const errorJson = await response.json().catch(() => null)
      const message = errorJson?.message || 'Error al aprobar el pago'
      snackbar.value = { show: true, color: 'error', text: message }
      return
    }

    const json = await response.json().catch(() => null)
    snackbar.value = {
      show: true,
      color: 'success',
      text: json?.message || 'Pago aprobado correctamente',
    }
    cerrarVerPago()
    await cargarPagos()
  } catch (err) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: 'Error de conexión al aprobar el pago',
    }
  }
}

const rechazarPago = async (pago) => {
  try {
    const response = await fetch(`/pagos/${pago.id}/rechazar`, {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    if (!response.ok) {
      const errorJson = await response.json().catch(() => null)
      const message = errorJson?.message || 'Error al rechazar el pago'
      snackbar.value = { show: true, color: 'error', text: message }
      return
    }

    const json = await response.json().catch(() => null)
    snackbar.value = {
      show: true,
      color: 'success',
      text: json?.message || 'Pago rechazado y eliminado correctamente',
    }
    cerrarVerPago()
    await cargarPagos()
  } catch (err) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: 'Error de conexión al rechazar el pago',
    }
  }
}

// Función para subir pago (temporal)
const subirPagoConfirmar = async () => {
  if (formPago.tipo_pago !== 1 && !formPago.foto) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: 'Debe subir el comprobante para Depósito o Cheque',
    }
    return
  }

  const formData = new FormData()
  formData.append('id_empresa', formPago.id_empresa ?? '')
  formData.append('id_feria', formPago.id_feria ?? '')
  formData.append('monto', formPago.monto ?? '')
  formData.append('tipo_pago', formPago.tipo_pago ?? 0)
  formData.append('generar_recibo', formPago.generar_recibo ? '1' : '0')
  if (formPago.foto) formData.append('foto', formPago.foto)

  try {
    const response = await fetch('/pagos', {
      method: 'POST',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      body: formData,
      credentials: 'same-origin',
    })

    if (!response.ok) {
      const errorJson = await response.json().catch(() => null)
      const message = errorJson?.message || 'Error al guardar el pago'
      snackbar.value = { show: true, color: 'error', text: message }
      return
    }

    const json = await response.json().catch(() => null)
    snackbar.value = {
      show: true,
      color: 'success',
      text: json?.message || 'Pago registrado correctamente',
    }
    cerrarDialogPago()
    await cargarPagos()
  } catch (err) {
    snackbar.value = {
      show: true,
      color: 'error',
      text: 'Error de conexión al guardar el pago',
    }
  }
}

// Función para subir pago (abre dialog)
const subirPago = (empresa) => {
  abrirDialogPago(empresa)
}

const descargarReporte = () => {
  if (!feriaSeleccionada.value) {
    snackbar.value = {
      show: true,
      color: 'warning',
      text: 'Por favor, selecciona una feria'
    }
    return
  }

  snackbar.value = {
    show: true,
    color: 'info',
    text: 'Generando reporte...'
  }

  // Crear un link temporal para descargar
  const link = document.createElement('a')
  link.href = `/pagos/exportar/${feriaSeleccionada.value}`
  link.click()

  snackbar.value = {
    show: true,
    color: 'success',
    text: 'Reporte generado correctamente'
  }
}

onMounted(async () => {
  await cargarFerias()
  
  // Restaurar feria seleccionada desde query params si existe
  const idFeriaQuery = route.query.id_feria
  if (idFeriaQuery) {
    const feriaId = parseInt(idFeriaQuery)
    if (ferias.value.some(f => f.id_feria === feriaId)) {
      feriaSeleccionada.value = feriaId
      await cargarPagos()
      suscribirCanalPagos()
    }
  }
})

watch(feriaSeleccionada, () => {
  suscribirCanalPagos()
})

onUnmounted(() => {
  limpiarSuscripcionPagos()
})

// Reactividad: búsqueda con debounce
watchDebounced(search, () => {
  page.value = 1
}, { debounce: 400 })

// Reactividad: paginación y orden
watch([page, itemsPerPage, sortBy], () => {
  page.value = Math.max(1, page.value)
})

// Resetear paginación al cambiar empresas
watch(empresas, () => {
  page.value = 1
})

// Handler para cambios en opciones de tabla
const updateOptions = (options) => {
  const { page: newPage, itemsPerPage: newPerPage, sortBy: newSortBy } = options
  if (newPage)
    page.value = newPage
  if (newPerPage)
    itemsPerPage.value = sanitizeItemsPerPage(newPerPage)
  if (newSortBy)
    sortBy.value = newSortBy
}

// Estado para notificaciones
const snackbar = ref({ show: false, color: 'info', text: '' })

watch(
  () => formPago.tipo_pago,
  (tipoPago) => {
    if (tipoPago === 1) {
      formPago.generar_recibo = true
      return
    }

    if (tipoPago === 2) {
      formPago.generar_recibo = false
      return
    }

    formPago.generar_recibo = false
  },
  { immediate: true },
)
</script>

<template>
  <div>
    <!-- Selector de Feria -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              Gestión de Pagos
            </h4>
          </div>
        </div>
        <VRow>
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              v-model="feriaSeleccionada"
              :items="ferias"
              :loading="loading"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Seleccionar Evento / Feria"
              placeholder="Elige una feria para ver los pagos"
              clearable
              @update:model-value="cargarPagos"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" />
              </template>
            </VSelect>
          </VCol>
          
          <VCol
            v-if="feriaInfo"
            cols="12"
            md="6"
          >
            <VAlert
              type="info"
              variant="tonal"
              class="mb-0"
            >
              <div class="d-flex align-center gap-2">
                <div>
                  <strong>{{ feriaInfo.nombre }}</strong>
                  <div class="text-caption">
                    Pagados: {{ resumenFeria.pagadas }} | Parciales: {{ resumenFeria.parciales }} | Pendientes: {{ resumenFeria.pendientes }}
                  </div>
                </div>
              </div>
            </VAlert>
          </VCol>
        </VRow>
        
        <!-- Error Alert -->
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mt-4"
          closable
          @click:close="error = null"
        >
          {{ error }}
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Tabla de Pagos -->
    <VCard v-if="feriaSeleccionada" id="pagos-list">
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
          <div class="contrato-list-filter">
            <AppTextField
              v-model="search"
              placeholder="Buscar empresa o contrato..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              outlined
            />
          </div>
          <VBtn
            v-if="auth.can('pagos.export')"
            color="primary"
            variant="flat"
            prepend-icon="tabler-download"
            @click="descargarReporte"
          >
            Descargar Reporte
          </VBtn>
        </div>
      </VCardText>

      <VDivider />

      <VCardText>
        <div class="d-flex align-center justify-space-between mb-4">
          <h5 class="text-h5">
            {{ tituloTabla }}
          </h5>
        </div>

        <!-- Loading -->
        <div
          v-if="loadingPagos"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">
            Cargando pagos...
          </p>
        </div>

        <!-- Sin empresas -->
        <VAlert
          v-else-if="!tieneEmpresasFiltradas"
          type="info"
          variant="tonal"
        >
          No hay empresas registradas para esta feria.
        </VAlert>

        <!-- Tabla con empresas y pagos -->
        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :items-length="totalFiltrado"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="empresasPaginadas"
          :loading="loadingPagos"
          item-value="id_empresa"
          class="text-no-wrap"
          @update:options="updateOptions"
        >
          <template #header.pagos_aprobados>
            <div class="header-dos-lineas">
              Pagos<br>
              Aprobados
            </div>
          </template>

          <template #header.pagos_pendientes>
            <div class="header-dos-lineas">
              Pagos Pendientes<br>
              de Aprobación
            </div>
          </template>

          <template #item.nombre_empresa="{ item }">
            <div class="font-weight-medium empresa-cell">
              <span class="empresa-nombre">
              {{ item.nombre_empresa || '—' }}
              </span>
            </div>
          </template>

          <template #item.contratos="{ item }">
            <div class="d-flex flex-wrap gap-1">
              <VChip
                v-for="contrato in item.contratos"
                :key="contrato.id_contrato"
                color="success"
                variant="tonal"
                size="small"
                class="cursor-pointer"
                @click="imprimirContrato(contrato.id_contrato)"
              >
                {{ feriaInfo.codigo }}{{ String(contrato.codigo_contrato).padStart(5, '0') }}
              </VChip>
              <span v-if="item.contratos.length === 0" class="text-muted">—</span>
            </div>
          </template>

          <template #item.total_contratado="{ item }">
            <div class="d-flex flex-column gap-1">
              <div class="font-weight-medium text-primary">
                {{ formatoPrecio(item.total_contratado) }}
              </div>
              <div v-if="item.contratos.some(c => c.descuento && c.descuento > 0)" class="text-caption">
                Desc. en algún contrato
              </div>
              <div v-else class="text-caption">
                Sin Desc.
              </div>
            </div>
          </template>

          <template #item.pagos_aprobados="{ item }">
            <div class="pagos-botones-columna">
              <VBtn
                v-for="pago in item.pagos_aprobados"
                :key="pago.id"
                color="success"
                size="small"
                variant="tonal"
                class="text-none pago-item-btn"
                @click="verPagoAprobado(pago)"
              >
                Pago de Bs.{{ formatoNumero(pago.monto) }}
              </VBtn>
              <span v-if="item.pagos_aprobados.length === 0" class="text-muted">—</span>
            </div>
          </template>

          <template #item.pagos_pendientes="{ item }">
            <div class="pagos-botones-columna">
              <VBtn
                v-for="pago in item.pagos_pendientes"
                :key="pago.id"
                color="warning"
                size="small"
                variant="tonal"
                class="text-none pago-item-btn"
                @click="verPago(pago)"
              >
                Pago de Bs.{{ formatoNumero(pago.monto) }}
              </VBtn>
              <span v-if="item.pagos_pendientes.length === 0" class="text-muted">—</span>
            </div>
          </template>

          <template #item.saldo="{ item }">
            <div class="font-weight-medium" :class="item.saldo <= 0 ? 'text-success' : 'text-error'">
              Bs. {{ Math.abs(item.saldo).toFixed(2) }}
            </div>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              v-if="item.saldo > 0 && auth.can('pagos.create')"
              color="primary"
              size="small"
              variant="flat"
              @click="subirPago(item)"
            >
              Subir Pago
            </VBtn>
            <span v-else class="text-success text-caption">Saldado</span>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Mensaje inicial -->
    <VCard v-else>
      <VCardText class="text-center py-12">
        <VIcon
          icon="tabler-file-description"
          size="64"
          color="disabled"
          class="mb-4"
        />
        <h5 class="text-h5 mb-2">
          Selecciona una Feria
        </h5>
        <p class="text-body-1 text-medium-emphasis">
          Elige un evento/feria del selector superior para visualizar los pagos
        </p>
      </VCardText>
    </VCard>

    <!-- Dialog Ver Pago Aprobado -->
    <VDialog v-model="dialogVerPagoAprobado" max-width="700" scrollable>
      <VCard class="pago-dialog-card pago-dialog-tall">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3">
            <VIcon icon="tabler-receipt" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Ver Pago de Bs. {{ pagoAprobadoSeleccionado ? formatoNumero(pagoAprobadoSeleccionado.monto) : '0.00' }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Comprobante aprobado</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarVerPagoAprobado"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pago-dialog-body pa-5 pt-4">
          <p class="text-body-2 mb-4">
            * Registrado por {{ pagoAprobadoSeleccionado?.nombre_reali || pagoAprobadoSeleccionado?.usuario || 'N/A' }} en fecha: {{ pagoAprobadoSeleccionado?.fecha || 'N/A' }}
            <span v-if="pagoAprobadoSeleccionado?.nombre_apro">
              <br>* Aprobado por {{ pagoAprobadoSeleccionado?.nombre_apro }}
            </span>
            <br>* Tipo de pago: {{ getTipoPagoLabel(pagoAprobadoSeleccionado?.tipo_pago) }}
          </p>

          <div v-if="auth.can('pagos.attachments.view') && (pagoAprobadoSeleccionado?.tipo_pago === 0 || pagoAprobadoSeleccionado?.tipo_pago === 2)">
            <div v-if="esImagenExtension(getPagoExtension(pagoAprobadoSeleccionado))">
              <img
                :src="getPagoArchivoUrl(pagoAprobadoSeleccionado)"
                alt="Comprobante"
                style="max-width: 100%; border-radius: 8px;"
              />
            </div>
            <div v-else>
              <VBtn
                color="info"
                target="_blank"
                :href="getPagoArchivoUrl(pagoAprobadoSeleccionado)"
              >
                Descargar Comprobante
              </VBtn>
            </div>
          </div>
          <p
            v-else-if="pagoAprobadoSeleccionado?.tipo_pago !== 1"
            class="text-medium-emphasis"
          >
            No tienes permiso para ver el comprobante.
          </p>

          <div v-else-if="pagoAprobadoSeleccionado?.tipo_pago === 1">
            Pago realizado en efectivo
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VSpacer />
          <VBtn
            v-if="pagoAprobadoSeleccionado?.tiene_recibo && auth.can('pagos.receipts.view')"
            color="info"
            variant="tonal"
            prepend-icon="tabler-download"
            :href="pagoAprobadoSeleccionado ? `/pagos/${pagoAprobadoSeleccionado.id}/recibo` : '#'"
            target="_blank"
          >
            Descargar Recibo {{ pagoAprobadoSeleccionado?.numero_recibo ? `(${pagoAprobadoSeleccionado.numero_recibo})` : '' }}
          </VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            @click="cerrarVerPagoAprobado"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Ver Pago -->
    <VDialog v-model="dialogVerPago" max-width="700" scrollable>
      <VCard class="pago-dialog-card pago-dialog-tall">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3">
            <VIcon icon="tabler-receipt" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Ver Pago de Bs. {{ pagoSeleccionado ? formatoNumero(pagoSeleccionado.monto) : '0.00' }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Comprobante pendiente de aprobación</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarVerPago"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pago-dialog-body pa-5 pt-4">
          <p class="text-body-2 mb-4">
            * Registrado por {{ pagoSeleccionado?.nombre_reali || pagoSeleccionado?.usuario || 'N/A' }} en fecha: {{ pagoSeleccionado?.fecha || 'N/A' }}
            <span v-if="pagoSeleccionado?.nombre_apro">
              <br>* Aprobado por {{ pagoSeleccionado?.nombre_apro }}
            </span>
            <br>* Tipo de pago: {{ getTipoPagoLabel(pagoSeleccionado?.tipo_pago) }}
          </p>

          <div v-if="auth.can('pagos.attachments.view') && (pagoSeleccionado?.tipo_pago === 0 || pagoSeleccionado?.tipo_pago === 2)">
            <div v-if="esImagenExtension(getPagoExtension(pagoSeleccionado))">
              <img
                :src="getPagoArchivoUrl(pagoSeleccionado)"
                alt="Comprobante"
                style="max-width: 100%;"
              />
            </div>
            <div v-else>
              <VBtn
                color="info"
                target="_blank"
                :href="getPagoArchivoUrl(pagoSeleccionado)"
              >
                Descargar Comprobante
              </VBtn>
            </div>
          </div>
          <p
            v-else-if="pagoSeleccionado?.tipo_pago !== 1"
            class="text-medium-emphasis"
          >
            No tienes permiso para ver el comprobante.
          </p>

          <div v-else-if="pagoSeleccionado?.tipo_pago === 1">
            Pago realizado en efectivo
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VSpacer />
          <VBtn
            v-if="pagoSeleccionado?.tiene_recibo && auth.can('pagos.receipts.view')"
            color="info"
            variant="tonal"
            prepend-icon="tabler-download"
            :href="pagoSeleccionado ? `/pagos/${pagoSeleccionado.id}/recibo` : '#'"
            target="_blank"
          >
            Descargar Recibo {{ pagoSeleccionado?.numero_recibo ? `(${pagoSeleccionado.numero_recibo})` : '' }}
          </VBtn>
          <VBtn
            v-if="pagoSeleccionado?.estado === 0 && auth.can('pagos.approve')"
            color="primary"
            variant="flat"
            @click="aprobarPago(pagoSeleccionado)"
          >
            Aprobar
          </VBtn>
          <VBtn
            v-if="pagoSeleccionado?.estado === 0 && auth.can('pagos.reject')"
            color="error"
            variant="flat"
            @click="rechazarPago(pagoSeleccionado)"
          >
            Rechazar y Eliminar
          </VBtn>
          <VBtn
            color="secondary"
            variant="flat"
            @click="cerrarVerPago"
          >
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Dialog Subir Pago -->
    <VDialog v-model="dialogPago" max-width="700" persistent scrollable>
      <VCard class="pago-dialog-card pago-dialog-tall">
        <VCardTitle class="pago-dialog-header d-flex align-center pa-5">
          <div class="pago-dialog-icon me-3">
            <VIcon icon="tabler-upload" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">Subir Pago</div>
            <div class="text-caption text-medium-emphasis mt-1">Registro de comprobante</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" @click="cerrarDialogPago"><VIcon icon="tabler-x" /></VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pago-dialog-body pa-5 pt-4">
          <p class="text-body-2 mb-4">
            Por favor, ingrese los datos de pago de la empresa <strong>{{ empresaSeleccionada.nombre_empresa }}</strong>
          </p>

          <VForm>
            <VRow>
              <!-- Monto -->
              <VCol cols="12" md="6">
                <AppTextField
                  v-model.number="formPago.monto"
                  label="Monto (Bs.)"
                  type="number"
                  step="0.01"
                  min="0"
                  placeholder="0.00"
                  prefix="Bs. "
                  required
                  outlined
                />
              </VCol>

              <!-- Tipo de Pago -->
              <VCol cols="12" md="6">
                <AppSelect
                  v-model="formPago.tipo_pago"
                  :items="tiposPago"
                  label="Tipo de Pago"
                  required
                  outlined
                />
              </VCol>
            </VRow>

            <VRow>
              <VCol cols="12">
                <VCheckbox
                  v-model="formPago.generar_recibo"
                  label="Generar recibo"
                  :disabled="formPago.tipo_pago === 1 || formPago.tipo_pago === 0"
                  :hint="formPago.tipo_pago === 1
                    ? 'Para efectivo se genera automáticamente.'
                    : formPago.tipo_pago === 2
                      ? 'Para cheque, marca esta opción si deseas emitir recibo.'
                      : 'Para depósito no se genera recibo.'"
                  persistent-hint
                />
              </VCol>

              <!-- Foto (requerida si es Depósito o Cheque, oculta si es Efectivo) -->
              <VCol v-if="formPago.tipo_pago !== 1" cols="12">
                <div class="d-flex flex-column gap-2">
                  <label class="text-body-2">
                    Foto / Comprobante
                    <span class="text-error">*</span>
                  </label>
                  <input
                    type="file"
                    accept="image/*,.pdf"
                    @change="(e) => formPago.foto = e.target.files?.[0] || null"
                    outlined
                  />
                  <span class="text-caption text-muted">
                    Formatos permitidos: JPG, PNG, PDF
                  </span>
                </div>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>

        <VDivider />

        <VCardActions class="pago-dialog-actions pa-5 d-flex justify-end gap-2">
          <VSpacer />
          <VBtn
            color="secondary"
            variant="flat"
            @click="cerrarDialogPago"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            variant="flat"
            @click="subirPagoConfirmar"
          >
            Subir Pago
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar Notificaciones -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="4000"
      location="top right"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped>
.contrato-list-filter {
  inline-size: 12rem;
}

.empresa-cell,
.empresa-nombre {
  white-space: normal !important;
  word-break: normal;
  overflow-wrap: break-word;
  line-height: 1.35;
}

.empresa-cell {
  min-inline-size: 260px;
}

.empresa-nombre {
  font-size: 0.95rem;
}

.pagos-botones-columna {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 8px;
  padding: 6px;
}

.pago-item-btn {
  inline-size: 100%;
  justify-content: flex-start;
}

.header-dos-lineas {
  white-space: normal !important;
  line-height: 1.2;
}

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
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.pago-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
  flex: 1 1 auto;
  overflow-y: auto;
}

.pago-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}

@media (max-width: 640px) {
  .pago-dialog-header,
  .pago-dialog-body,
  .pago-dialog-actions {
    padding: 16px !important;
  }
}
</style>
