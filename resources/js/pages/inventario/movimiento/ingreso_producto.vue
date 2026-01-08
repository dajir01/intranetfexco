<script setup>
import { ref } from 'vue'
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
    title: 'Áreas',
    value: 0,
    icon: 'tabler-layers-3',
  },
])

// 👉 headers
const headers = [
  { title: 'Código', key: 'codigo' },
  { title: 'Área', key: 'area_nombre' },
  { title: 'Fecha', key: 'fecha' },
  { title: 'Observaciones', key: 'observaciones' },
  { title: 'Persona Entrega', key: 'persona_entrega' },
  { title: 'Persona Recibe', key: 'persona_recibe' },
  { title: 'Total', key: 'total' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const formatCodigoIngreso = codigo => {
  if (!codigo) return '—'
  return `IA-${String(codigo).padStart(6, '0')}`
}

const formatFecha = fecha => {
  if (!fecha) return '—'
  const date = new Date(fecha)
  return `${date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })} ${date.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })}`
}

const fetchIngresos = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    params.set('page', String(page.value))
    params.set('per_page', String(itemsPerPage.value))
    if (searchQuery.value) params.set('q', searchQuery.value)
    if (sortBy.value) params.set('sort_by', sortBy.value)
    if (orderBy.value) params.set('sort_dir', orderBy.value === 'asc' ? 'asc' : 'desc')

    const res = await fetch(`/inventario/movimientos/ingreso?${params.toString()}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok) {
      console.warn('Endpoint no disponible, usando datos vacíos')
      ingresos.value = []
      totalIngresos.value = 0
      return
    }

    const contentType = res.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      console.warn('La respuesta no es JSON, usando datos vacíos')
      ingresos.value = []
      totalIngresos.value = 0
      return
    }

    const json = await res.json()

    ingresos.value = json.data || []
    totalIngresos.value = json.meta?.total ?? ingresos.value.length
    widgetData.value[0].value = totalIngresos.value
  } catch (err) {
    console.error('Error cargando ingresos', err)
    ingresos.value = []
    totalIngresos.value = 0
  } finally {
    loading.value = false
  }
}

fetchIngresos()

watchDebounced(searchQuery, () => { page.value = 1; fetchIngresos() }, { debounce: 400 })
watch([page, itemsPerPage, sortBy, orderBy], () => { fetchIngresos() })

const downloadPdf = id => {
  window.open(`/inventario/movimientos/ingreso-almacen/${id}/pdf`, '_blank')
}

const successMsg = ref('')
const showSuccess = ref(false)

if (route.query.success) {
  successMsg.value = String(route.query.success)
  showSuccess.value = true
  router.replace({ path: route.path, query: { ...route.query, success: undefined } })
}
</script>

<template>
  <section>
    <VSnackbar v-model="showSuccess" color="success" timeout="4000">
      {{ successMsg }}
    </VSnackbar>

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

          <VBtn prepend-icon="tabler-plus" @click="router.push('/inventario/movimiento/ingreso_registro/registro')">
            Registrar Nuevo Ingreso
          </VBtn>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <div class="ingreso-list-filter">
            <AppTextField
              v-model="searchQuery"
              placeholder="Buscar ingresos..."
            />
          </div>
        </div>
      </VCardText>
      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="totalIngresos"
        :headers="headers"
        :items="ingresos"
        :loading="loading"
        item-value="id_movimiento"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.codigo="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            {{ formatCodigoIngreso(item.codigo) }}
          </span>
        </template>

        <template #item.area_nombre="{ item }">
          <span class="text-body-1 font-weight-medium">
            {{ item.area_nombre || '—' }}
          </span>
        </template>

        <template #item.fecha="{ item }">
          {{ formatFecha(item.fecha) }}
        </template>

        <template #item.observaciones="{ item }">
          <span class="text-sm">
            {{ item.observaciones || '—' }}
          </span>
        </template>

        <template #item.persona_entrega="{ item }">
          <span class="text-sm">
            {{ item.persona_entrega || '—' }}
          </span>
        </template>

        <template #item.persona_recibe="{ item }">
          <span class="text-sm">
            {{ item.persona_recibe || '—' }}
          </span>
        </template>

        <template #item.total="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            Bs. {{ Number(item.total || 0).toFixed(2) }}
          </span>
        </template>

        <template #item.actions="{ item }">
          <VTooltip text="Ver Detalles">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="router.push(`/inventario/movimiento/ingreso_registro/${item.id_movimiento}`)">
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
    </VCard>
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
