<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Pabellones',
  },
})

const route = useRoute()
const router = useRouter()

const feriaId = computed(() => route.params.id)

// Estado
const items = ref([])
const feria = ref(null)
const loading = ref(false)
const error = ref(null)
const success = ref(null)

// Diálogos
const createDialog = ref(false)
const editDialog = ref(false)
const deleteDialog = ref(false)
const selectedItem = ref(null)

// Formularios
const createForm = ref({
  nombre_pabellon: '',
  mapa: null,
})

const editForm = ref({
  nombre_pabellon: '',
  mapa: null,
})

// Estados de guardado
const creating = ref(false)
const updating = ref(false)
const deleting = ref(false)

// Headers de la tabla
const headers = [
  { title: 'Nombre del Pabellón', key: 'nombre_pabellon', sortable: true },
  { title: 'Cantidad de Stands', key: 'cantidad_stands', sortable: true },
  { title: 'Mapa', key: 'mapa', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

/**
 * Cargar pabellones de la feria
 */
const loadPabellones = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await fetch(`/ferias/${feriaId.value}/pabellones`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)

    const json = await res.json()

    if (!json.success)
      throw new Error(json.message || 'Error al cargar pabellones')

    items.value = json.data?.pabellones || []
    feria.value = json.data?.feria || null
  } catch (err) {
    error.value = err.message || 'Error al cargar los pabellones'
    console.error('Error cargando pabellones:', err)
  } finally {
    loading.value = false
  }
}

/**
 * Abrir diálogo de creación
 */
const openCreateDialog = () => {
  createForm.value = {
    nombre_pabellon: '',
    mapa: null,
  }
  createDialog.value = true
}

/**
 * Crear pabellón
 */
const createPabellon = async () => {
  error.value = null
  success.value = null

  if (!createForm.value.nombre_pabellon?.trim()) {
    error.value = 'El nombre del pabellón es obligatorio.'
    return
  }

  creating.value = true
  try {
    const formData = new FormData()
    formData.append('nombre_pabellon', createForm.value.nombre_pabellon.trim())
    if (createForm.value.mapa) {
      formData.append('mapa', createForm.value.mapa)
    }

    const res = await fetch(`/ferias/${feriaId.value}/pabellones`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: formData,
    })

    const json = await res.json()

    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validación')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Pabellón creado correctamente.'
    createDialog.value = false
    await loadPabellones()
  } catch (err) {
    error.value = err.message || 'No se pudo crear el pabellón.'
    console.error('Error creando pabellón:', err)
  } finally {
    creating.value = false
  }
}

/**
 * Abrir diálogo de edición
 */
const openEditDialog = item => {
  selectedItem.value = item
  editForm.value = {
    nombre_pabellon: item.nombre_pabellon || '',
    mapa: null,
  }
  editDialog.value = true
}

/**
 * Actualizar pabellón
 */
const updatePabellon = async () => {
  if (!selectedItem.value) return

  error.value = null
  success.value = null

  if (!editForm.value.nombre_pabellon?.trim()) {
    error.value = 'El nombre del pabellón es obligatorio.'
    return
  }

  updating.value = true
  try {
    const formData = new FormData()
    formData.append('nombre_pabellon', editForm.value.nombre_pabellon.trim())
    if (editForm.value.mapa) {
      formData.append('mapa', editForm.value.mapa)
    }

    const res = await fetch(`/ferias/${feriaId.value}/pabellones/${selectedItem.value.id_pabellon}`, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: formData,
    })

    const json = await res.json()

    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validación')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Pabellón actualizado correctamente.'
    editDialog.value = false
    selectedItem.value = null
    await loadPabellones()
  } catch (err) {
    error.value = err.message || 'No se pudo actualizar el pabellón.'
    console.error('Error actualizando pabellón:', err)
  } finally {
    updating.value = false
  }
}

/**
 * Abrir confirmación de eliminación
 */
const openDeleteConfirm = item => {
  selectedItem.value = item
  deleteDialog.value = true
}

/**
 * Eliminar pabellón
 */
