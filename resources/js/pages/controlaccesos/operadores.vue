<script setup>
/* eslint-disable camelcase */
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { watchDebounced } from '@vueuse/core'
import { useSafePagination } from '@/composables/useSafePagination'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Operadores de Acceso',
  },
})

const router = useRouter()
const auth = useAuthStore()
const puedeVer = computed(() => auth.can('controlaccesos.operadores.ver'))

const puedeAdministrar = computed(() => auth.can('controlaccesos.operadores.gestionar')
  || auth.can('usuarios.gestionar_permisos'))

const items = ref([])
const total = ref(0)
const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy = ref([{ key: 'nombre', order: 'asc' }])
const search = ref('')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const formErrors = ref({})
const dialog = ref(false)
const operatorBeingEdited = ref(null)
const permissionsCatalog = ref([])
const eventos = ref([])
const loadingEvents = ref(false)
const selectedEventId = ref(null)
let operatorRequestId = 0

const emptyForm = () => ({
  nombre: '',
  usuario: '',
  password: '',
  activo: true,
  permissions: [],
  puertas: [],
})

const form = ref(emptyForm())

const headers = [
  { title: 'Nombre', key: 'nombre', sortable: true },
  { title: 'Usuario', key: 'usuario', sortable: true },
  { title: 'Puertas autorizadas', key: 'puertas', sortable: false },
  { title: 'Permisos', key: 'permissions', sortable: false },
  { title: 'Estado', key: 'activo', sortable: true },
  { title: 'Acciones', key: 'actions', sortable: false },
]

const eventosPorId = computed(() => new Map(eventos.value.map(evento => [Number(evento.id_feria), evento])))

const eventoSeleccionado = computed(() => eventosPorId.value.get(Number(selectedEventId.value)) || null)
const puertasDisponibles = computed(() => eventoSeleccionado.value?.puertas || [])

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const validationFieldLabels = {
  nombre: 'Nombre completo',
  usuario: 'Usuario de acceso',
  password: 'Contraseña',
  activo: 'Estado de la cuenta',
  permissions: 'Permisos de la app',
  puertas: 'Puertas autorizadas',
  id_feria: 'Evento seleccionado',
}

const formFieldForError = field => {
  const rootField = field.split('.')[0]

  return validationFieldLabels[rootField] ? rootField : null
}

const responseError = async response => {
  const json = await response.json().catch(() => ({}))
  const errors = json.errors || {}

  const validationErrors = Object.entries(errors)
    .flatMap(([field, messages]) => messages.map(message => {
      const label = validationFieldLabels[formFieldForError(field)] || field

      return `${label}: ${message}`
    }))

  const fieldErrors = Object.entries(errors).reduce((result, [field, messages]) => {
    const formField = formFieldForError(field)
    if (formField)
      result[formField] = [...(result[formField] || []), ...messages]

    return result
  }, {})

  return {
    message: validationErrors.join(' ') || json.message || `Error HTTP ${response.status}`,
    fieldErrors,
    errorId: json.error_id,
  }
}

const requestJson = async (url, options = {}) => {
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } : {}),
      ...options.headers,
    },
    ...options,
  })

  if (!response.ok) {
    const details = await responseError(response)
    const exception = new Error(details.message)

    exception.fieldErrors = details.fieldErrors
    exception.errorId = details.errorId
    throw exception
  }

  return response.json()
}

const loadOperators = async () => {
  const requestId = ++operatorRequestId
  const eventId = selectedEventId.value

  if (!puedeVer.value) {
    router.replace('/')

    return
  }

  if (!eventId) {
    items.value = []
    total.value = 0
    loading.value = false

    return
  }

  loading.value = true
  error.value = ''
  try {
    const params = new URLSearchParams({
      id_feria: String(eventId),
      page: String(page.value),
      per_page: String(itemsPerPage.value),
      sort_by: sortBy.value[0]?.key || 'nombre',
      sort_order: sortBy.value[0]?.order || 'asc',
    })

    if (search.value.trim())
      params.set('search', search.value.trim())

    const json = await requestJson(`/control-accesos/operadores?${params}`)

    if (requestId !== operatorRequestId)
      return

    items.value = json.data || []
    total.value = json.meta?.total || 0
    permissionsCatalog.value = json.permissions_catalog || []
  } catch (exception) {
    if (requestId !== operatorRequestId)
      return

    error.value = `No se pudo cargar la lista de operadores: ${exception.message}`
    items.value = []
    total.value = 0
  } finally {
    if (requestId === operatorRequestId)
      loading.value = false
  }
}

