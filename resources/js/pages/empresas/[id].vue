<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { openPdfWithError } from '@/utils/openPdfWithError'

definePage({
  meta: { requiresAuth: true },
})

const route  = useRoute()
const router = useRouter()
const auth   = useAuthStore()

const id = computed(() => route.params.id)
const goToEmpresasList = () => router.push({ name: 'empresas-list' })

// ── Estado empresa ─────────────────────────────────────────────────────────────
const empresa  = ref(null)
const loading  = ref(false)
const error    = ref(null)

// ── Estado modal: Ferias participantes ────────────────────────────────────────
const dialogFerias       = ref(false)
const feriasList         = ref([])
const loadingFerias      = ref(false)
const errorFerias        = ref(null)

const headersFerias = [
  { title: 'Feria / Evento',    key: 'feria',           sortable: false },
  { title: 'N° Contrato',       key: 'codigo_contrato', sortable: false },
  { title: 'Stands',            key: 'stands',          sortable: false },
  { title: 'Precio Total',      key: 'precio_total',    sortable: false },
  { title: 'Pago',              key: 'pago',            sortable: false },
  { title: 'Fecha',             key: 'fecha_contrato',  sortable: false },
  { title: 'Habilitado',        key: 'habilitado',      sortable: false },
]

// ── Estado modal: Historial de cambios ────────────────────────────────────────
const dialogHistorial    = ref(false)
const historialList      = ref([])
const loadingHistorial   = ref(false)
const errorHistorial     = ref(null)
const historialExpandido = ref([])

// ── Helpers ───────────────────────────────────────────────────────────────────
const formatDate = (val) => {
  if (!val) return '—'
  return new Date(val).toLocaleDateString('es-BO', {
    day: '2-digit', month: '2-digit', year: 'numeric',
  })
}

const formatDateTime = (val) => {
  if (!val) return '—'
  return new Date(val).toLocaleString('es-BO', {
    day: '2-digit', month: '2-digit', year: 'numeric',
    hour: '2-digit', minute: '2-digit',
  })
}

const formatMoney = (val) => {
  if (val === null || val === undefined || val === '') return '—'
  return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB' }).format(val)
}

const pagoLabel = (val) => {
  const m = { 0: 'Pendiente', 1: 'Pagado', 2: 'Parcial' }
  return m[val] ?? String(val ?? '—')
}

const abrirContratoPdf = async (idContrato) => {
  if (!idContrato || !auth.can('contratos.print'))
    return

  await openPdfWithError(`/contratos/${idContrato}/imprimir`)
}

const getNumeroContrato = (contratoItem) => {
  if (contratoItem?.contrato?.numero)
    return contratoItem.contrato.numero

  const codigo = Number(contratoItem?.codigo_contrato ?? 0)
  return Number.isFinite(codigo) && codigo > 0 ? String(codigo) : 'Sin código'
}

