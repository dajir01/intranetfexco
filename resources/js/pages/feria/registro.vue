<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Registrar Feria',
  },
})

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
  id_modelocontrato: null,
  id_modeloademda: null,
})

const loading = ref(false)
const error = ref(null)
const success = ref(null)
const modelosContrato = ref([])
const modelosAdenda = ref([])
const loadingModelos = ref(false)

const tipoCredencialOptions = [
  { value: 'Expositor', title: 'Expositor' },
  { value: 'Prensa', title: 'Prensa' },
  { value: 'Oficial', title: 'Oficial' },
  { value: 'Servicios', title: 'Servicios' },
  { value: 'Negocios', title: 'Negocios' },
]

const getCsrfToken = () => {
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

const validateForm = () => {
  const issues = []
  if (!form.value.id_modelocontrato)
    issues.push('El modelo de contrato es obligatorio.')
  if (!form.value.id_modeloademda)
    issues.push('El modelo de adenda es obligatorio.')

  if (!form.value.nombre_feria?.trim()) issues.push('El nombre de la feria es obligatorio.')
  if (!form.value.fecha_inicio) issues.push('La fecha de inicio es obligatoria.')
  if (!form.value.fecha_fin) issues.push('La fecha de fin es obligatoria.')
  if (!form.value.puertas_acceso?.trim()) issues.push('La puerta de acceso es obligatoria.')
  if (!form.value.codigo_contrato?.trim()) issues.push('El código de contrato es obligatorio.')
  if (!form.value.codigo_factura?.trim()) issues.push('El código de factura es obligatorio.')

  if (form.value.fecha_inicio && form.value.fecha_fin) {
    const start = new Date(form.value.fecha_inicio)
    const end = new Date(form.value.fecha_fin)
    if (end < start) issues.push('La fecha de fin no puede ser menor a la fecha de inicio.')
  }

  if (!form.value.informacion?.trim()) {
    issues.push('La información es obligatoria.')
  } else if (form.value.informacion.trim().length > 100) {
    issues.push('La información no puede exceder 100 caracteres.')
  }

  if (form.value.tipo_credenciales && !Array.isArray(form.value.tipo_credenciales)) {
    issues.push('Los tipos de credenciales deben ser un listado.')
  }

  if (Array.isArray(form.value.tipo_credenciales)) {
    const allowed = tipoCredencialOptions.map(o => o.value)
    const invalid = form.value.tipo_credenciales.filter(val => !allowed.includes(val))
    if (invalid.length)
      issues.push('Algún tipo de credencial no es válido.')
  }

  const credInicio = form.value.cred_inicio
  if (credInicio === '' || credInicio === null || credInicio === undefined) {
    issues.push('El código inicial es obligatorio.')
  } else if (isNaN(Number(credInicio)) || Number(credInicio) <= 0) {
    issues.push('El código inicial debe ser un número mayor o igual a 1.')
  }

  return issues
}

const loadModelos = async () => {
  loadingModelos.value = true
  try {
    const [resContrato, resAdenda] = await Promise.all([
      fetch('/modelos-contrato?tipo=1', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      }),
      fetch('/modelos-contrato?tipo=2', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      }),
    ])

    const jsonContrato = await resContrato.json()
    const jsonAdenda = await resAdenda.json()

    if (!resContrato.ok || !jsonContrato.success) throw new Error(jsonContrato.message || `HTTP ${resContrato.status}`)
    if (!resAdenda.ok || !jsonAdenda.success) throw new Error(jsonAdenda.message || `HTTP ${resAdenda.status}`)

    modelosContrato.value = jsonContrato.data || []
    modelosAdenda.value = jsonAdenda.data || []
  } catch (err) {
    console.error('Error cargando modelos:', err)
  } finally {
    loadingModelos.value = false
  }
}

