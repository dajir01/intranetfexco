<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { watchDebounced } from '@vueuse/core'

const route = useRoute()
const router = useRouter()

// Params y contexto
const idContrato = computed(() => Number(route.params.id))
const idFeria = computed(() => Number(route.query.id_feria))

// Estados generales
const loading = ref(false)
const error = ref(null)
const snackbar = ref({ show: false, color: 'info', text: '' })

// Datos cargados
const contrato = ref(null)
const feria = ref(null)
const pabellones = ref([])
const pabellonSeleccionado = ref(null)
const standsLibres = ref([])
const standsAsignados = ref([])

// Modo de cambio: 'empresa' o 'stands' (inicia sin selección)
const modoCambio = ref(null)

// Empresa: selección (reutiliza lógica de reserva.vue)
const empresaActual = ref(null)
const empresas = ref([])
const loadingEmpresas = ref(false)
const empresaSeleccionada = ref(null)
// ID seleccionado robusto (acepta número, string numérica u objeto con id)
const selectedEmpresaId = computed(() => {
  const v = empresaSeleccionada.value
  if (v && typeof v === 'object') {
    const id = v.id_empresa ?? v.id
    const n = Number(id)
    return Number.isNaN(n) ? null : n
  }
  const n = Number(v)
  return Number.isNaN(n) ? null : n
})
// Computadas (alineadas con reserva.vue, tolerando string libre)
const esEmpresaNueva = computed(() => {
  const v = empresaSeleccionada.value
  return !!v && selectedEmpresaId.value === null && typeof v === 'string' && v.trim() !== ''
})
const checkEmpresa = computed(() => {
  if (!empresaSeleccionada.value) return null
  if (selectedEmpresaId.value !== null) return 'A'
  return empresaSeleccionada.value
})

// Cálculos
const precioUnit = ref(0)
// Descuentos (idéntico a reserva)
const tipoDescuento = ref(null)
const porcentajeDescuento = ref(0)
const otroDescuentoTexto = ref('Otro Descuento')

// Plano
const planoUrl = ref(null)
// Soporte de escala responsiva como en reserva.vue
const imagenMapa = ref(null)
const originalWidth = ref(0)
const originalHeight = ref(0)
const currentWidth = ref(0)
const currentHeight = ref(0)
const isMapaListo = ref(false)
const scaleX = computed(() => currentWidth.value && originalWidth.value ? currentWidth.value / originalWidth.value : 1)
const scaleY = computed(() => currentHeight.value && originalHeight.value ? currentHeight.value / originalHeight.value : 1)

// Mapa helpers
const onImagenCargada = (evt) => {
  const img = imagenMapa.value
  if (!img) return
  originalWidth.value = img.naturalWidth || img.width || 0
  originalHeight.value = img.naturalHeight || img.height || 0
  currentWidth.value = img.clientWidth || img.width || originalWidth.value
  currentHeight.value = img.clientHeight || img.height || originalHeight.value
  isMapaListo.value = true
}
const recalcularMapa = () => {
  const img = imagenMapa.value
  if (!img) return
  currentWidth.value = img.clientWidth || img.width || originalWidth.value
  currentHeight.value = img.clientHeight || img.height || originalHeight.value
}
const getStandStyle = (stand) => {
  const left = Math.round((stand.izq || 0) * scaleX.value)
  const top = Math.round((stand.sup || 0) * scaleY.value)
  return {
    position: 'absolute',
    left: left + 'px',
    top: top + 'px',
    width: '18px',
    height: '18px',
  }
}

// Helpers formato
const formatoPrecio = precio => {
  if (!precio && precio !== 0) return '—'
  return new Intl.NumberFormat('es-BO', { style: 'currency', currency: 'BOB', minimumFractionDigits: 2 }).format(precio)
}

