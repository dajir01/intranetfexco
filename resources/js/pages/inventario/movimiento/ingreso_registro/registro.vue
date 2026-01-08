<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'
import ProductAlmacen from './ProductAlmacen.vue'

const authStore = useAuthStore()
const router = useRouter()

const formIngreso = ref({
  numero: '',
  id_visual: '',
  fecha_ingreso: '',
  area_id: null,
  area_nombre: '',
  descripcion: '',
  total_importe: 0,
})

const authenticatedUser = computed(() => authStore.user?.nombre_usuario || authStore.user?.nick_usuario || 'Usuario')
const personaEntrega = ref('')
const personaRecibe = ref('')
const observaciones = ref('')
const descripcion = ref('')

const areas = ref([])
const selectedAreaId = ref(null)
const areasSearch = ref('')
const csrfToken = ref('')

const productos = ref([{ asignacion_id: null, producto_id: null, codigo: '', nombre: '', cantidad: 1, precio_unitario: 0, stock_disponible: 0, importe: 0 }])
const productRows = ref([])

const loading = ref(false)
const submitting = ref(false)
const confirming = ref(false)
const errorSnack = ref(false)
const errorMsg = ref('')
const successSnack = ref(false)
const successMsg = ref('')
const invalidRows = ref(new Set())
const invalidRowMessages = ref({})
let validationTimer = null

const lineTotals = ref([])
const totalImporte = computed(() => lineTotals.value.reduce((sum, v) => sum + (Number(v) || 0), 0))

const isDialogVisible = ref(false)
const previewPayload = ref(null)
const previewResponse = ref(null)
const previewItems = computed(() => {
  const respItems = previewResponse.value?.received?.items
  if (Array.isArray(respItems) && respItems.length) return respItems
  const payloadItems = previewPayload.value?.items
  if (Array.isArray(payloadItems)) return payloadItems
  return []
})