const handleSubmit = async () => {
  error.value = null
  success.value = null

  const issues = validateForm()
  if (issues.length) {
    error.value = issues.join(' ')
    return
  }

  loading.value = true
  try {
    const res = await fetch('/ferias', {
      method: 'POST',
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
        inicio: form.value.cred_inicio?.trim() || null,
        informacion: form.value.informacion?.trim() || null,
        tipo_cred: form.value.tipo_credenciales,
        id_modelocontrato: form.value.id_modelocontrato,
        id_modeloademda: form.value.id_modeloademda,
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

    success.value = json.message || 'Feria registrada correctamente.'
    setTimeout(() => {
      router.push('/feria/list')
    }, 1000)
  } catch (err) {
    error.value = err.message || 'No se pudo registrar la feria.'
    console.error('Error registrando feria:', err)
  } finally {
    loading.value = false
  }
}

const handleCancel = () => {
  router.push('/feria/list')
}

onMounted(() => {
  loadModelos()
})
</script>

<template>
  <section>
    <VCard class="pa-6 pa-sm-10">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VIcon icon="tabler-calendar-plus" size="28" />
          <span class="text-h5">Registrar Feria</span>
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
                :disabled="loading"
                :rules="[v => !!v?.trim() || 'El nombre de la feria es obligatorio.']"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.fecha_inicio"
                label="Fecha de inicio"
                type="date"
                required
                :disabled="loading"
                :rules="[v => !!v || 'La fecha de inicio es obligatoria.']"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VTextField
                v-model="form.fecha_fin"
                label="Fecha de fin"
                type="date"
                required
                :disabled="loading"
                :rules="[
                  v => !!v || 'La fecha de fin es obligatoria.',
                  v => !v || !form.fecha_inicio || new Date(v) >= new Date(form.fecha_inicio) || 'La fecha de fin no puede ser antes que la fecha de inicio.',
                ]"
              />
            </VCol>
          </VRow>

          <VRow class="mb-2">
            <VCol cols="12" md="2">
              <VTextField
                v-model="form.puertas_acceso"
                label="Puerta de acceso"
                placeholder="Ej: Puerta Norte"
                :disabled="loading"
                :rules="[v => !!v?.trim() || 'La puerta de acceso es obligatoria.']"
              />
            </VCol>

            <VCol cols="12" md="2">
              <VTextField
                v-model="form.codigo_contrato"
                label="Código de contrato"
                placeholder="Ej: FAC-0001"
                :disabled="loading"
                :rules="[v => !!v?.trim() || 'El código de contrato es obligatorio.']"
              />
            </VCol>

            <VCol cols="12" md="2">
              <VTextField
                v-model="form.codigo_factura"
                label="Código de factura"
                placeholder="Ej: FAC-0001"
                :disabled="loading"
                :rules="[v => !!v?.trim() || 'El código de factura es obligatorio.']"
              />
            </VCol>
            <VCol cols="12" md="2">
              <VTextField
                v-model="form.cred_inicio"
                label="Código inicial de contrato"
                placeholder="Ej: 1"
                type="number"
                :min="1"
                :disabled="loading"
                :rules="[
                  v => (v !== '' && v !== null && v !== undefined) || 'El código inicial es obligatorio.',
                  v => (!isNaN(Number(v)) && Number(v) >= 1) || 'Debe ser mayor o igual a 1.',
                ]"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.id_modelocontrato"
                :items="modelosContrato"
                item-title="nombre"
                item-value="id_modelo_contrato"
                label="Modelo de Contrato"
                placeholder="Selecciona modelo"
                required
                :disabled="loading || loadingModelos"
                :loading="loadingModelos"
                :rules="[v => !!v || 'El modelo de contrato es obligatorio.']"
              />
            </VCol>
            <VCol cols="12" md="4">
              <VSelect
                v-model="form.id_modeloademda"
                :items="modelosAdenda"
                item-title="nombre"
                item-value="id_modelo_contrato"
                label="Modelo de Adenda"
                placeholder="Selecciona modelo"
                required
                :disabled="loading || loadingModelos"
                :loading="loadingModelos"
                :rules="[v => !!v || 'El modelo de adenda es obligatorio.']"
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
                  :disabled="loading"
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
                :disabled="loading"
                :rules="[
                  v => !!v?.trim() || 'La información es obligatoria.',
                  v => !v || v.trim().length <= 100 || 'No puede exceder 100 caracteres.',
                ]"
              />
            </VCol>
          </VRow>
          <VDivider class="my-4" />
          <div class="d-flex gap-3 mt-6">
            <VBtn
              color="primary"
              type="submit"
              :loading="loading"
              :disabled="loading || loadingModelos"
              prepend-icon="tabler-device-floppy"
            >
              Guardar
            </VBtn>

            <VBtn
              color="secondary"
              variant="tonal"
              @click="handleCancel"
              :disabled="loading"
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
