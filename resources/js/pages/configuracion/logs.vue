<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

definePage({
  meta: {
    requiresAuth: true,
    title: 'Auditoría de registros',
  },
})

const canViewLogs = computed(() => auth.can('auditoria.logs.view'))
const loading = ref(false)
const files = ref([])
const selectedFile = ref(null)
const entries = ref([])
const level = ref('')
const search = ref('')
const page = ref(1)
const perPage = ref(25)
const total = ref(0)
const lastPage = ref(1)
const truncated = ref(false)
const errorMessage = ref('')
const selectedEntry = ref(null)

const levels = [
  { title: 'Todos', value: '' },
  { title: 'Debug', value: 'debug' },
  { title: 'Información', value: 'info' },
  { title: 'Aviso', value: 'notice' },
  { title: 'Advertencia', value: 'warning' },
  { title: 'Error', value: 'error' },
  { title: 'Crítico', value: 'critical' },
  { title: 'Alerta', value: 'alert' },
  { title: 'Emergencia', value: 'emergency' },
]

const levelColors = {
  debug: 'secondary', info: 'info', notice: 'primary', warning: 'warning',
  error: 'error', critical: 'deep-purple', alert: 'red-darken-4', emergency: 'black',
}

const formatSize = bytes => {
  if (!bytes) return '0 B'
  const units = ['B', 'KB', 'MB', 'GB']
  const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1)

  return `${(bytes / (1024 ** exponent)).toFixed(exponent ? 1 : 0)} ${units[exponent]}`
}

const fileOptions = computed(() => files.value.map(file => ({
  title: `${file.name} · ${formatSize(file.size)} · ${file.updated_at}`,
  value: file.name,
})))

const loadFiles = async () => {
  const response = await fetch('/api/auditoria/logs', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  if (!response.ok) throw new Error('No se pudieron cargar los archivos de registro')

  const json = await response.json()

  files.value = json.data || []
  if (!selectedFile.value && files.value.length)
    selectedFile.value = files.value[0].name
}

let searchTimer

const loadEntries = async () => {
  if (!selectedFile.value) return

  loading.value = true
  errorMessage.value = ''
  try {
    const params = new URLSearchParams({ page: String(page.value) })

    params.set('per_page', String(perPage.value))
    if (level.value) params.set('level', level.value)
    if (search.value.trim()) params.set('search', search.value.trim())

    const response = await fetch(`/api/auditoria/logs/${encodeURIComponent(selectedFile.value)}?${params}`, {
      headers: { Accept: 'application/json' }, credentials: 'same-origin',
    })

    const json = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(json.message || 'No se pudieron cargar los registros')

    entries.value = json.data || []
    total.value = json.meta?.total || 0
    lastPage.value = json.meta?.last_page || 1
    truncated.value = Boolean(json.meta?.truncated)
  }
  catch (error) {
    errorMessage.value = error.message
  }
  finally {
    loading.value = false
  }
}

watch(selectedFile, () => {
  page.value = 1
  loadEntries()
})

watch(level, () => {
  page.value = 1
  loadEntries()
})

watch(perPage, () => {
  page.value = 1
  loadEntries()
})

watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    page.value = 1
    loadEntries()
  }, 350)
})

watch(page, loadEntries)

onMounted(async () => {
  if (!canViewLogs.value) {
    window.location.href = '/'

    return
  }

  try {
    await loadFiles()
    await loadEntries()
  }
  catch (error) {
    errorMessage.value = error.message
  }
})
</script>

