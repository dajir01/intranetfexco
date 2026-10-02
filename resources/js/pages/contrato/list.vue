<script setup>
import { watchDebounced } from '@vueuse/core'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useContratoAccess } from '@/composables/useContratoAccess'
import { useSafePagination } from '@/composables/useSafePagination'

const router = useRouter()
const route = useRoute()
const { 
  canViewContratos, 
  canCreateContrato,
  canDeleteContrato, 
  canUpdateEmpresa, 
  canUpdateStands,
  canPrintContrato,
  canExportContrato,
} = useContratoAccess()

// Validar acceso
if (!canViewContratos()) {
  router.push({ name: 'acceso-restringido' })
}

// Estado
const loading = ref(false)
const loadingContratos = ref(false)
const ferias = ref([])
const feriaSeleccionada = ref(null)
const contratos = ref([])
const feriaInfo = ref(null)
const error = ref(null)

// Estado de tabla y filtros
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy = ref([{ key: 'empresa', order: 'asc' }])
const search = ref('')

// Headers de la tabla
const headers = [
  { title: 'Empresa', key: 'empresa', sortable: true },
  { title: 'Contrato', key: 'contrato', sortable: true },
  { title: 'Pabellón y Stands', key: 'pabellon', sortable: true },
  { title: 'Área - Precios', key: 'area', sortable: true },
  { title: 'Personal', key: 'personal', sortable: true },
  { title: 'Productos', key: 'productos', sortable: true },
  { title: 'Cred', key: 'credenciales', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false },
]
const cargarFerias = async () => {
  loading.value = true
  error.value = null
  
  try {
    const response = await fetch('/contratos/ferias', {
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

// Cargar contratos cuando se selecciona una feria
const cargarContratos = async () => {
  if (!feriaSeleccionada.value) {
    contratos.value = []
    feriaInfo.value = null
    
    return
  }
  
  loadingContratos.value = true
  error.value = null
  
  try {
    const response = await fetch(`/contratos/ferias/${feriaSeleccionada.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const json = await response.json()
    
    if (json.success) {
      contratos.value = json.data.contratos
      feriaInfo.value = json.data.feria
    } else {
      error.value = json.message || 'Error al cargar los contratos'
      contratos.value = []
      feriaInfo.value = null
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar los contratos'
    contratos.value = []
    feriaInfo.value = null
  } finally {
    loadingContratos.value = false
  }
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

// Computadas
const normalizeText = value => String(value ?? '').toLowerCase()

const getSortableValue = (item, key) => {
  switch (key) {
    case 'empresa':
      return String(item.empresa?.nombre ?? '')
    case 'contrato':
      return String(item.contrato?.numero ?? '')
    case 'pabellon':
      return `${item.pabellon ?? ''} ${item.stands_texto ?? ''}`.trim()
    case 'area':
      return `${item.area?.metraje_total ?? ''} ${item.area?.precio_unitario ?? ''} ${item.area?.descuento ?? ''} ${item.area?.precio_con_descuento ?? item.area?.precio_total ?? ''}`.trim()
    case 'personal':
      return `${item.contacto?.responsable ?? ''} ${item.contacto?.gerente ?? ''}`.trim()
    case 'productos':
      return String(item.productos ?? '')
    case 'credenciales':
      return String(item.credenciales ?? '')
    default:
      return ''
  }
}

const contratosFiltrados = computed(() => {
  const term = normalizeText(search.value)
  if (!term) return contratos.value

  return contratos.value.filter(c => {
    const valores = [
      c.empresa?.nombre,
      c.contrato?.numero,
      c.pabellon,
      c.stands_texto,
      c.contacto?.responsable,
      c.contacto?.gerente,
      c.productos,
    ]

    return valores.some(v => normalizeText(v).includes(term))
  })
})

const totalFiltrado = computed(() => contratosFiltrados.value.length)

const contratosOrdenados = computed(() => {
  const items = [...contratosFiltrados.value]
  const sort = sortBy.value?.[0]
  if (!sort?.key) return items

  const dir = sort.order === 'desc' ? -1 : 1
  return items.sort((a, b) => {
    const aVal = getSortableValue(a, sort.key)
    const bVal = getSortableValue(b, sort.key)
    return aVal.localeCompare(bVal, 'es', { sensitivity: 'base' }) * dir
  })
})

const contratosPaginados = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value
  return contratosOrdenados.value.slice(start, end)
})

const tieneContratosFiltrados = computed(() => totalFiltrado.value > 0)

const tituloTabla = computed(() => {
  if (!feriaInfo.value) return 'Contratos'
  
  return `Contratos - ${feriaInfo.value.nombre}`
})

// Resumen de conteos: reservas, contratos y anulados
const resumenFeria = computed(() => {
  const items = contratos.value || []
  let reservas = 0
  let generados = 0
  let anulados = 0

  for (const c of items) {
    const anulado = !!c?.anulado
    if (anulado) {
      anulados++
      continue
    }
    const isGenerado = esContratoGenerado(c)
    if (isGenerado) generados++
    else reservas++
  }

  return { reservas, contratos: generados, anulados }
})

// Función para ir a nueva reserva
const irANuevaReserva = () => {
  if (!canCreateContrato()) return

  if (feriaSeleccionada.value) {
    router.push({
      name: 'contrato-reserva',
      query: { id_feria: feriaSeleccionada.value }
    })
  }
}

// Estado para exportación
const exportando = ref(false)

// Función para exportar contratos a Excel
const exportarContratos = async () => {
  if (!canExportContrato()) {
    snackbar.value = { show: true, color: 'error', text: 'No tienes permiso para exportar contratos' }
    return
  }

  if (!feriaSeleccionada.value || !feriaInfo.value) {
    snackbar.value = { show: true, color: 'error', text: 'Selecciona una feria para exportar' }
    return
  }

  exportando.value = true
  try {
    const response = await fetch(`/contratos/exportar/${feriaSeleccionada.value}`, {
      headers: { Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
      credentials: 'same-origin',
    })

    if (!response.ok) {
      let mensaje = 'Error en la descarga'

      try {
        const errorData = await response.json()
        mensaje = errorData?.message || mensaje
      } catch {
        try {
          const texto = await response.text()
          if (texto) {
            mensaje = texto
          }
        } catch {
          mensaje = 'Error en la descarga'
        }
      }

      throw new Error(mensaje)
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `Lista Contratos - ${feriaInfo.value.nombre}.xlsx`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)

    snackbar.value = { show: true, color: 'success', text: 'Archivo exportado correctamente' }
  } catch (err) {
    const mensaje = err instanceof Error && err.message ? err.message : 'Error al exportar los contratos'
    snackbar.value = { show: true, color: 'error', text: mensaje }
  } finally {
    exportando.value = false
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
      await cargarContratos()
    }
  }
})

// ===== TIEMPO REAL: Suscripción a cambios de contratos =====
const canalContratosActual = ref(null)
const refrescandoPorEventoContrato = ref(false)

const suscribirCanalContratos = () => {
  if (!feriaSeleccionada.value || !window.Echo) return
  
  const nuevoCanal = `contratos.feria.${feriaSeleccionada.value}`
  
  // Evitar duplicados: si ya está suscrito al mismo canal, no hacer nada
  if (canalContratosActual.value === nuevoCanal) return
  
  // Limpiar suscripción anterior si existe
  limpiarSuscripcionContratos()
  
  // Suscribirse al nuevo canal
  canalContratosActual.value = nuevoCanal
  window.Echo
    .private(nuevoCanal)
    .listen('.ContratoActualizado', manejarActualizacionContrato)
}

const manejarActualizacionContrato = (event) => {
  // Evitar loops de actualización automática
  if (refrescandoPorEventoContrato.value) return
  
  // Validar que el evento pertenece a la feria actual
  if (event.id_feria !== feriaSeleccionada.value) return
  
  refrescandoPorEventoContrato.value = true
  cargarContratos().finally(() => {
    refrescandoPorEventoContrato.value = false
  })
}

const limpiarSuscripcionContratos = () => {
  if (canalContratosActual.value && window.Echo) {
    window.Echo.leaveChannel(canalContratosActual.value)
    canalContratosActual.value = null
  }
}

// Watch: suscribirse cuando cambia la feria
watch(feriaSeleccionada, () => {
  suscribirCanalContratos()
})

// OnUnmounted: limpiar suscripción
onUnmounted(() => {
  limpiarSuscripcionContratos()
})

// Reactividad: búsqueda con debounce
watchDebounced(search, () => {
  page.value = 1
}, { debounce: 400 })

// Reactividad: paginación y orden
watch([page, itemsPerPage, sortBy], () => {
  page.value = Math.max(1, page.value)
})

// Resetear paginación al cambiar contratos
watch(contratos, () => {
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

// ===== Acciones dinámicas por estado =====
const dialogEliminarReserva = ref(false)
const eliminandoReserva = ref(false)
const dialogAnularContrato = ref(false)
const itemSeleccionado = ref(null)
const snackbar = ref({ show: false, color: 'success', text: '' })
const dialogLinkLlenado = ref(false)
const linkLlenado = ref('')
const empresaLinkLlenado = ref('')

const esContratoGenerado = (item) => {
  if (item?.anulado) return false
  const codigo = Number(item?.codigo_contrato ?? 0)
  if (codigo && codigo > 0) return true
  const numero = String(item?.contrato?.numero ?? '')
  return !!numero && !numero.endsWith('00000')
}

const abrirLinkLlenado = (item) => {
  const idContrato = item.id_contrato
  const clave = item.clave
  if (!idContrato || !clave) {
    snackbar.value = { show: true, color: 'error', text: 'No se encontró el enlace público de llenado.' }
    return
  }
  linkLlenado.value = `${window.location.origin}/formulario/${idContrato}/${clave}`
  empresaLinkLlenado.value = item.empresa?.nombre || 'No identificada'
  dialogLinkLlenado.value = true
}

const copiarLinkLlenado = async () => {
  try {
    await navigator.clipboard.writeText(linkLlenado.value)
    snackbar.value = { show: true, color: 'success', text: 'Link copiado al portapapeles' }
    dialogLinkLlenado.value = false
  } catch (err) {
    try {
      const textarea = document.createElement('textarea')
      textarea.value = linkLlenado.value
      textarea.setAttribute('readonly', '')
      textarea.style.position = 'absolute'
      textarea.style.left = '-9999px'
      document.body.appendChild(textarea)
      textarea.select()
      const ok = document.execCommand('copy')
      document.body.removeChild(textarea)

      if (ok) {
        snackbar.value = { show: true, color: 'success', text: 'Link copiado al portapapeles' }
        dialogLinkLlenado.value = false
        return
      }
    } catch (fallbackErr) {
      // Fallback failed
    }

    snackbar.value = { show: true, color: 'error', text: 'No se pudo copiar el link' }
  }
}

const abrirEnNuevaPestana = () => {
  window.open(linkLlenado.value, '_blank')
  dialogLinkLlenado.value = false
}
const irAEditarReserva = (item) => {
  const id = item.id_contrato
  const idFeria = feriaSeleccionada.value
  const path = idFeria ? `/contrato/edit/${id}?id_feria=${idFeria}` : `/contrato/edit/${id}`
  router.push(path)
}

const solicitarEliminarReserva = (item) => {
  itemSeleccionado.value = item
  dialogEliminarReserva.value = true
}

const solicitarAnularContrato = (item) => {
  itemSeleccionado.value = item
  dialogAnularContrato.value = true
}

// CSRF helper para peticiones protegidas
const obtenerCsrf = async () => {
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  if (!csrfToken) {
    const csrfResponse = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (csrfResponse.ok) {
      const csrfJson = await csrfResponse.json().catch(() => null)
      csrfToken = csrfJson?.token || ''
    }
  }
  return csrfToken
}

const confirmarEliminarReserva = async () => {
  const item = itemSeleccionado.value
  dialogEliminarReserva.value = false
  if (!item) return
  try {
    eliminandoReserva.value = true
    const csrf = await obtenerCsrf()
    const resp = await fetch(`/reservas/${item.id_contrato}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
      credentials: 'same-origin',
    })
    const json = await resp.json().catch(()=>({}))
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'Error al eliminar la reserva/contrato')
    // Refrescar listado para reflejar cambios (liberar stands o eliminar registro)
    await cargarContratos()
    snackbar.value = { show: true, color: 'success', text: json.message || 'Operación realizada correctamente.' }
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: e.message || 'No se pudo eliminar la reserva/contrato.' }
  }
  eliminandoReserva.value = false
}

const confirmarAnularContrato = async () => {
  const item = itemSeleccionado.value
  dialogAnularContrato.value = false
  if (!item) return
  try {
    // Reutilizar la lógica existente: DELETE /reservas/{id}
    const csrf = await obtenerCsrf()
    const resp = await fetch(`/reservas/${item.id_contrato}`, {
      method: 'DELETE',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
      credentials: 'same-origin',
    })
    const json = await resp.json().catch(()=>({}))
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'Error al anular el contrato')
    await cargarContratos()
    snackbar.value = { show: true, color: 'warning', text: json.message || 'Contrato anulado correctamente.' }
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: 'No se pudo anular el contrato.' }
  }
}

