<script setup>
import { ref, computed, onMounted } from 'vue'

definePage({
  meta: {
    requiresAuth: true,
  },
})

// Estados reactivos
const loading = ref(false)
const registros = ref([])
const areas = ref([])
const tiposProducto = ref([])
const productos = ref([])

// Filtros
const filtros = ref({
  producto_id: null,
  area_id: null,
  tipo: null,
})

// Headers de la tabla
const headers = [
  { title: 'Código', key: 'codigo', sortable: true },
  { title: 'Producto', key: 'producto', sortable: true },
  { title: 'Descripción', key: 'descripcion', sortable: false },
  { title: 'Tipo', key: 'tipo', sortable: true },
  { title: 'Área', key: 'area', sortable: true },
  { title: 'Unidad Medida', key: 'unidad_medida', sortable: true },
  { title: 'Stock', key: 'stock', sortable: true, align: 'end' },
  { title: 'Costo Total', key: 'costo_total', sortable: true, align: 'end' },
  { title: 'Ubicación', key: 'ubicacion', sortable: true },
  { title: 'Estado', key: 'estado', sortable: true },
]

// Computed para totales
const totales = computed(() => {
  if (!registros.value || registros.value.length === 0) {
    return {
      stock: 0,
      costo_total: 0,
      registros: 0,
    }
  }

  return {
    stock: registros.value.reduce((sum, item) => sum + (item.stock || 0), 0),
    costo_total: registros.value.reduce((sum, item) => sum + (item.costo_total || 0), 0),
    registros: registros.value.length,
  }
})

// Cargar datos iniciales
onMounted(async () => {
  await Promise.all([
    cargarProductos(),
    cargarAreas(),
    cargarTiposProducto()
  ])
})

// Función para cargar productos
async function cargarProductos() {
  try {
    const response = await fetch('/inventario/reporte/productos-lista')
    const data = await response.json()
    if (data.success) {
      productos.value = data.data
    }
  } catch (error) {
    console.error('Error al cargar productos:', error)
  }
}

// Función para cargar áreas
async function cargarAreas() {
  try {
    const response = await fetch('/inventario/reporte/areas')
    const data = await response.json()
    if (data.success) {
      areas.value = data.data
    }
  } catch (error) {
    console.error('Error al cargar áreas:', error)
  }
}

// Función para cargar tipos de producto
async function cargarTiposProducto() {
  try {
    const response = await fetch('/inventario/reporte/tipos-producto')
    const data = await response.json()
    if (data.success) {
      tiposProducto.value = data.data
    }
  } catch (error) {
    console.error('Error al cargar tipos de producto:', error)
  }
}

// Función para buscar con filtros
async function buscarReporte() {
  loading.value = true
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.producto_id) params.append('producto_id', filtros.value.producto_id)
    if (filtros.value.area_id) params.append('area_id', filtros.value.area_id)
    if (filtros.value.tipo) params.append('tipo', filtros.value.tipo)

    const response = await fetch(`/inventario/reporte/productos?${params.toString()}`)
    const data = await response.json()
    
    if (data.success) {
      registros.value = data.data
    } else {
      console.error('Error en la respuesta:', data.message)
      registros.value = []
    }
  } catch (error) {
    console.error('Error al buscar reporte:', error)
    registros.value = []
  } finally {
    loading.value = false
  }
}

// Función para limpiar filtros
function limpiarFiltros() {
  filtros.value = {
    producto_id: null,
    area_id: null,
    tipo: null,
  }
  registros.value = []
}

// Función para formatear moneda
function formatCurrency(value) {
  if (value === null || value === undefined) return 'Bs. 0.00'
  
  return `Bs. ${Number(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')}`
}

// Función para formatear número
function formatNumber(value) {
  if (value === null || value === undefined) return '0.00'
  
  return Number(value).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,')
}

// Función para obtener color del estado
function getEstadoColor(estado) {
  return estado === 'Activo' ? 'success' : 'error'
}

// Función para obtener color de ubicación
function getUbicacionColor(ubicacion) {
  return ubicacion === 'En Almacen' ? 'info' : 'warning'
}

// Función para descargar PDF
async function descargarPDF() {
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.producto_id) params.append('producto_id', filtros.value.producto_id)
    if (filtros.value.area_id) params.append('area_id', filtros.value.area_id)
    if (filtros.value.tipo) params.append('tipo', filtros.value.tipo)

    const response = await fetch(`/inventario/reporte/productos/pdf?${params.toString()}`)
    
    if (!response.ok) {
      throw new Error('Error al generar el PDF')
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = generarNombreArchivoPDF()
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error al descargar PDF:', error)
    alert('Error al generar el PDF. Por favor, intenta nuevamente.')
  }
}