// ── Carga empresa ──────────────────────────────────────────────────────────────
const cargarEmpresa = async () => {
  if (!auth.can('empresas.detalle')) {
    if (auth.can('empresas.ver'))
      router.replace({ name: 'empresas-list' })
    else
      router.replace('/')
    return
  }
  loading.value = true
  error.value   = null
  try {
    const res  = await fetch(`/api/empresas/${id.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (res.status === 404) throw new Error('Empresa no encontrada')
    if (!res.ok)            throw new Error(`HTTP ${res.status}`)
    const json  = await res.json()
    empresa.value = json.data
  } catch (err) {
    error.value = err.message || 'Error al cargar la empresa'
  } finally {
    loading.value = false
  }
}

// ── Abrir modal ferias ────────────────────────────────────────────────────────
const abrirFerias = async () => {
  dialogFerias.value   = true
  loadingFerias.value  = true
  errorFerias.value    = null
  feriasList.value     = []
  try {
    const res  = await fetch(`/api/empresas/${id.value}/ferias`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const json = await res.json()
    feriasList.value = json.data ?? []
  } catch (err) {
    errorFerias.value = 'Error al cargar participación en ferias'
  } finally {
    loadingFerias.value = false
  }
}

// ── Abrir modal historial ─────────────────────────────────────────────────────
const abrirHistorial = async () => {
  dialogHistorial.value   = true
  loadingHistorial.value  = true
  errorHistorial.value    = null
  historialList.value     = []
  historialExpandido.value = []
  try {
    const res  = await fetch(`/api/empresas/${id.value}/historial`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const json = await res.json()
    historialList.value = json.data ?? []
  } catch (err) {
    errorHistorial.value = 'Error al cargar historial de cambios'
  } finally {
    loadingHistorial.value = false
  }
}

onMounted(cargarEmpresa)
</script>

<template>
  <section>
    <!-- Botón volver -->
    <div class="d-flex align-center gap-3 mb-4">
      <VBtn
        variant="text"
        prepend-icon="tabler-arrow-left"
        @click="goToEmpresasList"
      >
        Volver al listado
      </VBtn>
    </div>

    <!-- Loading / Error global -->
    <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-4" />

    <VAlert v-if="error" type="error" class="mb-4">
      {{ error }}
    </VAlert>

    <template v-if="empresa">
      <!-- Encabezado de empresa -->
      <VCard class="mb-4">
        <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
          <div>
            <h4 class="text-h4 font-weight-bold">
              {{ empresa.nombre_empresa }}
            </h4>
            <p class="text-medium-emphasis mb-0">
              NIT: <strong>{{ empresa.nit || '—' }}</strong>
              &nbsp;·&nbsp;
              <VChip
                :color="empresa.es_activo ? 'success' : 'default'"
                size="small"
                label
              >
                {{ empresa.es_activo ? 'Activo' : 'Inactivo' }}
              </VChip>
            </p>
          </div>
          <div class="d-flex gap-3 flex-wrap">
            <VBtn
              v-if="auth.can('empresas.ferias')"
              color="primary"
              variant="tonal"
              prepend-icon="tabler-calendar-event"
              @click="abrirFerias"
            >
              Ferias participantes
            </VBtn>
            <VBtn
              v-if="auth.can('empresas.historial')"
              color="secondary"
              variant="tonal"
              prepend-icon="tabler-history"
              @click="abrirHistorial"
            >
              Registro de cambios
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <VForm>
        <VCard
          variant="outlined"
          class="mb-6"
        >
          <VCardText>
            <div class="text-h6 mb-4 font-weight-bold">
              1. Datos de la Empresa
            </div>
            <VRow>
              <VCol cols="12" md="4">
                <VTextField :model-value="empresa.nombre_empresa || '—'" label="Nombre de la Empresa *" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="5">
                <VTextField :model-value="empresa.direccion || '—'" label="Dirección *" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.nit || '—'" label="NIT *" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.telefono || '—'" label="Teléfono *" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.fax || '—'" label="Fax" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.email || '—'" label="Email *" type="email" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.web || '—'" label="Página Web" type="url" variant="outlined" dense readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.pais_nombre || empresa.pais || '—'" label="País *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.ciudad_nombre || empresa.ciudad || '—'" label="Ciudad *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.categoria_nombre || '—'" label="Categoria de la Empresa *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.aniversario || '—'" label="Fecha de Aniversario" type="date" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField :model-value="empresa.nr_escritura || '—'" label="Nro. Escritura de Constitución o Documento de personalidad Jurídica" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField :model-value="empresa.fecha_nr_escritura || '—'" label="Fecha de Escritura de Constitución o Documento de personalidad Jurídica" type="date" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="4">
                <VTextField :model-value="empresa.matricula || '—'" label="Matricula de Comercio (S/A)" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.nr_poder || '—'" label="Nro de Poder" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.nr_notario || '—'" label="Nro. Notaría" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.fecha_nr_poder || '—'" label="Fecha Nro de Poder" type="date" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.distrito || '—'" label="Distrito" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.actividad_principal_nombre || '—'" label="Actividad Principal *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.rubro_nombre || empresa.rubro || '—'" label="Rubro *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.subrubro_nombre || empresa.subrubro || '—'" label="Sub Rubro Principal *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.otro_rubro || '—'" label="Otros Rubros Adicionales *" variant="outlined" readonly />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>

        <VCard
          variant="outlined"
          class="mb-6"
        >
          <VCardText>
            <div class="text-h6 mb-4 font-weight-bold">
              2. Datos Responsable / Contacto
            </div>
            <VRow>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.nombre_gerente || '—'" label="Nombre del Representante *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.ci_gerente || '—'" label="Documento de Identidad del Representante *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.exp_ci_gerente || '—'" label="Exp. CI *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.fono_gerente || '—'" label="Teléfono del Representante *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.cargo_gerente || '—'" label="Cargo del Representante *" variant="outlined" readonly />
              </VCol>
              <VDivider class="my-4" />
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.nombre_responsable || '—'" label="Nombre del Contacto *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.telefono_responsable || '—'" label="Teléfono del Contacto *" variant="outlined" readonly />
              </VCol>
              <VCol cols="12" md="3">
                <VTextField :model-value="empresa.email_representante || '—'" label="Correo Electrónico del Contacto" type="email" variant="outlined" readonly />
              </VCol>
            </VRow>
          </VCardText>
        </VCard>
      </VForm>
    </template>

    <!-- ════════════════════════════════════════════════════════════════════
         MODAL: FERIAS PARTICIPANTES
    ════════════════════════════════════════════════════════════════════════ -->
    <VDialog
      v-model="dialogFerias"
      max-width="1400"
      width="95vw"
      scrollable
    >
      <VCard style="max-block-size: 88vh;">
        <VCardTitle class="d-flex justify-space-between align-center pa-4">
          <span class="text-h6">
            <VIcon icon="tabler-calendar-event" class="me-2" />
            Ferias Participantes — {{ empresa?.nombre_empresa }}
          </span>
          <VBtn icon variant="text" @click="dialogFerias = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText>
          <VProgressLinear v-if="loadingFerias" indeterminate color="primary" class="mb-4" />

          <VAlert v-if="errorFerias" type="error" class="mb-4">
            {{ errorFerias }}
          </VAlert>

          <div v-if="!loadingFerias && feriasList.length === 0 && !errorFerias" class="py-8 text-center text-medium-emphasis">
            <VIcon icon="tabler-calendar-off" size="40" class="mb-2 d-block mx-auto" />
            Esta empresa no tiene participaciones registradas.
          </div>

          <VTable v-if="feriasList.length > 0" density="compact" hover>
            <thead>
              <tr>
                <th>Feria / Evento</th>
                <th>N° Contrato</th>
                <th>Stands</th>
                <th>Precio Total</th>
                <th>Pago</th>
                <th>Fecha Contrato</th>
                <th>Habilitado</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="c in feriasList"
                :key="c.id_feria"
              >
                <td class="font-weight-medium">{{ c.feria }}</td>
                <td>
                  <div class="d-flex flex-wrap gap-1">
                    <VChip
                      v-for="contrato in c.contratos"
                      :key="contrato.id_contrato"
                      class="cursor-pointer"
                      size="small"
                      label
                      :color="contrato.anulado ? 'error' : 'success'"
                      variant="tonal"
                      prepend-icon="tabler-file-text"
                      @click="auth.can('contratos.print') && abrirContratoPdf(contrato.id_contrato)"
                    >
                      {{ getNumeroContrato(contrato) }}
                    </VChip>
                  </div>
                </td>
                <td>
                  <span v-if="c.stands?.length">
                    <VChip
                      v-for="s in c.stands"
                      :key="s.id_stand ?? s"
                      size="x-small"
                      class="me-1"
                      label
                    >{{ typeof s === 'object' ? s.label : s }}</VChip>
                  </span>
                  <span v-else>—</span>
                </td>
                <td>{{ formatMoney(c.precio_total) }}</td>
                <td>
                  <VChip
                    :color="c.pago == 1 ? 'success' : c.pago == 2 ? 'warning' : 'default'"
                    size="small"
                    label
                  >{{ pagoLabel(c.pago) }}</VChip>
                </td>
                <td>{{ formatDate(c.fecha_contrato) }}</td>
                <td>
                  <VIcon
                    :icon="c.habilitado ? 'tabler-check' : 'tabler-x'"
                    :color="c.habilitado ? 'success' : 'error'"
                  />
                </td>
              </tr>
            </tbody>
          </VTable>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="tonal" @click="dialogFerias = false">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ════════════════════════════════════════════════════════════════════
         MODAL: REGISTRO DE CAMBIOS
    ════════════════════════════════════════════════════════════════════════ -->
    <VDialog
      v-model="dialogHistorial"
      max-width="860"
      scrollable
    >
      <VCard>
        <VCardTitle class="d-flex justify-space-between align-center pa-4">
          <span class="text-h6">
            <VIcon icon="tabler-history" class="me-2" />
            Registro de Cambios — {{ empresa?.nombre_empresa }}
          </span>
          <VBtn icon variant="text" @click="dialogHistorial = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>

        <VDivider />

        <VCardText>
          <VProgressLinear v-if="loadingHistorial" indeterminate color="secondary" class="mb-4" />

          <VAlert v-if="errorHistorial" type="error" class="mb-4">
            {{ errorHistorial }}
          </VAlert>

          <div v-if="!loadingHistorial && historialList.length === 0 && !errorHistorial" class="py-8 text-center text-medium-emphasis">
            <VIcon icon="tabler-database-off" size="40" class="mb-2 d-block mx-auto" />
            No hay registros de cambios para esta empresa.
          </div>

          <VExpansionPanels
            v-if="historialList.length > 0"
            v-model="historialExpandido"
            multiple
          >
            <VExpansionPanel
              v-for="registro in historialList"
              :key="registro.id"
            >
              <VExpansionPanelTitle>
                <div class="d-flex align-center gap-4 w-100">
                  <VIcon icon="tabler-edit" size="18" color="secondary" />
                  <span class="font-weight-medium">{{ formatDateTime(registro.fecha) }}</span>
                  <VChip size="x-small" label color="info">
                    {{ registro.cambios?.length ?? 0 }} campo(s) modificado(s)
                  </VChip>
                  <span class="text-medium-emphasis text-caption ms-auto me-4">
                    por {{ registro.usuario }}
                  </span>
                </div>
              </VExpansionPanelTitle>

              <VExpansionPanelText>
                <VTable density="compact">
                  <thead>
                    <tr>
                      <th>Campo</th>
                      <th>Valor anterior</th>
                      <th>Valor nuevo</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="cambio in registro.cambios"
                      :key="cambio.campo"
                    >
                      <td><code>{{ cambio.campo }}</code></td>
                      <td class="text-error">{{ cambio.valor_anterior ?? '(vacío)' }}</td>
                      <td class="text-success">{{ cambio.valor_nuevo ?? '(vacío)' }}</td>
                    </tr>
                    <tr v-if="!registro.cambios?.length">
                      <td colspan="3" class="text-center text-medium-emphasis">Sin detalle de cambios</td>
                    </tr>
                  </tbody>
                </VTable>
              </VExpansionPanelText>
            </VExpansionPanel>
          </VExpansionPanels>
        </VCardText>

        <VDivider />

        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="tonal" @click="dialogHistorial = false">
            Cerrar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </section>
</template>
