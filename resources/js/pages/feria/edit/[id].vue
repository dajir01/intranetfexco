<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Editar Feria',
  },
})

const route = useRoute()
const router = useRouter()

const form = ref({
  nombre_feria: '',
  fecha_inicio: '',
  fecha_fin: '',
  puertas_acceso: '',
  codigo_contrato: '',
  codigo_factura: '',
  cred_inicio: '',
  informacion: '',
  tipo_credenciales: [],
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)
const saving = ref(false)

const tipoCredencialOptions = [
  { value: 'Expositor', title: 'Expositor' },
  { value: 'Prensa', title: 'Prensa' },
  { value: 'Oficial', title: 'Oficial' },
  { value: 'Servicios', title: 'Servicios' },
  { value: 'Negocios', title: 'Negocios' },
]

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const parseTipos = (val) => {
  if (!val) return []
  if (Array.isArray(val)) return val
  return String(val)
    .split(';')
    .map(s => s.trim())
    .filter(Boolean)
}

const loadFeria = async () => {
  const id = route.params.id
  if (!id) {
    error.value = 'ID de feria no proporcionado'
    return
  }
  loading.value = true
  error.value = null
  try {
    const res = await fetch(`/ferias/${id}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    const json = await res.json()
    if (!res.ok || !json.success) throw new Error(json.message || `HTTP ${res.status}`)

    const feria = json.data?.feria || {}
    form.value = {
      nombre_feria: feria.nombre_feria || '',
      fecha_inicio: feria.fecha_inicio || '',
      fecha_fin: feria.fecha_fin || '',
      puertas_acceso: feria.puertas_acceso || '',
      codigo_contrato: feria.codigo_contrato || '',
      codigo_factura: feria.codigo_factura || '',
      cred_inicio: feria.inicio != null ? String(feria.inicio) : '',
      informacion: feria.info || '',
      tipo_credenciales: parseTipos(json.data?.tipo_cred ?? feria.tipo_credenciales),
    }
  } catch (err) {
    console.error('Error cargando feria:', err)
    error.value = err.message || 'No se pudo cargar la feria'
  } finally {
    loading.value = false
  }
}

const validateForm = () => {
  const issues = []
  if (!form.value.nombre_feria?.trim()) issues.push('El nombre de la feria es obligatorio.')
  if (!form.value.fecha_inicio) issues.push('La fecha de inicio es obligatoria.')
  if (!form.value.fecha_fin) issues.push('La fecha de fin es obligatoria.')
  if (form.value.fecha_inicio && form.value.fecha_fin) {
    const start = new Date(form.value.fecha_inicio)
    const end = new Date(form.value.fecha_fin)
    if (end < start) issues.push('La fecha de fin no puede ser menor a la fecha de inicio.')
  }
  if (form.value.informacion && form.value.informacion.trim().length > 100) {
    issues.push('La información no puede exceder 100 caracteres.')
  }
  if (form.value.tipo_credenciales && !Array.isArray(form.value.tipo_credenciales)) {
    issues.push('Los tipos de credenciales deben ser un listado.')
  }
  if (Array.isArray(form.value.tipo_credenciales)) {
    const allowed = tipoCredencialOptions.map(o => o.value)
    const invalid = form.value.tipo_credenciales.filter(val => !allowed.includes(val))
    if (invalid.length) issues.push('Algún tipo de credencial no es válido.')
  }
  return issues
}

const handleSubmit = async () => {
  error.value = null
  success.value = null
  const issues = validateForm()
  if (issues.length) {
    error.value = issues.join(' ')
    return
  }
  saving.value = true
  try {
    const credInicio = form.value.cred_inicio != null && form.value.cred_inicio !== ''
      ? String(form.value.cred_inicio).trim() || null
      : null

    const res = await fetch(`/ferias/${route.params.id}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        nombre_feria: form.value.nombre_feria.trim(),
        fecha_inicio: form.value.fecha_inicio,
        fecha_fin: form.value.fecha_fin,
        puertas_acceso: form.value.puertas_acceso?.trim() || null,
        codigo_contrato: form.value.codigo_contrato?.trim() || null,
        codigo_factura: form.value.codigo_factura?.trim() || null,
        inicio: credInicio,
        informacion: form.value.informacion?.trim() || null,
        tipo_cred: form.value.tipo_credenciales,
      }),
    })

    const json = await res.json()
    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validación')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Feria actualizada correctamente.'
    setTimeout(() => {
      router.push('/feria/list')
    }, 1000)
  } catch (err) {
    error.value = err.message || 'No se pudo actualizar la feria.'
    console.error('Error actualizando feria:', err)
  } finally {
    saving.value = false
  }
}

