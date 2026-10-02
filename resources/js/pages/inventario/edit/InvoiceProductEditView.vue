<!-- Componente de edición: permite cambiar el producto con Autocomplete
  y recupera sus datos existentes (código, área, tipo, unidad, etc.). -->
<script setup>
import { ref, computed, watch } from 'vue'

const props = defineProps({
  id: {
    type: Number,
    required: true,
  },
  data: {
    type: Object,
    required: true,
  },
  tieneFactura: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['removeProduct', 'totalAmount'])

// ----------------------------
// Estado local del ítem
// ----------------------------
const cantidad = ref(props.data.cantidad || 0)
const cantidadOriginal = ref(props.data.cantidad || 0) // Para detectar cambios
const precio = ref(props.data.precio || 0)
const costo = ref(props.data.costo || 0)

// Datos del producto seleccionado (visualización)
const selectedProducto = ref(
  props.data?.producto_id
    ? {
        id_producto: props.data.producto_id,
        nombre: props.data.nombre,
        codigo: props.data.codigo,
        area_nombre: props.data.area_nombre,
        area_id: props.data.area_id,
        tipo: props.data.tipo,
        unidad_medida: props.data.unidad_medida,
        codigo_barras: props.data.codigo_barras,
        descripcion: props.data.descripcion,
        asignacion_id: props.data.asignacion_id,
      }
    : null
)
const productoSearch = ref(props.data?.nombre || '')
const productos = ref([])
const productoMenu = ref(false)
const loadingProductos = ref(false)
const creatingProducto = ref(false)
const productExists = computed(() => {
  if (!productoSearch.value) return false
  return productos.value.some(p => 
    p.nombre.toLowerCase() === productoSearch.value.toLowerCase()
  )
})

// Estos campos se muestran como solo-lectura y se rellenan
// al seleccionar un producto.
const nombre = ref(props.data.nombre || '')
const areaNombre = ref(props.data.area_nombre || '')
const codigo = ref(props.data.codigo || '')
const tipo = ref(props.data.tipo || '')
const unidadMedida = ref(props.data.unidad_medida || '')
const codigoBarras = ref(props.data.codigo_barras || '')
const descripcion = ref(props.data.descripcion || '')
const asignacionId = ref(props.data.asignacion_id || null)
const asignacionIdOriginal = ref(props.data.asignacion_id || null)
const areaIdOriginal = ref(props.data.area_id || null)

// Áreas disponibles y selección
const areas = ref([])
const selectedAreaId = ref(props.data.area_id ?? null)
const csrfToken = ref('')

// ----------------------------
// Operaciones pendientes (sin persistir hasta guardar)
// ----------------------------
const pendingOperations = ref({
  createProduct: null,        // { nombre, tipo, ... }
  createAsignacion: null,     // { producto_id, area_id, codigo, tipo }
  darDeBajaAsignacion: null,  // { asignacion_id, motivo }
  ajusteStock: null,          // { asignacion_id, delta, tipo: 'incremento'|'decremento' }
  registrarBaja: null,        // { asignacion_id, cantidad, motivo }
})

// Calcular importe automáticamente
const importe = computed(() => {
  const cant = Number(cantidad.value) || 0
  const precioNum = Number(precio.value) || 0
  return +(cant * precioNum).toFixed(2)
})

const esActivoFijo = computed(() => {
  const t = (tipo.value || '').toString().trim().toLowerCase()
  return t === 'activo fijo'
})

const esDetalleExistente = computed(() => {
  return !!props.data?.detalle_id
})

const bloquearCantidad = computed(() => {
  return esActivoFijo.value && esDetalleExistente.value
})

const incrementarCodigo = (codigoBase, incremento) => {
  const base = (codigoBase || '').toString().trim()
  if (!base || incremento <= 0) return base

  const m = base.match(/^(.*?)(\d+)$/)
  if (!m) return base

  const prefijo = m[1]
  const numeroStr = m[2]
  const ancho = numeroStr.length
  const numero = Number(numeroStr)
  if (!Number.isFinite(numero)) return base

  const nuevoNumero = String(numero + incremento).padStart(ancho, '0')
  return `${prefijo}${nuevoNumero}`
}

const codigoResumen = computed(() => {
  const base = (codigo.value || '').toString().trim()
  if (!base) return ''

  if (!esActivoFijo.value) return base

  const unidades = Math.max(1, Number(cantidad.value) || 1)
  if (unidades <= 1) return base

  const ultimo = incrementarCodigo(base, unidades - 1)
  if (!ultimo || ultimo === base) return base

  return `${base} / ${ultimo}`
})

// Recalcular costo basado en si tiene factura
watch([importe, () => props.tieneFactura], ([importeVal, tieneFactura]) => {
  costo.value = tieneFactura ? +(importeVal * 0.87).toFixed(2) : importeVal
}, { immediate: true })

// Emitir cambios de importe al padre
watch(importe, val => {
  emit('totalAmount', val)
}, { immediate: true })

const removeProduct = () => {
  emit('removeProduct', props.id)
}

// ----------------------------
// Búsqueda de productos
// ----------------------------
let fetchTimeout
const ensureSelectedInItems = () => {
  if (!selectedProducto.value) return
  const exists = productos.value.some(p => p.id_producto === selectedProducto.value.id_producto)
  if (!exists) productos.value.unshift(selectedProducto.value)
}

const fetchProductos = async () => {
  try {
    loadingProductos.value = true
    const q = (productoSearch.value || '').trim()
    const url = q
      ? `/inventario/productos?q=${encodeURIComponent(q)}&per_page=8`
      : '/inventario/productos?per_page=8'
    const r = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      const arr = j.data || []
      // Deduplicar por id_producto para evitar ítems repetidos
      const map = new Map()
      for (const p of arr) {
        if (!map.has(p.id_producto)) map.set(p.id_producto, p)
      }
      productos.value = Array.from(map.values())
      ensureSelectedInItems()
    }
  } catch (_) {
    // noop
  } finally {
    loadingProductos.value = false
  }
}

