<script setup>
import { ref, computed, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { useRouter, useRoute } from 'vue-router'

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
const salidas = ref([])
const totalSalidas = ref(0)

const widgetData = ref([
  {
    title: 'Total Salidas',
    value: 0,
    icon: 'tabler-package-export',
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
    title: 'Áreas',
    value: 0,
    icon: 'tabler-layers-3',
  },
])

// 👉 headers
const headers = [
  {
    title: 'Código',
    key: 'codigo',
  },
  {
    title: 'Área',
    key: 'area_nombre',
  },
  {
    title: 'Fecha',
    key: 'fecha',
  },
  {
    title: 'Observaciones',
    key: 'observaciones',
  },
  {
    title: 'Persona Entrega',
    key: 'persona_entrega',
  },
  {
    title: 'Persona Recibe',
    key: 'persona_recibe',
  },
  {
    title: 'Total',
    key: 'total',
  },
  {
    title: 'Acciones',
    key: 'actions',
    sortable: false,
  },
]

// Formatear código de salida
const formatCodigoSalida = codigo => {
  if (!codigo) return '—'
  return `SA-${String(codigo).padStart(6, '0')}`
}

// Formatear fecha a dd/mm/yyyy HH:mm
const formatFecha = fecha => {
  if (!fecha) return '—'
  const date = new Date(fecha)
  return date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' }) + ' ' + date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
}

// Fetch salidas from API
const fetchSalidas = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('per_page', String(itemsPerPage.value))
    if (searchQuery.value) params.set('q', searchQuery.value)
    if (sortBy.value) params.set('sort_by', sortBy.value)
    if (orderBy.value) params.set('sort_dir', orderBy.value === 'asc' ? 'asc' : 'desc')

    const res = await fetch(`/inventario/movimientos/salida?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok) {
      console.warn('Endpoint no disponible, usando datos vacíos')
      salidas.value = []
      totalSalidas.value = 0
      return
    }

    const contentType = res.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      console.warn('La respuesta no es JSON, usando datos vacíos')
      salidas.value = []
      totalSalidas.value = 0
      return
    }

    const json = await res.json()

    salidas.value = json.data || []
    totalSalidas.value = json.meta?.total ?? salidas.value.length
    
    // Actualizar widgets
    widgetData.value[0].value = totalSalidas.value
  } catch (err) {
    console.error('Error cargando salidas', err)
    salidas.value = []
    totalSalidas.value = 0
  } finally {
    loading.value = false
  }
}

// Cargar inicial
fetchSalidas()

// Watchers
watchDebounced(searchQuery, () => { page.value = 1; fetchSalidas() }, { debounce: 400 })
watch([page, itemsPerPage, sortBy, orderBy], () => { fetchSalidas() })

const downloadPdf = id => {
  window.open(`/inventario/movimientos/salida/${id}/pdf`, '_blank')
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
    <VSnackbar v-model="showSuccess" color="success" timeout="4000">
      {{ successMsg }}
    </VSnackbar>

    <VCard id="salida-list">
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
          <!-- 👉 Create salida -->
          <VBtn
            prepend-icon="tabler-plus"
            @click="router.push('/inventario/movimiento/salida_registro/registro')"
          >
            Registrar Nueva Salida
          </VBtn>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <!-- 👉 Search  -->
          <div class="salida-list-filter">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar salidas..."
            />
          </div>
        </div>
      </VCardText>
      <VDivider />

      <!-- SECTION Datatable -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="totalSalidas"
        :headers="headers"
        :items="salidas"
        :loading="loading"
        item-value="id_movimiento"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Código -->
        <template #item.codigo="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            {{ formatCodigoSalida(item.codigo) }}
          </span>
        </template>

        <!-- Área -->
        <template #item.area_nombre="{ item }">
          <span class="text-body-1 font-weight-medium">
            {{ item.area_nombre || '—' }}
          </span>
        </template>

        <!-- Fecha -->
        <template #item.fecha="{ item }">
          {{ formatFecha(item.fecha) }}
        </template>

        <!-- Observaciones -->
        <template #item.observaciones="{ item }">
          <span class="text-sm">
            {{ item.observaciones || '—' }}
          </span>
        </template>

        <!-- Persona Entrega -->
        <template #item.persona_entrega="{ item }">
          <span class="text-sm">
            {{ item.persona_entrega || '—' }}
          </span>
        </template>

        <!-- Persona Recibe -->
        <template #item.persona_recibe="{ item }">
          <span class="text-sm">
            {{ item.persona_recibe || '—' }}
          </span>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            Bs. {{ Number(item.total || 0).toFixed(2) }}
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <VTooltip text="Ver Detalles">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="router.push(`/inventario/movimiento/salida_registro/${item.id_movimiento}`)">
                  <VIcon icon="tabler-eye" color="info" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>

          <VTooltip text="Descargar PDF">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="downloadPdf(item.id_movimiento)">
                  <VIcon icon="tabler-download" color="primary" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    <!-- !SECTION -->
    </VCard>
  </section>
</template>

<style lang="scss">
#salida-list {
  .salida-list-actions {
    inline-size: 8rem;
  }

  .salida-list-filter {
    inline-size: 12rem;
  }
}
</style>
