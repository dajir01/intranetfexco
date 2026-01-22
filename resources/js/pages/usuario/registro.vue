<script setup>
import { ref, computed, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Nuevo Usuario',
  },
})

const router = useRouter()
const auth = useAuthStore()

if (!auth.can('users.create'))
  router.replace('/')

// Estado
const loading = ref(false)
const error = ref(null)
const successMessage = ref(null)
const showPassword = ref(true) // visible para que el usuario pueda copiar
const isSubmitting = ref(false)
const nickManuallyEdited = ref(false)
const passwordManuallyEdited = ref(false)

// Opciones de área
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

const jefaturaOptions = [
  { value: 1, title: 'Sí - Jefatura' },
  { value: 0, title: 'No - Ejecutivo' },
]

const form = ref({
  nombre_usuario: '',
  nick_usuario: '',
  email: '',
  area: '',
  nivel_usuario: null,
  pass_usuario: '',
  jefatura: null,
})

// Helpers de área
const getAreaFromNivelUsuario = (nivel) => {
  const area = areasOptions.find(a => a.value === Number(nivel))
  return area ? area.text : ''
}

// Generación de nick y contraseña a partir del nombre completo
const generateNick = (fullName) => {
  if (!fullName) return ''
  const parts = fullName.trim().split(/\s+/)
  if (parts.length < 2) return ''
  const first = parts[0]
  const second = parts[1]
  return `${first.charAt(0)}${second}`.toLowerCase()
}

const generatePassword = (fullName) => {
  if (!fullName) return ''
  const parts = fullName.trim().split(/\s+/)
  if (parts.length < 2) return ''
  const first = parts[0]
  const second = parts[1].toLowerCase()
  return `${first.length}${second}${second.length}`
}

// Generar credenciales cuando cambia el nombre si el usuario no editó manualmente
watch(() => form.value.nombre_usuario, (val) => {
  if (!val) {
    if (!nickManuallyEdited.value) form.value.nick_usuario = ''
    if (!passwordManuallyEdited.value) form.value.pass_usuario = ''
    return
  }
  if (!nickManuallyEdited.value) {
    form.value.nick_usuario = generateNick(val)
  }
  if (!passwordManuallyEdited.value) {
    form.value.pass_usuario = generatePassword(val)
  }
})

// Si cambia el área seleccionada, reflejar el texto
watch(() => form.value.nivel_usuario, (nivel) => {
  form.value.area = getAreaFromNivelUsuario(nivel)
})

const emailIsValid = computed(() => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email || ''))

const validateForm = () => {
  const errors = []
  const nameParts = (form.value.nombre_usuario || '').trim().split(/\s+/)
  if (nameParts.length < 2) errors.push('Ingrese al menos nombre y apellido')
  if (!form.value.nick_usuario) errors.push('El usuario (nick) es obligatorio')
  if (!form.value.pass_usuario) errors.push('La contraseña es obligatoria')
  if (!form.value.email || !emailIsValid.value) errors.push('Email inválido')
  if (form.value.nivel_usuario === null || form.value.nivel_usuario === undefined) errors.push('Seleccione un área')
  if (form.value.jefatura === null || form.value.jefatura === undefined) errors.push('Seleccione rol de jefatura')
  return errors
}

const getCsrfToken = () => {
  const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
  if (!token) console.warn('CSRF token no encontrado')
  return token || ''
}