const loadEventos = async () => {
  loadingEvents.value = true
  try {
    const json = await requestJson('/control-accesos/operadores/eventos')

    eventos.value = json.data || []
  } catch (exception) {
    error.value = `No se pudieron cargar los eventos: ${exception.message}`
  } finally {
    loadingEvents.value = false
  }
}

const selectEvent = eventId => {
  selectedEventId.value = eventId
  page.value = 1
  error.value = ''
  items.value = []
  total.value = 0
  permissionsCatalog.value = []
  loadOperators()
}

const updateTableOptions = options => {
  const nextPage = options.page || 1
  const nextItemsPerPage = sanitizeItemsPerPage(options.itemsPerPage)
  const nextSortBy = options.sortBy?.length ? options.sortBy : [{ key: 'nombre', order: 'asc' }]
  const currentSort = sortBy.value[0]
  const nextSort = nextSortBy[0]

  const hasChanged = nextPage !== page.value
    || nextItemsPerPage !== itemsPerPage.value
    || currentSort?.key !== nextSort?.key
    || currentSort?.order !== nextSort?.order

  page.value = nextPage
  itemsPerPage.value = nextItemsPerPage
  sortBy.value = nextSortBy

  if (selectedEventId.value && hasChanged)
    loadOperators()
}

watchDebounced(search, () => {
  page.value = 1
  if (selectedEventId.value)
    loadOperators()
}, { debounce: 350 })

const permissionLabel = permission => permissionsCatalog.value.find(item => item.permission === permission)?.label || permission

const openCreate = () => {
  if (!eventoSeleccionado.value)
    return

  operatorBeingEdited.value = null
  form.value = emptyForm()
  error.value = ''
  formErrors.value = {}
  dialog.value = true
}

const openEdit = operator => {
  if (!eventoSeleccionado.value)
    return

  operatorBeingEdited.value = operator
  form.value = {
    nombre: operator.nombre,
    usuario: operator.usuario,
    password: '',
    activo: operator.activo,
    permissions: [...operator.permissions],
    puertas: operator.puertas.map(Number),
  }
  error.value = ''
  formErrors.value = {}
  dialog.value = true
}

const saveOperator = async () => {
  if (!eventoSeleccionado.value) {
    formErrors.value = { id_feria: ['Selecciona un evento antes de guardar el operador.'] }

    return
  }

  const editing = operatorBeingEdited.value

  formErrors.value = {}
  saving.value = true
  error.value = ''
  try {
    const payload = {
      id_feria: Number(selectedEventId.value),
      nombre: form.value.nombre.trim(),
      usuario: form.value.usuario.trim(),
      activo: form.value.activo,
      permissions: form.value.permissions,
      puertas: form.value.puertas,
    }

    if (form.value.password)
      payload.password = form.value.password

    const url = editing
      ? `/control-accesos/operadores/${editing.id_usuario_control}`
      : '/control-accesos/operadores'

    await requestJson(url, {
      method: editing ? 'PUT' : 'POST',
      body: JSON.stringify(payload),
    })
    dialog.value = false
    await loadOperators()
  } catch (exception) {
    if (Object.keys(exception.fieldErrors || {}).length) {
      formErrors.value = exception.fieldErrors
    } else {
      error.value = exception.errorId
        ? `No se pudo ${editing ? 'actualizar' : 'crear'} el operador. Referencia: ${exception.errorId}.`
        : `No se pudo ${editing ? 'actualizar' : 'crear'} el operador: ${exception.message}`
    }
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!puedeVer.value) {
    router.replace('/')

    return
  }

  await loadEventos()
})
</script>