// Cargar contrato y dependencias
const cargarContrato = async () => {
  loading.value = true
  error.value = null
  try {
    const resp = await fetch(`/contratos/${idContrato.value}/cambiar-datos`)
    const json = await resp.json()
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'No se pudo cargar el contrato')
    const data = json.data || json
    contrato.value = data.contrato || null
    feria.value = data.feria || null
    empresaActual.value = data.empresa || null

    // Precio y descuento desde contrato
    precioUnit.value = Number(contrato.value?.precio_unit ?? 0)
    tipoDescuento.value = contrato.value?.tipo_desc ?? null
    porcentajeDescuento.value = Number(contrato.value?.descuento ?? 0)

    // Pabellón y mapa
    const pab = data.pabellon || null
    pabellonSeleccionado.value = pab?.id_pabellon ?? null
    planoUrl.value = pab?.mapa_url || null

    // Stands asignados
    const standsActuales = data.stands_asignados || []
    standsAsignados.value = standsActuales.map(s => ({
      id: s.id ?? s.id_stand,
      codigo: s.codigo ?? String(s.numero_stand ?? s.stand ?? ''),
      m2: Number(s.m2 ?? s.area_stand ?? 0),
      x: Number(s.x ?? s.izq ?? 0),
      y: Number(s.y ?? s.sup ?? 0),
      w: Number(s.w ?? 40),
      h: Number(s.h ?? 30),
      bloqueado: false,
    }))

    // Poblar listas auxiliares
    pabellones.value = Array.isArray(data.pabellones) ? data.pabellones : []
    empresas.value = Array.isArray(data.empresas) ? data.empresas : []

    // El cargado de stands ocurre en el watcher de pabellón
  } catch (e) {
    error.value = e.message || 'Error al cargar datos del contrato'
  } finally {
    loading.value = false
  }
}

const cargarPabellones = async (feriaId) => {
  try {
    const resp = await fetch(`/ferias/${feriaId}/pabellones`)
    const json = await resp.json()
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'No se pudo cargar pabellones')
    pabellones.value = json.data || json
  } catch (e) {
    pabellones.value = pabellones.value || []
  }
}

// Stands del pabellón (idéntico a reserva.vue, pero permite nuestros stands)
const stands = ref([])
const cargarStandsLibres = async (pabellonId) => {
  try {
    const feriaId = feria.value?.id || idFeria.value
    const url = `/contratos/stands/${feriaId}/${pabellonId}`
    const resp = await fetch(url)
    const json = await resp.json()
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'No se pudo cargar stands libres')
    const arr = Array.isArray(json.data) ? json.data : (json.data?.stands || [])
    const idsPropios = new Set(standsAsignados.value.map(s => s.id))
    // Marcar nuestros stands como disponibles aunque el endpoint los marque ocupados
    stands.value = arr.map(s => ({
      id_stand: s.id_stand,
      numero_stand: s.numero_stand,
      area_stand: Number(s.area_stand ?? 0),
      sup: Number(s.sup ?? 0),
      izq: Number(s.izq ?? 0),
      disponible: idsPropios.has(s.id_stand) ? true : !!s.disponible,
    }))
  } catch (e) {
    stands.value = []
  }
}

// Cargar empresas (idéntico a reserva.vue)
const buscarEmpresas = async () => {
  loadingEmpresas.value = true
  try {
    const resp = await fetch(`/contratos/empresas`)
    const json = await resp.json()
    empresas.value = Array.isArray(json.data) ? json.data : []
  } catch (e) {
    empresas.value = []
  } finally {
    loadingEmpresas.value = false
  }
}

// Selección stands (idéntico a reserva.vue)
const standsSeleccionados = ref([])
const preseleccionarStandsActuales = () => {
  standsSeleccionados.value = standsAsignados.value.map(s => s.id)
}

// Reglas: máximo 12
const maxStands = 12
const totalSeleccionados = computed(() => standsSeleccionados.value.length)
const maxStandsAlcanzado = computed(() => totalSeleccionados.value >= maxStands)
const isStandSeleccionado = (idStand) => standsSeleccionados.value.includes(idStand)
const isStandDeshabilitado = (stand) => {
  if (!stand.disponible && !isStandSeleccionado(stand.id_stand)) return true
  if (maxStandsAlcanzado.value && !isStandSeleccionado(stand.id_stand)) return true
  return false
}
const toggleStand = (stand) => {
  const id = stand.id_stand
  const idx = standsSeleccionados.value.indexOf(id)
  if (idx === -1) {
    if (!maxStandsAlcanzado.value) standsSeleccionados.value.push(id)
  } else {
    standsSeleccionados.value.splice(idx, 1)
  }
}