const handleCancel = () => {
  router.push('/feria/list')
}

onMounted(() => {
  loadFeria()
})
</script>

<template>
  <section>
    <VCard class="pa-6 pa-sm-10">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-calendar-cog" size="28" />
          <span class="text-h5">Editar Feria</span>
        </div>
      </VCardTitle>

      <VDivider class="mb-4" />

      <VCardText class="pt-0">
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          icon="tabler-alert-circle"
          @click:close="error = null"
        >
          {{ error }}
        </VAlert>

        <VAlert
          v-if="success"
          type="success"
          variant="tonal"
          class="mb-4"
          closable
          icon="tabler-circle-check"
          @click:close="success = null"
        >
          {{ success }}
        </VAlert>

        <VForm @submit.prevent="handleSubmit">
          <VRow class="mb-2">
            <VCol cols="12" md="6">
              <VTextField
                v-model="form.nombre_feria"
                label="Nombre de la feria"
                placeholder="Ej: Feria Internacional"
                required
                :disabled="loading || saving"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.fecha_inicio"
                label="Fecha de inicio"
                type="date"
                required
                :disabled="loading || saving"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.fecha_fin"
                label="Fecha de fin"
                type="date"
                required
                :disabled="loading || saving"
              />
            </VCol>
          </VRow>

          <VRow class="mb-2">
            <VCol cols="12" md="3">
              <VTextField
                v-model="form.puertas_acceso"
                label="Puerta de acceso"
                placeholder="Ej: Puerta Norte"
                :disabled="loading || saving"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.codigo_contrato"
                label="Código de contrato"
                placeholder="Ej: FAC-0001"
                :disabled="loading || saving"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.codigo_factura"
                label="Código de factura"
                placeholder="Ej: FAC-0001"
                :disabled="loading || saving"
              />
            </VCol>
            <VCol cols="12" md="3">
              <VTextField
                v-model="form.cred_inicio"
                label="Código inicial de contrato"
                placeholder="Ej: 1"
                :disabled="loading || saving"
              />
            </VCol>
          </VRow>

          <VRow>
            <VCol cols="12">
              <div class="d-flex align-center gap-2 mb-2">
                <VIcon icon="tabler-id" size="22" />
                <span class="text-subtitle-1 font-weight-medium">Tipos de Credenciales</span>
              </div>
              <div class="d-flex flex-wrap gap-4">
                <VCheckbox
                  v-for="opt in tipoCredencialOptions"
                  :key="opt.value"
                  v-model="form.tipo_credenciales"
                  :label="opt.title"
                  :value="opt.value"
                  :disabled="loading || saving"
                  color="primary"
                  hide-details
                  density="comfortable"
                />
              </div>
            </VCol>
          </VRow>

          <VRow class="mb-4">
            <VCol cols="12">
              <VTextarea
                v-model="form.informacion"
                label="Información"
                placeholder="Describe la feria (máximo 100 caracteres)"
                :counter="100"
                auto-grow
                rows="3"
                :disabled="loading || saving"
              />
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <div class="d-flex gap-3 mt-6">
            <VBtn
              color="primary"
              type="submit"
              :loading="saving"
              :disabled="saving"
              prepend-icon="tabler-device-floppy"
            >
              Guardar cambios
            </VBtn>

            <VBtn
              color="secondary"
              variant="tonal"
              @click="handleCancel"
              :disabled="saving"
              prepend-icon="tabler-arrow-left"
            >
              Cancelar
            </VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>
  </section>
</template>
