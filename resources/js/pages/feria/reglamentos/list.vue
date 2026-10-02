<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useSafePagination } from '@/composables/useSafePagination'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Reglamentos de Ferias',
  },
})

const router = useRouter()
const auth = useAuthStore()
const puedeCrear = computed(() => auth.can('ferias.reglamentos.create'))
const puedeEliminar = computed(() => auth.can('ferias.reglamentos.delete'))

if (!auth.can('ferias.reglamentos.view'))
  router.replace('/')

const ferias = ref([])
const feriaSeleccionada = ref(null)
const reglamentos = ref([])
const cargandoFerias = ref(false)
const cargandoReglamentos = ref(false)
const guardando = ref(false)
const error = ref(null)
const errorDialog = ref(null)
const dialog = ref(false)
const archivo = ref(null)
const nombreReglamento = ref('')
const descripcionReglamento = ref('')
const csrfToken = ref('')
let solicitudReglamentosId = 0
const reglamentoPendienteEliminar = ref(null)
const dialogConfirmarEliminacion = ref(false)
const eliminando = ref(false)
const errorEliminar = ref(null)

const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const itemsPerPage = ref(defaultItemsPerPage)
const page = ref(1)

const headers = [
  { title: 'Nombre del reglamento', key: 'nombre_reglamento' },
  { title: 'Descripción', key: 'descripcion' },
  { title: 'Archivo PDF', key: 'nombre_archivo' },
  { title: 'Fecha de carga', key: 'created_at' },
  { title: 'Acciones', key: 'actions', sortable: false, align: 'end' },
]

const cargarFerias = async () => {
  cargandoFerias.value = true
  error.value = null
  try {
    const response = await fetch('/reglamentos-feria/eventos', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })

    const json = await response.json()
    if (!response.ok || !json.success)
      throw new Error(json.message || 'No se pudieron cargar los eventos.')

    ferias.value = json.data || []
  }
  catch (e) {
    error.value = e.message || 'No se pudieron cargar los eventos.'
  }
  finally {
    cargandoFerias.value = false
  }
}

const cargarReglamentos = async () => {
  const solicitudId = ++solicitudReglamentosId
  const feriaId = feriaSeleccionada.value

  if (!feriaSeleccionada.value) {
    reglamentos.value = []
    error.value = null
    cargandoReglamentos.value = false
    
    return
  }

  cargandoReglamentos.value = true
  error.value = null
  try {
    const response = await fetch(`/ferias/${feriaId}/reglamentos`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })

    const json = await response.json()
    if (solicitudId !== solicitudReglamentosId)
      return

    if (!response.ok || !json.success)
      throw new Error(json.message || 'No se pudieron cargar los reglamentos.')

    reglamentos.value = json.data || []
  }
  catch (e) {
    if (solicitudId !== solicitudReglamentosId)
      return

    error.value = e.message || 'No se pudieron cargar los reglamentos.'
    reglamentos.value = []
  }
  finally {
    if (solicitudId === solicitudReglamentosId)
      cargandoReglamentos.value = false
  }
}

const obtenerCsrfToken = async () => {
  if (csrfToken.value)
    return csrfToken.value

  const response = await fetch('/csrf-token', {
    credentials: 'same-origin',
    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
  })

  const json = await response.json()
  if (!response.ok || !json.token)
    throw new Error('No se pudo validar la sesión. Recargue la página e intente nuevamente.')

  csrfToken.value = json.token
  
  return csrfToken.value
}

const abrirDialog = () => {
  archivo.value = null
  nombreReglamento.value = ''
  descripcionReglamento.value = ''
  errorDialog.value = null
  dialog.value = true
}

const cerrarDialog = () => {
  if (!guardando.value)
    dialog.value = false
}