<template>
  <section>
    <VCard class="mb-6">
      <VCardText>
        <h4 class="text-h4 mb-4">
          Operadores de Acceso
        </h4>
        <VSelect
          :model-value="selectedEventId"
          :items="eventos"
          :loading="loadingEvents"
          item-title="nombre_feria"
          item-value="id_feria"
          label="Seleccionar Evento / Feria"
          placeholder="Elige un evento para administrar sus operadores"
          clearable
          :disabled="dialog"
          @update:model-value="selectEvent"
        >
          <template #prepend-inner>
            <VIcon icon="tabler-calendar-event" />
          </template>
        </VSelect>
        <VAlert
          v-if="error && !dialog"
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

    <VCard
      v-if="!selectedEventId"
      class="mt-4"
    >
      <VCardText class="d-flex flex-column align-center justify-center text-center pa-8">
        <VIcon
          icon="tabler-user-shield"
          size="48"
          color="disabled"
          class="mb-4"
        />
        <h3 class="text-h6 mb-2">
          Selecciona un evento
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Elige primero un evento o feria para ver y administrar sus operadores de acceso.
        </p>
      </VCardText>
    </VCard>

    <VCard
      v-else
      id="control-accesos-operadores-list"
    >
      <VCardItem>
        <template #title>
          Operadores de Acceso
        </template>
        <template #subtitle>
          {{ eventoSeleccionado?.nombre_feria }} — cuentas independientes de los usuarios de la intranet.
        </template>
      </VCardItem>

      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Mostrar</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = sanitizeItemsPerPage($event); page = 1; loadOperators()"
            />
          </div>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <div style="inline-size: 280px; max-inline-size: 100%;">
            <AppTextField
              v-model="search"
              placeholder="Buscar operadores..."
              append-inner-icon="tabler-search"
              single-line
              hide-details
              dense
              outlined
            />
          </div>
          <VBtn
            v-if="puedeAdministrar"
            prepend-icon="tabler-user-plus"
            :disabled="loading || !puertasDisponibles.length"
            @click="openCreate"
          >
            Nuevo operador
          </VBtn>
        </div>
      </VCardText>
      <VDivider />

      <VCardText>
        <div class="d-flex align-center justify-space-between mb-4">
          <h5 class="text-h5">
            Operadores — {{ eventoSeleccionado?.nombre_feria }}
          </h5>
        </div>

        <VAlert
          v-if="!puertasDisponibles.length"
          type="info"
          variant="tonal"
          class="mb-4"
        >
          Este evento no tiene puertas activas configuradas. No se pueden crear o editar operadores hasta que se configuren sus puertas.
        </VAlert>

        <VDataTableServer
          v-model:items-per-page="itemsPerPage"
          v-model:page="page"
          v-model:sort-by="sortBy"
          :items-length="total"
          :items-per-page-options="itemsPerPageOptions"
          :headers="headers"
          :items="items"
          :no-data-text="loading ? 'Cargando operadores...' : 'No hay operadores registrados para este evento.'"
          :loading="loading"
          item-value="id_usuario_control"
          class="text-no-wrap"
          @update:options="updateTableOptions"
        >
          <template #item.puertas="{ item }">
            <div
              v-if="item.puertas.length"
              class="d-flex flex-wrap ga-1"
            >
              <VChip
                v-for="door in item.puertas"
                :key="door"
                size="small"
                variant="tonal"
              >
                Puerta {{ door }}
              </VChip>
            </div>
            <span
              v-else
              class="text-medium-emphasis"
            >Sin puertas</span>
          </template>

          <template #item.permissions="{ item }">
            <div
              v-if="item.permissions.length"
              class="d-flex flex-wrap ga-1"
            >
              <VChip
                v-for="permission in item.permissions"
                :key="permission"
                size="small"
                color="info"
                variant="tonal"
              >
                {{ permissionLabel(permission) }}
              </VChip>
            </div>
            <span
              v-else
              class="text-medium-emphasis"
            >Sin permisos</span>
          </template>

          <template #item.activo="{ item }">
            <VChip
              :color="item.activo ? 'success' : 'error'"
              :text="item.activo ? 'Activo' : 'Inactivo'"
              size="small"
            />
          </template>

          <template #item.actions="{ item }">
            <VBtn
              v-if="puedeAdministrar"
              icon="tabler-edit"
              size="small"
              variant="text"
              :aria-label="`Editar ${item.nombre}`"
              :disabled="!puertasDisponibles.length"
              @click="openEdit(item)"
            />
          </template>
        </VDataTableServer>
      </VCardText>
    </VCard>

    <VDialog
      v-model="dialog"
      max-width="850"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between">
          {{ operatorBeingEdited ? 'Editar operador' : 'Crear operador' }}
          <VBtn
            icon="tabler-x"
            variant="text"
            :disabled="saving"
            @click="dialog = false"
          />
        </VCardTitle>
        <VCardText>
          <VAlert
            v-if="error"
            type="error"
            variant="tonal"
            class="mb-4"
            closable
            @click:close="error = ''"
          >
            {{ error }}
          </VAlert>

          <VForm @submit.prevent="saveOperator">
            <VRow>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.nombre"
                  label="Nombre completo"
                  maxlength="150"
                  :error-messages="formErrors.nombre || []"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.usuario"
                  label="Usuario de acceso"
                  maxlength="100"
                  autocomplete="off"
                  :error-messages="formErrors.usuario || []"
                  required
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VTextField
                  v-model="form.password"
                  :label="operatorBeingEdited ? 'Nueva contraseña (opcional)' : 'Contraseña'"
                  type="password"
                  minlength="4"
                  maxlength="8"
                  :rules="[value => (operatorBeingEdited && !value) || (value?.length >= 4 && value.length <= 8) || 'La contraseña debe tener entre 4 y 8 caracteres.']"
                  :autocomplete="operatorBeingEdited ? 'new-password' : 'off'"
                  :required="!operatorBeingEdited"
                  :hint="operatorBeingEdited ? 'Déjala vacía para conservarla. Entre 4 y 8 caracteres.' : 'Entre 4 y 8 caracteres.'"
                  :error-messages="formErrors.password || []"
                  persistent-hint
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VSwitch
                  v-model="form.activo"
                  label="Cuenta activa"
                  color="success"
                  :error-messages="formErrors.activo || []"
                  hide-details="auto"
                />
              </VCol>
            </VRow>

            <VDivider class="my-5" />

            <div class="text-subtitle-1 font-weight-medium mb-2">
              Permisos de la app
            </div>
            <p class="text-body-2 text-medium-emphasis mb-2">
              Los permisos aplican a la cuenta en todos sus eventos; las puertas asignadas corresponden al evento seleccionado.
            </p>
            <div class="d-flex flex-wrap ga-4">
              <VCheckbox
                v-for="permission in permissionsCatalog"
                :key="permission.permission"
                v-model="form.permissions"
                :value="permission.permission"
                :label="permission.label"
                hide-details
              />
            </div>
            <div
              v-if="formErrors.permissions"
              class="text-error text-body-2 mt-2"
            >
              {{ formErrors.permissions.join(' ') }}
            </div>

            <VDivider class="my-5" />

            <div class="text-subtitle-1 font-weight-medium mb-2">
              Evento seleccionado
            </div>
            <VChip
              color="primary"
              variant="tonal"
              class="mb-4"
            >
              {{ eventoSeleccionado?.nombre_feria }}
            </VChip>
            <div
              v-if="formErrors.id_feria"
              class="text-error text-body-2 mb-2"
            >
              {{ formErrors.id_feria.join(' ') }}
            </div>
            <VSelect
              v-model="form.puertas"
              :items="puertasDisponibles"
              label="Puertas autorizadas"
              placeholder="Selecciona una o más puertas"
              multiple
              chips
              closable-chips
              :rules="[value => value.length > 0 || 'Selecciona al menos una puerta.']"
              :error-messages="formErrors.puertas || []"
              :disabled="saving || !puertasDisponibles.length"
              class="mb-4"
            />

            <div class="d-flex justify-end ga-2 mt-5">
              <VBtn
                variant="text"
                :disabled="saving"
                @click="dialog = false"
              >
                Cancelar
              </VBtn>
              <VBtn
                type="submit"
                :loading="saving"
              >
                Guardar operador
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>
    </VDialog>
  </section>
</template>
