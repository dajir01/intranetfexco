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
const areas = ref([])

// Filtros
const filtros = ref({
  tipo_movimiento: null,
  area_id: null,
  fecha_inicio: null,
  fecha_fin: null,
})

// Opciones para el filtro de tipo de movimiento
const tipoMovimientoOptions = [
  { value: null, title: 'Todos' },
  { value: 1, title: 'Salida (SA)' },
  { value: 2, title: 'Ingreso (IA)' },
]

// Headers de la tabla
const headers = [
  { title: 'Código', key: 'codigo', sortable: true },
  { title: 'Tipo', key: 'tipo', sortable: true },
  { title: 'Fecha', key: 'fecha', sortable: true },
  { title: 'Área', key: 'area', sortable: true },
  { title: 'Observaciones', key: 'observaciones', sortable: false },
  { title: 'Total', key: 'total', sortable: true, align: 'end' },
]

// Computed para totales
const totales = computed(() => {
  if (!registros.value || registros.value.length === 0) {
    return {
      total: 0,
      registros: 0,
    }
  }

  return {
    total: registros.value.reduce((sum, item) => sum + (item.total || 0), 0),
    registros: registros.value.length,
  }
})

// Cargar datos iniciales
onMounted(async () => {
  await cargarAreas()
})

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

// Función para buscar con filtros
async function buscarReporte() {
  loading.value = true
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.tipo_movimiento) params.append('tipo_movimiento', filtros.value.tipo_movimiento)
    if (filtros.value.area_id) params.append('area_id', filtros.value.area_id)
    if (filtros.value.fecha_inicio) params.append('fecha_inicio', formatDateToISO(filtros.value.fecha_inicio))
    if (filtros.value.fecha_fin) params.append('fecha_fin', formatDateToISO(filtros.value.fecha_fin))

    const response = await fetch(`/inventario/reporte/movimientos?${params.toString()}`)
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
    tipo_movimiento: null,
    area_id: null,
    fecha_inicio: null,
    fecha_fin: null,
  }
  registros.value = []
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

// Función para obtener color del tipo de movimiento
function getTipoColor(tipo) {
  return tipo === 'Salida' ? 'error' : 'success'
}

// Función para descargar PDF
async function descargarPDF() {
  try {
    // Construir query params
    const params = new URLSearchParams()
    
    if (filtros.value.tipo_movimiento) params.append('tipo_movimiento', filtros.value.tipo_movimiento)
    if (filtros.value.area_id) params.append('area_id', filtros.value.area_id)
    if (filtros.value.fecha_inicio) params.append('fecha_inicio', formatDateToISO(filtros.value.fecha_inicio))
    if (filtros.value.fecha_fin) params.append('fecha_fin', formatDateToISO(filtros.value.fecha_fin))

    const response = await fetch(`/inventario/reporte/movimientos/pdf?${params.toString()}`)
    
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
  
  return `Reporte_Movimientos_${año}${mes}${día}_${hora}${minuto}${segundo}.pdf`
}
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <VIcon icon="mdi-swap-horizontal" class="me-2" />
          Reporte de Movimientos de Inventario
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
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Tipo de Movimiento:</label>
            <VSelect
              v-model="filtros.tipo_movimiento"
              :items="tipoMovimientoOptions"
              item-title="title"
              item-value="value"
              placeholder="Seleccione tipo"
              clearable
            />
          </VCol>

          <VCol
            cols="12"
            md="3"
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
            md="3"
          >
            <label class="text-sm font-weight-medium mb-1 d-block">Fecha - Desde:</label>
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
            <label class="text-sm font-weight-medium mb-1 d-block">Fecha - Hasta:</label>
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
                <span><strong>Total General:</strong> {{ formatCurrency(totales.total) }}</span>
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
          <!-- Fecha -->
          <template #item.fecha="{ item }">
            {{ formatDate(item.fecha) }}
          </template>

          <!-- Tipo -->
          <template #item.tipo="{ item }">
            <VChip
              :color="getTipoColor(item.tipo)"
              size="small"
            >
              {{ item.tipo }}
            </VChip>
          </template>

          <!-- Total -->
          <template #item.total="{ item }">
            {{ formatCurrency(item.total) }}
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
