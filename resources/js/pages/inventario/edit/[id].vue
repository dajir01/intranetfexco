<script setup>
import { useRoute, useRouter } from 'vue-router'
import { ref, onMounted, computed, watch, onUnmounted } from 'vue'
import InvoiceProductEditView from './InvoiceProductEditView.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'

const route = useRoute()
const router = useRouter()
const id = route.params.id

const loading = ref(false)
const saving = ref(false)
const ingreso = ref(null)
const error = ref('')
const errorSnack = ref(false)
const errorMsg = ref('')

// Datos del invoice adaptados desde el ingreso cargado
const invoice = ref({
  id: '',
  numero: '',
  issuedDate: '',
  total: 0,
  client: {
    name: '',
    company: '',
  },
})

const purchasedProducts = ref([])
const salesperson = ref('')
const note = ref('')
const facturaNumero = ref('')
const date = ref('')
const toggleCheckboxOne = ref(true)
const selectedProveedorId = ref(null)
const selectedProveedor = ref(null)

// Proveedores
const proveedores = ref([])
const proveedorSearch = ref('')
const nuevoProveedorNombre = ref('')
const creandoProveedor = ref(false)
const dialogNuevoProveedor = ref(false)
const csrfToken = ref('')
const proveedorMenu = ref(false)
const proveedorSelect = ref(null)

// Refs a las filas de productos
const productRows = ref([])

// Totales
const lineTotals = ref([])
const totalImporte = computed(() => lineTotals.value.reduce((sum, v) => sum + (Number(v) || 0), 0))

const isInputEnabled = ref(toggleCheckboxOne.value)
const invalidRows = ref(new Set())
const invalidRowMessages = ref({})
let validationTimer = null

const onLineTotal = (index, amount) => {
  lineTotals.value[index] = Number(amount) || 0
  recomputeInvalidRows()
}

const fetchProveedores = async () => {
  try {
    const url = proveedorSearch.value ? `/inventario/proveedores?q=${encodeURIComponent(proveedorSearch.value)}` : '/inventario/proveedores'
    const r = await fetch(url, { credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      proveedores.value = j.data || []
    }
  } catch (e) {
    console.error('Error cargando proveedores', e)
  }
}

const crearProveedor = async () => {
  if (!nuevoProveedorNombre.value.trim()) return
  creandoProveedor.value = true
  try {
    const r = await fetch('/inventario/proveedores', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      credentials: 'same-origin',
      body: JSON.stringify({ nombre: nuevoProveedorNombre.value.trim() }),
    })
    if (r.ok) {
      const j = await r.json()
      proveedores.value.push(j.data)
      invoice.value.client = {
        name: j.data.nombre,
        company: j.data.nombre,
      }
      dialogNuevoProveedor.value = false
      nuevoProveedorNombre.value = ''
      selectedProveedor.value = j.data
    }
  } catch (e) {
    console.error('Error creando proveedor', e)
  } finally {
    creandoProveedor.value = false
  }
}

watch(selectedProveedor, (proveedor) => {
  if (!proveedor) {
    invoice.value.client = { name: '', company: '' }
    selectedProveedorId.value = null
    return
  }
  invoice.value.client = {
    name: proveedor.nombre,
    company: proveedor.nombre,
  }
  selectedProveedorId.value = proveedor.id_proveedores
  proveedorMenu.value = false
}, { immediate: true, deep: true })

const onProveedorClear = () => {
  selectedProveedor.value = null
  selectedProveedorId.value = null
  proveedorSearch.value = ''
  fetchProveedores()
  proveedorMenu.value = true
  requestAnimationFrame(() => {
    proveedorSelect.value?.focus?.()
  })
}

watch(toggleCheckboxOne, val => {
  isInputEnabled.value = !!val
})

const addItem = () => {
  purchasedProducts.value.push({
    producto_id: null,
    nombre: '',
    title: '',
    cantidad: 1,
    costo: 0,
    subtotal: 0,
    area_id: null,
    tipo: '',
    unidad_medida: '',
    codigo: '',
  })
  lineTotals.value.push(0)
  recomputeInvalidRows()
}

const removeProduct = idx => {
  purchasedProducts.value.splice(idx, 1)
  lineTotals.value.splice(idx, 1)
  recomputeInvalidRows()
}