// Calcular metraje_total y total
// Cálculos económicos (idénticos a reserva)
const standsDisponibles = computed(() => stands.value.filter(s => s.disponible))
const metrajeTotal = computed(() => {
  return standsSeleccionados.value.reduce((total, idStand) => {
    const stand = stands.value.find(s => s.id_stand === idStand)
    return total + (stand?.area_stand || 0)
  }, 0)
})
const montoInicial = computed(() => metrajeTotal.value * Number(precioUnit.value || 0))
const importeDescuento = computed(() => (montoInicial.value * porcentajeDescuento.value) / 100)
const totalAPagar = computed(() => montoInicial.value - importeDescuento.value)
const hayDescuentoActivo = computed(() => tipoDescuento.value !== null)
const mostrarBloqueEconomico = computed(() => standsSeleccionados.value.length > 0)
const seleccionarDescuento = (tipo) => {
  if (tipoDescuento.value === tipo) {
    tipoDescuento.value = null; porcentajeDescuento.value = 0
  } else { tipoDescuento.value = tipo; porcentajeDescuento.value = 0 }
}
const isDescuentoActivo = (tipo) => tipoDescuento.value === tipo

// Cargar mapa y stands del pabellón seleccionado (bloqueado en edición: solo inicial)
watch(pabellonSeleccionado, async (pid) => {
  if (!pid) return
  // Actualizar imagen del mapa según el pabellón seleccionado
  const pab = pabellones.value.find(p => p.id_pabellon === pid)
  if (pab && pab.mapa_url) {
    planoUrl.value = pab.mapa_url
  }
  // Reset de escala/estado del mapa para forzar recálculo al cargar la nueva imagen
  isMapaListo.value = false
  originalWidth.value = 0
  originalHeight.value = 0
  currentWidth.value = 0
  currentHeight.value = 0
  // Limpiar stands renderizados antes de recargar
  stands.value = []
  // Cargar stands del nuevo pabellón
  await cargarStandsLibres(pid)
  // Preseleccionar stands del contrato (solo inicial, ya que el pabellón no cambia)
  preseleccionarStandsActuales()
})

onMounted(async () => {
  await cargarContrato()
  preseleccionarStandsActuales()
  // No cargar empresas hasta seleccionar modo empresa
  window.addEventListener('resize', recalcularMapa)
})

onUnmounted(() => {
  window.removeEventListener('resize', recalcularMapa)
})

// Al cambiar modo, preparar datos específicos
watch(modoCambio, async (modo) => {
  if (modo === 'empresa') {
    if (!empresas.value.length) await buscarEmpresas()
  }
  if (modo === 'stands') {
    if (pabellonSeleccionado.value && !stands.value.length) {
      await cargarStandsLibres(pabellonSeleccionado.value)
    }
  }
})

// Exclusividad y validaciones de guardado
const idsAsignadosOriginal = computed(() => standsAsignados.value.map(s => s.id).sort((x,y)=>x-y))
const hayCambioStands = computed(() => {
  if (modoCambio.value !== 'stands') return false
  const a = idsAsignadosOriginal.value
  const b = [...standsSeleccionados.value].sort((x,y)=>x-y)
  if (b.length === 0) return false
  if (a.length !== b.length) return true
  for (let i=0;i<a.length;i++) if (a[i] !== b[i]) return true
  return false
})
const hayCambioEmpresa = computed(() => {
  if (modoCambio.value !== 'empresa') return false
  if (!empresaSeleccionada.value) return false
  if (esEmpresaNueva.value) return true
  const actualId = Number(empresaActual.value?.id ?? empresaActual.value?.id_empresa ?? NaN)
  return selectedEmpresaId.value !== null && !Number.isNaN(actualId) && selectedEmpresaId.value !== actualId
})
const puedeGuardar = computed(() => {
  if (modoCambio.value === 'empresa') return hayCambioEmpresa.value
  if (modoCambio.value === 'stands') return hayCambioStands.value
  return false
})

