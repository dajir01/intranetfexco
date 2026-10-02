<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()

definePage({
  meta: {
    requiresAuth: true,
    title: 'Configuración de notificaciones',
  },
})

const loading = ref(false)
const saving = ref(false)
const successDialog = ref(false)
const successMessage = ref('')
const configs = ref([])
const areas = ref([])
const users = ref([])
const selectedEvent = ref(null)
const canViewNotifications = computed(() => auth.can('notificaciones.ver'))
const canManageNotifications = computed(() => auth.can('notificaciones.editar') || auth.can('notificaciones.crear'))
const form = ref({
  name: '',
  event_key: null,
  active: true,
  description: '',
  areas: [],
  users: [],
  cc: [],
  bcc: [],
})

const eventOptions = [
  { title: 'Actualización de Datos', value: 'actualizacion_datos' },
  { title: 'Reserva creada', value: 'reserva_creada' },
  { title: 'Contrato creado', value: 'contrato_creado' },
  { title: 'Pago registrado', value: 'pago_registrado' },
]

const currentConfig = computed(() => configs.value.find(item => item.event_key === selectedEvent.value) || null)
const statusLabel = eventKey => configs.value.find(config => config.event_key === eventKey)?.active ? 'Activo' : 'Inactivo'
const statusColor = eventKey => configs.value.find(config => config.event_key === eventKey)?.active ? 'success' : 'secondary'
const userOptions = computed(() => {
  const selectedIds = new Set(form.value.users.map(userId => Number(userId)))

  return users.value
    .filter(user => Number(user.estado) === 1 || user.estado === null || selectedIds.has(Number(user.id_usuario)))
    .map(user => ({
      title: user.nombre_usuario,
      subtitle: user.email || 'Sin correo electrónico',
      value: Number(user.id_usuario),
      active: Number(user.estado) === 1 || user.estado === null,
    }))
})

const inactiveSelectedUsers = computed(() => userOptions.value.filter(user => !user.active && form.value.users.includes(user.value)))

const normalizeList = value => Array.isArray(value) ? value.filter(Boolean) : []

const toggleSelection = (field, value, checked) => {
  const current = Array.isArray(form.value[field]) ? form.value[field] : []
  const next = new Set(current.map(item => String(item)))

  if (checked)
    next.add(String(value))
  else
    next.delete(String(value))

  form.value[field] = Array.from(next).map(item => field === 'users' ? Number(item) : item)
}

