<!-- eslint-disable vue/no-mutating-props -->
<script setup>
import { ref, watch, computed, toRaw, reactive, onMounted } from 'vue'

const props = defineProps({
  id: {
    type: Number,
    required: true,
  },
  data: {
    type: Object,
    required: true,
    default: () => ({
      producto_id: null,
      nombre: '',
      title: '',
      cantidad: 1,
      precio: 0,
      costo: 0, // precio costo (87% de precio)
      importe: 0,
    }),
  },
  tieneFactura: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits([
  'removeProduct',
  'totalAmount',
])

// Estado local
const localProductData = ref(structuredClone(toRaw(props.data)))
const productos = ref([])
const productoSearch = ref('')
const creatingProducto = ref(false)
// Indica si el nombre escrito coincide con un producto existente
const productExists = computed(() => {
  const nombre = productoSearch.value.trim()
  return !!(nombre && productos.value.some(p => p.nombre && p.nombre.toLowerCase() === nombre.toLowerCase()))
})
const areas = ref([])
const selectedAreaId = ref(null)
// Unidad física (Unidad / Metro / Litro / Caja / Paquete/ Kilogramo / Bolsa / M3)
const tipos = ['Unidad', 'Metro', 'Litro', 'Caja', 'Paquete', 'Kilogramo', 'Bolsa', 'M3', 'Rollo', 'Kit', 'Pieza'];
// Usar null para que el placeholder se muestre correctamente en VSelect
const unidadMedidaFisica = ref(null)
// Tipo de producto (Activo Fijo / Consumible)
const tipoProducto = ref(null)
const unidadOpciones = ref(['Activo Fijo','Consumible'])
const generatedCodigo = ref('')
const codigoBarras = ref('')
const descripcion = ref('')
const selectedProductoId = ref(localProductData.value.producto_id)
const selectedProducto = ref(null) // Objeto completo del producto para VAutocomplete
const currentAsignacionId = ref(null)
const csrfToken = ref('')
const productoMenu = ref(false)
let fetchTimeout = null
let saveTimeout = null

// Inicializar el producto en la lista si viene en props.data
const initializeProducto = () => {
  const d = props.data
  if (!(d && d.producto_id && d.nombre)) return
  const p = {
    id_producto: d.producto_id,
    nombre: d.nombre,
    codigo: d.codigo || '',
    area_id: d.area_id || null,
    area_nombre: d.area_nombre || '',
    tipo: d.tipo || null,
    unidad_medida: d.unidad_medida || null,
    codigo_barras: d.codigo_barras || '',
    descripcion: d.descripcion || '',
  }
  if (!productos.value.some(x => x.id_producto === p.id_producto)) productos.value.unshift(p)
  selectedProducto.value = p
  selectedProductoId.value = p.id_producto
  generatedCodigo.value = p.codigo
  codigoBarras.value = p.codigo_barras
  descripcion.value = p.descripcion
  tipoProducto.value = p.tipo
  unidadMedidaFisica.value = p.unidad_medida
  selectedAreaId.value = p.area_id
}

// Log de montaje mínimo
onMounted(() => {
  console.info('InvoiceProductEdit montado')
})

// No bloquear el formulario completo, solo campos específicos cuando corresponda
const lockedFields = reactive({
  tipo: false,
  unidad_medida: false,
  descripcion: false,
  codigo_barras: false,
  area: false,
})

const fetchProductos = async () => {
  try {
    const q = productoSearch.value.trim()
    const url = q ? `/inventario/productos?q=${encodeURIComponent(q)}&per_page=8` : '/inventario/productos?per_page=8'
    const r = await fetch(url)
    if (r.ok) productos.value = (await r.json()).data || []
  } catch (_) {}
}

// Persistir cambios del producto seleccionado
const scheduleAutoSave = (fields = {}) => {
  if (!selectedProductoId.value) return
  clearTimeout(saveTimeout)
  saveTimeout = setTimeout(() => autoSaveSelected(fields), 400)
}

const autoSaveSelected = async (fields = {}) => {
  // Ajustar para evitar estructuras circulares y validar datos
  await ensureCsrf();
  try {
    const payload = {
      tipo: tipoProducto.value ? String(tipoProducto.value) : null,
      unidad_medida: unidadMedidaFisica.value ? String(unidadMedidaFisica.value) : null,
      descripcion: descripcion.value ? String(descripcion.value) : null,
      codigo_barras: codigoBarras.value ? String(codigoBarras.value) : null,
      ...fields,
    };

    const r = await fetch(`/inventario/productos/${selectedProductoId.value}`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify(payload),
    });

    if (r.ok) {
      const updated = await r.json();
      const idx = productos.value.findIndex(p => p.id_producto === updated.id_producto);
      if (idx !== -1) productos.value[idx] = { ...productos.value[idx], ...updated };
      generatedCodigo.value = updated.codigo || generatedCodigo.value;
    } else {
      const errorText = await r.text();
      console.error('Error al guardar cambios:', errorText);
    }
  } catch (error) {
    console.error('Error en la solicitud de guardado:', error);
  }
}

// Lógica de asignación ahora centralizada en watch(selectedAreaId) usando
// el endpoint POST /inventario/asignaciones-productos. Función anterior eliminada.

const fetchAreas = async () => {
  try {
    const r = await fetch('/inventario/areas', {
      headers: { Accept: 'application/json' },
    })
    if (!r.ok) {
      console.error('No se pudo cargar las áreas. Estado:', r.status)
      areas.value = []
      return
    }

    const contentType = r.headers.get('content-type') || ''
    if (!contentType.includes('application/json')) {
      const body = await r.text()
      console.error('Respuesta inesperada al cargar áreas:', body.slice(0, 200))
      areas.value = []
      return
    }

    const data = await r.json()
    areas.value = Array.isArray(data) ? data : (data.data || [])
    if (!areas.value.length) {
      console.warn('El servidor no devolvió áreas disponibles.')
    }
  } catch (error) {
    console.error('Error al cargar áreas:', error)
    areas.value = []
  }
}

const debounceFetch = () => { clearTimeout(fetchTimeout); fetchTimeout = setTimeout(fetchProductos, 250) }

const ensureCsrf = async () => {
  if (csrfToken.value) return
  try {
    const t = await fetch('/csrf-token')
    if (t.ok) csrfToken.value = (await t.json()).token || ''
  } catch (_) {}
}

// Código ahora se genera / reutiliza vía asignación (POST /inventario/asignaciones-productos)
// Se elimina fetchNextCodigo que consultaba endpoint antiguo.

// Generar o recuperar código según tipo y área seleccionados
const assignOrRecoverCode = async () => {
  const areaId = selectedAreaId.value
  const tipo = (tipoProducto.value || '').toString().trim()
  if (!areaId || !tipo) return

  await ensureCsrf()
  try {
    const productoId = selectedProductoId.value || 0
    const response = await fetch('/inventario/asignaciones-productos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({
        producto_id: productoId,
        area_id: areaId,
        tipo,
      }),
    })

    if (response.ok) {
      const data = await response.json()
      if (data && data.data && data.data.codigo) {
        generatedCodigo.value = data.data.codigo
        currentAsignacionId.value = data.data.id_asignacion
        // Bloquear área solo tras éxito de asignación/recuperación
        lockedFields.area = true
      }
    } else {
      const errorText = await response.text()
      console.error('Error al asignar/verificar producto:', errorText)
    }
  } catch (error) {
    console.error('Error en la solicitud de asignación:', error)
  }
}