// Abrir impresión de contrato en nueva pestaña
const imprimirContrato = (item) => {
  try {
    if (!item || item.anulado) {
      snackbar.value = { show: true, color: 'warning', text: 'Contrato anulado: no disponible para impresión.' }
      return
    }
    // Usar la misma lógica que el chip/acciones para determinar si el contrato está generado
    if (!esContratoGenerado(item)) {
      snackbar.value = { show: true, color: 'warning', text: 'Primero genere el contrato para habilitar la impresión.' }
      return
    }
    const url = `/contratos/${item.id_contrato}/imprimir`
    window.open(url, '_blank')
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: 'No se pudo abrir la impresión del contrato.' }
  }
}

const imprimirAdendum = (item) => {
  try {
    if (!item || item.anulado) {
      snackbar.value = { show: true, color: 'warning', text: 'Contrato anulado: no disponible para impresión.' }
      return
    }
    if (!esContratoGenerado(item)) {
      snackbar.value = { show: true, color: 'warning', text: 'Primero genere el contrato para habilitar la impresión del adendum.' }
      return
    }
    const url = `/imprimir/adendum/${item.id_contrato}`
    window.open(url, '_blank')
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: 'No se pudo abrir la impresión del adendum.' }
  }
}
</script>

<template>
  <div>
    <!-- Selector de Feria -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              Gestión de Contratos
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
              placeholder="Elige una feria para ver sus contratos"
              clearable
              @update:model-value="cargarContratos"
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
                    Código: {{ feriaInfo.codigo }} | Reservas: {{ resumenFeria.reservas }} | Contratos: {{ resumenFeria.contratos }} | Anulados: {{ resumenFeria.anulados }}
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

    <!-- Tabla de Contratos -->
    <VCard v-if="feriaSeleccionada" id="contrato-list">
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
              placeholder="Buscar contratos..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              dense
              outlined
            />
          </div>

          <!-- Botón Nueva Reserva -->
          <VBtn
            v-if="feriaInfo && feriaInfo.estado === 1 && canCreateContrato()"
            color="primary"
            prepend-icon="tabler-plus"
            @click="irANuevaReserva"
          >
            Nueva Reserva
          </VBtn>

          <!-- Botón Exportar -->
          <VBtn
            v-if="feriaInfo && contratos.length > 0 && canExportContrato()"
            color="success"
            variant="outlined"
            prepend-icon="tabler-download"
            :loading="exportando"
            :disabled="exportando"
            @click="exportarContratos"
          >
            Exportar {{ feriaInfo.nombre }}
          </VBtn>

          <!-- Botón Estado de Ventas -->
          <VBtn
            v-if="feriaInfo"
            color="info"
            variant="outlined"
            prepend-icon="tabler-chart-bar"
            @click="router.push({ path: `/contrato/reporte/${feriaSeleccionada}` })"
          >
            Estado de Ventas
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
          v-if="loadingContratos"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">
            Cargando contratos...
          </p>
        </div>

        <!-- Sin contratos -->
        <VAlert
          v-else-if="!tieneContratosFiltrados"
          type="info"
          variant="tonal"
        >
          No hay contratos registrados para esta feria.
        </VAlert>

        <!-- Tabla con contratos -->
        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :items-length="totalFiltrado"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="contratosPaginados"
          :loading="loadingContratos"
          item-value="id_contrato"
          class="text-no-wrap"
          @update:options="updateOptions"
        >
          <template #item.empresa="{ item }">
            <div class="d-flex flex-column empresa-cell">
              <span class="font-weight-medium empresa-nombre">
                {{ item.empresa?.nombre || '—' }}
              </span>
            </div>
          </template>

          <template #item.contrato="{ item }">
            <VTooltip location="top">
              <template #activator="{ props }">
                <VChip
                  v-bind="props"
                  :color="item.anulado ? 'error' : (esContratoGenerado(item) ? 'success' : 'info')"
                  size="small"
                  :style="{ cursor: item.anulado ? 'not-allowed' : 'pointer', transition: 'all 0.2s' }"
                  class="contrato-link"
                  @click="!item.anulado && router.push({ 
                    path: `/contrato/llenado/${item.id_contrato}`,
                    query: { id_feria: feriaSeleccionada }
                  })"
                >
                  <VIcon icon="tabler-file-text" size="16" class="me-1" />
                  {{ item.contrato?.numero || '—' }}
                </VChip>
              </template>
              <span>{{ item.anulado ? 'Ver contrato anulado' : 'Clic para editar/llenar contrato' }}</span>
            </VTooltip>
          </template>

          <template #item.pabellon="{ item }">
            <div v-if="item.anulado">
              <VChip
                color="error"
                size="small"
              >
                CONTRATO ANULADO
              </VChip>
            </div>
            <div v-else>
              <div class="font-weight-medium mb-1">
                {{ item.pabellon || '—' }}
              </div>
              <div class="text-caption">
                Stands: {{ item.stands_texto || '—' }}
              </div>
            </div>
          </template>

          <template #item.area="{ item }">
            <div class="d-flex flex-column gap-1">
              <div class="text-caption">
                <strong>Área:</strong> {{ item.area?.metraje_total ?? '—' }} m²
              </div>
              <div class="text-caption">
                <strong>Precio/m²:</strong> {{ formatoPrecio(item.area?.precio_unitario) }}
              </div>
              <div
                v-if="item.area?.descuento > 0"
                class="text-caption"
              >
                <strong>Descuento:</strong> {{ item.area.descuento }}%
              </div>
              <div class="text-caption">
                <strong>Total:</strong>
                <span
                  class="text-success font-weight-bold"
                >
                  <span v-if="item.area?.precio_con_descuento">
                    {{ formatoPrecio(item.area.precio_con_descuento) }}
                  </span>
                  <span v-else>
                    {{ formatoPrecio(item.area?.precio_total) }}
                  </span>
                </span>
              </div>
            </div>
          </template>

          <template #item.personal="{ item }">
            <div class="d-flex flex-column gap-1 personal-cell">
              <div class="text-caption">
                <strong>Responsable:</strong><br>
                <span class="personal-text">{{ item.contacto?.responsable || '—' }}</span>
              </div>
              <div class="text-caption">
                <strong>Gerente:</strong><br>
                <span class="personal-text">{{ item.contacto?.gerente || '—' }}</span>
              </div>
            </div>
          </template>

          <template #item.productos="{ item }">
            <div
              class="text-caption productos-cell"
              :title="item.productos || ''"
            >
              {{ item.productos || '—' }}
            </div>
          </template>

          <template #item.credenciales="{ item }">
            <VChip
              color="info"
              size="small"
            >
              {{ item.credenciales ?? '—' }}
            </VChip>
          </template>

          <template #item.actions="{ item }">
            <div class="acciones-columna">
              <!-- Estado: CONTRATO ANULADO (sin acciones) -->
              <template v-if="item.anulado">
                <VTooltip location="top" class="accion-item">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      color="error"
                      size="small"
                      variant="text"
                      disabled
                      prepend-icon="tabler-ban"
                    >
                      Contrato anulado
                    </VBtn>
                  </template>
                  <span>Contrato actualmente anulado</span>
                </VTooltip>
              </template>

              <!-- Acciones para RESERVA -->
              <template v-else-if="!esContratoGenerado(item)">
                <VTooltip location="top" class="accion-item">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      color="info"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-external-link"
                      @click="abrirLinkLlenado(item)"
                    >
                      Link de Llenado
                    </VBtn>
                  </template>
                  <span>Abrir formulario público de llenado</span>
                </VTooltip>

                <VTooltip location="top" class="accion-item">
                  <template #activator="{ props }">
                    <VBtn
                      v-if="canUpdateEmpresa() || canUpdateStands()"
                      v-bind="props"
                      color="secondary"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-edit"
                      @click="irAEditarReserva(item)"
                    >
                      Cambiar Empresa/Stand
                    </VBtn>
                  </template>
                  <span>Editar empresa y stands de la reserva</span>
                </VTooltip>

                <VTooltip location="top" class="accion-item">
                  <template #activator="{ props }">
                    <VBtn
                      v-if="canDeleteContrato()"
                      v-bind="props"
                      color="error"
                      size="small"
                      variant="tonal"
                      prepend-icon="tabler-trash"
                      @click="solicitarEliminarReserva(item)"
                    >
                      Eliminar Reserva
                    </VBtn>
                  </template>
                  <span>Eliminar la reserva y liberar stands</span>
                </VTooltip>
              </template>

              <!-- Acciones para CONTRATO GENERADO -->
              <template v-else>
                <template v-if="!item.anulado">
                  <VTooltip location="top" class="accion-item">
                    <template #activator="{ props }">
                      <VBtn
                        v-if="canPrintContrato()"
                        v-bind="props"
                        color="primary"
                        size="small"
                        variant="tonal"
                        prepend-icon="tabler-printer"
                          @click="imprimirContrato(item)"
                      >
                        Imprimir Contrato
                      </VBtn>
                    </template>
                    <span>Generar/abrir PDF del contrato</span>
                  </VTooltip>
                  <VTooltip location="top" class="accion-item">
                    <template #activator="{ props }">
                      <VBtn
                        v-if="canPrintContrato()"
                        v-bind="props"
                        color="secondary"
                        size="small"
                        variant="tonal"
                        prepend-icon="tabler-file-plus"
                        @click="imprimirAdendum(item)"
                      >
                        Imprimir Adendum #2
                      </VBtn>
                    </template>
                    <span>Generar/abrir PDF del addendum #2</span>
                  </VTooltip>

                  <VTooltip location="top" class="accion-item">
                    <template #activator="{ props }">
                      <VBtn
                        v-if="canDeleteContrato()"
                        v-bind="props"
                        color="error"
                        size="small"
                        variant="tonal"
                        prepend-icon="tabler-ban"
                        @click="solicitarAnularContrato(item)"
                      >
                        Anular Contrato
                      </VBtn>
                    </template>
                    <span>Anular contrato (sin eliminar registro)</span>
                  </VTooltip>
                </template>

                <VTooltip v-else location="top" class="accion-item">
                  <template #activator="{ props }">
                    <VBtn
                      v-bind="props"
                      color="error"
                      size="small"
                      variant="text"
                      disabled
                      prepend-icon="tabler-ban"
                    >
                      Contrato anulado
                    </VBtn>
                  </template>
                  <span>Contrato actualmente anulado</span>
                </VTooltip>
              </template>
            </div>
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
          Elige un evento/feria del selector superior para visualizar sus contratos
        </p>
      </VCardText>
    </VCard>
  
    <!-- Diálogos y Snackbar -->
    <VDialog v-model="dialogEliminarReserva" max-width="460">
      <VCard>
        <VCardTitle>Confirmar eliminación</VCardTitle>
        <VCardText>
          ¿Está seguro que desea eliminar esta reserva?
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" :disabled="eliminandoReserva" @click="dialogEliminarReserva = false">Cancelar</VBtn>
          <VBtn color="error" :loading="eliminandoReserva" :disabled="eliminandoReserva" @click="confirmarEliminarReserva">Eliminar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogAnularContrato" max-width="460">
      <VCard>
        <VCardTitle>Anular Contrato</VCardTitle>
        <VCardText>
          ¿Confirmas la anulación del contrato? No se eliminará el registro.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogAnularContrato = false">Cancelar</VBtn>
          <VBtn color="error" @click="confirmarAnularContrato">Anular</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">
      {{ snackbar.text }}
    </VSnackbar>

    <VDialog v-model="dialogLinkLlenado" max-width="600">
      <VCard>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-link" />
          Link de Llenado Público
        </VCardTitle>
        <VCardText>
          <div class="mb-4">
            <p class="text-body-2 text-medium-emphasis mb-2">
              <strong>Empresa:</strong> {{ empresaLinkLlenado }}
            </p>
            <p class="text-body-2 text-medium-emphasis mb-4">
              Comparte este link con la empresa para que complete su formulario sin necesidad de login:
            </p>
            <VTextField
              readonly
              :model-value="linkLlenado"
              variant="outlined"
              append-inner-icon="tabler-copy"
              @click:append-inner="copiarLinkLlenado"
              hint="Click en el icono para copiar"
              class="cursor-pointer"
            />
          </div>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="dialogLinkLlenado = false">Cerrar</VBtn>
          <VBtn color="primary" prepend-icon="tabler-external-link" @click="abrirEnNuevaPestana">
            Abrir en nueva pestaña
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
    </div>
