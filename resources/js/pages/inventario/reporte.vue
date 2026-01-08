<script setup>
import { ref, computed, onMounted } from 'vue'
import AppDateTimePicker from '@core/components/app-form-elements/AppDateTimePicker.vue'
import { Spanish } from 'flatpickr/dist/l10n/es.js'

definePage({
  meta: {
    requiresAuth: true,
  },
})

// Estados reactivos
const loading = ref(false)
const registros = ref([])
const proveedores = ref([])

// Filtros
const filtros = ref({
  proveedor_id: null,
  fecha_inicio: null,
  fecha_fin: null,
  fecha_factura: null,
  estado: null, // null = todos, 'activo', 'anulado'
})

// Opciones para el filtro de estado
const estadoOptions = [
  { value: null, title: 'Todos' },
  { value: 'activo', title: 'Activo' },
  { value: 'anulado', title: 'Anulado' },
]

// Headers de la tabla
const headers = [
  { title: 'N° Ingreso', key: 'numero', sortable: true },
  { title: 'Fecha Ingreso', key: 'fecha_ingreso', sortable: true },
  { title: 'Fecha Factura', key: 'fecha_factura', sortable: true },
  { title: 'N° Factura', key: 'factura_numero', sortable: false },
  { title: 'Proveedor', key: 'proveedor', sortable: true },
  { title: 'Importe', key: 'importe', sortable: true, align: 'end' },
  { title: 'Persona Recibe', key: 'persona_recibe', sortable: false },
  { title: 'Persona Entrega', key: 'persona_entrega', sortable: false },
  { title: 'Observaciones', key: 'observaciones', sortable: false },
  { title: 'Estado', key: 'estado', sortable: true },
]

// Computed para totales
const totales = computed(() => {
  if (!registros.value || registros.value.length === 0) {
    return {
      importe: 0,
      registros: 0,
    }
  }

  return {
    importe: registros.value.reduce((sum, item) => sum + (item.importe || 0), 0),
    registros: registros.value.length,
  }
})

// Cargar datos iniciales
onMounted(async () => {
  await cargarProveedores()
})

// Función para cargar proveedores
async function cargarProveedores() {
  try {
    const response = await fetch('/inventario/reporte/proveedores')
    const data = await response.json()
    if (data.success) {
      proveedores.value = data.data
    }
  } catch (error) {
    console.error('Error al cargar proveedores:', error)
  }
}

// Función para buscar con filtros
async function buscarReporte() {
  loading.value = true
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.proveedor_id) params.append('proveedor_id', filtros.value.proveedor_id)
    if (filtros.value.fecha_inicio) params.append('fecha_inicio', formatDateToISO(filtros.value.fecha_inicio))
    if (filtros.value.fecha_fin) params.append('fecha_fin', formatDateToISO(filtros.value.fecha_fin))
    if (filtros.value.fecha_factura) params.append('fecha_factura', formatDateToISO(filtros.value.fecha_factura))
    if (filtros.value.estado) params.append('estado', filtros.value.estado)

    console.log('Filtros enviados:', {
      proveedor_id: filtros.value.proveedor_id,
      fecha_inicio: filtros.value.fecha_inicio,
      fecha_fin: filtros.value.fecha_fin,
      fecha_factura: filtros.value.fecha_factura,
      estado: filtros.value.estado
    })
    console.log('URL:', `/inventario/reporte/notas-ingreso?${params.toString()}`)

    const response = await fetch(`/inventario/reporte/notas-ingreso?${params.toString()}`)
    const data = await response.json()
    
    console.log('Respuesta del servidor:', data)
    
    if (data.success) {
      registros.value = data.data
      console.log('Registros cargados:', registros.value.length)
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
    proveedor_id: null,
    fecha_inicio: null,
    fecha_fin: null,
    fecha_factura: null,
    estado: null,
  }
  registros.value = []
}