const debounceFetch = () => {
  clearTimeout(fetchTimeout)
  fetchTimeout = setTimeout(fetchProductos, 250)
}

// Crear producto nuevo cuando no existe
const createNewProducto = async () => {
  if (!productoSearch.value?.trim() || productExists.value) return
  
  creatingProducto.value = true
  try {
    // Crear objeto de producto temporal (sin ID)
    const nuevoProducto = {
      id_producto: null,
      nombre: productoSearch.value.trim(),
      codigo: null,
      area_nombre: null,
      area_id: null,
      tipo: null,
      unidad_medida: null,
      codigo_barras: null,
      descripcion: null,
      asignacion_id: null,
    }
    
    // Establecer como producto seleccionado
    selectedProducto.value = nuevoProducto
    productos.value.unshift(nuevoProducto)
    productoMenu.value = false
    
    // Establecer valores por defecto
    nombre.value = nuevoProducto.nombre
    tipo.value = 'Consumible' // Por defecto
    
  } catch (error) {
    console.error('Error al crear producto temporal:', error)
  } finally {
    creatingProducto.value = false
  }
}

const onSearchUpdate = (val) => {
  productoSearch.value = val
  if (val) {
    productoMenu.value = true
    debounceFetch()
  } else {
    productoMenu.value = false
  }
}

const onItemEnter = (event) => {
  if (event.key === 'Enter' && !productExists.value && productoSearch.value?.trim()) {
    createNewProducto()
  }
}

const onBlurCreate = () => {
  if (!productExists.value && productoSearch.value?.trim() && !selectedProducto.value) {
    setTimeout(() => createNewProducto(), 200)
  }
}