const fetchAreas = async () => {
  const response = await fetch('/api/notificaciones/areas', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  if (!response.ok) throw new Error('No se pudo cargar áreas')
  const json = await response.json()
  areas.value = normalizeList(json.data)
}

const fetchUsers = async () => {
  const response = await fetch('/api/notificaciones/usuarios', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
  if (!response.ok) throw new Error('No se pudo cargar usuarios')
  const json = await response.json()
  users.value = normalizeList(json.data)
}

const fetchConfigs = async () => {
  loading.value = true
  try {
    const response = await fetch('/api/notificaciones/configuraciones', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (!response.ok) throw new Error('No se pudo cargar la configuración')
    const json = await response.json()
    configs.value = json.data || []
  } finally {
    loading.value = false
  }
}

const applyCurrentConfig = () => {
  if (!selectedEvent.value) {
    form.value = {
      name: '',
      event_key: null,
      active: true,
      description: '',
      areas: [],
      users: [],
      cc: [],
      bcc: [],
    }
    return
  }

  const cfg = currentConfig.value
  if (!cfg) {
    form.value = {
      name: '',
      event_key: selectedEvent.value,
      active: true,
      description: '',
      areas: [],
      users: [],
      cc: [],
      bcc: [],
    }
    return
  }

  form.value = {
    name: cfg.name || '',
    event_key: cfg.event_key || selectedEvent.value,
    active: Boolean(cfg.active),
    description: cfg.description || '',
    areas: normalizeList(cfg.areas),
    users: normalizeList(cfg.users.map(item => item.user_id || item.id_usuario)),
    cc: normalizeList(cfg.cc),
    bcc: normalizeList(cfg.bcc),
  }
}

watch(selectedEvent, () => {
  applyCurrentConfig()
})

watch(() => configs.value, () => {
  applyCurrentConfig()
}, { deep: true })

const submit = async () => {
  if (!canManageNotifications.value) return

  successDialog.value = false
  saving.value = true
  try {
    const payload = {
      ...form.value,
      event_key: selectedEvent.value,
      areas: form.value.areas,
      users: form.value.users,
      cc: form.value.cc,
      bcc: form.value.bcc,
    }

    const method = currentConfig.value ? 'PUT' : 'POST'
    const url = currentConfig.value ? `/api/notificaciones/configuraciones/${selectedEvent.value}` : '/api/notificaciones/configuraciones'
    const response = await fetch(url, {
      method,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const responseBody = await response.json().catch(() => ({}))
    if (!response.ok) {
      throw new Error(responseBody.message || 'No se pudo guardar la configuración')
    }

    await fetchConfigs()
    successMessage.value = responseBody.message || 'Configuración guardada correctamente.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    successDialog.value = true
  } catch (error) {
    console.error(error)
    window.alert(error.message || 'Error al guardar la configuración')
  } finally {
    saving.value = false
  }
}

const toggleActive = async (eventKey) => {
  if (!canManageNotifications.value) return

  successDialog.value = false
  try {
    const response = await fetch(`/api/notificaciones/configuraciones/${eventKey}/toggle`, {
      method: 'PATCH',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      credentials: 'same-origin',
    })

    const responseBody = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(responseBody.message || 'No se pudo cambiar el estado')
    await fetchConfigs()
    successMessage.value = responseBody.message || 'Estado actualizado correctamente.'
    window.scrollTo({ top: 0, behavior: 'smooth' })
    successDialog.value = true
  } catch (error) {
    console.error(error)
    window.alert(error.message || 'Error al cambiar el estado')
  }
}

onMounted(async () => {
  if (!canViewNotifications.value && !canManageNotifications.value) {
    window.location.href = '/'
    return
  }

  await Promise.all([fetchAreas(), fetchUsers(), fetchConfigs()])
  applyCurrentConfig()
})
</script>

<template>
  <section class="notification-page pa-4">
    <VRow>
      <VCol cols="12" md="8">
        <VCard class="notification-card" elevation="1">
          <VCardTitle class="notification-card__header d-flex justify-space-between align-start flex-wrap ga-3">
            <div>
              <div class="text-h5 font-weight-semibold">Notificaciones por correo</div>
              <div class="text-body-2 text-medium-emphasis mt-1">
                Define quién recibe cada aviso automático del sistema.
              </div>
            </div>
            <VChip color="primary" size="small" variant="tonal" prepend-icon="tabler-mail-check">
              {{ configs.length }} configurados
            </VChip>
          </VCardTitle>

          <VCardText>
            <div class="form-section">
              <div class="section-heading">
                <VIcon icon="tabler-settings" size="20" color="primary" />
                <div>
                  <div class="text-subtitle-1 font-weight-semibold">Evento y estado</div>
                  <div class="text-caption text-medium-emphasis">Selecciona el aviso que deseas configurar.</div>
                </div>
              </div>

              <VRow>
                <VCol cols="12" sm="7">
                  <VSelect
                    v-model="selectedEvent"
                    :items="eventOptions"
                    item-title="title"
                    item-value="value"
                    label="Evento"
                    prepend-inner-icon="tabler-mail"
                    variant="outlined"
                    clearable
                    placeholder="Selecciona un evento"
                    hide-details
                  />
                </VCol>
                <VCol cols="12" sm="5" class="d-flex align-center">
                  <VSwitch v-model="form.active" label="Notificación activa" inset color="primary" hide-details :disabled="!canManageNotifications || !selectedEvent" />
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-5" />

            <div class="form-section">
              <div class="section-heading">
                <VIcon icon="tabler-file-description" size="20" color="primary" />
                <div>
                  <div class="text-subtitle-1 font-weight-semibold">Identificación</div>
                  <div class="text-caption text-medium-emphasis">Información visible de la configuración.</div>
                </div>
              </div>

              <VRow>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.name" label="Nombre" variant="outlined" hide-details="auto" :disabled="!canManageNotifications || !selectedEvent" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VTextField v-model="form.description" label="Descripción" variant="outlined" hide-details="auto" :disabled="!canManageNotifications || !selectedEvent" />
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-5" />

            <div class="form-section">
              <div class="section-heading">
                <VIcon icon="tabler-users" size="20" color="primary" />
                <div>
                  <div class="text-subtitle-1 font-weight-semibold">Destinatarios</div>
                  <div class="text-caption text-medium-emphasis">Los usuarios se filtran según sus permisos granulares.</div>
                </div>
              </div>

              <VRow>
                <VCol cols="12" md="6">
                  <div class="recipient-list">
                    <div class="recipient-list__header">
                      <div>
                        <div class="text-body-2 font-weight-medium">Áreas destinatarias</div>
                        <div class="text-caption text-medium-emphasis">Selecciona una o varias áreas.</div>
                      </div>
                      <VChip size="x-small" color="primary" variant="tonal">
                        {{ form.areas.length }} seleccionadas
                      </VChip>
                    </div>
                    <VDivider />
                    <div class="recipient-list__body">
                      <VCheckbox
                        v-for="area in areas"
                        :key="area"
                        :model-value="form.areas.includes(area)"
                        :label="area"
                        density="compact"
                        hide-details
                        :disabled="!canManageNotifications || !selectedEvent"
                        @update:model-value="checked => toggleSelection('areas', area, checked)"
                      />
                      <div v-if="!areas.length" class="text-caption text-medium-emphasis pa-3">
                        No hay áreas disponibles.
                      </div>
                    </div>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="recipient-list">
                    <div class="recipient-list__header">
                      <div>
                        <div class="text-body-2 font-weight-medium">Usuarios específicos</div>
                        <div class="text-caption text-medium-emphasis">Selecciona destinatarios individuales.</div>
                      </div>
                      <VChip size="x-small" color="primary" variant="tonal">
                        {{ form.users.length }} seleccionados
                      </VChip>
                    </div>
                    <VAlert v-if="inactiveSelectedUsers.length" type="warning" variant="tonal" density="compact" class="ma-3 mb-1">
                      Hay {{ inactiveSelectedUsers.length }} usuario(s) seleccionado(s) que ahora están inactivos. Desmárcalos para retirarlos de esta configuración.
                    </VAlert>
                    <VDivider />
                    <div class="recipient-list__body">
                      <div v-for="user in userOptions" :key="user.value" class="recipient-user">
                        <VCheckbox
                          :model-value="form.users.includes(user.value)"
                          density="compact"
                          hide-details
                          :disabled="!canManageNotifications || !selectedEvent || (!user.active && !form.users.includes(user.value))"
                          @update:model-value="checked => toggleSelection('users', user.value, checked)"
                        />
                        <div class="recipient-user__text">
                          <div class="text-body-2 d-flex align-center ga-2">
                            <span>{{ user.title }}</span>
                            <VChip v-if="!user.active" size="x-small" color="warning" variant="tonal">Inactivo</VChip>
                          </div>
                          <div class="text-caption text-medium-emphasis">{{ user.subtitle }}</div>
                        </div>
                      </div>
                      <div v-if="!userOptions.length" class="text-caption text-medium-emphasis pa-3">
                        No hay usuarios disponibles.
                      </div>
                    </div>
                  </div>
                </VCol>
              </VRow>
            </div>

            <VDivider class="my-5" />

            <div class="form-section">
              <div class="section-heading">
                <VIcon icon="tabler-copy" size="20" color="primary" />
                <div>
                  <div class="text-subtitle-1 font-weight-semibold">Copias del correo</div>
                  <div class="text-caption text-medium-emphasis">Añade direcciones adicionales usando Enter.</div>
                </div>
              </div>

              <VRow>
                <VCol cols="12" sm="6">
                  <VAutocomplete v-model="form.cc" :items="[]" label="CC" prepend-inner-icon="tabler-mail-forward" chips closable-chips multiple clearable variant="outlined" hide-details="auto" :disabled="!canManageNotifications || !selectedEvent" />
                </VCol>
                <VCol cols="12" sm="6">
                  <VAutocomplete v-model="form.bcc" :items="[]" label="BCC" prepend-inner-icon="tabler-mail-share" chips closable-chips multiple clearable variant="outlined" hide-details="auto" :disabled="!canManageNotifications || !selectedEvent" />
                </VCol>
              </VRow>
            </div>

            <div class="d-flex justify-end mt-6">
              <VBtn color="primary" prepend-icon="tabler-device-floppy" :loading="saving" :disabled="!canManageNotifications || !selectedEvent" @click="submit">
                Guardar configuración
              </VBtn>
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <VCol cols="12" md="4">
        <VCard class="status-card" elevation="1">
          <VCardTitle class="d-flex align-center ga-2">
            <VIcon icon="tabler-activity" color="primary" />
            <span>Estado del sistema</span>
          </VCardTitle>
          <VCardText>
            <div v-if="loading" class="text-medium-emphasis">Cargando...</div>
            <div v-else>
              <div v-for="item in eventOptions" :key="item.value" class="status-row">
                <div class="status-row__name">
                  <span class="status-dot" :class="statusColor(item.value)" />
                  <span>{{ item.title }}</span>
                </div>
                <div class="d-flex align-center ga-2">
                  <VChip
                    :color="statusColor(item.value)"
                    size="small"
                    variant="tonal"
                    :text="statusLabel(item.value)"
                  />
                  <VBtn
                    size="small"
                    variant="text"
                    color="primary"
                    :disabled="!canManageNotifications"
                    @click="toggleActive(item.value)"
                  >
                    {{ statusLabel(item.value) === 'Activo' ? 'Desactivar' : 'Activar' }}
                  </VBtn>
                </div>
              </div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <VDialog v-model="successDialog" max-width="420">
      <VCard class="success-dialog text-center pa-6">
        <VAvatar color="success" variant="tonal" size="64" class="mx-auto mb-4">
          <VIcon icon="tabler-check" size="34" />
        </VAvatar>
        <VCardTitle class="text-h6 pa-0">Guardado exitoso</VCardTitle>
        <VCardText class="text-body-1 text-medium-emphasis px-0 pb-5">
          {{ successMessage }}
        </VCardText>
        <VBtn color="success" block @click="successDialog = false">
          Entendido
        </VBtn>
      </VCard>
    </VDialog>
  </section>
</template>

<style scoped>
.notification-page {
  max-width: 1440px;
  margin-inline: auto;
}

.notification-card,
.status-card {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.notification-card__header {
  padding-block: 24px 20px;
}

.form-section {
  padding-inline: 4px;
}

.section-heading {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  margin-bottom: 18px;
}

.recipient-list {
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
}

.recipient-list__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  background: rgba(var(--v-theme-on-surface), 0.025);
}

.recipient-list__body {
  padding: 8px 12px;
}

.recipient-list__body :deep(.v-selection-control) {
  min-height: 36px;
}

.recipient-user {
  display: flex;
  align-items: center;
  gap: 4px;
  min-height: 52px;
}

.recipient-user__text {
  min-width: 0;
  overflow: hidden;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
}

.recipient-user__text .text-body-2 {
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity)) !important;
}

.recipient-user__text .text-caption {
  color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity)) !important;
}

.recipient-user__text > div {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.status-card {
  position: sticky;
  inset-block-start: 24px;
}

.status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-block: 14px;
  border-bottom: 1px solid rgba(var(--v-border-color), 0.7);
}

.status-row:last-child {
  border-bottom: 0;
  padding-bottom: 4px;
}

.status-row__name {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  color: rgba(var(--v-theme-on-surface), var(--v-high-emphasis-opacity));
  font-size: 0.9375rem;
}

.status-dot {
  width: 8px;
  height: 8px;
  flex: 0 0 8px;
  border-radius: 50%;
  background: rgb(var(--v-theme-secondary));
}

.status-dot.success {
  background: rgb(var(--v-theme-success));
}

@media (max-width: 959px) {
  .status-card {
    position: static;
  }
}
</style>