// Crear producto mínimo (solo nombre) si no existe y seleccionarlo
const createProductIfMissing = async () => {
  const nombre = productoSearch.value.trim()
  if (!nombre) return

  // Si ya hay producto seleccionado: no crear (se actualizará código si falta)
  if (selectedProductoId.value) return

  const exists = productos.value.some(p => p.nombre.toLowerCase() === nombre.toLowerCase())
  if (exists) return

  await ensureCsrf()
  try {
    creatingProducto.value = true
    const response = await fetch('/inventario/productos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({ nombre })
    })

    if (response.ok) {
      const prod = await response.json()
      productos.value.push(prod)
      selectedProductoId.value = prod.id_producto
      productoSearch.value = ''
    } else {
      const errorText = await response.text()
      console.error('Error al crear producto:', errorText)
    }
  } catch (error) {
    console.error('Error en la solicitud de creación de producto:', error)
  } finally {
    creatingProducto.value = false
  }
};

// Actualizar código en producto ya creado sin código
const updateExistingCodigo = async (prod) => {
  if (!prod || prod.codigo) return
  await ensureCsrf()
  try {
    creatingProducto.value = true
    const r = await fetch('/inventario/productos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      body: JSON.stringify({
        nombre: prod.nombre,
        tipo: tipoProducto.value || null,
        unidad_medida: unidadMedidaFisica.value || null,
        area_id: selectedAreaId.value || null,
        codigo: generatedCodigo.value || null,
      }),
    })
    if (r.ok) {
      const updated = await r.json()
      // Reemplazar en arreglo
      const idx = productos.value.findIndex(p => p.id_producto === updated.id_producto)
      if (idx !== -1) productos.value[idx] = { ...productos.value[idx], ...updated }
      generatedCodigo.value = updated.codigo || generatedCodigo.value
    }
  } catch (_) {}
  finally { creatingProducto.value = false }
}

