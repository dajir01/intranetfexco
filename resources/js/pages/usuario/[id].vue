<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// Estado del formulario
const usuario = ref(null)
const loading = ref(true)
const error = ref(null)
const notificationDialog = ref(false)
const notificationAssignments = ref([])
const isSubmitting = ref(false)

// Toggle estado loading
const isToggling = ref(false)

// Datos del formulario (preparado para edición futura)
const form = ref({
  id_usuario: '',
  nombre_usuario: '',
  nick_usuario: '',
  email: '',
  area: '',
  pass_usuario: '',
  jefatura: null,
  estado: null,
})

// Función para obtener etiqueta de jefatura
const getJefaturaLabel = (value) => {
  return value === 1 ? 'Sí - Jefatura' : 'No - Ejecutivo'
}

// Función para obtener etiqueta de estado
const getEstadoLabel = (value) => {
  return value === 1 ? 'Activo' : 'Inactivo'
}

// Obtener token CSRF
const getCsrfToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  return token || ''
}

/**
 * Obtiene los datos del usuario desde la BD
 */
const fetchUsuario = async () => {
  try {
    if (!auth.can('users.view')) {
      router.replace('/')
      return
    }

    loading.value = true
    error.value = null

    const id = route.params.id
    if (!id) {
      throw new Error('ID de usuario no proporcionado')
    }

    const res = await fetch(`/usuarios/${id}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok) {
      if (res.status === 404) {
        throw new Error('Usuario no encontrado')
      }
      throw new Error(`Error HTTP ${res.status}`)
    }

    const data = await res.json()
    usuario.value = data.data

    // Cargar datos en el formulario
    if (usuario.value) {
      form.value = {
        id_usuario: usuario.value.id_usuario || '',
        nombre_usuario: usuario.value.nombre_usuario || '',
        nick_usuario: usuario.value.nick_usuario || '',
        email: usuario.value.email || '',
        area: usuario.value.area || '',
        pass_usuario: '',
        jefatura: usuario.value.jefatura !== undefined ? usuario.value.jefatura : null,
        estado: usuario.value.estado !== undefined ? usuario.value.estado : null,
      }
    }
  } catch (err) {
    error.value = err.message
    console.error('Error cargando usuario:', err)
  } finally {
    loading.value = false
  }
}

/**
 * Navegar de vuelta al listado
 */
const goBack = () => {
  router.push('/usuario/list')
}

/**
 * Navegar a la vista de edición
 */
const goToEdit = () => {
  router.push(`/usuario/edit/${route.params.id}`)
}

const goToPermissions = () => {
  router.push(`/usuario/permisos/${route.params.id}`)
}

/**
 * Cambiar estado del usuario
 */
const toggleEstado = async () => {
  if (!usuario.value) return
  const nuevoEstado = form.value.estado === 1 ? 0 : 1
  try {
    isToggling.value = true
    error.value = null

    const res = await fetch(`/usuarios/${route.params.id}/estado`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({ estado: nuevoEstado }),
    })

    const data = await res.json()
    if (!res.ok) {
      if (res.status === 409 && data.error === 'notification_assignments') {
        notificationAssignments.value = data.data?.configurations || []
        notificationDialog.value = true
        return
      }
      throw new Error(data.message || `Error HTTP ${res.status}`)
    }

    // Actualizar estado local
    form.value.estado = data.data.estado
    usuario.value.estado = data.data.estado
  } catch (err) {
    console.error('Error cambiando estado:', err)
    error.value = err.message || 'No se pudo cambiar el estado'
  } finally {
    isToggling.value = false
  }
}

/**
 * Manejo del envío del formulario (preparado para edición)
 */
const handleSubmit = async () => {
  // Preparado para implementar edición en el futuro
  // isSubmitting.value = true
  // try {
  //   // Lógica de guardado aquí
  // } catch (err) {
  //   // Manejo de errores
  // } finally {
  //   isSubmitting.value = false
  // }
}

// Cargar datos al montar el componente
onMounted(() => {
  fetchUsuario()
})
</script>

<template>
  <div>
    <!-- Encabezado con botón de retorno -->
    <div class="d-flex align-center gap-3 mb-6">
      <VBtn
        icon="tabler-arrow-left"
        size="large"
        variant="tonal"
        color="primary"
        @click="goBack"
      />
      <h2 class="text-h5 ma-0">Detalle de Usuario</h2>
    </div>

    <!-- Contenedor principal -->
    <VCard v-if="!loading" class="pa-6 pa-sm-12">
      <!-- Mensaje de error -->
      <VAlert
        v-if="error"
        class="mb-0"
        type="error"
        variant="tonal"
        closable
        dismissible
        icon="tabler-alert-circle"
      >
        <div class="text-body2">
          <strong>Error:</strong> {{ error }}
        </div>
      </VAlert>

      <!-- Contenido del formulario -->
      <template v-else-if="usuario">
        <!-- Encabezado -->
        <VCardTitle class="d-flex align-center gap-3">
          <VAvatar
            :color="`${form.estado === 1 ? 'success' : 'error'}`"
            variant="tonal"
          >
            <VIcon
              :icon="`${form.estado === 1 ? 'tabler-user-check' : 'tabler-user-x'}`"
            />
          </VAvatar>
          <div>
            <div class="text-h6">{{ form.nombre_usuario }}</div>
            <div class="text-caption text-medium-emphasis">
              @{{ form.nick_usuario }}
            </div>
          </div>
        </VCardTitle>

        <VDivider class="my-0" />

        <!-- Formulario -->
        <VCardText>
          <VForm class="mt-6" @submit.prevent="handleSubmit">
            <!-- Fila 1: Nombre y Email -->
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.nombre_usuario"
                  label="Nombre"
                  placeholder="Nombre completo del usuario"
                  readonly
                  outlined
                  dense
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.email"
                  label="Email"
                  type="email"
                  placeholder="correo@ejemplo.com"
                  readonly
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Fila 2: Usuario (nick) y Área -->
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.nick_usuario"
                  label="Usuario (Nick)"
                  placeholder="nombre_usuario"
                  readonly
                  outlined
                  dense
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.area"
                  label="Área"
                  placeholder="Área de trabajo"
                  readonly
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Fila 3: Contraseña -->
            <VRow>
              <VCol cols="12">
                <AppTextField
                  model-value="No disponible por seguridad"
                  label="Contraseña"
                  readonly
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Fila 4: Jefatura y Estado -->
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getJefaturaLabel(form.jefatura)"
                  label="Rol en el Área"
                  placeholder="Rol en el área"
                  readonly
                  outlined
                  dense
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getEstadoLabel(form.estado)"
                  label="Estado"
                  placeholder="Estado"
                  readonly
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Botones de acción (preparado para futuras acciones) -->
            <VRow class="mt-6">
              <VCol cols="12" class="d-flex gap-3">
                <!-- Botón Editar -->
                <VBtn
                  v-if="auth.can('users.update')"
                  variant="tonal"
                  color="primary"
                  prepend-icon="tabler-edit"
                  @click="goToEdit"
                >
                  Editar usuario
                </VBtn>

                <VBtn
                  v-if="auth.can('usuarios.gestionar_permisos')"
                  variant="tonal"
                  color="info"
                  prepend-icon="tabler-lock-cog"
                  @click="goToPermissions"
                >
                  Gestionar permisos
                </VBtn>

                <!-- Botón Cambiar Estado -->
                <VBtn
                  v-if="auth.can('users.update')"
                  variant="tonal"
                  :loading="isToggling"
                  :disabled="isToggling"
                  :color="form.estado === 1 ? 'error' : 'success'"
                  :prepend-icon="form.estado === 1 ? 'tabler-user-x' : 'tabler-user-check'"
                  @click="toggleEstado"
                >
                  {{ form.estado === 1 ? 'Desactivar' : 'Activar' }}
                </VBtn>
              </VCol>
            </VRow>

            <!-- Nota informativa -->
            <VRow class="mt-4">
              <VCol cols="12">
                <VAlert
                  type="info"
                  variant="tonal"
                  icon="tabler-info-circle"
                  class="mb-0"
                >
                  <div class="text-body-2">
                    Está en modo de solo lectura. Para modificar los datos del usuario, haga clic en el botón "Editar usuario".
                  </div>
                </VAlert>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </template>

      <VDialog v-model="notificationDialog" max-width="520">
        <VCard>
          <VCardTitle class="d-flex align-center ga-3">
            <VAvatar color="warning" variant="tonal" size="42">
              <VIcon icon="tabler-mail-cog" />
            </VAvatar>
            <span>Asignación de notificación</span>
          </VCardTitle>
          <VCardText>
            <p class="mb-3">
              Este usuario está seleccionado como destinatario en las siguientes configuraciones de correo:
            </p>
            <VList density="compact" class="mb-3">
              <VListItem v-for="assignment in notificationAssignments" :key="assignment.event_key">
                <template #prepend>
                  <VIcon icon="tabler-mail" color="primary" />
                </template>
                <VListItemTitle>{{ assignment.name }}</VListItemTitle>
                <VListItemSubtitle>{{ assignment.event_key }}</VListItemSubtitle>
              </VListItem>
            </VList>
            <VAlert type="warning" variant="tonal">
              Ingresa a Configuración &gt; Notificaciones por correo y desmarca este usuario en “Usuarios específicos” antes de desactivarlo.
            </VAlert>
          </VCardText>
          <VCardActions class="justify-end">
            <VBtn color="primary" @click="notificationDialog = false">Entendido</VBtn>
            <VBtn variant="tonal" color="primary" @click="router.push('/configuracion/notificaciones')">
              Ir a notificaciones
            </VBtn>
          </VCardActions>
        </VCard>
      </VDialog>

      <!-- Estado de carga -->
      <VCardText v-if="loading" class="d-flex align-center justify-center py-12">
        <VProgressCircular
          indeterminate
          color="primary"
          size="48"
        />
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
.usuario-detail {
  max-width: 900px;
}
</style>
