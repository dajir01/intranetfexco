<!-- eslint-disable vue/no-mutating-props -->
<script setup>
import { ref, watch, computed, toRaw } from 'vue'

const props = defineProps({
  id: {
    type: Number,
    required: true,
  },
  data: {
    type: Object,
    required: true,
    default: () => ({
      asignacion_id: null,
      producto_id: null,
      codigo: '',
      nombre: '',
      cantidad: 1,
      precio_unitario: 0,
      stock_disponible: 0,
      costo_total: 0,
      importe: 0,
    }),
  },
})

const emit = defineEmits([
  'removeProduct',
  'totalAmount',
])

// Estado local
const localProductData = ref(structuredClone(toRaw(props.data)))
const asignaciones = ref([])
const productoSearch = ref('')
const selectedAsignacion = ref(null)
const cantidadError = ref(false)
const cantidadErrorMessage = ref('')
let fetchTimeout = null

const fetchAsignaciones = async (query = '') => {
  try {
    const url = query
      ? `/inventario/asignaciones-productos?q=${encodeURIComponent(query)}&per_page=15`
      : '/inventario/asignaciones-productos?per_page=15'
    
    const r = await fetch(url)
    if (!r.ok) {
      console.error('Error en respuesta del servidor:', r.status, r.statusText)
      asignaciones.value = []
      return
    }

    const contentType = r.headers.get('content-type') || ''
    if (!contentType.includes('application/json')) {
      console.error('Respuesta no es JSON:', contentType)
      asignaciones.value = []
      return
    }

    const data = await r.json()
    
    // Manejar respuesta paginada de Laravel
    let items = []
    if (data.data && Array.isArray(data.data)) {
      items = data.data
    } else if (Array.isArray(data)) {
      items = data
    }
    
    if (!items.length) {
      console.warn('No se encontraron asignaciones')
    }
    
    // Usar displayName que viene del servidor, o construir si no existe
    asignaciones.value = items.map(a => ({
      ...a,
      displayName: a.displayName || `${a.codigo || '—'} - ${a.area?.nombre || '—'} - ${a.producto?.nombre || '—'}`,
    }))
  } catch (e) {
    console.error('Error al cargar asignaciones:', e.message)
    asignaciones.value = []
  }
}

const debounceFetch = () => {
  clearTimeout(fetchTimeout)
  fetchTimeout = setTimeout(() => fetchAsignaciones(productoSearch.value), 250)
}

const onSearchUpdate = (val) => {
  productoSearch.value = val
  debounceFetch()
}

// Al seleccionar una asignación
watch(selectedAsignacion, (asignacion) => {
  if (!asignacion) {
    localProductData.value.asignacion_id = null
    localProductData.value.producto_id = null
    localProductData.value.codigo = ''
    localProductData.value.nombre = ''
    localProductData.value.stock_disponible = 0
    localProductData.value.costo_total = 0
    localProductData.value.precio_unitario = 0
    localProductData.value.cantidad = 1
    return
  }

  localProductData.value.asignacion_id = asignacion.id_asignacion
  localProductData.value.producto_id = asignacion.id_producto
  localProductData.value.codigo = asignacion.codigo || ''
  localProductData.value.nombre = asignacion.producto?.nombre || ''
  localProductData.value.stock_disponible = asignacion.stock || 0
  localProductData.value.costo_total = asignacion.costo_total || 0
  localProductData.value.precio_unitario = asignacion.costo_total / (asignacion.stock || 1) || 0
  localProductData.value.cantidad = 1
}, { immediate: true })

// Validar cantidad vs stock disponible
watch(() => localProductData.value.cantidad, (value) => {
  const cantidad = Number(value) || 0
  const stock = Number(localProductData.value.stock_disponible) || 0
  
  if (cantidad > stock && stock > 0) {
    cantidadError.value = true
    cantidadErrorMessage.value = 'La cantidad excede el stock disponible'
    // Ajustar automáticamente al stock máximo
    localProductData.value.cantidad = stock
  } else if (cantidad < 1) {
    cantidadError.value = true
    cantidadErrorMessage.value = 'La cantidad debe ser mayor a 0'
  } else {
    cantidadError.value = false
    cantidadErrorMessage.value = ''
  }
})

