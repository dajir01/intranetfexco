<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Ferias',
  },
})

const router = useRouter()

// Estado de la tabla
const items = ref([])
const loading = ref(false)
const error = ref(null)
const activatingId = ref(null)
const deactivatingId = ref(null)
const confirmDialog = ref(false)
const deactivateDialog = ref(false)
const selectedItem = ref(null)

// Headers de la tabla
const headers = [
  { title: 'Nombre de la Feria', key: 'nombre_feria', sortable: true },
  { title: 'Fecha Inicio', key: 'fecha_inicio', sortable: true },
  { title: 'Fecha Fin', key: 'fecha_fin', sortable: true },
  { title: 'Pabellones', key: 'pabellones', sortable: false },
  { title: 'Estado', key: 'estado_feria', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false },
]

/**
 * Obtener etiqueta de estado
 */
const getEstadoLabel = estado => {
  switch (estado) {
  case 1:
    return 'Activa'
  case 0:
    return 'Inactiva'
  default:
    return 'Desconocido'
  }
}

/**
 * Obtener color del chip de estado
 */
const getEstadoColor = estado => {
  switch (estado) {
  case 1:
    return 'success'
  case 0:
    return 'error'
  default:
    return 'default'
  }
}

/**
 * Formatear fecha para mostrar
 */
const formatDate = date => {
  if (!date) return '—'
  try {
    const normalized = typeof date === 'string' && date.length === 10
      ? `${date}T00:00:00`
      : date

    const d = new Date(normalized)

    return d.toLocaleDateString('es-ES', {
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    })
  } catch {
    return date
  }
}

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

/**
 * Cargar ferias desde el backend
 */
const loadFerias = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await fetch('/ferias', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)

    const json = await res.json()

    if (!json.success)
      throw new Error(json.message || 'Error al cargar ferias')

    items.value = json.data || []
  } catch (err) {
    error.value = err.message || 'Error al cargar las ferias'
    console.error('Error cargando ferias:', err)
  } finally {
    loading.value = false
  }
}

const openActivateConfirm = item => {
  if (!item || item.estado_feria === 1) return
  selectedItem.value = item
  confirmDialog.value = true
}

const openDeactivateConfirm = item => {
  if (!item || item.estado_feria === 0) return
  selectedItem.value = item
  deactivateDialog.value = true
}