// Cargar al montar para tener una lista inicial
fetchProductos()
// Cargar catálogo de áreas
const fetchAreas = async () => {
  try {
    const r = await fetch('/inventario/areas', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
    if (r.ok) {
      const j = await r.json()
      areas.value = Array.isArray(j) ? j : (j.data || [])
    }
  } catch (_) {}
}
fetchAreas()

const ensureCsrf = async () => {
  if (csrfToken.value) return
  try {
    const t = await fetch('/csrf-token', { credentials: 'same-origin' })
    if (t.ok) {
      const j = await t.json()
      csrfToken.value = j.token || ''
    }
  } catch (_) {}
}

// ----------------------------
// Funciones del flujo de lógica
// ----------------------------

// Obtener siguiente código correlativo del área (dry-run)
const fetchNextCode = async (productoId, areaId, tipoProducto) => {
  try {
    await ensureCsrf()
    const r = await fetch('/inventario/asignaciones-productos', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken.value },
      credentials: 'same-origin',
      body: JSON.stringify({
        producto_id: productoId || 0,
        area_id: areaId,
        tipo: tipoProducto || null,
        dry_run: true,
      }),
    })
    if (r.ok) {
      const j = await r.json()
      return j?.data?.codigo || null
    } else {
      console.error('Error fetchNextCode:', r.status, await r.text())
    }
  } catch (e) {
    console.error('Error en fetchNextCode:', e)
  }
  return null
}

// Ejecutar flujo completo al cambiar producto/área/cantidad
const ejecutarFlujo = async () => {
  if (!selectedProducto.value || !selectedAreaId.value) return
  
  const productoExiste = !!selectedProducto.value.id_producto
  const areaObj = areas.value.find(a => a.id_area === selectedAreaId.value)
  const tipoProducto = (tipo.value || '').toLowerCase()
  const esConsumible = tipoProducto === 'consumible'
  const esActivoFijo = tipoProducto === 'activo fijo'
  
  // Limpiar operaciones pendientes
  pendingOperations.value = {
    createProduct: null,
    createAsignacion: null,
    darDeBajaAsignacion: null,
    ajusteStock: null,
    registrarBaja: null,
  }
  
  // CASO 1: Producto NO existe → crear producto temporal
  if (!productoExiste) {
    pendingOperations.value.createProduct = {
      nombre: nombre.value,
      tipo: tipo.value,
      unidad_medida: unidadMedida.value,
      codigo_barras: codigoBarras.value,
      descripcion: descripcion.value,
    }
    
    // Crear asignación según tipo
    const nextCode = await fetchNextCode(null, selectedAreaId.value, tipo.value)
    if (nextCode) {
      codigo.value = nextCode
    } else {
      // Fallback local si falla la petición
      codigo.value = `${areaObj?.codigo || 'TMP'}-000001`
    }
    asignacionId.value = null
    
    if (esConsumible) {
      pendingOperations.value.createAsignacion = {
        producto_id: null, // Se asignará al crear el producto
        area_id: selectedAreaId.value,
        codigo: nextCode,
        tipo: 'Consumible',
        cantidad: Number(cantidad.value) || 0,
      }
    } else if (esActivoFijo) {
      // Por cada unidad, crear asignación
      const cant = Number(cantidad.value) || 1
      pendingOperations.value.createAsignacion = {
        producto_id: null,
        area_id: selectedAreaId.value,
        codigo: nextCode,
        tipo: 'Activo Fijo',
        unidades: cant, // Indica que se crearán N asignaciones
      }
    }
    return
  }
  
  // CASO 2: Producto SÍ existe
  // Verificar si existe asignación en esta área (comparar con área original)
  const asignacionExistente = asignacionIdOriginal.value && selectedAreaId.value === areaIdOriginal.value
  
  if (!asignacionExistente) {
    // NO existe asignación en esta área → crear nueva
    const nextCode = await fetchNextCode(selectedProducto.value.id_producto, selectedAreaId.value, tipo.value)
    if (nextCode) {
      codigo.value = nextCode
    } else {
      // Fallback local si falla la petición
      codigo.value = `${areaObj?.codigo || 'TMP'}-000001`
    }
    asignacionId.value = null
    
    if (esConsumible) {
      pendingOperations.value.createAsignacion = {
        producto_id: selectedProducto.value.id_producto,
        area_id: selectedAreaId.value,
        codigo: codigo.value,
        tipo: 'Consumible',
        cantidad: Number(cantidad.value) || 0,
      }
    } else if (esActivoFijo) {
      const cant = Number(cantidad.value) || 1
      pendingOperations.value.createAsignacion = {
        producto_id: selectedProducto.value.id_producto,
        area_id: selectedAreaId.value,
        codigo: codigo.value,
        tipo: 'Activo Fijo',
        unidades: cant,
      }
      
      // Si había una asignación original en otra área, dar de baja
      if (asignacionIdOriginal.value && areaIdOriginal.value !== selectedAreaId.value) {
        pendingOperations.value.darDeBajaAsignacion = {
          asignacion_id: asignacionIdOriginal.value,
          motivo: 'Cambio de área en edición',
        }
        pendingOperations.value.registrarBaja = {
          asignacion_id: asignacionIdOriginal.value,
          cantidad: 1,
          motivo: 'Reasignación de activo fijo',
        }
      }
    }
  } else {
    // SÍ existe asignación en esta área
    if (esConsumible) {
      // Recuperar datos, detectar cambio en cantidad
      const delta = Number(cantidad.value) - cantidadOriginal.value
      if (delta > 0) {
        // Cantidad aumenta → sumar stock
        pendingOperations.value.ajusteStock = {
          asignacion_id: asignacionIdOriginal.value,
          delta,
          tipo: 'incremento',
        }
      } else if (delta < 0) {
        // Cantidad disminuye → restar + registrar baja
        pendingOperations.value.ajusteStock = {
          asignacion_id: asignacionIdOriginal.value,
          delta: Math.abs(delta),
          tipo: 'decremento',
        }
        pendingOperations.value.registrarBaja = {
          asignacion_id: asignacionIdOriginal.value,
          cantidad: Math.abs(delta),
          motivo: 'Ajuste en edición de ingreso',
        }
      }
      // Mantener código existente
      codigo.value = props.data.codigo
    } else if (esActivoFijo) {
      // Para Activo Fijo en misma área: no hacer nada, mantener igual
      // (el flujo dice que si existe asignación de AF en misma área, solo recuperar datos)
      codigo.value = props.data.codigo
    }
  }
}

