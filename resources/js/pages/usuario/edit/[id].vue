<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Editar Usuario',
  },
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

// Estado del formulario
const usuario = ref(null)
const loading = ref(true)
const error = ref(null)
const isSubmitting = ref(false)
const successMessage = ref(null)
const showPassword = ref(false)

// Opciones de área - value es el nivel_usuario (número), text es el área (texto)
const areasOptions = [
  { value: 1, title: 'Sistemas', text: 'Sistemas' },
  { value: 2, title: 'Comercial', text: 'Comercial' },
  { value: 3, title: 'Administración', text: 'Administración' },
  { value: 4, title: 'Legal', text: 'Legal' },
  { value: 5, title: 'Gerencia', text: 'Gerencia' },
  { value: 6, title: 'Comunicación', text: 'Comunicación' },
  { value: 7, title: 'Auditoría', text: 'Auditoría' },
  { value: 8, title: 'Almacén', text: 'Almacén' },
]

// Opciones de jefatura
const jefaturaOptions = [
  { value: 1, title: 'Sí - Jefatura' },
  { value: 0, title: 'No - Ejecutivo' },
]

// Datos del formulario
const form = ref({
  id_usuario: '',
  nombre_usuario: '',
  nick_usuario: '',
  email: '',
  area: '',
  nivel_usuario: null,
  pass_usuario: '',
  jefatura: null,
  estado: null,
})

// Función para obtener etiqueta de estado
const getEstadoLabel = (value) => {
  return value === 1 ? 'Activo' : 'Inactivo'
}

// Función para obtener nivel_usuario desde el texto del área
const getNivelUsuarioFromArea = (areaText) => {
  const area = areasOptions.find(a => a.text === areaText)
  return area ? area.value : null
}

// Función para obtener texto del área desde nivel_usuario
const getAreaFromNivelUsuario = (nivel) => {
  const area = areasOptions.find(a => a.value === Number(nivel))
  return area ? area.text : ''
}

/**
 * Obtiene el token CSRF
 */
const getCsrfToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (!token) {
    console.warn('CSRF token no encontrado')
  }
  return token || ''
}

/**
 * Obtiene los datos del usuario desde la BD
 */