const obtenerSiguienteNumero = async () => {
  try {
    const r = await fetch('/inventario/movimientos/ingreso/next-numero', { credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      const next = j?.data?.next ?? j?.next ?? j?.numero ?? null
      if (next) {
        formIngreso.value.numero = next
        const padded = String(next).padStart(6, '0')
        formIngreso.value.id_visual = `IA-${padded}`
        return true
      }
    }
  } catch (_) {}
  return false
}

const generarCodigoIngreso = async () => {
  try {
    const r = await fetch('/inventario/movimientos/ultimo-ingreso', { credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      const ultimoCodigo = j?.codigo ?? 0
      const nuevoNumero = parseInt(ultimoCodigo) + 1
      const codigoFormato = `IA-${String(nuevoNumero).padStart(6, '0')}`
      formIngreso.value.id_visual = codigoFormato
      formIngreso.value.numero = nuevoNumero
      return true
    }
  } catch (e) {
    console.error('Error generando código de ingreso', e)
  }
  return false
}

const fetchAreas = async (q = '') => {
  try {
    const url = q ? `/inventario/areas?q=${encodeURIComponent(q)}` : '/inventario/areas'
    const r = await fetch(url)
    if (r.ok) {
      const j = await r.json()
      areas.value = Array.isArray(j) ? j : (j.data || [])
    }
  } catch (e) {
    console.error('Error cargando áreas', e)
  }
}

watch(selectedAreaId, id => {
  if (!id) {
    formIngreso.value.area_id = null
    formIngreso.value.area_nombre = ''
    return
  }
  const a = areas.value.find(x => x.id_area === id)
  if (a) {
    formIngreso.value.area_id = id
    formIngreso.value.area_nombre = a.nombre
  }
})

onMounted(() => {
  if (!formIngreso.value.fecha_ingreso) {
    const d = new Date()
    const pad = n => String(n).padStart(2, '0')
    formIngreso.value.fecha_ingreso = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`
  }
  validationTimer = setInterval(() => recomputeInvalidRows(), 400)
  fetchAreas()
  generarCodigoIngreso()

  fetch('/csrf-token').then(r => r.json()).then(j => {
    csrfToken.value = j.token || ''
  }).catch(() => {})
})

onUnmounted(() => {
  if (validationTimer) {
    clearInterval(validationTimer)
    validationTimer = null
  }
})

const addProducto = () => {
  productos.value.push({ asignacion_id: null, producto_id: null, codigo: '', nombre: '', cantidad: 1, precio_unitario: 0, stock_disponible: 0, importe: 0 })
  lineTotals.value.push(0)
  recomputeInvalidRows()
}

const removeProducto = (idx) => {
  if (idx === undefined || idx === null) return
  productos.value.splice(idx, 1)
  lineTotals.value.splice(idx, 1)
  productRows.value.splice(idx, 1)
  if (productos.value.length === 0) addProducto()
  recomputeInvalidRows()
}

const onLineTotal = (index, amount) => {
  lineTotals.value[index] = Number(amount) || 0
  recomputeInvalidRows()
}

const recomputeInvalidRows = () => {
  invalidRows.value = new Set()
  invalidRowMessages.value = {}
}

const submitPreview = async () => {
  if (!await obtenerSiguienteNumero()) {
    errorMsg.value = 'Número de ingreso no asignado. Recargue o intente de nuevo.'
    errorSnack.value = true
    return
  }
  if (!selectedAreaId.value) {
    errorMsg.value = 'Seleccione un área antes de registrar.'
    errorSnack.value = true
    return
  }

  let validProducts = []
  const refs = productRows.value.filter(ref => ref)
  if (refs.length > 0) {
    validProducts = refs
      .map((ref, idx) => {
        try {
          return ref?.getSnapshot?.() || null
        } catch (e) {
          console.error('Error en getSnapshot() para fila', idx, ':', e)
          return null
        }
      })
      .filter(p => p && !!p.asignacion_id && Number(p.cantidad) > 0 && Number(p.cantidad) <= Number(p.stock_disponible || 0))
  }

  if (validProducts.length === 0) {
    validProducts = productos.value.filter(p => {
      return !!p.asignacion_id && Number(p.cantidad) > 0 && Number(p.cantidad) <= Number(p.stock_disponible || 0)
    })
  }

  if (validProducts.length === 0) {
    errorMsg.value = 'Agregue al menos un producto válido con cantidad menor o igual al stock disponible.'
    errorSnack.value = true
    return
  }

  const payload = {
    numero: formIngreso.value.numero || null,
    id_visual: formIngreso.value.id_visual || '',
    fecha_ingreso: formIngreso.value.fecha_ingreso || '',
    area_id: selectedAreaId.value,
    area_nombre: formIngreso.value.area_nombre || '',
    persona_entrega: personaEntrega.value || '',
    persona_recibe: personaRecibe.value || '',
    descripcion: descripcion.value || '',
    observaciones: observaciones.value || '',
    total_importe: Number(totalImporte.value) || 0,
    items: validProducts.map(p => ({
      asignacion_id: p.asignacion_id,
      producto_nombre: p.nombre || '',
      codigo: p.codigo || '',
      cantidad: p.cantidad,
      precio_unitario: p.precio_unitario,
      importe: p.importe,
    })),
  }

  try {
    submitting.value = true
    previewPayload.value = payload
    previewResponse.value = { received: payload }
    isDialogVisible.value = true
  } catch (e) {
    errorMsg.value = `Error en previsualización: ${e}`
    errorSnack.value = true
  } finally {
    submitting.value = false
  }
}

const confirmSave = async () => {
  confirming.value = true
  try {
    if (!await obtenerSiguienteNumero()) {
      errorMsg.value = 'Número de ingreso no asignado. Recargue o intente de nuevo.'
      errorSnack.value = true
      return
    }

    let validProducts = []
    const refs = productRows.value.filter(ref => ref)
    if (refs.length > 0) {
      validProducts = refs
        .map((ref, idx) => {
          try {
            return ref?.getSnapshot?.() || null
          } catch (e) {
            console.error('Error en getSnapshot() para fila', idx, ':', e)
            return null
          }
        })
        .filter(p => p && !!p.asignacion_id && Number(p.cantidad) > 0 && Number(p.cantidad) <= Number(p.stock_disponible || 0))
    }

    if (validProducts.length === 0) {
      validProducts = productos.value.filter(p => {
        return !!p.asignacion_id && Number(p.cantidad) > 0 && Number(p.cantidad) <= Number(p.stock_disponible || 0)
      })
    }

    if (!selectedAreaId.value || validProducts.length === 0) {
      errorMsg.value = 'Complete todos los campos requeridos antes de guardar.'
      errorSnack.value = true
      return
    }

    if (!csrfToken.value) {
      try {
        const t = await fetch('/csrf-token', { credentials: 'same-origin' })
        if (t.ok) {
          const j = await t.json()
          csrfToken.value = j.token || ''
        }
      } catch (_) {}
    }

    const payload = {
      numero: formIngreso.value.numero || null,
      id_visual: formIngreso.value.id_visual || '',
      fecha_ingreso: formIngreso.value.fecha_ingreso || '',
      area_id: selectedAreaId.value,
      area_nombre: formIngreso.value.area_nombre || '',
      persona_entrega: personaEntrega.value || '',
      persona_recibe: personaRecibe.value || '',
      descripcion: descripcion.value || '',
      observaciones: observaciones.value || '',
      total_importe: Number(totalImporte.value) || 0,
      items: validProducts.map(p => ({
        asignacion_id: p.asignacion_id,
        producto_nombre: p.nombre || '',
        codigo: p.codigo || '',
        cantidad: p.cantidad,
        precio_unitario: p.precio_unitario,
        importe: p.importe,
      })),
    }

    const r = await fetch('/inventario/movimientos/ingreso-almacen', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken.value,
      },
      body: JSON.stringify(payload),
    })

    if (r.ok) {
      const j = await r.json()
      isDialogVisible.value = false
      router.push({ path: '/inventario/movimiento/ingreso_producto', query: { success: `Ingreso #${j?.data?.numero ?? ''} guardado correctamente` } })
    } else {
      const txt = await r.text()
      errorMsg.value = `Error al guardar: ${txt}`
      errorSnack.value = true
    }
  } catch (e) {
    errorMsg.value = `Error al guardar: ${e}`
    errorSnack.value = true
  } finally {
    confirming.value = false
  }
}
</script>

<template>
  <VCard class="pa-6 pa-sm-12">
    <div class="d-flex flex-wrap justify-space-between flex-column rounded bg-var-theme-background flex-sm-row gap-6 pa-6 mb-6">
      <div>
        <div class="d-flex align-center app-logo mb-6">
          <VNodeRenderer :nodes="themeConfig.app.logo" />
          <h6 class="app-logo-title">{{ themeConfig.app.title }}</h6>
        </div>
      </div>

      <div class="d-flex flex-column gap-2">
        <div class="d-flex align-start align-sm-center gap-x-4 font-weight-medium text-lg flex-column flex-sm-row">
          <span class="text-high-emphasis text-sm-end" style="inline-size: 5.625rem;">N°:</span>
          <span>
            <AppTextField id="ingreso-id" v-model="formIngreso.id_visual" disabled prefix="#" style="inline-size: 9.5rem;" />
          </span>
          <span class="text-high-emphasis text-sm-end" style="inline-size: 5.625rem;">Fecha Ingreso:</span>
          <span style="inline-size: 9.5rem;">
            <AppDateTimePicker
              id="fecha-ingreso"
              v-model="formIngreso.fecha_ingreso"
              placeholder="YYYY-MM-DD HH:mm"
              :config="{ enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i', position: 'auto right' }"
              disabled
              readonly
            />
          </span>
        </div>
      </div>
    </div>

    <VRow>
      <VCol cols="12" md="4">
        <AppAutocomplete
          class="area-autocomplete"
          label="Área"
          :items="areas"
          item-title="nombre"
          item-value="id_area"
          v-model="selectedAreaId"
          placeholder="Seleccione área"
          clearable
          @update:search-input="val => { areasSearch.value = val; fetchAreas(val) }"
        />
      </VCol>

      <VCol cols="12" md="4">
        <AppTextField v-model="personaEntrega" label="Persona que entrega" placeholder="Nombre completo" />
      </VCol>

      <VCol cols="12" md="4">
        <AppTextField v-model="personaRecibe" label="Persona que recibe" placeholder="Nombre completo" />
      </VCol>

      <VCol cols="12">
        <VTextarea v-model="observaciones" label="Observaciones" rows="2" placeholder="Escriba las observaciones aquí..." />
      </VCol>
    </VRow>

    <VDivider class="my-6 border-dashed" />

    <div class="add-products-form">
      <div v-for="(producto, index) in productos" :key="index" class="mb-4" :class="{ 'invalid-row': invalidRows.has(index) }">
        <ProductAlmacen
          :ref="el => { if (el) productRows[index] = el }"
          :id="index"
          :data="producto"
          @total-amount="amount => onLineTotal(index, amount)"
          @remove-product="removeProducto"
        />
        <div v-if="invalidRows.has(index)" class="invalid-hint">
          {{ invalidRowMessages[index] || 'Complete los campos obligatorios.' }}
        </div>
      </div>

      <VBtn size="small" prepend-icon="tabler-plus" @click="addProducto">
        Añadir Producto
      </VBtn>
    </div>

    <VDivider class="my-6 border-dashed" />

    <div class="d-flex justify-space-between flex-wrap flex-column flex-sm-row">
      <div>
        <table class="w-100">
          <tbody>
            <tr>
              <td class="pe-16">Total:</td>
              <td :class="$vuetify.locale.isRtl ? 'text-start' : 'text-end'">
                <h6 class="text-h6">Bs. {{ totalImporte.toFixed(2) }}</h6>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="d-flex justify-center mt-4">
      <VBtn color="primary" :loading="submitting" :disabled="submitting" @click="submitPreview">
        Registrar Ingreso
        <VIcon end icon="tabler-checkbox" />
      </VBtn>
    </div>

    <VDialog v-model="isDialogVisible" fullscreen :scrim="false" transition="dialog-bottom-transition">
      <VCard>
        <div>
          <VToolbar color="primary">
            <VBtn icon variant="plain" @click="isDialogVisible = false">
              <VIcon color="white" icon="tabler-x" />
            </VBtn>

            <VToolbarTitle>Previsualización de Ingreso</VToolbarTitle>

            <VSpacer />

            <VToolbarItems>
              <VBtn variant="flat" color="error" @click="isDialogVisible = false">Cancelar</VBtn>
              <VBtn variant="flat" color="success" :loading="confirming" :disabled="confirming" @click="confirmSave">
                Confirmar Guardado
              </VBtn>
            </VToolbarItems>
          </VToolbar>
        </div>

        <div class="pa-8">
          <div class="d-flex justify-space-between align-start mb-8">
            <div>
              <div class="text-h6 font-weight-bold">{{ themeConfig.app.title }}</div>
              <div class="text-caption text-disabled">REGISTRO DE INGRESO A ALMACEN</div>
            </div>
            <div class="text-right">
              <div class="text-body-1 font-weight-bold">{{ previewPayload?.id_visual || '—' }}</div>
              <div class="text-caption">Fecha: {{ previewPayload?.fecha_ingreso || '—' }}</div>
            </div>
          </div>

          <VCard class="mb-6" variant="outlined">
            <VCardTitle class="text-h6">Información General</VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="12" md="6">
                  <div class="mb-3">
                    <span class="text-caption font-weight-bold">ÁREA:</span>
                    <div class="text-body-2">{{ previewPayload?.area_nombre || '—' }}</div>
                  </div>
                  <div class="mb-3">
                    <span class="text-caption font-weight-bold">Persona que entrega:</span>
                    <div class="text-body-2">{{ previewPayload?.persona_entrega || '—' }}</div>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <div class="mb-3">
                    <span class="text-caption font-weight-bold">Persona que recibe:</span>
                    <div class="text-body-2">{{ previewPayload?.persona_recibe || '—' }}</div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VCard v-if="previewPayload?.descripcion" class="mb-6" variant="outlined">
            <VCardTitle class="text-h6">Descripción</VCardTitle>
            <VCardText>
              <div class="text-body-2">{{ previewPayload.descripcion }}</div>
            </VCardText>
          </VCard>

          <VCard class="mb-6" variant="outlined">
            <VCardTitle class="text-h6">Productos/Artículos</VCardTitle>
            <VCardText>
              <VTable class="text-no-wrap" density="compact">
                <thead>
                  <tr>
                    <th class="text-left">Código</th>
                    <th class="text-left">Descripción</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-right">C. Unitario</th>
                    <th class="text-right">Importe</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(it, i) in previewPayload?.items" :key="i">
                    <td class="text-left font-weight-medium">{{ it?.codigo ?? '—' }}</td>
                    <td class="text-left">{{ it?.producto_nombre ?? '—' }}</td>
                    <td class="text-center">{{ Number(it?.cantidad ?? 0).toFixed(2) }}</td>
                    <td class="text-right">Bs. {{ Number(it?.precio_unitario ?? 0).toFixed(2) }}</td>
                    <td class="text-right font-weight-bold">Bs. {{ Number(it?.importe ?? 0).toFixed(2) }}</td>
                  </tr>
                </tbody>
              </VTable>
              <div v-if="!previewPayload?.items || previewPayload.items.length === 0" class="text-center text-disabled pa-4">
                No hay productos registrados
              </div>
            </VCardText>
          </VCard>

          <VCard class="mb-6" variant="outlined">
            <VCardText>
              <VRow>
                <VCol cols="12" md="4">
                  <div class="text-right">
                    <span class="text-caption font-weight-bold">TOTAL Bs:</span>
                  </div>
                </VCol>
                <VCol cols="12" md="4">
                  <div class="text-right">
                    <span class="text-h6 font-weight-bold text-success">
                      Bs. {{ Number(previewPayload?.total_importe ?? 0).toFixed(2) }}
                    </span>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>

          <VCard v-if="previewPayload?.observaciones" class="mb-6" variant="outlined">
            <VCardTitle class="text-h6">Observaciones</VCardTitle>
            <VCardText>
              <div class="text-body-2">{{ previewPayload.observaciones }}</div>
            </VCardText>
          </VCard>

          <VRow class="mt-12">
            <VCol cols="6" class="text-center">
              <VDivider class="mb-2" />
              <div class="text-caption">Entregado por: {{ previewPayload?.persona_entrega || '_____' }}</div>
            </VCol>
            <VCol cols="6" class="text-center">
              <VDivider class="mb-2" />
              <div class="text-caption">Recibido por: {{ previewPayload?.persona_recibe || '_____' }}</div>
            </VCol>
          </VRow>
        </div>
      </VCard>
    </VDialog>

    <VSnackbar v-model="errorSnack" color="error" timeout="4000">
      {{ errorMsg }}
    </VSnackbar>
    <VSnackbar v-model="successSnack" color="success" timeout="3000">
      {{ successMsg }}
    </VSnackbar>
  </VCard>
</template>

<style scoped>
.area-autocomplete :deep(.v-list-item--active .v-list-item-title) {
  color: #9e9e9e !important;
}

.area-autocomplete :deep(.v-list-item--active) {
  background-color: rgba(158,158,158,0.15) !important;
}

.invalid-row {
  border: 1px solid #ff5252;
  border-radius: 8px;
}

.invalid-hint {
  color: #ff5252;
  font-size: 0.8rem;
  margin-top: 6px;
}
</style>