// Guardado
const obtenerCsrf = async () => {
  let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
  if (!csrfToken) {
    const csrfResponse = await fetch('/csrf-token', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (csrfResponse.ok) {
      const csrfJson = await csrfResponse.json().catch(() => null)
      csrfToken = csrfJson?.token || ''
      if (csrfToken) {
        const meta = document.querySelector('meta[name="csrf-token"]')
        if (meta) meta.setAttribute('content', csrfToken)
      }
    }
  }
  return csrfToken
}

const guardarEmpresa = async () => {
  try {
    const csrf = await obtenerCsrf()
    const payload = {
      check_empresa: checkEmpresa.value,
      id_empresa: selectedEmpresaId.value,
    }
    const resp = await fetch(`/contratos/${idContrato.value}/empresa`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
    const json = await resp.json().catch(()=>({}))
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'No se pudo actualizar la empresa del contrato')
    snackbar.value = { show: true, color: 'success', text: 'Empresa actualizada correctamente.' }
    // Refrescar empresa actual
    empresaActual.value = json.data?.empresa || empresaActual.value
    return true
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: e.message || 'Error al actualizar empresa.' }
    return false
  }
}

const guardarStands = async () => {
  try {
    const csrf = await obtenerCsrf()
    const idsSeleccionados = [...standsSeleccionados.value]
    const payload = {
      id_pabellon: pabellonSeleccionado.value,
      stands: idsSeleccionados,
      precio_unit: precioUnit.value,
      tipo_desc: tipoDescuento.value,
      porcentaje_desc: porcentajeDescuento.value,
    }
    const resp = await fetch(`/contratos/${idContrato.value}/stands`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}) },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
    const json = await resp.json().catch(()=>({}))
    if (!resp.ok || !json?.success) throw new Error(json?.message || 'No se pudo actualizar los stands del contrato')
    snackbar.value = { show: true, color: 'success', text: 'Stands actualizados correctamente.' }
    return true
  } catch (e) {
    snackbar.value = { show: true, color: 'error', text: e.message || 'Error al actualizar stands.' }
    return false
  }
}

// Confirmación y redirección después del guardado
const confirmDialog = ref(false)
const saving = ref(false)
const confirmMessage = computed(() => {
  if (modoCambio.value === 'empresa') {
    return esEmpresaNueva.value
      ? `Se creará la nueva empresa "${empresaSeleccionada.value}" y se asignará al contrato.`
      : `Se asignará la empresa seleccionada (ID ${selectedEmpresaId.value}) al contrato.`
  }
  if (modoCambio.value === 'stands') {
    return `Se actualizarán los stands seleccionados (${standsSeleccionados.value.length}) y el precio por m².`
  }
  return 'Selecciona un modo de cambio para continuar.'
})

const ejecutarGuardado = async () => {
  if (!puedeGuardar.value) return
  saving.value = true
  let ok = false
  if (modoCambio.value === 'empresa') ok = await guardarEmpresa()
  else if (modoCambio.value === 'stands') ok = await guardarStands()
  saving.value = false
  confirmDialog.value = false
  if (ok) {
    // Dar 500ms para que el snackbar de éxito sea visible
    await new Promise(resolve => setTimeout(resolve, 500))
    const path = idFeria.value ? `/contrato/llenado/${idContrato.value}?id_feria=${idFeria.value}` : `/contrato/llenado/${idContrato.value}`
    router.push(path)
  }
}

// Acción principal (no guarda aún)
const realizarCambio = () => {
  snackbar.value = { show: true, color: 'info', text: 'Cambios preparados. Guardado final se implementará en la siguiente fase.' }
}

