<script setup>
import { ref, computed, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

const router = useRouter()
const route = useRoute()
const searchQuery = ref('')
const selectedRows = ref([])

// Data table options
const itemsPerPage = ref(10)
const page = ref(1)
const sortBy = ref()
const orderBy = ref()

const updateOptions = options => {
  sortBy.value = options.sortBy[0]?.key
  orderBy.value = options.sortBy[0]?.order
}

// Estados de carga
const loading = ref(false)
const ingresos = ref([])
const totalIngresos = ref(0)

const widgetData = ref([
  {
    title: 'Total Ingresos',
    value: 0,
    icon: 'tabler-package-import',
  },
  {
    title: 'Este Mes',
    value: 0,
    icon: 'tabler-calendar',
  },
  {
    title: 'Importe Total',
    value: 'Bs. 0',
    icon: 'tabler-cash',
  },
  {
    title: 'Proveedores',
    value: 0,
    icon: 'tabler-building-store',
  },
])

const auth = useAuthStore()
const canViewIngresos = computed(() => auth.can('ingresos.view'))
const canCreateIngreso = computed(() => auth.can('ingresos.create'))
const canEditIngreso = computed(() => auth.can('ingresos.update'))
const canCancelIngreso = computed(() => auth.can('ingresos.cancel'))
const canDownloadPdf = computed(() => auth.can('reports.download'))

// 👉 headers
const headers = [
  {
    title: 'Número',
    key: 'numero',
  },
  {
    title: 'Proveedor',
    key: 'proveedor_nombre',
  },
  {
    title: 'Fecha Ingreso',
    key: 'fecha_ingreso',
  },
  {
    title: 'Recibe',
    key: 'persona_recibe',
  },
  {
    title: 'Observaciones',
    key: 'Observaciones',
  },
  {
    title: 'Importe',
    key: 'importe',
  },
  {
    title: 'Estado',
    key: 'estado',
  },
  {
    title: 'Acciones',
    key: 'actions',
    sortable: false,
  },
]

// Formatear número con prefijo NI
const formatNumero = num => {
  if (!num) return '—'
  const padded = String(num).padStart(6, '0')
  return `NI-${padded}`
}

// Fetch ingresos from API
const fetchIngresos = async () => {
  loading.value = true
  if (!canViewIngresos.value) {
    ingresos.value = []
    totalIngresos.value = 0
    loading.value = false
    return
  }
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('per_page', String(itemsPerPage.value))
    if (searchQuery.value) params.set('q', searchQuery.value)
    if (sortBy.value) params.set('sort_by', sortBy.value)
    if (orderBy.value) params.set('sort_dir', orderBy.value === 'asc' ? 'asc' : 'desc')

    const res = await fetch(`/inventario/ingresos?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()

    ingresos.value = json.data || []
    totalIngresos.value = json.meta?.total ?? ingresos.value.length
    
    // Actualizar widgets
    widgetData.value[0].value = totalIngresos.value
  } catch (err) {
    console.error('Error cargando ingresos', err)
  } finally {
    loading.value = false
  }
}

// Cargar inicial
fetchIngresos()

// Watchers
watchDebounced(searchQuery, () => { page.value = 1; fetchIngresos() }, { debounce: 400 })
watch([page, itemsPerPage, sortBy, orderBy], () => { fetchIngresos() })

const downloadPdf = id => {
  window.open(`/inventario/ingresos/${id}/pdf`, '_blank')
}

// Dialog de anulación
const deleteDialogVisible = ref(false)
const ingresoToDelete = ref(null)
const motivoAnulacion = ref('')
const cargandoAnulacion = ref(false)
const mensajeExito = ref('')
const mensajeError = ref('')
const showSnackSuccess = ref(false)
const showSnackError = ref(false)

const openDeleteDialog = ingreso => {
  ingresoToDelete.value = ingreso
  motivoAnulacion.value = ''
  deleteDialogVisible.value = true
}

const deleteIngreso = async () => {
  if (!ingresoToDelete.value || !motivoAnulacion.value.trim()) return
  
  cargandoAnulacion.value = true
  try {
    const res = await fetch('/inventario/anularIngreso', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        ingreso_id: ingresoToDelete.value.id_ingreso,
        motivo: motivoAnulacion.value.trim(),
      }),
    })

    if (!res.ok) {
      const errorData = await res.json()
      throw new Error(errorData.message || 'Error al anular ingreso')
    }

    const json = await res.json()
    mensajeExito.value = json.message || 'Ingreso anulado correctamente'
    showSnackSuccess.value = true
    
    // Delete from selectedRows
    const index = selectedRows.value.findIndex(row => row === ingresoToDelete.value.id_ingreso)
    if (index !== -1)
      selectedRows.value.splice(index, 1)

    // Close dialog and refetch
    deleteDialogVisible.value = false
    ingresoToDelete.value = null
    motivoAnulacion.value = ''
    
    // Recargar tabla después de 1.5 segundos
    setTimeout(() => {
      page.value = 1
      fetchIngresos()
    }, 1500)
  } catch (err) {
    mensajeError.value = err.message || 'Error al anular el ingreso'
    showSnackError.value = true
    console.error('Error anulando ingreso', err)
  } finally {
    cargandoAnulacion.value = false
  }
}

// Mostrar mensaje de éxito si viene en la URL
const successMsg = ref('')
const showSuccess = ref(false)

if (route.query.success) {
  successMsg.value = String(route.query.success)
  showSuccess.value = true
  // Limpiar el parámetro de la URL después de mostrarlo
  router.replace({ path: route.path, query: { ...route.query, success: undefined } })
}
</script>

<template>
  <section>
    <VAlert
      v-if="!canViewIngresos"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      No tienes permisos para ver ingresos.
    </VAlert>

    <template v-else>
    <VSnackbar v-model="showSuccess" color="success" timeout="4000">
      {{ successMsg }}
    </VSnackbar>

    <!-- Snackbar de Éxito de Anulación -->
    <VSnackbar v-model="showSnackSuccess" color="success" timeout="4000">
      {{ mensajeExito }}
    </VSnackbar>

    <!-- Snackbar de Error de Anulación -->
    <VSnackbar v-model="showSnackError" color="error" timeout="4000">
      {{ mensajeError }}
    </VSnackbar>

    <!-- Dialog de confirmación de anulación -->
    <VDialog
      v-model="deleteDialogVisible"
      max-width="500"
    >
      <VCard>
        <VCardTitle class="text-h5 d-flex align-center gap-2">
          <VIcon icon="tabler-alert-circle" color="error" />
          Anular Ingreso
        </VCardTitle>
        <VDivider />
        <VCardText class="pa-6">
          <p class="mb-4 text-body2">
            ¿Está seguro que desea anular la nota de ingreso
            <strong class="text-error">{{ formatNumero(ingresoToDelete?.numero) }}</strong>?
          </p>
          <p class="mb-4 text-caption text-secondary">
            Esta acción revertirá el stock y costo de todas las asignaciones asociadas.
          </p>
          <AppTextField
            v-model="motivoAnulacion"
            label="Motivo de Anulación"
            placeholder="Ingrese el motivo..."
            outlined
            required
            :rules="[v => !!v || 'El motivo es requerido']"
            counter="255"
            multi-line
            rows="3"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4 justify-end gap-2">
          <VBtn
            variant="tonal"
            @click="deleteDialogVisible = false; ingresoToDelete = null; motivoAnulacion = ''"
            :disabled="cargandoAnulacion"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            @click="deleteIngreso"
            :loading="cargandoAnulacion"
            :disabled="!motivoAnulacion.trim()
 || cargandoAnulacion"
          >
            <VIcon icon="tabler-check" class="me-2" />
            Confirmar Anulación
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VCard id="ingreso-list">
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Mostrar</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: -1, title: 'Todos' },
              ]"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = parseInt($event, 10)"
            />
          </div>
          <!-- 👉 Create ingreso -->
          <VBtn
            prepend-icon="tabler-plus"
            v-if="canCreateIngreso"
            @click="router.push('/inventario/ingreso-register')"
          >
            Registrar Ingreso
          </VBtn>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div class="ingreso-list-filter">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar ingresos..."
            />
          </div>
        </div>
      </VCardText>
      <VDivider />

      <!-- SECTION Datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="totalIngresos"
        :headers="headers"
        :items="ingresos"
        :loading="loading"
        item-value="id_ingreso"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Numero -->
        <template #item.numero="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            {{ formatNumero(item.numero) }}
          </span>
        </template>

        <!-- Proveedor -->
        <template #item.proveedor_nombre="{ item }">
          <div class="d-flex align-center">
            <VAvatar
              size="34"
              color="primary"
              variant="tonal"
              class="me-3"
            >
              <VIcon icon="tabler-building-store" />
            </VAvatar>
            <div class="d-flex flex-column">
              <span class="text-body-1 font-weight-medium">
                {{ item.proveedor_nombre || '—' }}
              </span>
            </div>
          </div>
        </template>

        <!-- Fecha Ingreso -->
        <template #item.fecha_ingreso="{ item }">
          {{ item.fecha_ingreso || '—' }}
        </template>

        <!-- Persona Recibe -->
        <template #item.persona_recibe="{ item }">
          {{ item.persona_recibe || '—' }}
        </template>

        <!-- Observaciones -->
        <template #item.Observaciones="{ item }">
          <span class="text-sm text-truncate" style="max-width: 200px; display: block;">
            {{ item.Observaciones || '—' }}
          </span>
        </template>

        <!-- Importe -->
        <template #item.importe="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            Bs. {{ Number(item.importe || 0).toFixed(2) }}
          </span>
        </template>

        <!-- Estado -->
        <template #item.estado="{ item }">
          <VChip
            :color="Number(item.estado) === 1 ? 'success' : 'error'"
            variant="tonal"
            size="small"
            class="font-weight-medium"
          >
            {{ Number(item.estado) === 1 ? 'Activo' : 'Anulado' }}
          </VChip>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <VTooltip text="Ver">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="router.push(`/inventario/preview/${item.id_ingreso}`)">
                  <VIcon icon="tabler-eye" color="info" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>

          <VTooltip v-if="canEditIngreso" :text="Number(item.estado) === 0 ? 'Ingreso anulado – no se puede editar' : 'Editar'">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn 
                  :disabled="Number(item.estado) === 0"
                  @click="Number(item.estado) !== 0 && router.push(`/inventario/edit/${item.id_ingreso}`)"
                >
                  <VIcon 
                    icon="tabler-edit" 
                    :color="Number(item.estado) === 0 ? 'grey' : 'success'" 
                  />
                </IconBtn>
              </span>
            </template>
          </VTooltip>

          <VTooltip v-if="canDownloadPdf" text="Descargar PDF">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="downloadPdf(item.id_ingreso)">
                  <VIcon icon="tabler-download" color="primary" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>

          <VTooltip v-if="canCancelIngreso" text="Anular">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="openDeleteDialog(item)">
                  <VIcon icon="tabler-ban" color="error" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    <!-- !SECTION -->
    </VCard>
    </template>
  </section>
</template>

<style lang="scss">
#ingreso-list {
  .ingreso-list-actions {
    inline-size: 8rem;
  }

  .ingreso-list-filter {
    inline-size: 12rem;
  }
}
</style>