</template>

<style lang="scss">
#contrato-list {
  .contrato-list-filter {
    inline-size: 12rem;
  }

  .contrato-link {
    &:hover {
      opacity: 0.8;
      transform: scale(1.05);
    }
  }

  .acciones-columna {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px; // separación vertical entre botones
    padding: 6px; // espacio interno del contenedor
  }

  .acciones-columna .accion-item {
    display: block;
    padding: 4px; // espacio alrededor de cada botón (arriba, abajo, izquierda, derecha)
  }

  .acciones-columna .v-btn {
    inline-size: 100%; // opcional: hace que el botón use el ancho disponible
  }

  .productos-cell {
    white-space: normal !important; // anula text-no-wrap en esta celda
    overflow: hidden;
    word-break: break-word;
    overflow-wrap: anywhere;
    max-inline-size: 240px;
    display: -webkit-box;
    -webkit-line-clamp: 2; // mostrar hasta 2 líneas
    -webkit-box-orient: vertical;
  }

  .empresa-cell,
  .empresa-nombre {
    white-space: normal !important; // permite que nombres largos bajen de línea
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

  .personal-cell {
    min-inline-size: 220px;
  }

  .personal-text {
    display: -webkit-box;
    white-space: normal !important;
    word-break: normal;
    overflow-wrap: break-word;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    line-height: 1.35;
  }
}
</style>