// Cálculo automático del importe: (costo_total / stock) * cantidad
const importe = computed(() => {
  if (!localProductData.value.stock_disponible || localProductData.value.stock_disponible === 0) {
    return 0
  }
  const costoUnitario = localProductData.value.costo_total / localProductData.value.stock_disponible
  const cant = Number(localProductData.value.cantidad) || 0
  return +(costoUnitario * cant).toFixed(2)
})

watch(importe, (val) => {
  localProductData.value.importe = val
  emit('totalAmount', val)
}, { immediate: true })

const removeProduct = () => {
  emit('removeProduct', props.id)
}

// Cargar inicial
fetchAsignaciones()

// Exponer snapshot
defineExpose({
  getSnapshot: () => ({
    asignacion_id: localProductData.value?.asignacion_id || null,
    producto_id: localProductData.value?.producto_id || null,
    codigo: localProductData.value?.codigo || '',
    nombre: localProductData.value?.nombre || '',
    cantidad: Number(localProductData.value?.cantidad) || 0,
    precio_unitario: Number(localProductData.value?.precio_unitario) || 0,
    stock_disponible: Number(localProductData.value?.stock_disponible) || 0,
    costo_total: Number(localProductData.value?.costo_total) || 0,
    importe: Number(importe.value) || 0,
  }),
})
</script>

<template>
  <!-- eslint-disable vue/no-mutating-props -->
  <div class="add-products-header d-none d-md-flex mb-4">
    <VRow class="me-10">
      <VCol cols="12" md="4">
        <h6 class="text-h6">Asignación</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6 ps-2">Stock Disp.</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6 ps-2">Costo Total</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6 ps-2">Cantidad</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6">Importe</h6>
      </VCol>
    </VRow>
  </div>

  <VCard
    flat
    border
    class="d-flex flex-sm-row flex-column-reverse"
  >
    <!-- 👉 Left Form -->
    <div class="pa-6 flex-grow-1">
      <VRow>
        <VCol cols="12" md="4">
          <VAutocomplete
            ref="productoSelect"
            v-model="selectedAsignacion"
            v-model:search="productoSearch"
            :items="asignaciones"
            item-title="displayName"
            item-value="id_asignacion"
            return-object
            placeholder="Buscar producto (código, área, nombre)"
            clearable
            density="compact"
            style="inline-size: 100%;"
            @update:search="onSearchUpdate"
          >
            <template #no-data>
              <div class="px-4 py-2">
                <span class="text-disabled">Escriba para buscar</span>
              </div>
            </template>
          </VAutocomplete>
        </VCol>

        <VCol cols="12" md="2" sm="4">
          <AppTextField
            v-model="localProductData.stock_disponible"
            placeholder="Stock"
            type="number"
            disabled
            density="compact"
          />
        </VCol>

        <VCol cols="12" md="2" sm="4">
          <AppTextField
            v-model="localProductData.costo_total"
            placeholder="Costo Total"
            type="number"
            disabled
            density="compact"
          />
        </VCol>

        <VCol cols="12" md="2" sm="4">
          <AppTextField
            :id="`item-cantidad-${props.id}`"
            v-model="localProductData.cantidad"
            type="number"
            min="1"
            placeholder="1"
            :max="localProductData.stock_disponible"
            density="compact"
            :error="cantidadError"
            :error-messages="cantidadErrorMessage"
          />
        </VCol>

        <VCol cols="12" md="2" sm="4">
          <p class="my-2">
            <span class="d-inline d-md-none">Importe: </span>
            <span class="text-high-emphasis">Bs. {{ importe }}</span>
          </p>
        </VCol>
      </VRow>
    </div>

    <!-- 👉 Item Actions -->
    <div
      class="d-flex flex-column align-end item-actions"
      :class="$vuetify.display.smAndUp ? 'border-s' : 'border-b' "
    >
      <IconBtn size="36" @click="removeProduct">
        <VIcon :size="24" icon="tabler-x" />
      </IconBtn>
    </div>
  </VCard>
</template>