const guardarReglamento = async () => {
  errorDialog.value = null

  if (!nombreReglamento.value.trim()) {
    errorDialog.value = 'Ingrese el nombre del reglamento.'

    return
  }

  if (!descripcionReglamento.value.trim()) {
    errorDialog.value = 'Ingrese una breve descripción del reglamento.'

    return
  }

  const file = Array.isArray(archivo.value) ? archivo.value[0] : archivo.value
  if (!file) {
    errorDialog.value = 'Seleccione un archivo PDF.'
    
    return
  }

  guardando.value = true
  try {
    const token = await obtenerCsrfToken()
    const formData = new FormData()

    formData.append('nombre_reglamento', nombreReglamento.value.trim())
    formData.append('descripcion', descripcionReglamento.value.trim())
    formData.append('archivo', file)

    const response = await fetch(`/ferias/${feriaSeleccionada.value}/reglamentos`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
      body: formData,
    })

    const contentType = response.headers.get('content-type') || ''
    const json = contentType.includes('application/json') ? await response.json() : null

    if (response.status === 413)
      throw new Error('El archivo supera el tamaño máximo permitido de 20 MB.')

    if (!response.ok || !json.success)
      throw new Error(json?.message || `No se pudo guardar el reglamento (HTTP ${response.status}).`)

    dialog.value = false
    await cargarReglamentos()
  }
  catch (e) {
    errorDialog.value = e.message || 'No se pudo guardar el reglamento.'
  }
  finally {
    guardando.value = false
  }
}

const descargarReglamento = reglamento => {
  window.location.href = `/reglamentos-feria/${reglamento.id_reglamento}/download`
}

const abrirConfirmacionEliminar = reglamento => {
  reglamentoPendienteEliminar.value = reglamento
  errorEliminar.value = null
  dialogConfirmarEliminacion.value = true
}

const cerrarConfirmacionEliminar = () => {
  if (eliminando.value)
    return

  dialogConfirmarEliminacion.value = false
  reglamentoPendienteEliminar.value = null
  errorEliminar.value = null
}

const confirmarEliminacion = async () => {
  if (!reglamentoPendienteEliminar.value)
    return

  eliminando.value = true
  errorEliminar.value = null

  try {
    const token = await obtenerCsrfToken()

    const response = await fetch(`/reglamentos-feria/${reglamentoPendienteEliminar.value.id_reglamento}`, {
      method: 'DELETE',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': token,
      },
    })

    const json = await response.json()
    if (!response.ok || !json.success)
      throw new Error(json?.message || 'No se pudo eliminar el reglamento.')

    dialogConfirmarEliminacion.value = false
    reglamentoPendienteEliminar.value = null
    await cargarReglamentos()
    page.value = Math.min(page.value, Math.max(1, Math.ceil(reglamentos.value.length / itemsPerPage.value)))
  }
  catch (e) {
    errorEliminar.value = e.message || 'No se pudo eliminar el reglamento.'
  }
  finally {
    eliminando.value = false
  }
}

const formatoFecha = value => value
  ? new Date(value).toLocaleString('es-BO', { dateStyle: 'short', timeStyle: 'short' })
  : '—'

onMounted(cargarFerias)
</script>