// Función para generar nombre de archivo con fecha
function generarNombreArchivoPDF() {
  const ahora = new Date()
  const año = ahora.getFullYear()
  const mes = String(ahora.getMonth() + 1).padStart(2, '0')
  const día = String(ahora.getDate()).padStart(2, '0')
  const hora = String(ahora.getHours()).padStart(2, '0')
  const minuto = String(ahora.getMinutes()).padStart(2, '0')
  const segundo = String(ahora.getSeconds()).padStart(2, '0')
  
  return `Reporte_Productos_${año}${mes}${día}_${hora}${minuto}${segundo}.pdf`
}
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VIcon icon="mdi-package-variant" class="me-2" />
          Reporte de Productos
        </div>
        <VBtn
          v-if="registros.length > 0"
          color="error"
          variant="elevated"
          @click="descargarPDF"
        >
          <VIcon icon="mdi-file-pdf-box" class="me-1" />
          Descargar PDF
        </VBtn>
      </VCardTitle>

      <VCardText>
        <!-- Filtros -->
        <VRow>
          <VCol
            cols="12"
            md="4"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Producto:</label>
            <VAutocomplete
              v-model="filtros.producto_id"
              :items="productos"
              item-title="nombre"
              item-value="id_producto"
              placeholder="Seleccione producto"
              clearable
            >
              <template #item="{ props, item }">
                <VListItem v-bind="props">
                  <VListItemSubtitle>{{ item.raw.tipo }}</VListItemSubtitle>
                </VListItem>
              </template>
            </VAutocomplete>
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Área:</label>
            <VSelect
              v-model="filtros.area_id"
              :items="areas"
              item-title="nombre"
              item-value="id_area"
              placeholder="Seleccione área"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="4"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Tipo de Producto:</label>
            <VSelect
              v-model="filtros.tipo"
              :items="tiposProducto"
              placeholder="Seleccione tipo"
              clearable
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol
            cols="12"
            class="d-flex gap-2"
          >
            <VBtn
              color="primary"
              @click="buscarReporte"
              :loading="loading"
            >
              <VIcon icon="mdi-magnify" class="me-1" />
              Buscar
            </VBtn>

            <VBtn
              color="secondary"
              variant="outlined"
              @click="limpiarFiltros"
            >
              <VIcon icon="mdi-refresh" class="me-1" />
              Limpiar
            </VBtn>
          </VCol>
        </VRow>

        <!-- Totales -->
        <VRow v-if="registros.length > 0">
          <VCol cols="12">
            <VAlert
              type="info"
              variant="tonal"
              density="compact"
            >
              <div class="d-flex justify-space-between">
                <span><strong>Total Registros:</strong> {{ totales.registros }}</span>
                <span><strong>Stock Total:</strong> {{ formatNumber(totales.stock) }}</span>
                <span><strong>Costo Total:</strong> {{ formatCurrency(totales.costo_total) }}</span>
              </div>
            </VAlert>
          </VCol>
        </VRow>

        <!-- Tabla de resultados -->
        <VDataTable
          :headers="headers"
          :items="registros"
          :loading="loading"
          density="compact"
          class="mt-4"
        >
          <!-- Stock -->
          <template #item.stock="{ item }">
            {{ formatNumber(item.stock) }}
          </template>

          <!-- Costo Total -->
          <template #item.costo_total="{ item }">
            {{ formatCurrency(item.costo_total) }}
          </template>

          <!-- Ubicación (solo para activos fijos) -->
          <template #item.ubicacion="{ item }">
            <VChip
              v-if="item.es_activo_fijo"
              :color="getUbicacionColor(item.ubicacion)"
              size="small"
            >
              {{ item.ubicacion }}
            </VChip>
            <span v-else>-</span>
          </template>

          <!-- Estado -->
          <template #item.estado="{ item }">
            <VChip
              :color="getEstadoColor(item.estado)"
              size="small"
            >
              {{ item.estado }}
            </VChip>
          </template>

          <!-- No data -->
          <template #no-data>
            <div class="text-center py-8">
              <VIcon
                icon="mdi-information-outline"
                size="48"
                class="mb-4"
                color="grey"
              />
              <div class="text-h6 text-grey">
                {{ registros.length === 0 && !loading ? 'Selecciona filtros y presiona "Buscar"' : 'No se encontraron registros' }}
              </div>
            </div>
          </template>

          <!-- Loading -->
          <template #loading>
            <div class="text-center py-8">
              <VProgressCircular
                indeterminate
                color="primary"
              />
              <div class="mt-2">Cargando datos...</div>
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 0.5rem;
}
</style>