// Función para descargar PDF
async function descargarPDF() {
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.proveedor_id) params.append('proveedor_id', filtros.value.proveedor_id)
    if (filtros.value.fecha_inicio) params.append('fecha_inicio', formatDateToISO(filtros.value.fecha_inicio))
    if (filtros.value.fecha_fin) params.append('fecha_fin', formatDateToISO(filtros.value.fecha_fin))
    if (filtros.value.fecha_factura) params.append('fecha_factura', formatDateToISO(filtros.value.fecha_factura))
    if (filtros.value.estado) params.append('estado', filtros.value.estado)

    const response = await fetch(`/inventario/reporte/notas-ingreso/pdf?${params.toString()}`)
    
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

// Función para formatear fecha a ISO (YYYY-MM-DD)
function formatDateToISO(dateString) {
  if (!dateString) return null
  
  // Si ya viene en formato ISO
  if (dateString.includes('-') && dateString.split('-')[0].length === 4) {
    return dateString.split('T')[0]
  }
  
  // Si viene en formato d/m/Y
  if (dateString.includes('/')) {
    const [day, month, year] = dateString.split('/')
    return `${year}-${month.padStart(2, '0')}-${day.padStart(2, '0')}`
  }
  
  return dateString
}

// Función para formatear fecha a dd/mm/yyyy
function formatDate(dateString) {
  if (!dateString) return '-'
  
  try {
    const date = new Date(dateString)
    const day = String(date.getDate()).padStart(2, '0')
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const year = date.getFullYear()
    
    return `${day}/${month}/${year}`
  } catch (error) {
    return dateString
  }
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

// Función para generar nombre de archivo con fecha
function generarNombreArchivoPDF() {
  const ahora = new Date()
  const año = ahora.getFullYear()
  const mes = String(ahora.getMonth() + 1).padStart(2, '0')
  const día = String(ahora.getDate()).padStart(2, '0')
  const hora = String(ahora.getHours()).padStart(2, '0')
  const minuto = String(ahora.getMinutes()).padStart(2, '0')
  const segundo = String(ahora.getSeconds()).padStart(2, '0')
  
  return `Reporte_Notas_Ingreso_${año}${mes}${día}_${hora}${minuto}${segundo}.pdf`
}
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VIcon icon="mdi-file-document-outline" class="me-2" />
          Reporte de Notas de Ingreso
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
            class="text-no-wrap"
            cols="12"
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Datos del Proveedor:</label>
            <VAutocomplete
              v-model="filtros.proveedor_id"
              :items="proveedores"
              item-title="nombre"
              item-value="id_proveedores"
              placeholder="Seleccione proveedor"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Estado:</label>
            <VSelect
              v-model="filtros.estado"
              :items="estadoOptions"
              item-title="title"
              item-value="value"
              placeholder="Seleccione estado"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Fecha Ingreso - Desde:</label>
            <AppDateTimePicker
              v-model="filtros.fecha_inicio"
              placeholder="Seleccione una fecha"
              :config="{
                dateFormat: 'd/m/Y',
                locale: Spanish,
              }"
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Fecha Ingreso - Hasta:</label>
            <AppDateTimePicker
              v-model="filtros.fecha_fin"
              placeholder="Seleccione una fecha"
              :config="{
                dateFormat: 'd/m/Y',
                locale: Spanish,
              }"
            />
          </VCol>
        </VRow>

        <VRow>
          <VCol
            cols="12"
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Fecha Factura:</label>
            <AppDateTimePicker
              v-model="filtros.fecha_factura"
              placeholder="Seleccione una fecha"
              :config="{
                dateFormat: 'd/m/Y',
                locale: Spanish,
              }"
            />
          </VCol>

          <VCol
            cols="12"
            md="9"
            class="d-flex align-center gap-2"
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
                <span><strong>Total Importe:</strong> {{ formatCurrency(totales.importe) }}</span>
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
          <!-- Fecha Ingreso -->
          <template #item.fecha_ingreso="{ item }">
            {{ formatDate(item.fecha_ingreso) }}
          </template>

          <!-- Fecha Factura -->
          <template #item.fecha_factura="{ item }">
            {{ formatDate(item.fecha_factura) }}
          </template>

          <!-- Importe -->
          <template #item.importe="{ item }">
            {{ formatCurrency(item.importe) }}
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