const deletePabellon = async () => {
  if (!selectedItem.value) return

  deleting.value = true
  error.value = null
  success.value = null

  try {
    const res = await fetch(`/ferias/${feriaId.value}/pabellones/${selectedItem.value.id_pabellon}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    const json = await res.json()

    if (!res.ok) {
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Pabellón eliminado correctamente.'
    deleteDialog.value = false
    selectedItem.value = null
    await loadPabellones()
  } catch (err) {
    error.value = err.message || 'No se pudo eliminar el pabellón.'
    console.error('Error eliminando pabellón:', err)
  } finally {
    deleting.value = false
  }
}

/**
 * Obtener URL del mapa siguiendo la convención del sistema antiguo
 * Formato: /img/pabellones/{id_feria}_{id_pabellon}.png
 */
const getMapaUrl = item => {
  if (!item || !item.id_pabellon) return null
  return `/img/pabellones/${feriaId.value}_${item.id_pabellon}.png?t=${Date.now()}`
}

/**
 * Verificar si existe la imagen del mapa
 */
const mapaExists = item => {
  // En producción, se puede verificar con un request head
  // Por ahora asumimos que existe si el pabellón tiene ID
  return item?.id_pabellon != null
}

/**
 * Manejar selección de archivo
 */
const handleFileCreate = event => {
  const file = event.target.files?.[0]
  if (file) {
    createForm.value.mapa = file
  }
}

const handleFileEdit = event => {
  const file = event.target.files?.[0]
  if (file) {
    editForm.value.mapa = file
  }
}

const backToFerias = () => {
  router.push('/feria/list')
}

onMounted(() => {
  loadPabellones()
})
</script>

<template>
  <section>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VBtn
            icon
            variant="text"
            size="small"
            @click="backToFerias"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <VIcon icon="tabler-building" size="28" />
          <div class="d-flex flex-column">
            <span class="text-h5">Pabellones</span>
            <span
              v-if="feria"
              class="text-caption text-medium-emphasis"
            >
              {{ feria.nombre_feria }}
            </span>
          </div>
        </div>

        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
        >
          Agregar Pabellón
        </VBtn>
      </VCardTitle>

      <VDivider />

      <!-- Mensaje de error -->
      <VCardText v-if="error && !loading">
        <VAlert
          type="error"
          variant="tonal"
          closable
          icon="tabler-alert-circle"
          @click:close="error = null"
        >
          <div class="text-body-2">
            <strong>Error:</strong> {{ error }}
          </div>
        </VAlert>
      </VCardText>

      <!-- Mensaje de éxito -->
      <VCardText v-if="success && !loading">
        <VAlert
          type="success"
          variant="tonal"
          closable
          icon="tabler-circle-check"
          @click:close="success = null"
        >
          <div class="text-body-2">
            {{ success }}
          </div>
        </VAlert>
      </VCardText>

      <!-- Tabla de pabellones -->
      <VDataTable
        :items="items"
        :headers="headers"
        :loading="loading"
        item-value="id_pabellon"
        class="text-no-wrap"
        :items-per-page="10"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 25, title: '25' },
          { value: 50, title: '50' },
          { value: -1, title: 'Todas' },
        ]"
      >
        <!-- Nombre -->
        <template #item.nombre_pabellon="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon icon="tabler-building-warehouse" size="20" color="primary" />
            <span class="text-high-emphasis font-weight-medium">
              {{ item.nombre_pabellon || '—' }}
            </span>
          </div>
        </template>

        <!-- Cantidad de Stands -->
        <template #item.cantidad_stands="{ item }">
          <div class="d-flex align-center gap-2">
            <VChip
              :color="item.cantidad_stands > 0 ? 'success' : 'default'"
              size="small"
              variant="tonal"
            >
              <VIcon icon="tabler-layout-grid" size="16" class="me-1" />
              {{ item.cantidad_stands || 0 }}
            </VChip>
          </div>
        </template>

        <!-- Mapa -->
        <template #item.mapa="{ item }">
          <div class="py-2">
            <VImg
              v-if="mapaExists(item)"
              :src="getMapaUrl(item)"
              width="80"
              height="60"
              cover
              class="rounded"
            >
              <template #error>
                <div
                  class="d-flex align-center justify-center rounded bg-surface-variant"
                  style="width: 80px; height: 60px;"
                >
                  <VIcon icon="tabler-photo-off" size="24" color="disabled" />
                </div>
              </template>
            </VImg>
            <div
              v-else
              class="d-flex align-center justify-center rounded bg-surface-variant"
              style="width: 80px; height: 60px;"
            >
              <VIcon icon="tabler-photo-off" size="24" color="disabled" />
            </div>
          </div>
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex flex-column gap-1">
            <VTooltip text="Editar" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  variant="tonal"
                  size="small"
                  color="primary"
                  prepend-icon="tabler-edit"
                  @click="() => openEditDialog(item)"
                >
                  Editar
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip text="Modificar Stands" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  variant="tonal"
                  size="small"
                  color="info"
                  prepend-icon="tabler-layout-grid"
                  @click="() => router.push(`/feria/stand/${item.id_pabellon}`)"
                >
                  Stands
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip text="Eliminar" location="top">
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  variant="tonal"
                  size="small"
                  color="error"
                  prepend-icon="tabler-trash"
                  @click="() => openDeleteConfirm(item)"
                >
                  Eliminar
                </VBtn>
              </template>
            </VTooltip>
          </div>
        </template>

        <!-- Loading state -->
        <template #loading>
          <VSkeletonLoader type="table-row@5" />
        </template>

        <!-- No data -->
        <template #no-data>
          <div class="text-center pa-8">
            <VIcon icon="tabler-building-off" size="64" color="disabled" class="mb-4" />
            <div class="text-h6 text-disabled">
              No hay pabellones registrados
            </div>
            <div class="text-body-2 text-disabled mt-2">
              Haz clic en "Agregar Pabellón" para comenzar
            </div>
          </div>
        </template>
      </VDataTable>

      <!-- Diálogo de creación -->
      <VDialog v-model="createDialog" width="600" persistent>
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-plus" size="24" />
            Agregar Pabellón
          </VCardTitle>

          <VDivider />

          <VCardText class="pt-4">
            <VForm @submit.prevent="createPabellon">
              <VRow>
                <VCol cols="12">
                  <VTextField
                    v-model="createForm.nombre_pabellon"
                    label="Nombre del Pabellón"
                    placeholder="Ej: Pabellón A"
                    required
                    :disabled="creating"
                  />
                </VCol>

                <VCol cols="12">
                  <VFileInput
                    label="Mapa del Pabellón"
                    placeholder="Seleccionar imagen"
                    accept="image/*"
                    prepend-icon="tabler-photo"
                    :disabled="creating"
                    @change="handleFileCreate"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>

          <VDivider />

          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="creating"
              @click="createDialog = false"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-device-floppy"
              :loading="creating"
              :disabled="creating"
              @click="createPabellon"
            >
              Guardar
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>

      <!-- Diálogo de edición -->
      <VDialog v-model="editDialog" width="600" persistent>
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-edit" size="24" />
            Editar Pabellón
          </VCardTitle>

          <VDivider />

          <VCardText class="pt-4">
            <VForm @submit.prevent="updatePabellon">
              <VRow>
                <VCol cols="12">
                  <VTextField
                    v-model="editForm.nombre_pabellon"
                    label="Nombre del Pabellón"
                    placeholder="Ej: Pabellón A"
                    required
                    :disabled="updating"
                  />
                </VCol>

                <VCol cols="12">
                  <div v-if="selectedItem?.id_pabellon" class="mb-4">
                    <div class="text-caption mb-2">
                      Mapa actual:
                    </div>
                    <VImg
                      :src="getMapaUrl(selectedItem)"
                      max-width="100%"
                      height="300"
                      cover
                      class="rounded"
                    >
                      <template #error>
                        <div
                          class="d-flex align-center justify-center rounded bg-surface-variant"
                          style="width: 100%; height: 300px;"
                        >
                          <VIcon icon="tabler-photo-off" size="48" color="disabled" />
                        </div>
                      </template>
                    </VImg>
                  </div>

                  <VFileInput
                    label="Nuevo Mapa (opcional)"
                    placeholder="Seleccionar nueva imagen"
                    accept="image/*"
                    prepend-icon="tabler-photo"
                    :disabled="updating"
                    @change="handleFileEdit"
                  />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>

          <VDivider />

          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="updating"
              @click="() => { editDialog = false; selectedItem = null }"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="primary"
              prepend-icon="tabler-device-floppy"
              :loading="updating"
              :disabled="updating"
              @click="updatePabellon"
            >
              Guardar Cambios
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>

      <!-- Diálogo de eliminación -->
      <VDialog v-model="deleteDialog" width="480" persistent>
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-alert-triangle" color="warning" size="24" />
            Confirmar eliminación
          </VCardTitle>

          <VCardText>
            <p class="mb-0">
              ¿Eliminar el pabellón
              <strong>{{ selectedItem?.nombre_pabellon }}</strong>?
            </p>
            <p class="text-body-2 text-error mt-2 mb-0">
              Esta acción no se puede deshacer.
            </p>
          </VCardText>

          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="deleting"
              @click="() => { deleteDialog = false; selectedItem = null }"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="error"
              prepend-icon="tabler-trash"
              :loading="deleting"
              :disabled="deleting"
              @click="deletePabellon"
            >
              Eliminar
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </VCard>
  </section>
</template>

<style lang="scss" scoped>
.rounded {
  border-radius: 8px;
}
</style>