const fetchUsuario = async () => {
  try {
    if (!auth.can('users.update')) {
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
      // Determinar nivel_usuario desde area (texto) o nivel_usuario (número)
      let nivelUsuario = null
      if (usuario.value.nivel_usuario !== null && usuario.value.nivel_usuario !== undefined) {
        nivelUsuario = Number(usuario.value.nivel_usuario)
      } else if (usuario.value.area) {
        // Si no hay nivel_usuario, intentar obtenerlo del área
        nivelUsuario = getNivelUsuarioFromArea(usuario.value.area)
      }
      
      form.value = {
        id_usuario: usuario.value.id_usuario || '',
        nombre_usuario: usuario.value.nombre_usuario || '',
        nick_usuario: usuario.value.nick_usuario || '',
        email: usuario.value.email || '',
        area: usuario.value.area || '',
        nivel_usuario: nivelUsuario,
        pass_usuario: usuario.value.pass_usuario || '',
        jefatura: usuario.value.jefatura !== undefined && usuario.value.jefatura !== null ? Number(usuario.value.jefatura) : 0,
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
 * Cancelar edición y volver al detalle
 */
const cancelEdit = () => {
  router.push(`/usuario/${route.params.id}`)
}

/**
 * Validación del formulario
 */
const validateForm = () => {
  const errors = []

  if (!form.value.nombre_usuario || form.value.nombre_usuario.trim() === '') {
    errors.push('El nombre es obligatorio')
  }

  if (!form.value.email || form.value.email.trim() === '') {
    errors.push('El email es obligatorio')
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    errors.push('El email no es válido')
  }

  if (form.value.nivel_usuario === null || form.value.nivel_usuario === undefined) {
    errors.push('Debe seleccionar un área')
  }

  if (form.value.jefatura === null || form.value.jefatura === undefined) {
    errors.push('Debe seleccionar el rol de jefatura')
  }

  return errors
}

/**
 * Guardar cambios del usuario
 */
const handleSubmit = async () => {
  try {
    // Limpiar mensajes previos
    error.value = null
    successMessage.value = null

    // Validar formulario
    const errors = validateForm()
    if (errors.length > 0) {
      error.value = errors.join(', ')
      return
    }

    isSubmitting.value = true

    const id = route.params.id
    
    // Obtener el área seleccionada
    const areaSeleccionada = areasOptions.find(a => a.value === form.value.nivel_usuario)
    
    const payload = {
      nombre_usuario: form.value.nombre_usuario.trim(),
      email: form.value.email.trim(),
      area: areaSeleccionada ? areaSeleccionada.text : form.value.area,
      nivel_usuario: form.value.nivel_usuario,
      jefatura: form.value.jefatura,
    }

    // Incluir contraseña solo si se modificó
    if (form.value.pass_usuario && form.value.pass_usuario.trim() !== '') {
      payload.pass_usuario = form.value.pass_usuario.trim()
    }

    const res = await fetch(`/usuarios/${id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const data = await res.json()

    if (!res.ok) {
      throw new Error(data.message || `Error HTTP ${res.status}`)
    }

    // Mostrar mensaje de éxito
    successMessage.value = data.message || 'Usuario actualizado correctamente'

    // Actualizar datos locales
    if (data.data) {
      usuario.value = data.data
      form.value.nombre_usuario = data.data.nombre_usuario
      form.value.email = data.data.email
      form.value.area = data.data.area
      form.value.nivel_usuario = data.data.nivel_usuario ? Number(data.data.nivel_usuario) : null
      form.value.jefatura = Number(data.data.jefatura)
    }

    // Redirigir al detalle después de 2 segundos
    setTimeout(() => {
      router.push(`/usuario/${id}`)
    }, 2000)

  } catch (err) {
    error.value = err.message
    console.error('Error actualizando usuario:', err)
  } finally {
    isSubmitting.value = false
  }
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
      <h2 class="text-h5 ma-0">Editar Usuario</h2>
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
        @click:close="error = null"
        icon="tabler-alert-circle"
      >
        <div class="text-body-2">
          <strong>Error:</strong> {{ error }}
        </div>
      </VAlert>

      <!-- Mensaje de éxito -->
      <VAlert
        v-if="successMessage"
        class="mb-0"
        type="success"
        variant="tonal"
        closable
        @click:close="successMessage = null"
        icon="tabler-check"
      >
        <div class="text-body-2">
          <strong>Éxito:</strong> {{ successMessage }}
        </div>
      </VAlert>

      <!-- Contenido del formulario -->
      <template v-if="usuario && !error">
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
                  label="Nombre *"
                  placeholder="Nombre completo del usuario"
                  :disabled="isSubmitting"
                  outlined
                  dense
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.email"
                  label="Email *"
                  type="email"
                  placeholder="correo@ejemplo.com"
                  :disabled="isSubmitting"
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Fila 2: Contraseña (opcional) -->
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.pass_usuario"
                  label="Contraseña"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Dejar en blanco para no cambiar"
                  :disabled="isSubmitting"
                  outlined
                  dense
                  hint="Solo ingrese una contraseña si desea cambiarla"
                  persistent-hint
                >
                  <template #append-inner>
                    <VIcon
                      :icon="showPassword ? 'tabler-eye-off' : 'tabler-eye'"
                      class="cursor-pointer"
                      @click="showPassword = !showPassword"
                    />
                  </template>
                </AppTextField>
              </VCol>
            </VRow>

            <!-- Fila 3: Usuario (nick) y Estado - Solo lectura -->
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.nick_usuario"
                  label="Usuario (Nick)"
                  placeholder="nombre_usuario"
                  readonly
                  outlined
                  dense
                  hint="El usuario no se puede modificar"
                  persistent-hint
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
                  hint="El estado no se puede modificar desde esta vista"
                  persistent-hint
                />
              </VCol>
            </VRow>

            <!-- Fila 3: Área y Rol de jefatura -->
            <VRow>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.nivel_usuario"
                  label="Área *"
                  :items="areasOptions"
                  item-title="title"
                  item-value="value"
                  placeholder="Seleccione un área"
                  :disabled="isSubmitting"
                  outlined
                  dense
                  @update:model-value="(val) => { form.area = getAreaFromNivelUsuario(val) }"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="form.jefatura"
                  label="Rol de Jefatura *"
                  :items="jefaturaOptions"
                  item-title="title"
                  item-value="value"
                  placeholder="Seleccione un rol"
                  :disabled="isSubmitting"
                  outlined
                  dense
                />
              </VCol>
            </VRow>

            <!-- Información adicional -->
            <VRow class="mt-4">
              <VCol cols="12">
                <VAlert
                  type="info"
                  variant="tonal"
                  icon="tabler-info-circle"
                  class="mb-0"
                >
                  <div class="text-body-2">
                    <strong>Campos obligatorios (*):</strong> Nombre, Email, Área y Rol de Jefatura.
                    <br>
                    <strong>Contraseña:</strong> Campo opcional. Solo ingrese una nueva contraseña si desea cambiarla.
                    <br>
                    <strong>Nota:</strong> El usuario (nick) y el estado no pueden modificarse desde esta vista.
                  </div>
                </VAlert>
              </VCol>
            </VRow>

            <!-- Botones de acción -->
            <VRow class="mt-6">
              <VCol cols="12" class="d-flex gap-3">
                <!-- Botón Guardar -->
                <VBtn
                  type="submit"
                  color="primary"
                  :loading="isSubmitting"
                  :disabled="isSubmitting"
                  prepend-icon="tabler-device-floppy"
                >
                  Guardar cambios
                </VBtn>

                <!-- Botón Cancelar -->
                <VBtn
                  variant="tonal"
                  color="secondary"
                  :disabled="isSubmitting"
                  prepend-icon="tabler-x"
                  @click="cancelEdit"
                >
                  Cancelar
                </VBtn>
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
      </template>

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
.usuario-edit {
  max-width: 900px;
}
</style>
