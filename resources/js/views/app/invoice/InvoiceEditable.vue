<script setup>
import InvoiceProductEdit from './InvoiceProductEdit.vue'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { themeConfig } from '@themeConfig'
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useRouter } from 'vue-router'

const props = defineProps({
  data: {
    type: null,
    required: true,
  },
})

const emit = defineEmits([
  'push',
  'remove',
])

const authStore = useAuthStore()
const router = useRouter()

const invoice = ref(props.data.invoice)
const salesperson = ref(props.data.salesperson)
const thanksNote = ref(props.data.thanksNote)
const note = ref(props.data.note)
const authenticatedUser = computed(() => authStore.user?.nombre_usuario || authStore.user?.nick_usuario || 'Usuario')

// 👉 Proveedores
const proveedores = ref([])
const proveedorSearch = ref('')
const nuevoProveedorNombre = ref('')
const creandoProveedor = ref(false)
const dialogNuevoProveedor = ref(false)
const selectedProveedorId = ref(null)
const csrfToken = ref('')
const proveedorMenu = ref(false)
const proveedorSelect = ref(null)
const date = ref('')
const facturaNumero = ref('')

// Refs a las filas de productos para obtener snapshot de datos
const productRows = ref([])

// UI de previsualización y errores (fullscreen dialog)
const isDialogVisible = ref(false)
const previewPayload = ref(null)
const previewResponse = ref(null)
// Lista de items para previsualización (prioriza respuesta del servidor)
const previewItems = computed(() => {
  const respItems = previewResponse.value?.received?.items
  if (Array.isArray(respItems) && respItems.length) return respItems
  const payloadItems = previewPayload.value?.items
  if (Array.isArray(payloadItems)) return payloadItems
  return []
})
const errorSnack = ref(false)
const errorMsg = ref('')
const successSnack = ref(false)
const successMsg = ref('')
const submitting = ref(false)
const invalidRows = ref(new Set())
const invalidRowMessages = ref({})
let validationTimer = null