// Cuando cambia el producto seleccionado, rellenar campos locales
watch(selectedProducto, p => {
  if (!p) return
  nombre.value = p.nombre || ''
  // Requiere selección explícita de área antes de asignar/generar código.
  codigo.value = ''
  areaNombre.value = ''
  tipo.value = p.tipo || ''
  unidadMedida.value = p.unidad_medida || ''
  codigoBarras.value = p.codigo_barras || ''
  descripcion.value = p.descripcion || ''
  asignacionId.value = null
  selectedAreaId.value = null
  
  // Fallback: si faltan datos del producto, intentar obtener detalle por id
  if (p.id_producto && (!tipo.value || !unidadMedida.value || (!codigoBarras.value && !descripcion.value))) {
    fetch(`/inventario/productos/${p.id_producto}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then(r => r.ok ? r.json() : null)
      .then(d => {
        if (!d) return
        tipo.value = d.tipo || tipo.value
        unidadMedida.value = d.unidad_medida || unidadMedida.value
        codigoBarras.value = d.codigo_barras || codigoBarras.value
        descripcion.value = d.descripcion || descripcion.value
      }).catch(() => {})
  }
  
  // El flujo se ejecuta al seleccionar área.
})

// Cuando cambia el área seleccionada, ejecutar flujo
watch(selectedAreaId, async id => {
  if (!id) {
    areaNombre.value = ''
    codigo.value = ''
    asignacionId.value = null
    return
  }
  if (!selectedProducto.value) return
  const areaObj = areas.value.find(a => a.id_area === id)
  if (areaObj) areaNombre.value = areaObj.nombre
  
  // Ejecutar flujo al cambiar área
  await ejecutarFlujo()
})

// Cuando cambia la cantidad, detectar ajustes (solo para consumibles)
watch(cantidad, () => {
  if ((tipo.value || '').toLowerCase() === 'consumible' && asignacionIdOriginal.value) {
    ejecutarFlujo()
  }
})

// Cuando cambia el tipo (para productos nuevos), ejecutar flujo
watch(tipo, () => {
  if (selectedProducto.value && !selectedProducto.value.id_producto && selectedAreaId.value) {
    ejecutarFlujo()
  }
})

// Cuando cambia unidad de medida, descripción o código de barras para productos nuevos
watch([unidadMedida, descripcion, codigoBarras], () => {
  if (selectedProducto.value && !selectedProducto.value.id_producto) {
    // Actualizar pending operations
    if (pendingOperations.value.createProduct) {
      pendingOperations.value.createProduct.unidad_medida = unidadMedida.value
      pendingOperations.value.createProduct.descripcion = descripcion.value
      pendingOperations.value.createProduct.codigo_barras = codigoBarras.value
    }
  }
})

// Exponer snapshot para validación del padre
defineExpose({
  getSnapshot: () => {
    // Si hay operación pendiente de crear asignación, usar un ID temporal para pasar validación
    // O si todos los campos requeridos están completos
    const tieneAsignacionPendiente = pendingOperations.value.createAsignacion !== null
    const todosLosCamposCompletos = selectedProducto.value && selectedAreaId.value && tipo.value && codigo.value
    
    const asignacionIdTemp = tieneAsignacionPendiente || (todosLosCamposCompletos && !asignacionId.value)
      ? `TEMP_${selectedAreaId.value}_${Date.now()}` 
      : asignacionId.value
    
    return {
      // Identificador del detalle (si existe)
      detalle_id: props.data?.detalle_id || null,
      // Datos básicos del ítem
      producto_id: selectedProducto.value?.id_producto || null,
      asignacion_id: asignacionIdTemp,
      nombre: nombre.value,
      codigo: codigo.value,
      codigo_resumen: codigoResumen.value || codigo.value,
      area_id: selectedAreaId.value,
      area_nombre: areaNombre.value,
      tipo: tipo.value,
      cantidad: Number(cantidad.value) || 0,
      precio: Number(precio.value) || 0,
      costo: Number(costo.value) || 0,
      importe: Number(importe.value) || 0,
      
      // Operaciones pendientes para ejecutar al guardar
      pendingOperations: pendingOperations.value,
    }
  },
})
</script>

<template>
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
      <VCol cols="12" md="2">
        <h6 class="text-h6">Importe</h6>
      </VCol>
    </VRow>
  </div>

  <VCard flat border class="d-flex flex-sm-row flex-column-reverse">
    <!-- 👉 Left Form -->
    <div class="pa-6 grow">
      <VRow>
        <VCol cols="12" md="4">
          <!-- Selector de producto -->
          <VAutocomplete
            ref="productoSelect"
            v-model="selectedProducto"
            v-model:search="productoSearch"
            :items="productos"
            item-title="nombre"
            item-value="id_producto"
            return-object
            placeholder="Nombre del producto"
            :name="`edit_producto_autocomplete_${props.id}`"
            autocomplete="new-password"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            clearable
            :menu="productoMenu"
            class="mb-6"
            style="inline-size: 100%;"
            :loading="loadingProductos || creatingProducto"
            :disabled="esActivoFijo"
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
              :name="`edit_producto_area_${props.id}`"
              autocomplete="new-password"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              clearable
              style="min-inline-size: 9rem;"
              :disabled="!selectedProducto"
            />
            <VSelect
              v-if="!selectedProducto?.id_producto"
              v-model="tipo"
              :items="['Consumible', 'Activo Fijo']"
              placeholder="Tipo"
              :name="`edit_producto_tipo_${props.id}`"
              autocomplete="new-password"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              clearable
              style="min-inline-size: 12rem;"
            />
            <AppTextField
              v-else
              :model-value="tipo || 'Sin tipo'"
              placeholder="Tipo"
              :name="`edit_producto_tipo_readonly_${props.id}`"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              disabled
              readonly
              style="min-inline-size: 12rem;"
            />
            <VTextarea
              :model-value="codigoResumen"
              placeholder="Código"
              :name="`edit_producto_codigo_${props.id}`"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              rows="2"
              no-resize
              readonly
              hide-details
              class="codigo-resumen-field"
              style="min-inline-size: 10rem;"
            />
            <VSelect
              v-if="!selectedProducto?.id_producto"
              v-model="unidadMedida"
              :items="['Unidad', 'Metro', 'Litro', 'Caja', 'Paquete']"
              placeholder="Unidad medida"
              :name="`edit_producto_unidad_${props.id}`"
              autocomplete="new-password"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              clearable
              style="min-inline-size: 8rem;"
            />
            <AppTextField
              v-else
              :model-value="unidadMedida || 'Sin unidad'"
              placeholder="Unidad medida"
              :name="`edit_producto_unidad_readonly_${props.id}`"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              disabled
              readonly
              style="min-inline-size: 8rem;"
            />
            <AppTextField
              v-if="!selectedProducto?.id_producto"
              v-model="codigoBarras"
              placeholder="Código Barras"
              :name="`edit_producto_codigo_barras_${props.id}`"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              style="min-inline-size: 10rem;"
            />
            <AppTextField
              v-else
              :model-value="codigoBarras || 'Sin código de barras'"
              placeholder="Código Barras"
              :name="`edit_producto_codigo_barras_readonly_${props.id}`"
              autocomplete="off"
              autocorrect="off"
              autocapitalize="off"
              spellcheck="false"
              disabled
              readonly
              style="min-inline-size: 10rem;"
            />
          </div>
          <AppTextField
            v-if="!selectedProducto?.id_producto"
            v-model="descripcion"
            placeholder="Descripción"
            :name="`edit_producto_descripcion_${props.id}`"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            class="mb-4"
          />
          <AppTextField
            v-else
            :model-value="descripcion || 'Sin descripción'"
            placeholder="Descripción"
            :name="`edit_producto_descripcion_readonly_${props.id}`"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            disabled
            readonly
            class="mb-4"
          />
        </VCol>
        
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            v-model="cantidad"
            type="number"
            min="1"
            placeholder="1"
            :name="`edit_producto_cantidad_${props.id}`"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            :disabled="bloquearCantidad"
          />
        </VCol>
        
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            v-model="precio"
            type="number"
            min="0"
            step="0.01"
            placeholder="0"
            :name="`edit_producto_precio_${props.id}`"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
          />
        </VCol>
        
        <VCol cols="12" md="2" sm="4">
          <AppTextField
            :model-value="costo"
            type="number"
            min="0"
            placeholder="0"
            :name="`edit_producto_costo_${props.id}`"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="off"
            spellcheck="false"
            disabled
            readonly
          />
        </VCol>
        
        <VCol cols="12" md="2" sm="4">
          <p class="my-2">
            <span class="d-inline d-md-none">Importe: </span>
            <span class="text-high-emphasis">Bs. {{ importe.toFixed(2) }}</span>
          </p>
        </VCol>
      </VRow>
    </div>

    <!-- 👉 Item Actions -->
    <div
      class="d-flex flex-column align-end item-actions"
      :class="$vuetify.display.smAndUp ? 'border-s' : 'border-b'"
    >
      <IconBtn size="36" @click="removeProduct">
        <VIcon :size="24" icon="tabler-x" />
      </IconBtn>
    </div>
  </VCard>
</template>

<style scoped>
.item-actions {
  padding: 1rem;
}
</style>
