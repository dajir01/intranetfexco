<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useCan } from '@/composables/useCan'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Tipos de Credenciales',
    ability: 'credenciales.empresas.ver',
  },
})

const route = useRoute()
const can = useCan()
const eventos = ref([])
const eventoSeleccionado = ref(null)
const tiposCredenciales = ref([])
const loadingEventos = ref(false)
const loadingTipos = ref(false)
const error = ref('')
const dialogDiseño = ref(false)
const tipoDiseñoSeleccionado = ref(null)
const archivoDiseño = ref(null)
const previewDiseñoUrl = ref('')
const guardandoDiseño = ref(false)
const errorDiseño = ref('')
const avisoDiseño = ref('')
const inputArchivoDiseño = ref(null)

const headers = [
  { title: 'Tipo de Credencial', key: 'tipo_credencial', sortable: false },
  { title: 'Diseño', key: 'diseño', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const obtenerCsrfToken = async () => {
  const response = await fetch('/csrf-token', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })
  const payload = await response.json().catch(() => ({}))
  const meta = document.querySelector('meta[name="csrf-token"]')

  if (payload?.token && meta)
    meta.setAttribute('content', payload.token)

  return payload?.token || meta?.getAttribute('content') || ''
}

const cargarEventos = async () => {
  loadingEventos.value = true
  error.value = ''

  try {
    const response = await fetch('/credenciales/eventos', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los eventos')

    eventos.value = payload.data || []

    const feriaDesdeQuery = Number(route.query.feria)
    if (feriaDesdeQuery && eventos.value.some(evento => Number(evento.id_feria) === feriaDesdeQuery)) {
      eventoSeleccionado.value = feriaDesdeQuery
    }
  } catch (err) {
    error.value = err.message
  } finally {
    loadingEventos.value = false
  }
}

const cargarTipos = async () => {
  tiposCredenciales.value = []
  if (!eventoSeleccionado.value) return

  loadingTipos.value = true
  error.value = ''

  try {
    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/tipos`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    const payload = await response.json()
    if (!response.ok) throw new Error(payload.message || 'No se pudieron cargar los tipos de credencial')
    tiposCredenciales.value = payload.data || []
  } catch (err) {
    error.value = err.message
  } finally {
    loadingTipos.value = false
  }
}

const cerrarDialogDiseño = () => {
  dialogDiseño.value = false
  tipoDiseñoSeleccionado.value = null
  archivoDiseño.value = null
  errorDiseño.value = ''
  avisoDiseño.value = ''
  if (previewDiseñoUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewDiseñoUrl.value)
  previewDiseñoUrl.value = ''
}

const abrirDialogDiseño = item => {
  if (!item || !eventoSeleccionado.value) return

  tipoDiseñoSeleccionado.value = item
  archivoDiseño.value = null
  errorDiseño.value = ''
  avisoDiseño.value = ''
  previewDiseñoUrl.value = item.diseño_url || ''
  dialogDiseño.value = true
}

const validarImagenDiseño = async archivo => {
  if (!archivo) return null

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(archivo.type)) {
    throw new Error('El archivo debe ser una imagen JPG, PNG o WEBP.')
  }

  if (archivo.size > 10 * 1024 * 1024) {
    throw new Error('El diseño debe pesar como máximo 10 MB antes de optimizarlo.')
  }

  const image = await new Promise((resolve, reject) => {
    const url = URL.createObjectURL(archivo)
    const img = new Image()

    img.onload = () => {
      URL.revokeObjectURL(url)
      resolve(img)
    }

    img.onerror = () => {
      URL.revokeObjectURL(url)
      reject(new Error('No se pudo procesar la imagen seleccionada.'))
    }

    img.src = url
  })

  return image
}

const seleccionarImagenDiseño = async event => {
  const archivo = event.target.files?.[0] || null
  if (!archivo) return

  try {
    errorDiseño.value = ''
    const image = await validarImagenDiseño(archivo)

    if (previewDiseñoUrl.value.startsWith('blob:')) URL.revokeObjectURL(previewDiseñoUrl.value)

    archivoDiseño.value = archivo
    previewDiseñoUrl.value = URL.createObjectURL(archivo)
    const avisos = []

    if (image.naturalWidth !== 1749 || image.naturalHeight !== 2722)
      avisos.push(`Dimensión recomendada: 1749 x 2722 px en orientación vertical. La imagen conservará sus dimensiones actuales.`)

    if (archivo.size > 2 * 1024 * 1024)
      avisos.push('El archivo supera 2 MB y se optimizará automáticamente sin modificar sus dimensiones.')

    avisoDiseño.value = avisos.join(' ')
  } catch (err) {
    errorDiseño.value = err.message || 'No se pudo validar la imagen.'
    if (inputArchivoDiseño.value) inputArchivoDiseño.value.value = ''
  }
}

const guardarDiseño = async () => {
  if (!eventoSeleccionado.value || !tipoDiseñoSeleccionado.value || !archivoDiseño.value) {
    errorDiseño.value = 'Seleccione una imagen válida para guardar el diseño.'
    return
  }

  guardandoDiseño.value = true
  errorDiseño.value = ''

  try {
    const formData = new FormData()
    formData.append('archivo', archivoDiseño.value)

    const csrfToken = await obtenerCsrfToken()
    const response = await fetch(`/credenciales/eventos/${eventoSeleccionado.value}/tipos/${encodeURIComponent(tipoDiseñoSeleccionado.value.tipo_credencial)}/diseño`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
      },
      credentials: 'same-origin',
      body: formData,
    })

    const payload = await response.json().catch(() => ({}))
    if (!response.ok) {
      throw new Error(payload.message || Object.values(payload.errors || {}).flat().join(' ') || 'No se pudo guardar el diseño')
    }

    await cargarTipos()
    cerrarDialogDiseño()
  } catch (err) {
    errorDiseño.value = err.message || 'No se pudo guardar el diseño.'
  } finally {
    guardandoDiseño.value = false
  }
}

const feriaSeleccionadaInfo = computed(() => eventos.value.find(evento => Number(evento.id_feria) === Number(eventoSeleccionado.value)))

watch(
  () => eventoSeleccionado.value,
  valor => {
    if (valor) cargarTipos()
    else tiposCredenciales.value = []
  },
)

onMounted(cargarEventos)
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">Tipos de Credenciales</h4>
          </div>
        </div>

        <VRow>
          <VCol cols="12" md="6">
            <VSelect
              v-model="eventoSeleccionado"
              :items="eventos"
              :loading="loadingEventos"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Seleccionar Evento / Feria"
              placeholder="Elige una feria para ver sus tipos de credencial"
              clearable
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" />
              </template>
            </VSelect>
          </VCol>

          <VCol v-if="feriaSeleccionadaInfo" cols="12" md="6">
            <VAlert type="info" variant="tonal" class="mb-0">
              <strong>{{ feriaSeleccionadaInfo.nombre_feria }}</strong>
              <div class="text-caption">Tipos de credencial configurados: {{ tiposCredenciales.length }}</div>
            </VAlert>
          </VCol>
        </VRow>

        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mt-4"
          closable
          @click:close="error = ''"
        >
          {{ error }}
        </VAlert>
      </VCardText>
    </VCard>

    <VCard v-if="eventoSeleccionado" id="credenciales-tipos-list">
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <h5 class="text-h5">Tipos de credenciales configurados</h5>
      </VCardText>

      <VDivider />

      <VCardText>
        <div v-if="loadingTipos" class="text-center py-8">
          <VProgressCircular indeterminate color="primary" size="64" />
          <p class="text-body-1 mt-4">Cargando tipos de credencial...</p>
        </div>

        <VAlert v-else-if="!tiposCredenciales.length" type="info" variant="tonal">
          No existen tipos de credencial configurados para este evento.
        </VAlert>

        <VDataTable
          v-else
          :headers="headers"
          :items="tiposCredenciales"
          item-value="tipo_credencial"
          class="text-no-wrap"
        >
          <template #item.diseño="{ item }">
            <img
              v-if="item.diseño_cargado && item.diseño_url"
              :src="item.diseño_url"
              :alt="`Diseño de credencial ${item.tipo_credencial}`"
              class="design-thumbnail"
              @click="abrirDialogDiseño(item)"
            >
            <VChip v-else color="default" variant="tonal" size="small">No cargado</VChip>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              v-if="can('credenciales.tipos.editar')"
              color="primary"
              variant="flat"
              size="small"
              @click="abrirDialogDiseño(item)"
            >
              {{ item.diseño_cargado ? 'Cambiar diseño' : 'Subir diseño' }}
            </VBtn>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <VCard v-else class="credential-types-empty-state">
      <VCardText class="d-flex flex-column align-center justify-center text-center py-16">
        <VIcon icon="tabler-file-description" size="56" color="secondary" class="mb-4" />
        <h5 class="text-h5 mb-2">Selecciona una Feria</h5>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Elige un evento/feria del selector superior para visualizar sus tipos de credencial.
        </p>
      </VCardText>
    </VCard>

    <VDialog v-model="dialogDiseño" max-width="620" persistent scrollable>
      <VCard class="design-dialog-card">
        <VCardTitle class="design-dialog-header d-flex align-center pa-5">
          <div class="design-dialog-icon me-3">
            <VIcon icon="tabler-id-badge-2" size="24" />
          </div>
          <div>
            <div class="text-h5 font-weight-bold">{{ tipoDiseñoSeleccionado?.diseño_cargado ? 'Cambiar diseño' : 'Subir diseño' }}</div>
            <div class="text-caption text-medium-emphasis mt-1">Diseño para {{ tipoDiseñoSeleccionado?.tipo_credencial || 'la credencial' }}</div>
          </div>
          <VSpacer />
          <VBtn icon variant="tonal" size="small" :disabled="guardandoDiseño" @click="cerrarDialogDiseño()">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText class="pa-5 pt-4 design-dialog-body">
          <VAlert v-if="errorDiseño" type="error" density="compact" class="mb-4">
            {{ errorDiseño }}
          </VAlert>

          <VAlert v-if="avisoDiseño" type="info" density="compact" class="mb-4">
            {{ avisoDiseño }}
          </VAlert>

          <div class="design-section-title mb-2">
            <VIcon icon="tabler-file-image" size="18" class="me-2" />
            Información del diseño
          </div>

          <VRow>
            <VCol cols="12" md="6">
              <VTextField
                :model-value="tipoDiseñoSeleccionado?.tipo_credencial || '—'"
                label="Tipo de credencial"
                variant="outlined"
                readonly
                hide-details
              />
            </VCol>
            <VCol cols="12" md="6">
              <VTextField
                :model-value="feriaSeleccionadaInfo?.nombre_feria || 'Evento seleccionado'"
                label="Evento"
                variant="outlined"
                readonly
                hide-details
              />
            </VCol>
          </VRow>

          <div class="design-section-title mt-5 mb-2">
            <VIcon icon="tabler-upload" size="18" class="me-2" />
            Archivo de diseño
          </div>

          <div>
            <VBtn color="primary" variant="outlined" prepend-icon="tabler-upload" @click="inputArchivoDiseño?.click()">
              Seleccionar imagen
            </VBtn>
            <div class="text-caption text-medium-emphasis mt-2">
              Dimensión recomendada: vertical 1749 x 2722 px. Tamaño final máximo: 2 MB; las imágenes más pesadas se optimizarán sin cambiar sus dimensiones.
            </div>
            <input
              ref="inputArchivoDiseño"
              class="d-none"
              type="file"
              accept="image/jpeg,image/png,image/webp"
              @change="seleccionarImagenDiseño"
            >
          </div>

          <div v-if="previewDiseñoUrl" class="mt-5">
            <div class="text-body-2 font-weight-medium mb-2">Vista previa</div>
            <div class="preview-container">
              <img :src="previewDiseñoUrl" alt="Vista previa del diseño" class="preview-image" />
            </div>
          </div>
        </VCardText>

        <VDivider />

        <VCardActions class="design-dialog-actions pa-4 d-flex justify-end gap-3">
          <VBtn color="secondary" variant="tonal" :disabled="guardandoDiseño" @click="cerrarDialogDiseño()">Cancelar</VBtn>
          <VBtn color="primary" :loading="guardandoDiseño" :disabled="!archivoDiseño" prepend-icon="tabler-device-floppy" @click="guardarDiseño">
            Guardar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.credential-types-empty-state :deep(.v-card-text) {
  min-height: 250px;
}

.design-dialog-card {
  overflow: hidden;
  border-radius: 16px;
}

.design-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.design-dialog-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: rgba(var(--v-theme-primary), 0.16);
  color: rgb(var(--v-theme-primary));
}

.design-dialog-body {
  background: rgba(var(--v-theme-on-surface), 0.01);
}

.design-section-title {
  display: flex;
  align-items: center;
  font-size: 0.88rem;
  font-weight: 700;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.design-dialog-actions {
  background: rgba(var(--v-theme-primary), 0.03);
}

.design-thumbnail {
  display: block;
  width: 48px;
  height: 64px;
  object-fit: cover;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 4px;
  cursor: pointer;
}

.preview-container {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 260px;
  border-radius: 12px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  background: rgba(var(--v-theme-primary), 0.02);
  overflow: hidden;
}

.preview-image {
  max-width: 100%;
  max-height: 320px;
  object-fit: contain;
  border-radius: 10px;
  display: block;
}
</style>