// Asegurar que el número de ingreso esté asignado; si falta, intentar traerlo
const ensureIngresoNumero = async () => {
  if (invoice.value?.numero) return true
  try {
    const r = await fetch('/inventario/ingresos/next-numero', { credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      const next = j?.data?.next ?? j?.next ?? j?.numero ?? null
      if (next) {
        invoice.value.numero = next
        const padded = String(next).padStart(6, '0')
        invoice.value.id = `NI-${padded}`
        return true
      }
    }
  } catch (_) {}
  return false
}

// Placeholder de confirmación de guardado (implementación vendrá luego)
const confirming = ref(false)
const confirmSave = async () => {
  confirming.value = true
  try {
    // Verificar/asegurar número de ingreso
    const hasNumero = await ensureIngresoNumero()
    if (!hasNumero) {
      errorMsg.value = 'Número de ingreso no asignado. Recargue o intente de nuevo.'
      errorSnack.value = true
      return
    }
    // Validación igual que submitPreview (reutilizamos estado actual)
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
    if (invalidIdx.length > 0 || complete.length === 0) {
      errorMsg.value = invalidIdx.length > 0
        ? 'Corrija los ítems resaltados antes de guardar.'
        : 'Agregue al menos un ítem completo para guardar.'
      errorSnack.value = true
      return
    }

    // Asegurar CSRF
    if (!csrfToken.value) {
      try {
        const t = await fetch('/csrf-token', { credentials: 'same-origin' })
        if (t.ok) {
          const j = await t.json()
          csrfToken.value = j.token || ''
        }
      } catch (_) {}
    }

    // Construir payload (similar a preview)
    const payload = {
      numero: invoice.value?.numero || null,
      id_visual: invoice.value?.id || '',
      fecha_ingreso: invoice.value?.issuedDate || '',
      proveedor_id: selectedProveedorId.value,
      proveedor_nombre: invoice.value?.client?.name || '',
      recibido_por: authenticatedUser.value || '',
      entregado_por: salesperson.value || '',
      tiene_factura: !!toggleCheckboxOne.value,
      factura_numero: isInputEnabled.value ? (facturaNumero.value || '') : '',
      fecha_factura: isInputEnabled.value ? (date.value || '') : '',
      descripcion: note.value || '',
      total_importe: Number(totalImporte.value) || 0,
      items: complete.map(x => ({
        asignacion_id: x.data.asignacion_id || null,
        producto_nombre: x.data.nombre || x.data.title || '',
        codigo: x.data.codigo || '',
        cantidad: x.data.cantidad,
        precio: x.data.precio,
        costo: x.data.costo,
        importe: x.data.importe,
      })),
    }

    const r = await fetch('/inventario/ingresos', {
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
      // Mostrar mensaje de éxito y redirigir
      router.push({
        path: '/inventario/ingreso',
        query: { success: `Ingreso #${j?.data?.numero ?? ''} guardado correctamente` }
      })
      // Opcional: también puedes usar a nivel global un snackbar
    } else {
      const txt = await r.text()
      errorMsg.value = `Error al guardar: ${txt}`
      errorSnack.value = true
    }
  } finally {
    confirming.value = false
  }
}

const fetchProveedores = async () => {
  try {
    const url = proveedorSearch.value ? `/inventario/proveedores?q=${encodeURIComponent(proveedorSearch.value)}` : '/inventario/proveedores'
    const r = await fetch(url)
    if (r.ok) {
      const j = await r.json()

      proveedores.value = j.data || []
    }
  } catch (e) {
    // Silenciar errores
  }
}

onMounted(async () => {
  // Cargar lista inicial y token CSRF para POST
  fetchProveedores()
  try {
    const t = await fetch('/csrf-token')
    if (t.ok) {
      const j = await t.json()

      csrfToken.value = j.token || ''
    }
  } catch (_) {}
  // Intentar asegurar número al montar
  await ensureIngresoNumero()
})

const crearProveedor = async () => {
  if (!nuevoProveedorNombre.value.trim()) return
  creandoProveedor.value = true
  try {
    
    const r = await fetch('/inventario/proveedores', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({ nombre: nuevoProveedorNombre.value.trim() }),
    })
    
    if (r.ok) {
      const j = await r.json()

      proveedores.value.push(j.data)

      invoice.value.client = {
        name: j.data.nombre,
        company: j.data.nombre,
        companyEmail: '',
        contact: '',
        address: '',
        country: '',
      }

      dialogNuevoProveedor.value = false
      nuevoProveedorNombre.value = ''
      selectedProveedorId.value = j.data.id_proveedores
    }
  } catch (e) {
    // Silenciar errores
  } finally {
    creandoProveedor.value = false
  }
}

// Mapear selección de id a objeto invoice.client para mantener compatibilidad visual
watch(selectedProveedorId, id => {
  if (!id) {
    
    // Al limpiar, reiniciar cliente y abrir el menú para permitir nueva búsqueda inmediata
    invoice.value.client = {
      name: '',
      company: '',
      companyEmail: '',
      contact: '',
      address: '',
      country: '',
    }
    proveedorMenu.value = true
    
    // Foco en el campo
    requestAnimationFrame(() => {
      proveedorSelect.value?.focus?.()
    })
    
    return
  }
  const p = proveedores.value.find(x => x.id_proveedores === id)
  if (p) {
    invoice.value.client = {
      name: p.nombre,
      company: p.nombre,
      companyEmail: '',
      contact: '',
      address: '',
      country: '',
    }
  }
  
  // Limpiar texto de búsqueda para evitar que hide-selected deje la lista vacía
  if (proveedorSearch.value) {
    proveedorSearch.value = ''
    fetchProveedores()
  }
  
  // Tras seleccionar cerramos el menú.
  proveedorMenu.value = false
})

const onProveedorClear = () => {
  selectedProveedorId.value = null
  proveedorSearch.value = ''
  fetchProveedores()
  proveedorMenu.value = true
  requestAnimationFrame(() => {
    proveedorSelect.value?.focus?.()
  })
}

// Establecer fecha actual (YYYY-MM-DD HH:mm:ss) si no viene ya definida
onMounted(() => {
  if (!invoice.value.issuedDate) {
    const d = new Date()
    const pad = n => String(n).padStart(2, '0')
    
    const formatted = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`

    invoice.value.issuedDate = formatted
  }
})

// 👉 Add item function
const addItem = () => {
  emit('push', {
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
  // Inicializar total en 0 para nueva línea
  lineTotals.value.push(0)
  // Revalidar al agregar
  recomputeInvalidRows()
}

const removeProduct = id => {
  emit('remove', id)
  // Remover total de la línea eliminada
  lineTotals.value.splice(id, 1)
  // Revalidar al eliminar
  recomputeInvalidRows()
}

const toggleCheckboxOne = ref(true)

// Mostrar "Sí" / "No" en lugar de True/False
const capitalizedLabel = label => (label ? 'Sí' : 'No')

const includeFiles = ref(true)
const isInputEnabled = ref(toggleCheckboxOne.value)

// Totales individuales de cada producto (importe)
const lineTotals = ref([])

// Registrar el total emitido desde el hijo
const onLineTotal = (index, amount) => {
  lineTotals.value[index] = Number(amount) || 0
  // Validación automática al cambiar importe (cantidad/precio)
  recomputeInvalidRows()
}

// Total acumulado
const totalImporte = computed(() => lineTotals.value.reduce((sum, v) => sum + (Number(v) || 0), 0))

// Habilitar/deshabilitar input de N° de Factura según el checkbox
watch(toggleCheckboxOne, val => {
  isInputEnabled.value = !!val
})

// Recalcular automáticamente filas inválidas
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

onMounted(() => {
  // Validación continua sin botones
  validationTimer = setInterval(recomputeInvalidRows, 400)
})

onUnmounted(() => {
  if (validationTimer) {
    clearInterval(validationTimer)
    validationTimer = null
  }
})

// Enviar previsualización de ingreso
const submitPreview = async () => {
  // Validaciones mínimas
  const hasNumero = await ensureIngresoNumero()
  if (!hasNumero) {
    errorMsg.value = 'Número de ingreso no asignado. Recargue o intente de nuevo.'
    errorSnack.value = true
    return
  }
  if (!selectedProveedorId.value) {
    errorMsg.value = 'Seleccione un proveedor antes de registrar.'
    errorSnack.value = true
    return
  }
  if (!salesperson.value || salesperson.value.trim().length < 3) {
    errorMsg.value = 'Ingrese un nombre válido en "Entregado Por".'
    errorSnack.value = true
    return
  }

  // Recolectar items desde las filas hijas y validar completos
  const snapshots = (productRows.value || [])
    .map((c, idx) => ({ idx, data: c?.getSnapshot?.() }))
    .filter(x => !!x.data)

  const isComplete = it => !!it.producto_id && !!it.area_id && Number(it.cantidad) > 0 && Number(it.precio) > 0
  const isTouched = it => !!it.producto_id || !!it.area_id || Number(it.cantidad) > 0 || Number(it.precio) > 0
  const getMissing = it => {
    const missing = []
    if (!it.producto_id) missing.push('producto')
    if (!it.area_id) missing.push('área')
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

  if (invalidIdx.length > 0) {
    errorMsg.value = 'Complete los datos de los productos, área, cantidad y precio (> 0) en los ítems resaltados.'
    errorSnack.value = true
    return
  }

  if (complete.length === 0) {
    errorMsg.value = 'Agregue al menos un ítem completo (producto, área, cantidad y precio > 0).'
    errorSnack.value = true
    return
  }

  const items = complete.map(x => ({
    asignacion_id: x.data.asignacion_id || null,
    producto_nombre: x.data.nombre || x.data.title || '',
    codigo: x.data.codigo || '',
    cantidad: x.data.cantidad,
    precio: x.data.precio,
    costo: x.data.costo,
    importe: x.data.importe,
  }))

  // Asegurar token CSRF cargado
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
    numero: invoice.value?.numero || null,
    id_visual: invoice.value?.id || '',
    fecha_ingreso: invoice.value?.issuedDate || '',
    proveedor_id: selectedProveedorId.value,
    proveedor_nombre: invoice.value?.client?.name || '',
    recibido_por: authenticatedUser.value || '',
    entregado_por: salesperson.value || '',
    tiene_factura: !!toggleCheckboxOne.value,
    factura_numero: isInputEnabled.value ? (facturaNumero.value || '') : '',
    fecha_factura: isInputEnabled.value ? (date.value || '') : '',
    descripcion: note.value || '',
    total_importe: Number(totalImporte.value) || 0,
    items,
  }

  try {
    submitting.value = true
    const r = await fetch('/inventario/ingresos/preview', {
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
      previewPayload.value = payload
      previewResponse.value = j
      isDialogVisible.value = true
    } else {
      const txt = await r.text()
      errorMsg.value = `Error en preview: ${txt}`
      errorSnack.value = true
    }
  } catch (e) {
    errorMsg.value = `Fallo de red en preview: ${e}`
    errorSnack.value = true
  } finally {
    submitting.value = false
  }
}
</script>

<template>
  <VCard class="pa-6 pa-sm-12">
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
              disabled
              readonly
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
          v-model="selectedProveedorId"
          v-model:search-input="proveedorSearch"
          v-model:menu="proveedorMenu"
          :items="proveedores"
          item-title="nombre"
          item-value="id_proveedores"
          placeholder="Seleccione proveedor"
          clearable
          class="mb-4 proveedor-autocomplete"
          :hide-selected="proveedores.length > 1"
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
        v-for="(product, index) in props.data.purchasedProducts"
        :key="product.title"
        class="mb-4"
        :class="{ 'invalid-row': invalidRows.has(index) }"
      >
        <InvoiceProductEdit
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
            v-model="authenticatedUser"
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
    <div class="d-flex justify-center mt-4">
      <VBtn color="primary" :loading="submitting" :disabled="submitting" @click="submitPreview">
        Registrar Entrada
        <VIcon end icon="tabler-checkbox" />
      </VBtn>
    </div>

    <!-- Se elimina el diálogo modal simple y se usa fullscreen -->
    <VDialog
      v-model="isDialogVisible"
      fullscreen
      :scrim="false"
      transition="dialog-bottom-transition"
    >
      <!-- Dialog Content -->
      <VCard>
        <!-- Toolbar -->
        <div>
          <VToolbar color="primary">
            <VBtn
              icon
              variant="plain"
              @click="isDialogVisible = false"
            >
              <VIcon
                color="white"
                icon="tabler-x"
              />
            </VBtn>

            <VToolbarTitle>Previsualización de Ingreso</VToolbarTitle>

            <VSpacer />

            <VToolbarItems>
              <VBtn
                variant="flat"
                color="error"
                @click="isDialogVisible = false"
              >
                Cerrar
              </VBtn>
              <VBtn
                variant="flat"
                color="success"
                :loading="confirming"
                :disabled="confirming"
                @click="confirmSave"
              >
                Confirmar Guardado
              </VBtn>
            </VToolbarItems>
          </VToolbar>
        </div>

        <!-- Contenido de previsualización estilo nota de recepción -->
        <div class="pa-8">
          <!-- Encabezado -->
          <div class="d-flex justify-space-between align-start mb-8">
            <div>
              <div class="text-h6 font-weight-bold">{{ themeConfig.app.title }}</div>
            </div>
            <div class="text-right">
              <div class="text-body-1 font-weight-bold">{{ invoice.id }}</div>
              <div class="text-caption">Fecha: {{ invoice.issuedDate }}</div>
            </div>
          </div>

          <!-- Título -->
          <div class="text-center mb-6">
            <h4 class="text-h4 font-weight-bold">NOTA DE RECEPCIÓN</h4>
          </div>

          <!-- Información del proveedor y factura -->
          <VRow class="mb-6">
            <VCol cols="8">
              <div class="mb-2"><strong>PROVEEDOR:</strong> {{ invoice.client?.name || '—' }}</div>
            </VCol>
            <VCol cols="4" class="text-right">
              <div v-if="toggleCheckboxOne" class="mb-2">
                <strong>Factura N°:</strong> {{ facturaNumero || '—' }}
              </div>
              <div v-if="toggleCheckboxOne" class="mb-2"><strong>Fecha Factura:</strong> {{ date || '—' }}</div>
            </VCol>
          </VRow>

          <!-- Tabla de productos -->
          <div class="mb-6">
            <VTable class="text-no-wrap">
              <thead>
                <tr>
                  <th class="text-left">Código</th>
                  <th class="text-center">Cantidad</th>
                  <th class="text-left">Descripción</th>
                  <th class="text-right">Precio</th>
                  <th class="text-right">P.Costo</th>
                  <th class="text-right">Importe</th>
                  <th class="text-right">Total Bs</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(it, i) in previewItems" :key="i">
                  <td class="text-left">{{ it?.codigo ?? '—' }}</td>
                  <td class="text-center">{{ Number(it?.cantidad ?? 0).toFixed(2) }}</td>
                  <td class="text-left">{{ it?.producto_nombre ?? '—' }}</td>
                  <td class="text-right">{{ Number(it?.precio ?? 0).toFixed(2) }}</td>
                  <td class="text-right">{{ Number(it?.costo ?? 0).toFixed(2) }}</td>
                  <td class="text-right">{{ Number(it?.importe ?? (it?.cantidad * it?.precio) ?? 0).toFixed(2) }}</td>
                  <td class="text-right font-weight-bold">{{ Number(it?.importe ?? (it?.cantidad * it?.precio) ?? 0).toFixed(2) }}</td>
                </tr>
              </tbody>
            </VTable>
          </div>

          <!-- Totales -->
          <div class="d-flex justify-end mb-8">
            <div style="min-width: 300px;">
              <VDivider class="mb-2" />
              <div class="d-flex justify-space-between mb-1">
                <span>TOTALES</span>
                <span class="font-weight-bold">{{ totalImporte.toFixed(2) }}</span>
              </div>
              <div class="d-flex justify-space-between mb-1">
                <span class="font-weight-bold">TOTAL Bs</span>
                <span class="font-weight-bold">{{ totalImporte.toFixed(2) }}</span>
              </div>
            </div>
          </div>

          <!-- Observaciones -->
          <div v-if="note" class="mb-6">
            <div class="text-body-2"><strong>Detalle:</strong> {{ note }}</div>
          </div>

          <!-- Firmas -->
          <VRow class="mt-12">
            <VCol cols="4" class="text-center">
              <VDivider class="mb-2" />
              <div class="text-caption">Recibido por: {{ authenticatedUser }}</div>
            </VCol>
            <VCol cols="4" class="text-center">
              <VDivider class="mb-2" />
              <div class="text-caption">Entregado por: {{ salesperson }}</div>
            </VCol>
          </VRow>
        </div>
      </VCard>
    </VDialog>

    <!-- Snackbar de error -->
    <VSnackbar v-model="errorSnack" color="error" timeout="4000">
      {{ errorMsg }}
    </VSnackbar>
    <VSnackbar v-model="successSnack" color="success" timeout="3000">
      {{ successMsg }}
    </VSnackbar>
  </VCard>
</template>

<style scoped>
/* Color gris para el item activo del autocomplete de proveedores */
.proveedor-autocomplete :deep(.v-list-item--active .v-list-item-title) {
  color: #9e9e9e !important; /* gris plomo */
}
/* Opcional: evitar que el fondo activo tome el primary muy intenso */
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

.preview-block {
  max-height: 240px;
  overflow: auto;
  background: #f6f6f6;
  padding: 12px;
  border-radius: 6px;
  font-size: 0.75rem;
  line-height: 1.1rem;
  font-family: monospace;
}
.preview-table {
  border: 1px solid #e0e0e0;
}

.preview-table thead th {
  background-color: #f5f5f5;
  font-weight: 600;
  border-bottom: 2px solid #424242;
  padding: 8px 12px;
}

.preview-table tbody td {
  padding: 6px 12px;
  border-bottom: 1px solid #e0e0e0;
}
</style>