// Al cambiar selección del objeto completo
watch(selectedProducto, async (producto) => {
  if (!producto) {
    selectedProductoId.value = null
    selectedAreaId.value = null;
    generatedCodigo.value = '';
    codigoBarras.value = '';
    descripcion.value = '';
    tipoProducto.value = null;
    unidadMedidaFisica.value = null;
    currentAsignacionId.value = null;
    lockedFields.tipo = false
    lockedFields.unidad_medida = false
    lockedFields.descripcion = false
    lockedFields.codigo_barras = false
    lockedFields.area = false
    return;
  }

  const id = producto.id_producto
  selectedProductoId.value = id
  
  // Si NO hay id_producto, es un producto nuevo → no hacer nada más aquí
  if (!id) {
    localProductData.value.nombre = producto.nombre;
    localProductData.value.title = producto.nombre;
    // Limpiar campos para que el usuario pueda llenarlos
    selectedAreaId.value = null;
    generatedCodigo.value = '';
    codigoBarras.value = '';
    descripcion.value = '';
    tipoProducto.value = null;
    unidadMedidaFisica.value = null;
    currentAsignacionId.value = null;
    lockedFields.tipo = false
    lockedFields.unidad_medida = false
    lockedFields.descripcion = false
    lockedFields.codigo_barras = false
    lockedFields.area = false
    productoMenu.value = false;
    return;
  }
  
  // Producto existente: buscar sus datos
  let p = productos.value.find(x => x.id_producto === id);
  
  // Si el producto no está en la lista, buscarlo en la API
  if (!p) {
    try {
      const r = await fetch(`/inventario/productos/${id}`);
      if (r.ok) {
        p = await r.json();
        // Agregar el producto a la lista para que el VAutocomplete lo muestre correctamente
        if (p && p.id_producto) {
          productos.value.push(p);
          selectedProducto.value = p
        }
      } else {
        console.error('❌ Error en la respuesta de la API:', r.status);
      }
    } catch (error) {
      console.error('❌ Error al cargar producto:', error);
    }
  }
  
  if (p) {
    console.info('Producto seleccionado (modelo):', {
      id_producto: p.id_producto,
      codigo_barras: p.codigo_barras || '',
      nombre: p.nombre,
      descripcion: p.descripcion || '',
      tipo: p.tipo || null,
      unidad_medida: p.unidad_medida || null,
    })
    localProductData.value.producto_id = p.id_producto;
    localProductData.value.nombre = p.nombre;
    localProductData.value.title = p.nombre;
    
    // Bloquear SOLO tipo y unidad si ya existen en el producto (configurar antes de asignar valores para evitar watchers)
    const hasTipo = !!(p.tipo && String(p.tipo).trim())
    const hasUnidad = !!(p.unidad_medida && String(p.unidad_medida).trim())
    lockedFields.tipo = hasTipo
    lockedFields.unidad_medida = hasUnidad

    // Recuperar del producto existente: modelo básico (asignar después de bloquear)
    tipoProducto.value = p.tipo || null;
    unidadMedidaFisica.value = p.unidad_medida || null;
    // Recuperar código de barras desde el modelo
    codigoBarras.value = p.codigo_barras || '';
    descripcion.value = p.descripcion || '';
    
    // Mantener área sin seleccionar; se define manualmente
    selectedAreaId.value = null;
    currentAsignacionId.value = null;
    // Desbloquear estos campos para que el usuario pueda editarlos
    lockedFields.descripcion = false
    lockedFields.codigo_barras = false
    lockedFields.area = false

  }
  productoMenu.value = false;
}, { immediate: true })