const handleSubmit = async () => {
  if (!auth.can('users.create')) {
    router.replace('/')
    return
  }

  error.value = null
  successMessage.value = null

  const errs = validateForm()
  if (errs.length) {
    error.value = errs.join(', ')
    return
  }

  isSubmitting.value = true
  try {
    const payload = {
      nombre_usuario: form.value.nombre_usuario.trim(),
      nick_usuario: form.value.nick_usuario.trim(),
      email: form.value.email.trim(),
      area: form.value.area,
      nivel_usuario: form.value.nivel_usuario,
      jefatura: form.value.jefatura,
      pass_usuario: form.value.pass_usuario,
    }

    const res = await fetch('/usuarios', {
      method: 'POST',
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
      if (res.status === 422 && data.errors) {
        const messages = Object.values(data.errors).flat().join(', ')
        throw new Error(messages || 'Error de validación')
      }
      throw new Error(data.message || `Error HTTP ${res.status}`)
    }

    successMessage.value = data.message || 'Usuario creado correctamente'

    // Redirigir al detalle
    if (data.data?.id_usuario) {
      setTimeout(() => {
        router.push(`/usuario/${data.data.id_usuario}`)
      }, 1200)
    }
  } catch (err) {
    console.error('Error creando usuario:', err)
    error.value = err.message
  } finally {
    isSubmitting.value = false
  }
}

const markNickEdited = () => { nickManuallyEdited.value = true }
const markPassEdited = () => { passwordManuallyEdited.value = true }

const goBack = () => router.push('/usuario/list')

const resetForm = () => {
  form.value = {
    nombre_usuario: '',
    nick_usuario: '',
    email: '',
    area: '',
    nivel_usuario: null,
    pass_usuario: '',
    jefatura: null,
  }
  nickManuallyEdited.value = false
  passwordManuallyEdited.value = false
  error.value = null
  successMessage.value = null
}
</script>

<template>
  <section>
    <VCard id="usuario-registro" class="usuario-registro">
      <VCardTitle class="d-flex align-center gap-3">
        <VBtn
          icon="tabler-arrow-left"
          size="small"
          variant="tonal"
          color="primary"
          @click="goBack"
        />
        <div>
          <div class="text-h6">Nuevo Usuario</div>
          <div class="text-caption text-medium-emphasis">Complete los datos para registrar un nuevo usuario</div>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="error = null"
        >
          {{ error }}
        </VAlert>

        <VAlert
          v-if="successMessage"
          type="success"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="successMessage = null"
        >
          {{ successMessage }}
        </VAlert>

        <VForm class="mt-2" @submit.prevent="handleSubmit">
          <!-- Nombre y Email -->
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.nombre_usuario"
                label="Nombre completo *"
                placeholder="Ej: Dajir Moreno Mendez"
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
                :error="form.email && !emailIsValid"
                outlined
                dense
              />
            </VCol>
          </VRow>

          <!-- Usuario y Contraseña -->
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.nick_usuario"
                label="Usuario (nick) *"
                placeholder="Generado automáticamente"
                :disabled="isSubmitting"
                outlined
                dense
                @input="markNickEdited"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.pass_usuario"
                label="Contraseña generada *"
                :type="showPassword ? 'text' : 'password'"
                placeholder="Se genera con el nombre"
                :disabled="isSubmitting"
                outlined
                dense
                @input="markPassEdited"
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

          <!-- Area y Rol de jefatura -->
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

          <VRow class="mt-4">
            <VCol cols="12">
              <VAlert type="info" variant="tonal" icon="tabler-info-circle" class="mb-0">
                <div class="text-body-2">
                  <strong>Generación automática:</strong> El usuario y la contraseña se recalculan al cambiar el nombre. Puede editar manualmente si es necesario.
                  <br>
                  <strong>Contraseña:</strong> No se almacena en texto plano en backend; se enviará hasheada.
                </div>
              </VAlert>
            </VCol>
          </VRow>

          <VRow class="mt-6">
            <VCol cols="12" class="d-flex gap-3">
              <VBtn
                type="submit"
                color="primary"
                :loading="isSubmitting"
                :disabled="isSubmitting"
                prepend-icon="tabler-device-floppy"
              >
                Crear usuario
              </VBtn>

              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="isSubmitting"
                prepend-icon="tabler-x"
                @click="resetForm"
              >
                Limpiar
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </section>
</template>

<style lang="scss" scoped>
.usuario-registro {
  max-width: 960px;
  margin-inline: auto;
}
</style>