<template>
  <section>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">
              Gestión de Reglamentos
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
              :loading="cargandoFerias"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Seleccionar Evento / Feria"
              placeholder="Elige una feria para ver sus reglamentos"
              clearable
              @update:model-value="cargarReglamentos"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-calendar-event" />
              </template>
            </VSelect>
          </VCol>
        </VRow>
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

    <VCard
      v-if="!feriaSeleccionada"
      class="mt-4"
    >
      <VCardText
        class="d-flex flex-column align-center justify-center text-center pa-8"
        style="min-height: 210px;"
      >
        <VIcon
          icon="tabler-file-description"
          size="48"
          color="disabled"
          class="mb-4"
        />
        <h3 class="text-h6 mb-2">
          Selecciona una Feria
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Elige un evento/feria del selector superior para visualizar sus reglamentos
        </p>
      </VCardText>
    </VCard>

    <VCard
      v-else
      class="mt-4"
    >
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <span>Mostrar</span>
          <AppSelect
            :model-value="itemsPerPage"
            :items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
            style="inline-size: 5.5rem;"
            @update:model-value="itemsPerPage = sanitizeItemsPerPage($event)"
          />
        </div>
        <VBtn
          v-if="puedeCrear"
          color="primary"
          prepend-icon="tabler-plus"
          @click="abrirDialog"
        >
          Añadir reglamento
        </VBtn>
      </VCardText>
      <VDivider />
      <VDataTable
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :headers="headers"
        :items="reglamentos"
        :items-per-page-options="itemsPerPageOptions"
        :loading="cargandoReglamentos"
        :no-data-text="feriaSeleccionada ? 'No hay reglamentos para este evento.' : 'Seleccione un evento.'"
      >
        <template #item.created_at="{ item }">
          {{ formatoFecha(item.created_at) }}
        </template>
        <template #item.actions="{ item }">
          <VBtn
            icon="tabler-download"
            variant="text"
            :aria-label="`Descargar ${item.nombre_archivo}`"
            @click="descargarReglamento(item)"
          />
          <VBtn
            v-if="puedeEliminar"
            icon="tabler-trash"
            variant="text"
            color="error"
            :aria-label="`Eliminar ${item.nombre_reglamento}`"
            @click="abrirConfirmacionEliminar(item)"
          />
        </template>
      </VDataTable>
    </VCard>

    <VDialog
      v-model="dialogConfirmarEliminacion"
      max-width="480"
      persistent
    >
      <VCard>
        <VCardTitle>Confirmar eliminación</VCardTitle>
        <VCardText>
          <VAlert
            v-if="errorEliminar"
            type="error"
            variant="tonal"
            class="mb-4"
          >
            {{ errorEliminar }}
          </VAlert>
          Se eliminará el reglamento
          <strong>{{ reglamentoPendienteEliminar?.nombre_reglamento }}</strong>
          y su archivo PDF. Esta acción no se puede deshacer.
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            variant="text"
            :disabled="eliminando"
            @click="cerrarConfirmacionEliminar"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="error"
            :loading="eliminando"
            @click="confirmarEliminacion"
          >
            Eliminar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog
      v-model="dialog"
      max-width="980"
    >
      <VCard class="reglamento-dialog-card">
        <VCardTitle class="reglamento-dialog-header d-flex align-center justify-space-between">
          <div>
            <h5 class="text-h5 mb-1">
              Añadir reglamento
            </h5>
            <p class="text-body-2 text-medium-emphasis mb-0">
              Completa los datos del reglamento para continuar.
            </p>
          </div>
          <VBtn
            icon
            variant="text"
            color="primary"
            aria-label="Cerrar diálogo"
            @click="cerrarDialog"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VCardText class="reglamento-dialog-body">
          <VAlert
            v-if="errorDialog"
            type="error"
            class="mb-4"
          >
            {{ errorDialog }}
          </VAlert>
          <VTextField
            v-model="nombreReglamento"
            label="Nombre del reglamento"
            maxlength="255"
            counter="255"
            :disabled="guardando"
          />
          <VTextarea
            v-model="descripcionReglamento"
            label="Breve descripción"
            rows="3"
            maxlength="1000"
            counter="1000"
            :disabled="guardando"
          />
          <VFileInput
            v-model="archivo"
            label="Archivo PDF"
            accept="application/pdf,.pdf"
            prepend-icon="tabler-paperclip"
            show-size
            :disabled="guardando"
          />
          <VCardActions class="reglamento-dialog-actions">
            <VSpacer />
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="guardando"
              @click="cerrarDialog"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="primary"
              :loading="guardando"
              @click="guardarReglamento"
            >
              Guardar
            </VBtn>
          </VCardActions>
        </VCardText>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.reglamento-dialog-card {
  border-radius: 16px;
  overflow: hidden;
}

.reglamento-dialog-header {
  background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.1), rgba(var(--v-theme-info), 0.08));
  padding-block: 16px;
}

.reglamento-dialog-body {
  padding-block-start: 20px;
}

.reglamento-dialog-actions {
  border-top: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding-top: 16px;
}
</style>