const activateFeria = async item => {
  if (!item?.id_feria || item.estado_feria === 1) return

  activatingId.value = item.id_feria
  error.value = null

  try {
    const res = await fetch(`/ferias/${item.id_feria}/activate`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    const json = await res.json()

    if (!res.ok || !json.success) {
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    await loadFerias()
    confirmDialog.value = false
    selectedItem.value = null
  } catch (err) {
    error.value = err.message || 'No se pudo activar la feria'
    console.error('Error activando feria:', err)
  } finally {
    activatingId.value = null
  }
}

const deactivateFeria = async item => {
  if (!item?.id_feria || item.estado_feria === 0) return

  deactivatingId.value = item.id_feria
  error.value = null

  try {
    const res = await fetch(`/ferias/${item.id_feria}/deactivate`, {
      method: 'PATCH',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    const json = await res.json()

    if (!res.ok || !json.success) {
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    await loadFerias()
    deactivateDialog.value = false
    selectedItem.value = null
  } catch (err) {
    error.value = err.message || 'No se pudo desactivar la feria'
    console.error('Error desactivando feria:', err)
  } finally {
    deactivatingId.value = null
  }
}

// Cargar ferias al montar el componente
onMounted(() => {
  loadFerias()
})
</script>

<template>
  <section>
    <VCard id="feria-list">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VIcon
            icon="tabler-calendar-event"
            size="28"
          />
          <span class="text-h5">Gestión de Ferias</span>
        </div>

        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="router.push('/feria/registro')"
        >
          Registrar Feria
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

      <!-- Tabla de ferias -->
      <VDataTable
        :items="items"
        :headers="headers"
        :loading="loading"
        item-value="id_feria"
        class="text-no-wrap"
        :items-per-page="10"
        :items-per-page-options="[
          { value: 10, title: '10' },
          { value: 25, title: '25' },
          { value: 50, title: '50' },
          { value: -1, title: 'Todas' },
        ]"
      >
        <!-- Nombre de la feria -->
        <template #item.nombre_feria="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-building-store"
              size="20"
              color="primary"
            />
            <span class="text-high-emphasis font-weight-medium">
              {{ item.nombre_feria || '—' }}
            </span>
          </div>
        </template>

        <!-- Fecha inicio -->
        <template #item.fecha_inicio="{ item }">
          <div class="d-flex align-center gap-1">
            <VIcon
              icon="tabler-calendar-check"
              size="18"
              color="success"
            />
            <span>{{ formatDate(item.fecha_inicio) }}</span>
          </div>
        </template>

        <!-- Fecha fin -->
        <template #item.fecha_fin="{ item }">
          <div class="d-flex align-center gap-1">
            <VIcon
              icon="tabler-calendar-x"
              size="18"
              color="error"
            />
            <span>{{ formatDate(item.fecha_fin) }}</span>
          </div>
        </template>

        <!-- Pabellones -->
        <template #item.pabellones="{ item }">
          <VTooltip location="top">
            <template #activator="{ props }">
              <div
                v-bind="props"
                class="text-truncate"
                style="max-width: 250px;"
              >
                <VIcon
                  icon="tabler-home"
                  size="18"
                  class="me-1"
                />
                {{ item.pabellones }}
              </div>
            </template>
            <span>{{ item.pabellones }}</span>
          </VTooltip>
        </template>

        <!-- Estado -->
        <template #item.estado_feria="{ item }">
          <VChip
            :color="getEstadoColor(item.estado_feria)"
            :text="getEstadoLabel(item.estado_feria)"
            size="small"
            variant="tonal"
          />
        </template>

        <!-- Acciones -->
        <template #item.actions="{ item }">
          <div class="d-flex flex-column gap-1">
            <VTooltip
              v-if="item.estado_feria === 0"
              text="Activar"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  color="success"
                  variant="flat"
                  size="small"
                  prepend-icon="tabler-check"
                  :loading="activatingId === item.id_feria"
                  :disabled="loading || activatingId === item.id_feria"
                  @click="() => openActivateConfirm(item)"
                >
                  Activar
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip
              v-if="item.estado_feria === 1"
              text="Desactivar"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  color="error"
                  variant="flat"
                  size="small"
                  prepend-icon="tabler-x"
                  :loading="deactivatingId === item.id_feria"
                  :disabled="loading || deactivatingId === item.id_feria"
                  @click="() => openDeactivateConfirm(item)"
                >
                  Desactivar
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip
              text="Ver / Editar"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  color="primary"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-edit"
                  @click="() => router.push(`/feria/edit/${item.id_feria}`)"
                >
                  Ver / Editar
                </VBtn>
              </template>
            </VTooltip>

            <VTooltip
              text="Gestionar Pabellones"
              location="top"
            >
              <template #activator="{ props }">
                <VBtn
                  v-bind="props"
                  color="info"
                  variant="tonal"
                  size="small"
                  prepend-icon="tabler-building"
                  @click="() => router.push(`/feria/pabellon/${item.id_feria}`)"
                >
                  Pabellones
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
            <VIcon
              icon="tabler-calendar-off"
              size="64"
              color="disabled"
              class="mb-4"
            />
            <div class="text-h6 text-disabled">
              No hay ferias registradas
            </div>
          </div>
        </template>
      </VDataTable>

      <VDialog
        v-model="confirmDialog"
        width="480"
        persistent
      >
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-alert-triangle" color="warning" size="24" />
            Confirmar activación
          </VCardTitle>
          <VCardText>
            <p class="mb-0">
              ¿Activar la feria
              <strong>{{ selectedItem?.nombre_feria }}</strong>?
            </p>
          </VCardText>
          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="activatingId !== null"
              @click="() => { confirmDialog = false; selectedItem = null }"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="success"
              prepend-icon="tabler-check"
              :loading="activatingId === selectedItem?.id_feria"
              :disabled="activatingId !== null"
              @click="() => activateFeria(selectedItem)"
            >
              Activar
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>

      <VDialog
        v-model="deactivateDialog"
        width="480"
        persistent
      >
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-alert-triangle" color="warning" size="24" />
            Confirmar desactivación
          </VCardTitle>
          <VCardText>
            <p class="mb-0">
              ¿Desactivar la feria
              <strong>{{ selectedItem?.nombre_feria }}</strong>?
            </p>
          </VCardText>
          <VCardActions class="justify-end">
            <VBtn
              variant="tonal"
              color="secondary"
              :disabled="deactivatingId !== null"
              @click="() => { deactivateDialog = false; selectedItem = null }"
            >
              Cancelar
            </VBtn>
            <VBtn
              color="error"
              prepend-icon="tabler-x"
              :loading="deactivatingId === selectedItem?.id_feria"
              :disabled="deactivatingId !== null"
              @click="() => deactivateFeria(selectedItem)"
            >
              Desactivar
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </VCard>
  </section>
</template>

<style lang="scss" scoped>
#feria-list {
  .text-truncate {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }
}
</style>