const recomputeInvalidRows = () => {
  const snapshots = (productRows.value || [])
    .map((c, idx) => ({ idx, data: c?.getSnapshot?.() }))
    .filter(x => !!x.data)

  const isComplete = it => !!it.asignacion_id && Number(it.cantidad) > 0 && Number(it.precio) > 0
  const isTouched = it => !!it.producto_id || !!it.asignacion_id || !!it.area_id || Number(it.cantidad) > 0 || Number(it.precio) > 0
  const getMissing = it => {
    const missing = []
    if (!it.asignacion_id) missing.push('asignación')
    if (!(Number(it.cantidad) > 0)) missing.push('cantidad')
    if (!(Number(it.precio) > 0)) missing.push('precio')
    return missing
  }

  const invalidDetails = snapshots
    .filter(x => isTouched(x.data) && !isComplete(x.data))
    .map(x => ({ idx: x.idx, missing: getMissing(x.data) }))

  const invalidIdx = invalidDetails.map(x => x.idx)
  invalidRows.value = new Set(invalidIdx)
  invalidRowMessages.value = Object.fromEntries(
    invalidDetails.map(d => [d.idx, `Falta: ${d.missing.join(', ')}`])
  )
}

const capitalizedLabel = label => (label ? 'Sí' : 'No')

