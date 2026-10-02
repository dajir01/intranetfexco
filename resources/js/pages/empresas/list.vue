<script setup>
import { watchDebounced } from '@vueuse/core'
import { ref, watch, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSafePagination } from '@/composables/useSafePagination'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: { requiresAuth: true },
})

const router = useRouter()
const auth   = useAuthStore()

// ── Estado ────────────────────────────────────────────────────────────────────
const items      = ref([])
const total      = ref(0)
const loading    = ref(false)
const error      = ref(null)

const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page         = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy       = ref([{ key: 'nombre_empresa', order: 'asc' }])
const search       = ref('')
const headers = [
  { title: 'Empresa', key: 'nombre_empresa', sortable: true, minWidth: '200px' },
  { title: 'NIT',             key: 'nit',             sortable: true  },
  { title: 'Representante',   key: 'nombre_gerente',  sortable: true  },
  { title: 'Teléfono',        key: 'telefono',        sortable: false },
  { title: 'Registro',        key: 'created_at',      sortable: true  },
  { title: 'Acciones',        key: 'actions',         sortable: false },
]

// ── Helpers ───────────────────────────────────────────────────────────────────
const buildQuery = () => {
  const params = new URLSearchParams()
  params.set('page',     String(page.value))
  params.set('per_page', String(itemsPerPage.value))
  if (search.value)
    params.set('q', search.value)
  const s = sortBy.value?.[0]
  if (s?.key)   params.set('sort_by',  s.key)
  if (s?.order) params.set('sort_dir', s.order)
  return params.toString()
}

const formatDate = (val) => {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('es-BO', { day: '2-digit', month: '2-digit', year: 'numeric' })
}

// ── Carga de datos ─────────────────────────────────────────────────────────────
const load = async () => {
  if (!auth.can('empresas.ver')) {
    router.replace('/')
    return
  }
  loading.value = true
  error.value   = null
  try {
    const res  = await fetch(`/api/empresas?${buildQuery()}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const json = await res.json()
    items.value = json.data  ?? []
    total.value = json.meta?.total ?? 0
  } catch (err) {
    error.value = 'Error al cargar empresas'
    console.error(err)
  } finally {
    loading.value = false
  }
}

// ── Reactividad ───────────────────────────────────────────────────────────────
if (auth.can('empresas.ver'))
  await load()
else
  router.replace('/')

watchDebounced(search, () => { page.value = 1; load() }, { debounce: 400 })

watch([page, itemsPerPage, sortBy], () => {
  page.value = Math.max(1, page.value)
  load()
})

const updateOptions = ({ page: p, itemsPerPage: pp, sortBy: sb }) => {
  if (p)  page.value  = p
  if (pp) itemsPerPage.value = sanitizeItemsPerPage(pp)
  if (sb?.length) sortBy.value = sb
}

const verDetalle = (empresa) => {
  router.push(`/empresas/${empresa.id_empresa}`)
}
</script>

<template>
  <section>
    <VCard id="empresa-list">
      <!-- Encabezado y filtros -->
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex align-center gap-4 flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Mostrar</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="itemsPerPageOptions.map(v => ({ value: v, title: String(v) }))"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = sanitizeItemsPerPage($event)"
            />
          </div>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <AppTextField
            v-model="search"
            placeholder="Buscar empresa, NIT, representante..."
            append-inner-icon="tabler-search"
            single-line
            hide-details
            dense
            outlined
            style="min-inline-size: 260px;"
          />
        </div>
      </VCardText>

      <VDivider />

      <!-- Alerta de error -->
      <VAlert
        v-if="error"
        type="error"
        class="ma-4"
        closable
        @click:close="error = null"
      >
        {{ error }}
      </VAlert>

      <!-- Tabla -->
      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="total"
        :items-per-page-options="itemsPerPageOptions"
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id_empresa"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <!-- Empresa -->
        <template #item.nombre_empresa="{ item }">
          <span class="text-high-emphasis font-weight-medium" style="white-space: normal; word-break: break-word;">
            {{ item.nombre_empresa || '—' }}
          </span>
        </template>

        <!-- NIT -->
        <template #item.nit="{ item }">
          <code class="text-body-2">{{ item.nit || '—' }}</code>
        </template>

        <!-- Representante -->
        <template #item.nombre_gerente="{ item }">
          {{ item.nombre_gerente || '—' }}
        </template>

        <!-- Teléfono -->
        <template #item.telefono="{ item }">
          {{ item.telefono || '—' }}
        </template>

        <!-- Fecha -->
        <template #item.created_at="{ item }">
          <div class="text-caption">
            <span class="text-medium-emphasis">Creado por:</span>
            {{ item.usuario_creacion_nombre || '—' }}
            <br>
            <span class="text-disabled">{{ formatDate(item.created_at) }}</span>
          </div>
          <div v-if="item.updated_at" class="text-caption mt-1">
            <span class="text-medium-emphasis">Actualizado por:</span>
            {{ item.usuario_actualizacion_nombre || '—' }}
            <br>
            <span class="text-disabled">{{ formatDate(item.updated_at) }}</span>
          </div>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <VBtn
            v-if="auth.can('empresas.detalle')"
            icon
            size="small"
            variant="text"
            color="primary"
            :to="`/empresas/${item.id_empresa}`"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Ver detalle</VTooltip>
          </VBtn>
        </template>

        <!-- Empty -->
        <template #no-data>
          <div class="py-6 text-center text-medium-emphasis">
            <VIcon icon="tabler-building-off" size="32" class="mb-2 d-block mx-auto" />
            No se encontraron empresas
          </div>
        </template>
      </VDataTableServer>
    </VCard>
  </section>
</template>