<template>
  <section class="pa-4">
    <VCard elevation="1">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap ga-3 py-4">
        <div>
          <div class="text-h5 font-weight-semibold">Auditoría de registros del sistema</div>
          <div class="text-body-2 text-medium-emphasis mt-1">
            Histórico de errores y eventos registrados por Laravel. Lectura exclusiva.
          </div>
        </div>
        <VBtn icon="tabler-refresh" variant="tonal" :loading="loading" aria-label="Actualizar registros" @click="loadEntries" />
      </VCardTitle>

      <VCardText>
        <VAlert v-if="errorMessage" type="error" variant="tonal" class="mb-4" closable @click:close="errorMessage = ''">
          {{ errorMessage }}
        </VAlert>

        <VAlert v-if="truncated" type="warning" variant="tonal" density="compact" class="mb-4">
          El archivo supera el límite de lectura. Se muestran únicamente los registros más recientes; el archivo completo permanece disponible en el servidor.
        </VAlert>

        <VRow>
          <VCol cols="12" md="5">
            <VSelect v-model="selectedFile" :items="fileOptions" label="Archivo de registro" prepend-inner-icon="tabler-file-text" hide-details />
          </VCol>
          <VCol cols="12" sm="6" md="3">
            <VSelect v-model="level" :items="levels" label="Nivel" prepend-inner-icon="tabler-filter" hide-details />
          </VCol>
          <VCol cols="12" sm="6" md="4">
            <VTextField v-model="search" label="Buscar en los registros" prepend-inner-icon="tabler-search" clearable hide-details />
          </VCol>
        </VRow>

        <div v-if="!files.length && !loading" class="text-center py-12 text-medium-emphasis">
          <VIcon icon="tabler-file-off" size="48" class="mb-3" />
          <div>No hay archivos Laravel disponibles para auditoría.</div>
        </div>

        <VTable v-else class="logs-table mt-5" hover>
          <thead>
            <tr>
              <th style="width: 175px">Fecha y hora</th>
              <th style="width: 120px">Nivel</th>
              <th>Evento</th>
              <th style="width: 56px" aria-label="Detalles" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(entry, index) in entries" :key="`${entry.timestamp}-${index}`">
              <td class="text-no-wrap">{{ entry.timestamp }}</td>
              <td>
                <VChip :color="levelColors[entry.level] || 'secondary'" size="small" variant="tonal" class="text-uppercase">
                  {{ entry.level }}
                </VChip>
              </td>
              <td>
                <div class="log-message">{{ entry.message || 'Sin mensaje' }}</div>
                <div class="text-caption text-medium-emphasis mt-1">{{ entry.environment }}</div>
              </td>
              <td>
                <VBtn icon="tabler-eye" size="small" variant="text" :disabled="!entry.details" aria-label="Ver detalle" @click="selectedEntry = entry" />
              </td>
            </tr>
            <tr v-if="!entries.length && !loading">
              <td colspan="4" class="text-center py-10 text-medium-emphasis">
                No existen registros que coincidan con los filtros.
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-if="total" class="d-flex align-center justify-space-between flex-wrap ga-3 mt-4">
          <div class="text-caption text-medium-emphasis">{{ total }} registro(s) encontrado(s)</div>
          <div class="d-flex align-center ga-2">
            <VSelect v-model="perPage" :items="[25, 50, 100]" density="compact" style="width: 96px" hide-details />
            <VPagination v-model="page" :length="lastPage" :total-visible="5" density="compact" />
          </div>
        </div>
      </VCardText>
    </VCard>

    <VDialog v-model="selectedEntry" max-width="900">
      <VCard v-if="selectedEntry">
        <VCardTitle class="d-flex align-center justify-space-between ga-3">
          <span>Detalle del registro</span>
          <VBtn icon="tabler-x" variant="text" aria-label="Cerrar" @click="selectedEntry = null" />
        </VCardTitle>
        <VCardText>
          <div class="d-flex flex-wrap ga-2 mb-4">
            <VChip size="small" :color="levelColors[selectedEntry.level] || 'secondary'" variant="tonal" class="text-uppercase">{{ selectedEntry.level }}</VChip>
            <VChip size="small" variant="outlined">{{ selectedEntry.timestamp }}</VChip>
            <VChip size="small" variant="outlined">{{ selectedEntry.environment }}</VChip>
          </div>
          <div class="text-subtitle-2 mb-2">Mensaje</div>
          <div class="detail-block mb-4">{{ selectedEntry.message }}</div>
          <div class="text-subtitle-2 mb-2">Contexto</div>
          <pre class="detail-block">{{ selectedEntry.details }}</pre>
        </VCardText>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.logs-table :deep(td) {
  vertical-align: top;
  padding-block: 14px;
}

.log-message {
  max-width: 760px;
  overflow-wrap: anywhere;
  white-space: pre-wrap;
}

.detail-block {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background: rgb(var(--v-theme-surface));
  padding: 12px;
  white-space: pre-wrap;
  overflow-wrap: anywhere;
  font-family: var(--v-font-family-monospace, monospace);
  font-size: 12px;
  max-height: 55vh;
  overflow: auto;
}
</style>