const fetchData = async () => {
  loading.value = true
  error.value = ''
  try {
    const res = await fetch(`/inventario/edisingreso/${id}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)
    const json = await res.json()
    ingreso.value = json

    // Prellenar invoice
    invoice.value.id = json.numero ? `NI-${String(json.numero).padStart(6, '0')}` : ''
    invoice.value.numero = json.numero ?? ''
    invoice.value.issuedDate = json.fecha_ingreso ?? ''

    salesperson.value = json.persona_entrega ?? ''
    note.value = json.Observaciones ?? ''
    facturaNumero.value = json.factura_numero ?? ''
    date.value = json.fecha_factura ?? ''
    toggleCheckboxOne.value = !!json.factura_numero

    // Convertir detalles a purchasedProducts con estructura completa
    purchasedProducts.value = (json.detalles || []).map(d => ({
      // Datos básicos del item
      detalle_id: d.id_detalle_ingreso || d.id || null,
      producto_id: d.producto_id || null,
      asignacion_id: d.asignacion_id || null,
      nombre: d.producto_nombre || '',
      title: d.producto_nombre || '',
      
      // Datos de la asignación y área
      codigo: d.codigo || '',
      area_id: d.area_id || null,
      area_nombre: d.area_nombre || '',
      
      // Datos del producto
      tipo: d.tipo || '',
      unidad_medida: d.unidad_medida || '',
      codigo_barras: d.codigo_barras || '',
      descripcion: d.descripcion || '',
      
      // Datos financieros
      cantidad: Number(d.cantidad) || 0,
      precio: Number(d.precio) || 0,
      costo: Number(d.costo) || 0,
      importe: Number(d.importe) || 0,
    }))

    lineTotals.value = purchasedProducts.value.map(p => Number(p.importe) || 0)
    
    // Establecer proveedor - asegurar que esté en la lista
    if (json.proveedor_id) {
      let proveedor = proveedores.value.find(p => p.id_proveedores === json.proveedor_id)
      
      if (!proveedor && json.proveedor_nombre) {
        // Agregar proveedor a la lista si no existe
        proveedor = {
          id_proveedores: json.proveedor_id,
          nombre: json.proveedor_nombre
        }
        proveedores.value.push(proveedor)
      }
      
      // Cerrar el menú y establecer el proveedor
      proveedorMenu.value = false
      await new Promise(resolve => setTimeout(resolve, 100))
      selectedProveedor.value = proveedor
    }
  } catch (e) {
    error.value = String(e?.message || e)
  } finally {
    loading.value = false
  }
}

const getCsrfToken = async () => {
  try {
    const res = await fetch('/csrf-token', { credentials: 'same-origin' })
    if (!res.ok) return null
    const json = await res.json()
    return json.token
  } catch {
    return null
  }
}

const save = async () => {
  if (!selectedProveedorId.value) {
    errorMsg.value = 'Seleccione un proveedor antes de guardar.'
    errorSnack.value = true
    return
  }
  if (!salesperson.value || salesperson.value.trim().length < 3) {
    errorMsg.value = 'Ingrese un nombre válido en "Entregado Por".'
    errorSnack.value = true
    return
  }

  const snapshots = (productRows.value || [])
    .map((c, idx) => ({ idx, data: c?.getSnapshot?.() }))
    .filter(x => !!x.data)

  const isComplete = it => !!it.asignacion_id && Number(it.cantidad) > 0 && Number(it.precio) > 0
  const isTouched = it => !!it.producto_id || !!it.asignacion_id || !!it.area_id || Number(it.cantidad) > 0 || Number(it.precio) > 0
  const getMissing = it => {
    const missing = []
    if (!it.asignacion_id) missing.push('asignación')
    if (!(Number(it.cantidad) > 0)) missing.push('cantidad')
    if (!(Number(it.precio) > 0)) missing.push('precio')
    return missing
  }

  const complete = snapshots.filter(x => isComplete(x.data))
  const invalidDetails = snapshots
    .filter(x => isTouched(x.data) && !isComplete(x.data))
    .map(x => ({ idx: x.idx, missing: getMissing(x.data) }))

  const invalidIdx = invalidDetails.map(x => x.idx)
  invalidRows.value = new Set(invalidIdx)
  invalidRowMessages.value = Object.fromEntries(
    invalidDetails.map(d => [d.idx, `Falta: ${d.missing.join(', ')}`])
  )

  if (invalidIdx.length > 0 || complete.length === 0) {
    errorMsg.value = invalidIdx.length > 0
      ? 'Corrija los ítems resaltados antes de guardar.'
      : 'Agregue al menos un ítem completo para guardar.'
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
    proveedor_id: selectedProveedorId.value,
    factura_numero: isInputEnabled.value ? (facturaNumero.value || '') : '',
    fecha_ingreso: invoice.value?.issuedDate || '',
    fecha_factura: isInputEnabled.value ? (date.value || '') : '',
    persona_recibe: ingreso.value?.persona_recibe || '',
    persona_entrega: salesperson.value || '',
    Observaciones: note.value || '',
    importe: Number(totalImporte.value) || 0,
  }

  saving.value = true
  try {
    const token = await getCsrfToken()
    
    // 1. Obtener snapshots de todas las filas
    const detailSnapshots = (productRows.value || [])
      .map(c => c?.getSnapshot?.())
      .filter(x => !!x)

    // 2. Procesar operaciones pendientes (crear productos/asignaciones) antes de guardar
    for (let i = 0; i < detailSnapshots.length; i++) {
      const snap = detailSnapshots[i]
      const pending = snap.pendingOperations || {}

      // Si hay operación de crear producto
      if (pending.createProduct) {
        const prodRes = await fetch('/inventario/productos', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
          },
          credentials: 'same-origin',
          body: JSON.stringify(pending.createProduct),
        })
        if (!prodRes.ok) throw new Error('Error creando producto')
        const prodData = await prodRes.json()
        snap.producto_id = prodData.id_producto || prodData.data?.id_producto
      }

      // Si hay operación de crear asignación
      if (pending.createAsignacion) {
        const asigPayload = {
          producto_id: snap.producto_id || pending.createAsignacion.producto_id,
          area_id: pending.createAsignacion.area_id,
          tipo: pending.createAsignacion.tipo,
        }
        const asigRes = await fetch('/inventario/asignaciones-productos', {
          method: 'POST',
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token,
          },
          credentials: 'same-origin',
          body: JSON.stringify(asigPayload),
        })
        if (!asigRes.ok) throw new Error('Error creando asignación')
        const asigData = await asigRes.json()
        snap.asignacion_id = asigData.data?.id_asignacion || asigData.id_asignacion
      }

      // Si asignacion_id sigue siendo string temporal, intentar obtener asignación existente
      if (typeof snap.asignacion_id === 'string' && snap.asignacion_id.startsWith('TEMP_')) {
        // Buscar asignación existente por producto_id y area_id
        if (snap.producto_id && snap.area_id) {
          const asigPayload = {
            producto_id: snap.producto_id,
            area_id: snap.area_id,
            tipo: snap.tipo,
          }
          const asigRes = await fetch('/inventario/asignaciones-productos', {
            method: 'POST',
            headers: {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
              'X-CSRF-TOKEN': token,
            },
            credentials: 'same-origin',
            body: JSON.stringify(asigPayload),
          })
          if (asigRes.ok) {
            const asigData = await asigRes.json()
            snap.asignacion_id = asigData.data?.id_asignacion || asigData.id_asignacion
          }
        }
      }
    }

    // 3. Actualizar encabezado del ingreso
    const res = await fetch(`/inventario/ingresos/${id}`, {
      method: 'PATCH',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
    if (!res.ok) throw new Error('HTTP ' + res.status)

    // 4. Enviar edición de detalles con asignacion_id reales
    const detailsToUpdate = detailSnapshots
      .map(s => ({
        detalle_id: s.detalle_id || null,
        asignacion_id: typeof s.asignacion_id === 'number' ? s.asignacion_id : parseInt(s.asignacion_id) || 0,
        cantidad: Number(s.cantidad) || 0,
        precio: Number(s.precio) || 0,
        costo: Number(s.costo) || 0,
        importe: Number(s.importe) || 0,
      }))
      .filter(it => it.detalle_id && it.asignacion_id > 0)

    if (detailsToUpdate.length > 0) {
      const res2 = await fetch(`/inventario/ingresos/${id}/detalles`, {
        method: 'PATCH',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ items: detailsToUpdate }),
      })
      if (!res2.ok) throw new Error('HTTP ' + res2.status + ' al actualizar detalles')
    }
    
    router.push({ path: '/inventario/ingreso', query: { success: 'Ingreso actualizado correctamente' } })
  } catch (e) {
    errorMsg.value = 'No se pudo guardar: ' + String(e?.message || e)
    errorSnack.value = true
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  // Primero cargar proveedores
  await fetchProveedores()
  
  // Luego cargar datos del ingreso
  await fetchData()
  
  // Cargar CSRF token
  try {
    const t = await fetch('/csrf-token')
    if (t.ok) {
      const j = await t.json()
      csrfToken.value = j.token || ''
    }
  } catch (_) {}

  validationTimer = setInterval(recomputeInvalidRows, 400)
})

onUnmounted(() => {
  if (validationTimer) {
    clearInterval(validationTimer)
    validationTimer = null
  }
})
</script>

<template>
  <VCard class="pa-6 pa-sm-12">
    <div v-if="loading" class="text-center pa-6">Cargando...</div>
    <div v-else>
      <!-- SECTION Header -->
      <div class="d-flex flex-wrap justify-space-between flex-column rounded bg-var-theme-background flex-sm-row gap-6 pa-6 mb-6">
        <!-- 👉 Left Content -->
        <div>
          <div class="d-flex align-center app-logo mb-6">
            <!-- 👉 Logo -->
            <VNodeRenderer :nodes="themeConfig.app.logo" />

            <!-- 👉 Title -->
            <h6 class="app-logo-title">
              {{ themeConfig.app.title }}
            </h6>
          </div>
        </div>

        <!-- 👉 Right Content -->
        <div class="d-flex flex-column gap-2">
          <!-- 👉 Invoice Id -->
          <div class="d-flex align-start align-sm-center gap-x-4 font-weight-medium text-lg flex-column flex-sm-row">
            <span
              class="text-high-emphasis text-sm-end"
              style="inline-size: 5.625rem ;"
            >N°:</span>
            <span>
              <AppTextField
                id="invoice-id"
                v-model="invoice.id"
                disabled
                prefix="#"
                style="inline-size: 9.5rem;"
              />
            </span>
            <span
              class="text-high-emphasis text-sm-end"
              style="inline-size: 5.625rem;"
            >Fecha Ingreso:</span>

            <span style="inline-size: 9.5rem;">
              <AppDateTimePicker
                id="issued-date"
                v-model="invoice.issuedDate"
                placeholder="YYYY-MM-DD HH:mm"
                :config="{ enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i', position: 'auto right' }"
              />
            </span>
          </div>
        </div>
      </div>
      <!-- !SECTION -->

      <VRow>
        <VCol class="text-no-wrap">
          <h6 class="text-h6 mb-4">
            Datos del Proveedor:
          </h6>

          <VAutocomplete
            id="proveedor-select"
            ref="proveedorSelect"
            v-model="selectedProveedor"
            v-model:search-input="proveedorSearch"
            v-model:menu="proveedorMenu"
            :items="proveedores"
            item-title="nombre"
            item-value="id_proveedores"
            return-object
            placeholder="Seleccione proveedor"
            clearable
            class="mb-4 proveedor-autocomplete"
            hide-no-data
            style="inline-size: 22rem;"
            @update:search-input="fetchProveedores"
            @click:clear="onProveedorClear"
          />
          <div class="d-flex gap-2 mb-4">
            <VBtn
              size="small"
              variant="tonal"
              color="primary"
              @click="dialogNuevoProveedor = true"
            >
              Nuevo
            </VBtn>
            <VBtn
              size="small"
              variant="outlined"
              @click="fetchProveedores"
            >
              Recargar
            </VBtn>
            <VBtn
              size="small"
              variant="text"
              color="secondary"
              @click="selectedProveedorId = null"
            >
              Limpiar
            </VBtn>
          </div>
          <VDialog
            v-model="dialogNuevoProveedor"
            max-width="400"
          >
            <VCard>
              <VCardTitle>Nuevo Proveedor</VCardTitle>
              <VCardText>
                <AppTextField
                  v-model="nuevoProveedorNombre"
                  label="Nombre"
                  required
                />
              </VCardText>
              <VCardActions class="justify-end">
                <VBtn
                  variant="text"
                  @click="dialogNuevoProveedor = false"
                >
                  Cancelar
                </VBtn>
                <VBtn
                  :loading="creandoProveedor"
                  color="primary"
                  @click="crearProveedor"
                >
                  Guardar
                </VBtn>
              </VCardActions>
            </VCard>
          </VDialog>
        </VCol>

        <VCol class="text-no-wrap">
          <h6 class="text-h6 mb-4">
            Tiene Factura?
          </h6>
          <VCheckbox
            v-model="toggleCheckboxOne"
            :label="capitalizedLabel(toggleCheckboxOne)"
            true-icon="tabler-check"
            false-icon="tabler-x"
          />
          <AppTextField
            v-model="facturaNumero"
            :disabled="!isInputEnabled"
            placeholder="N° de Factura"
            clearable
          />
          <br>
          <h6 class="text-h6 mb-4">
            Fecha de Factura o Recibo:
          </h6>
          <AppDateTimePicker
            v-model="date"
            placeholder="Seleccione la Fecha"
          />
        </VCol>
      </VRow>
      <div>
        <h6 class="text-h6 mb-2">
          Descripcion:
        </h6>
        <VTextarea
          id="descripcion"
          v-model="note"
          placeholder="Escriba la descripción aquí..."
          :rows="2"
        />
      </div>

      <VDivider class="my-6 border-dashed" />
      <!-- 👉 Add purchased products -->
      <div class="add-products-form">
        <div
          v-for="(product, index) in purchasedProducts"
          :key="index"
          class="mb-4"
          :class="{ 'invalid-row': invalidRows.has(index) }"
        >
          <InvoiceProductEditView
            ref="productRows"
            :id="index"
            :data="product"
            :tiene-factura="toggleCheckboxOne"
            @total-amount="amount => onLineTotal(index, amount)"
            @remove-product="removeProduct"
          />
          <div v-if="invalidRows.has(index)" class="invalid-hint">
            {{ invalidRowMessages[index] || 'Complete los campos obligatorios.' }}
          </div>
        </div>

        <VBtn
          size="small"
          prepend-icon="tabler-plus"
          @click="addItem"
        >
          Añadir Producto
        </VBtn>
      </div>

      <VDivider class="my-6 border-dashed" />

      <!-- 👉 Total Amount -->
      <div class="d-flex justify-space-between flex-wrap flex-column flex-sm-row">
        <div class="mb-6 mb-sm-0">
          <div class="d-flex align-center mb-4">
            <h6 class="text-h6 me-2">
              Recibido por:
            </h6>
            <AppTextField
              id="recibido-por"
              :model-value="ingreso?.persona_recibe || ''"
              style="inline-size: 12rem;"
              disabled
              readonly
            />
          </div>
        </div>
        <div class="mb-6 mb-sm-0">
          <div class="d-flex align-center mb-4">
            <h6 class="text-h6 me-2">
              Entregado Por:
            </h6>
            <AppTextField
              id="salesperson"
              v-model="salesperson"
              style="inline-size: 12rem;"
              placeholder="Ingrese un nombre"
              :rules="[v => !!v || 'El nombre es requerido', v => (v && v.trim().length >= 3) || 'Mínimo 3 caracteres']"
              required
            />
          </div>
        </div>

        <div>
          <table class="w-100">
            <tbody>
              <tr>
                <td class="pe-16">
                  Total:
                </td>
                <td :class="$vuetify.locale.isRtl ? 'text-start' : 'text-end'">
                  <h6 class="text-h6">
                    Bs. {{ totalImporte.toFixed(2) }}
                  </h6>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
      <div class="d-flex justify-center mt-4 gap-3">
        <VBtn color="primary" :loading="saving" :disabled="saving" @click="save">
          Guardar Cambios
          <VIcon end icon="tabler-device-floppy" />
        </VBtn>
        <VBtn variant="tonal" @click="router.back()">
          Cancelar
        </VBtn>
      </div>
    </div>

    <!-- Snackbar de error -->
    <VSnackbar v-model="errorSnack" color="error" timeout="4000">
      {{ errorMsg }}
    </VSnackbar>
  </VCard>
</template>

<style scoped>
.proveedor-autocomplete :deep(.v-list-item--active .v-list-item-title) {
  color: #9e9e9e !important;
}
.proveedor-autocomplete :deep(.v-list-item--active) {
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
