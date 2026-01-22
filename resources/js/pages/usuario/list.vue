<script setup>
import { watchDebounced } from '@vueuse/core'
import { useRouter } from 'vue-router'
import { ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

// Estado de tabla y filtros
const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)

// Controles de servidor
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref([{ key: 'nombre_usuario', order: 'asc' }])
const search = ref('')
const selectedEstado = ref(null)
const router = useRouter()
const auth = useAuthStore()

// Headers de la tabla
const headers = [
  { title: 'Nombre', key: 'nombre_usuario' },
  { title: 'Email', key: 'email' },
  { title: 'Área', key: 'area' },
  { title: 'Estado', key: 'estado' },
  { title: 'Acciones', key: 'actions', sortable: false },
]

// Opciones de estado
const estadoOptions = [
  { label: 'Administrador', value: 1 },
  { label: 'Usuario', value: 2 },
  { label: 'Visualización', value: 3 },
]

const buildQuery = () => {
  const params = new URLSearchParams()

  params.set('page', String(page.value))
  params.set('per_page', String(itemsPerPage.value))
  if (search.value)
    params.set('q', search.value)

  const s = sortBy.value?.[0]
  if (s?.key)
    params.set('sort_by', s.key)
  if (s?.order)
    params.set('sort_dir', s.order === 'asc' ? 'asc' : 'desc')

  return params.toString()
}

const load = async () => {
  if (!auth.can('users.view')) {
    router.replace('/')
    return
  }

  loading.value = true
  error.value = null
  try {
    const qs = buildQuery()

    const res = await fetch(`/usuarios?${qs}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)
    const json = await res.json()

    items.value = json.data || []
    total.value = json.meta?.total ?? 0
  } catch (err) {
    error.value = err
    console.error('Error cargando usuarios', err)
  } finally {
    loading.value = false
  }
}

// Cargar inicial solo si tiene permiso
if (auth.can('users.view'))
  await load()
else
  router.replace('/')

// Reactividad: búsqueda con debounce
watchDebounced(search, () => { page.value = 1; load() }, { debounce: 400 })

// Reactividad: paginación y orden
watch([page, itemsPerPage, sortBy], () => {
  page.value = Math.max(1, page.value)
  load()
})

// Handler para cambios en opciones de tabla
const updateOptions = (options) => {
  const { page: newPage, itemsPerPage: newPerPage, sortBy: newSortBy } = options
  if (newPage)
    page.value = newPage
  if (newPerPage)
    itemsPerPage.value = newPerPage
  if (newSortBy && newSortBy.length > 0)
    sortBy.value = newSortBy
}
</script>

<template>
  <section>
    <VCard id="usuario-list">
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
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <div class="usuario-list-filter">
            <AppTextField
              v-model="search"
              placeholder="Buscar usuarios..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              dense
              outlined
            />
          </div>

          <VBtn
            color="primary"
            prepend-icon="tabler-user-plus"
            @click="router.push('/usuario/registro')"
          >
            Nuevo Usuario
          </VBtn>
        </div>
      </VCardText>
      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="total"
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id_usuario"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.nombre_usuario="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            {{ item.nombre_usuario || '—' }}
          </span>
        </template>

        <template #item.email="{ item }">
          {{ item.email || '—' }}
        </template>

        <template #item.area="{ item }">
          {{ item.area || '—' }}
        </template>

        <template #item.estado="{ item }">
          <VChip
            :color="item.estado === 1 ? 'success' : 'error'"
            :text="item.estado === 1 ? 'Activo' : 'Inactivo'"
            size="small"
          />
        </template>

        <template #item.actions="{ item }">
          <VTooltip text="Ver usuario" location="top">
            <template #activator="{ props }">
              <IconBtn v-bind="props" @click="() => router.push(`/usuario/${item.id_usuario}`)">
                <VIcon icon="tabler-eye" />
              </IconBtn>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    </VCard>
  </section>
</template>

<style lang="scss">
#usuario-list {
  .usuario-list-filter {
    inline-size: 12rem;
  }
}
</style>