// Navegación
const volverAlListado = () => {
  if (idFeria.value) {
    router.push({ path: '/contrato/list', query: { id_feria: idFeria.value } })
  } else {
    router.back()
  }
}
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <h4 class="text-h4 mb-1">Editar Contrato</h4>
            <div class="text-caption">
              ID Contrato: {{ idContrato }}
              <span v-if="idFeria"> | Feria: {{ feria?.nombre || idFeria }}</span>
            </div>
          </div>
          <VBtn color="secondary" prepend-icon="tabler-arrow-left" @click="volverAlListado">Volver al listado</VBtn>
        </div>
        <VAlert v-if="error" type="error" variant="tonal" class="mt-4" closable @click:close="error = null">{{ error }}</VAlert>
      </VCardText>
    </VCard>

    <VCard>
      <VCardText>
        <div class="d-flex flex-column gap-4">
          <!-- Radios de modo -->
          <div>
            <div class="text-body-1 mb-2">¿Qué deseas cambiar?</div>
            <VRadioGroup v-model="modoCambio" inline>
              <VRadio label="Cambiar Empresa" value="empresa" />
              <VRadio label="Cambiar Stand(s)" value="stands" />
            </VRadioGroup>
          </div>

          <!-- CAMBIAR EMPRESA -->
          <div v-if="modoCambio === 'empresa'" class="d-flex flex-column gap-4">
            <VAlert type="info" variant="tonal">
              Empresa actual: <strong>{{ empresaActual?.nombre || empresaActual?.nombre_empresa || '—' }}</strong>
            </VAlert>

            <!-- Reutilización del VCombobox de reserva.vue -->
            <VCombobox
              v-model="empresaSeleccionada"
              :items="empresas"
              :loading="loadingEmpresas"
              item-title="nombre_empresa"
              item-value="id_empresa"
              label="Buscar empresa existente o escribir nueva"
              placeholder="Escribe o selecciona el nombre de la empresa..."
              clearable
              no-data-text="No se encontraron empresas"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-building" />
              </template>
              <template #item="{ props, item }">
                <VListItem v-bind="props" :title="item.raw.nombre_empresa">
                  <template #subtitle>
                    <div class="text-caption">
                      <span v-if="item.raw.nombre_responsable">Responsable: {{ item.raw.nombre_responsable }}</span>
                      <span v-if="item.raw.nombre_gerente" class="ml-2">| Gerente: {{ item.raw.nombre_gerente }}</span>
                    </div>
                  </template>
                </VListItem>
              </template>
            </VCombobox>

            <!-- Indicador de empresa nueva o existente -->
            <VAlert v-if="empresaSeleccionada" :type="esEmpresaNueva ? 'info' : 'success'" variant="tonal">
              <div class="d-flex align-center gap-2">
                <VIcon :icon="esEmpresaNueva ? 'tabler-plus' : 'tabler-check'" />
                <div>
                  <strong v-if="esEmpresaNueva">Nueva empresa</strong>
                  <strong v-else>Empresa existente</strong>
                  <div class="text-caption">
                    <span v-if="esEmpresaNueva">Se creará automáticamente: "{{ empresaSeleccionada }}"</span>
                    <span v-else>ID: {{ empresaSeleccionada }}</span>
                  </div>
                </div>
              </div>
            </VAlert>
          </div>

          <!-- CAMBIAR STAND(S) -->
          <div v-if="modoCambio === 'stands'" class="d-flex flex-column gap-4">
            <div class="d-flex gap-4 align-center flex-wrap">
              <VSelect
                v-model="pabellonSeleccionado"
                :items="pabellones"
                item-title="nombre_pabellon"
                item-value="id_pabellon"
                label="Pabellón"
                :disabled="true"
                clearable
              />
              <div class="text-caption text-medium-emphasis">
                <VIcon icon="tabler-alert-circle" size="small" /> El pabellón está bloqueado en edición: no puede cambiarse.
              </div>
            </div>

            <div>
              <div class="text-body-2 mb-2">Plano del pabellón (mismo de la reserva original)</div>
              <div class="pabellon-mapa-container">
                <div class="pabellon-mapa-wrapper">
                  <img
                    ref="imagenMapa"
                    :src="planoUrl"
                    :alt="'Mapa pabellón'"
                    class="pabellon-mapa-imagen"
                    @load="onImagenCargada"
                  >
                  <div class="stands-layer">
                    <input
                      v-for="stand in stands"
                      :key="stand.id_stand"
                      type="checkbox"
                      class="stand-checkbox"
                      :style="getStandStyle(stand)"
                      :checked="isStandSeleccionado(stand.id_stand)"
                      :disabled="isStandDeshabilitado(stand)"
                      @change="toggleStand(stand)"
                    >
                  </div>
                </div>
                <div class="text-caption mt-2">Seleccionados: {{ totalSeleccionados }} / {{ maxStands }}</div>
              </div>
            </div>

            <!-- Datos y cálculos -->
            <VDivider class="my-4" />

            <div class="d-flex flex-wrap gap-4">
              <AppTextField :model-value="metrajeTotal.toFixed(2)" label="Superficie total (m²)" readonly />
              <AppTextField v-model.number="precioUnit" label="Precio por m² (Bs.)" />
              <div class="d-flex flex-column">
                <strong class="text-body-2 mb-1">Asignar un Descuento:</strong>
                <VCheckbox :model-value="isDescuentoActivo('fepc')" label="FEPC" density="compact" hide-details @update:model-value="seleccionarDescuento('fepc')" />
                <VCheckbox :model-value="isDescuentoActivo('pronto_pago_1')" label="Pronto Pago 1" density="compact" hide-details @update:model-value="seleccionarDescuento('pronto_pago_1')" />
                <VCheckbox :model-value="isDescuentoActivo('pronto_pago_2')" label="Pronto Pago 2" density="compact" hide-details @update:model-value="seleccionarDescuento('pronto_pago_2')" />
                <div class="d-flex align-center gap-2">
                  <VCheckbox :model-value="isDescuentoActivo('otro')" label="Otro" density="compact" hide-details @update:model-value="seleccionarDescuento('otro')" />
                  <AppTextField v-model="otroDescuentoTexto" :readonly="!isDescuentoActivo('otro')" density="compact" />
                </div>
              </div>
              <AppTextField v-if="hayDescuentoActivo" v-model.number="porcentajeDescuento" label="% Descuento" />
            </div>
            <div class="d-flex gap-2 align-center mt-2">
              <VAlert type="info" variant="tonal" density="compact">
                <div class="text-caption"><strong>Monto inicial:</strong> Bs. {{ montoInicial.toFixed(2) }} <span v-if="hayDescuentoActivo && porcentajeDescuento > 0"> • <strong>Descuento ({{ porcentajeDescuento }}%):</strong> Bs. {{ importeDescuento.toFixed(2) }}</span></div>
              </VAlert>
              <div class="text-body-2">Total: <strong>Bs. {{ totalAPagar.toFixed(2) }}</strong></div>
            </div>
          </div>
        </div>
      </VCardText>

      <VDivider />
      <VCardActions>
        <VSpacer />
        <VBtn color="primary" :disabled="!puedeGuardar" @click="confirmDialog = true">Guardar cambios</VBtn>
      </VCardActions>
    </VCard>

    <!-- Diálogo de confirmación -->
    <VDialog v-model="confirmDialog" max-width="520">
      <VCard>
        <VCardTitle>Confirmar cambio</VCardTitle>
        <VCardText>
          {{ confirmMessage }}
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="confirmDialog = false">Cancelar</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!puedeGuardar" @click="ejecutarGuardado">Confirmar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" :color="snackbar.color" timeout="3000">{{ snackbar.text }}</VSnackbar>
  </div>
</template>

<style scoped>
.pabellon-mapa-container {
  width: 100%;
}
.pabellon-mapa-wrapper {
  position: relative;
  display: inline-block;
  max-width: 100%;
}
.pabellon-mapa-imagen {
  display: block;
  max-width: 100%;
  height: auto;
  border: 1px solid #ddd;
  border-radius: 4px;
}
.stands-layer {
  position: absolute;
  left: 0;
  top: 0;
}
.stand-checkbox {
  position: absolute;
  width: 18px;
  height: 18px;
}
</style>