// Al cambiar el área para un producto seleccionado, obtener o crear la asignación y código
watch(selectedAreaId, async areaId => {
  if (!areaId) {
    // Limpiar datos de asignación si se deselecciona área
    currentAsignacionId.value = null
    generatedCodigo.value = ''
    lockedFields.area = false
    return
  }

  // No generar código aún si no hay tipo; esperar selección de tipo
  if (!tipoProducto.value || String(tipoProducto.value).trim() === '') {
    generatedCodigo.value = ''
    currentAsignacionId.value = null
    lockedFields.area = false
    return
  }

  // Si ya hay tipo seleccionado, generar/recuperar acorde al nuevo área
  await assignOrRecoverCode()
});

watch(tipoProducto, async tipo => {
  if (lockedFields.tipo) return
  if (!selectedProductoId.value) return // No autosave si no hay producto seleccionado
  // Persistimos solo el tipo; código se genera/recupera cuando hay área
  scheduleAutoSave({ tipo })

  // Si ya hay un área seleccionada, generar/recuperar código ahora
  if (selectedAreaId.value) await assignOrRecoverCode()
})

watch(unidadMedidaFisica, async unidad => {
  if (lockedFields.unidad_medida) return
  if (!selectedProductoId.value) return // No autosave si no hay producto seleccionado
  await scheduleAutoSave({ unidad_medida: unidad });
})
watch(descripcion, d => {
  if (lockedFields.descripcion) return
  if (!selectedProductoId.value) return // No autosave si no hay producto seleccionado
  if (typeof d === 'string') {
    scheduleAutoSave({ descripcion: d });
  } else {
    console.error('Descripción inválida, no se guardará:', d);
  }
})
watch(codigoBarras, cb => {
  if (lockedFields.codigo_barras) return
  if (!selectedProductoId.value) return // No autosave si no hay producto seleccionado
  scheduleAutoSave({ codigo_barras: cb })
})

// Importe = cantidad * precio
const importe = computed(() => {
  const cant = Number(localProductData.value.cantidad) || 0
  const precioNum = Number(localProductData.value.precio) || 0
  return +(cant * precioNum).toFixed(2)
})

// Derivar costo dependiendo si tiene factura:
// - Si tiene factura => costo = 87% del importe total
// - Si no tiene factura => costo = importe total (sin descuento)
watch([importe, () => props.tieneFactura], ([importeVal, tieneFactura]) => {
  localProductData.value.costo = tieneFactura ? +(importeVal * 0.87).toFixed(2) : importeVal
}, { immediate: true })

watch(importe, val => {
  localProductData.value.importe = val
  emit('totalAmount', val)
}, { immediate: true })

const onSearchUpdate = val => {
  productoSearch.value = val
  // Mantener nombre escrito aunque no exista aún
  if (!selectedProductoId.value) {
    localProductData.value.nombre = val
    localProductData.value.title = val
  }
  debounceFetch()
}

const onItemEnter = e => { if (e.key === 'Enter') createProductIfMissing() }
const onBlurCreate = () => { createProductIfMissing() }

const removeProduct = async () => {
  const asignacionId = currentAsignacionId.value
  const tipo = tipoProducto.value

  // Solo eliminar de BD si es Activo Fijo
  if (asignacionId && tipo === 'Activo Fijo') {
    try {
      await ensureCsrf()
      const response = await fetch('/inventario/asignaciones-productos', {
        method: 'DELETE',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
        body: JSON.stringify({ id_asignacion: asignacionId }),
      })
      if (response.ok) {
        // Eliminado correctamente
      } else {
        console.error('Error al eliminar asignación:', await response.text())
      }
    } catch (error) {
      console.error('Error al eliminar asignación:', error)
    }
  } else if (tipo === 'Consumible') {
    // Consumible: no se borra en BD
  }

  currentAsignacionId.value = null
  emit('removeProduct', props.id)
}

// Cargar inicial
initializeProducto()
fetchProductos()
fetchAreas()
ensureCsrf()

// Exponer snapshot para que el padre pueda recolectar datos al registrar
defineExpose({
  getSnapshot: () => ({
    producto_id: selectedProductoId.value || null,
    nombre: localProductData.value?.nombre || '',
    title: localProductData.value?.title || '',
    area_id: selectedAreaId.value || null,
    tipo: tipoProducto.value || null,
    codigo: generatedCodigo.value || '',
    unidad_medida: unidadMedidaFisica.value || null,
    codigo_barras: codigoBarras.value || '',
    descripcion: descripcion.value || '',
    cantidad: Number(localProductData.value?.cantidad) || 0,
    precio: Number(localProductData.value?.precio) || 0,
    costo: Number(localProductData.value?.costo) || 0,
    importe: Number(importe.value) || 0,
    asignacion_id: currentAsignacionId.value || null,
  }),
})
</script>

