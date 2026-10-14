<script setup>
import { watchDebounced } from '@vueuse/core'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSafePagination } from '@/composables/useSafePagination'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const auth = useAuthStore()

const canViewNoticias = computed(() => auth.can('noticias.view'))
const canCreateNoticias = computed(() => auth.can('noticias.create'))
const canUpdateNoticias = computed(() => auth.can('noticias.update'))
const canDeleteNoticias = computed(() => auth.can('noticias.delete'))

// Estado
const loading = ref(false)
const noticias = ref([])

// Estado de tabla y filtros
const page = ref(1)
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy = ref([{ key: 'fecha', order: 'desc' }])
const search = ref('')

// Headers de la tabla
const headers = [
  { title: 'Título', key: 'titulo', sortable: true },
  { title: 'Imagen', key: 'imagen', sortable: false },
  { title: 'Fecha', key: 'fecha', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false },
]

// Cargar noticias desde la API
const cargarNoticias = async () => {
  if (!canViewNoticias.value) return

  loading.value = true

  try {
    const response = await fetch('/noticias/data', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const json = await response.json()

    if (json.success) {
      noticias.value = json.data.data || json.data
    }
  } catch (err) {
    noticias.value = []
  } finally {
    loading.value = false
  }
}

// Formatear fecha
const formatoFecha = fecha => {
  if (!fecha) return 'N/A'

  try {
    return new Intl.DateTimeFormat('es-BO', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(new Date(fecha))
  } catch {
    return fecha
  }
}

// Computadas
const normalizeText = value => String(value ?? '').toLowerCase()

const getSortableValue = (item, key) => {
  switch (key) {
  case 'titulo':
    return String(item.titulo ?? '')
  case 'fecha':
    return String(item.fecha ?? '')
  default:
    return ''
  }
}

const noticiasFiltradas = computed(() => {
  const term = normalizeText(search.value)

  if (!term) return noticias.value

  return noticias.value.filter(n => {
    const valores = [
      n.titulo,
    ]

    return valores.some(v => normalizeText(v).includes(term))
  })
})

const totalFiltrado = computed(() => noticiasFiltradas.value.length)

const noticiasOrdenadas = computed(() => {
  const items = [...noticiasFiltradas.value]
  const sort = sortBy.value?.[0]

  if (!sort?.key) return items

  const dir = sort.order === 'desc' ? -1 : 1

  return items.sort((a, b) => {
    const aVal = getSortableValue(a, sort.key)
    const bVal = getSortableValue(b, sort.key)

    return aVal.localeCompare(bVal, 'es', { sensitivity: 'base' }) * dir
  })
})

const noticiasPaginadas = computed(() => {
  const start = (page.value - 1) * itemsPerPage.value
  const end = start + itemsPerPage.value

  return noticiasOrdenadas.value.slice(start, end)
})

const tieneNoticiasFiltradas = computed(() => totalFiltrado.value > 0)

// Diálogos y estados
const dialogEliminarNoticia = ref(false)
const eliminandoNoticia = ref(false)
const itemSeleccionado = ref(null)
const snackbar = ref({ show: false, color: 'success', text: '' })

// Funciones de acción
const irANuevaNoticia = () => {
  router.push({ path: '/noticia/registro' })
}

const irAEditarNoticia = item => {
  router.push({ path: `/noticia/edit/${item.id_noticia}` })
}

const solicitarEliminarNoticia = item => {
  itemSeleccionado.value = item
  dialogEliminarNoticia.value = true
}

// CSRF helper para peticiones protegidas
const obtenerCsrf = async () => {
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

  if (!csrfToken) {
    const csrfResponse = await fetch('/csrf-token', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (csrfResponse.ok) {
      const csrfJson = await csrfResponse.json().catch(() => null)

      csrfToken = csrfJson?.token || ''
    }
  }

  return csrfToken
}

const confirmarEliminarNoticia = async () => {
  const item = itemSeleccionado.value

  dialogEliminarNoticia.value = false

  if (!item) return

  try {
    eliminandoNoticia.value = true

    const csrf = await obtenerCsrf()

    const resp = await fetch(`/noticias/${item.id_noticia}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      },
      credentials: 'same-origin',
    })

    const json = await resp.json().catch(() => ({}))

    if (!resp.ok || !json?.success)
      throw new Error(json?.message || 'Error al eliminar la noticia')

    // Refrescar listado
    await cargarNoticias()
    snackbar.value = { show: true, color: 'success', text: json.message || 'Noticia eliminada correctamente' }
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: e.message || 'No se pudo eliminar la noticia' }
  } finally {
    eliminandoNoticia.value = false
  }
}

onMounted(async () => {
  if (!canViewNoticias.value) {
    router.replace('/')

    return
  }

  await cargarNoticias()

  // Suscribirse a cambios en noticias en tiempo real
  if (window.Echo) {
    window.Echo.channel('noticias').listen('.NoticiaActualizada', () => {
      cargarNoticias()
    })
  }
})

onUnmounted(() => {
  // Desuscribirse al desmontar
  if (window.Echo) {
    window.Echo.leaveChannel('noticias')
  }
})

// Reactividad: búsqueda con debounce
watchDebounced(
  search,
  () => {
    page.value = 1
  },
  { debounce: 400 },
)
</script>

<template>
  <div>
    <!-- Tabla de Noticias -->
    <VCard id="noticia-list">
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
          <div class="noticia-list-filter">
            <AppTextField
              v-model="search"
              placeholder="Buscar noticias..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              dense
              outlined
            />
          </div>
          <!-- Botón Nueva Noticia -->
          <VBtn
            v-if="canCreateNoticias"
            color="primary"
            prepend-icon="tabler-plus"
            @click="irANuevaNoticia"
          >
            Nueva Noticia
          </VBtn>
        </div>
      </VCardText>
      <VDivider />

      <VCardText>
        <div class="d-flex align-center justify-space-between mb-4">
          <h5 class="text-h5">
            Noticias
          </h5>
        </div>

        <!-- Loading -->
        <div
          v-if="loading"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">
            Cargando noticias...
          </p>
        </div>

        <!-- Sin noticias -->
        <VAlert
          v-else-if="!tieneNoticiasFiltradas"
          type="info"
          variant="tonal"
        >
          No hay noticias registradas.
        </VAlert>

        <!-- Tabla con noticias -->
        <VDataTableServer
          v-else
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          :items-length="totalFiltrado"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="noticiasPaginadas"
          :loading="loading"
          item-value="id_noticia"
          class="text-no-wrap"
        >
          <template #item.titulo="{ item }">
            <div class="d-flex flex-column titulo-cell">
              <span class="font-weight-medium titulo-texto">
                {{ item.titulo || '—' }}
              </span>
            </div>
          </template>

          <template #item.imagen="{ item }">
            <div class="d-flex align-center">
              <VImg
                v-if="item.imagen"
                :src="item.imagen"
                width="120"
                height="75"
                cover
                class="rounded-lg"
              />
              <span
                v-else
                class="text-caption text-disabled"
              >Sin imagen</span>
            </div>
          </template>

          <template #item.fecha="{ item }">
            <span class="text-caption">
              {{ formatoFecha(item.fecha) }}
            </span>
          </template>

          <template #item.actions="{ item }">
            <div class="acciones-columna">
              <VTooltip
                v-if="canUpdateNoticias"
                location="top"
                class="accion-item"
              >
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    color="secondary"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-edit"
                    @click="irAEditarNoticia(item)"
                  >
                    Editar
                  </VBtn>
                </template>
                <span>Editar noticia</span>
              </VTooltip>

              <VTooltip
                v-if="canDeleteNoticias"
                location="top"
                class="accion-item"
              >
                <template #activator="{ props }">
                  <VBtn
                    v-bind="props"
                    color="error"
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-trash"
                    @click="solicitarEliminarNoticia(item)"
                  >
                    Eliminar
                  </VBtn>
                </template>
                <span>Eliminar noticia</span>
              </VTooltip>
            </div>
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <!-- Diálogos y Snackbar -->
    <VDialog
      v-model="dialogEliminarNoticia"
      max-width="460"
    >
      <VCard>
        <VCardTitle>Confirmar eliminación</VCardTitle>
        <VCardText>
          ¿Está seguro que desea eliminar esta noticia?
          Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            :disabled="eliminandoNoticia"
            @click="dialogEliminarNoticia = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            :loading="eliminandoNoticia"
            :disabled="eliminandoNoticia"
            @click="confirmarEliminarNoticia"
          >
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="3000"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style lang="scss">
#noticia-list {
  .titulo-cell {
    max-inline-size: 280px;
  }

  .titulo-texto {
    white-space: normal !important;
    word-break: normal;
    overflow-wrap: break-word;
    line-height: 1.35;
  }

  .noticia-list-filter {
    inline-size: 12rem;
  }

  .acciones-columna {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    gap: 8px;
    padding: 6px;
  }

  .acciones-columna .accion-item {
    display: block;
    padding: 4px;
  }

  .acciones-columna .v-btn {
    inline-size: 100%;
  }
}
</style>