<template>
  <!-- eslint-disable vue/no-mutating-props -->
  <div class="add-products-header d-none d-md-flex mb-4">
    <VRow class="me-10">
      <VCol cols="12" md="4">
        <h6 class="text-h6">Producto</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6 ps-2">Cantidad</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6 ps-2">Precio</h6>
      </VCol>
      <VCol cols="12" md="2">
        <h6 class="text-h6">Precio Costo</h6>
      </VCol>
      <VCol cols="12" md="2" class="d-none">
        <h6 class="text-h6">(hidden)</h6>
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
    <div class="pa-6 grow">
      <VRow>
        <VCol cols="12" md="4">
          <VAutocomplete
            ref="productoSelect"
            v-model="selectedProducto"
            v-model:search="productoSearch"
            :items="productos"
            item-title="nombre"
            item-value="id_producto"
            return-object
            placeholder="Nombre del producto"
            clearable
            :menu="productoMenu"
            class="mb-6"
            style="inline-size: 100%;"
            :loading="creatingProducto"
            @update:search="onSearchUpdate"
            @keyup="onItemEnter"
            @blur="onBlurCreate"
          >
            <template #append-inner>
              <VProgressCircular
                v-if="creatingProducto"
                size="20"
                width="2"
                indeterminate
                color="primary"
                class="me-2"
              />
              <VIcon
                v-else-if="productoSearch && productExists"
                icon="mdi-check-circle"
                color="success"
                size="20"
                class="me-2"
              />
              <VIcon
                v-else-if="productoSearch && !productExists"
                icon="mdi-plus-circle"
                color="warning"
                size="20"
                class="me-2"
              />
            </template>
            <template #messages>
              <div v-if="productoSearch && productExists" class="text-success text-caption">
                El producto ya existe.
              </div>
              <div v-else-if="productoSearch && !productExists && !creatingProducto" class="text-warning text-caption">
                Se creará al confirmar (Enter / perder foco).
              </div>
              <div v-else-if="creatingProducto" class="text-primary text-caption">
                Creando producto...
              </div>
            </template>
            <template #no-data>
              <div class="px-4 py-2">
                <span class="text-disabled">Escriba para buscar</span>
              </div>
            </template>
          </VAutocomplete>
          <div class="d-flex flex-wrap gap-4 mb-4">
            <VAutocomplete
              v-model="selectedAreaId"
              :items="areas"
              item-title="nombre"
              item-value="id_area"
              placeholder="Área"
              style="min-inline-size: 9rem;"
              :disabled="lockedFields.area"
            />
            <VSelect
              v-model="tipoProducto"
              :items="unidadOpciones"
              label="Tipo de producto"
              placeholder="Seleccione tipo"
              clearable
              style="min-inline-size: 12rem;"
              :disabled="lockedFields.tipo"
            />
            <AppTextField
              v-model="generatedCodigo"
              placeholder="Código"
              disabled
              style="min-inline-size: 10rem;"
            />
            <VSelect
              v-model="unidadMedidaFisica"
              :items="tipos"
              placeholder="Unidad medida"
              clearable
              style="min-inline-size: 8rem;"
              :disabled="lockedFields.unidad_medida"
            />
            <AppTextField
              v-model="codigoBarras"
              placeholder="Código Barras"
              style="min-inline-size: 10rem;"
              :disabled="lockedFields.codigo_barras"
            />
          </div>
          <AppTextField
            v-model="descripcion"
            placeholder="Descripción"
            class="mb-4"
            variant="outlined"
            :counter="255"
            :disabled="lockedFields.descripcion"
          />
          <!-- Campo oculto retirado; se usa autocomplete con search binding -->
        </VCol>
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            :id="`item-cantidad-${props.id}`"
            v-model="localProductData.cantidad"
            type="number"
            min="1"
            placeholder="1"
          />
        </VCol>
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            :id="`item-precio-${props.id}`"
            v-model="localProductData.precio"
            type="number"
            min="0"
            placeholder="0"
          />
        </VCol>
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            :id="`item-costo-${props.id}`"
            v-model="localProductData.costo"
            type="number"
            min="0"
            placeholder="0"
            disabled
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


