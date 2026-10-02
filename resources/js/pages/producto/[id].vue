<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppDateTimePicker from '@core/components/app-form-elements/AppDateTimePicker.vue'
import { Spanish } from 'flatpickr/dist/l10n/es.js'

const route = useRoute()
const router = useRouter()
const producto = ref(null)
const loading = ref(true)
const error = ref(null)
const fechaInicio = ref(null)
const fechaFin = ref(null)

const fetchProducto = async () => {
  try {
    const id = route.params.id
    const res = await fetch(`/asignacion-producto/${id}`, {
      credentials: 'same-origin',
    })
    
    if (!res.ok) {
      throw new Error(`Error HTTP: ${res.status}`)
    }
    
    const data = await res.json()
    producto.value = data
  } catch (e) {
    error.value = e.message
    console.error('Error cargando producto:', e)
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchProducto()
})

const goBack = () => {
  router.push('/producto/lista')
}

// Headers para la tabla kardex
const headers = [
  { title: 'Fecha', key: 'fecha', width: '100px' },
  { title: 'Tipo Doc', key: 'tipo_doc', width: '80px' },
  { title: 'Documento', key: 'documento', width: '120px' },
  { title: 'Detalle', key: 'detalle', width: '180px' },
  { title: 'Ingreso (Cant)', key: 'ingreso', width: '100px', align: 'right' },
  { title: 'Salida (Cant)', key: 'salida', width: '100px', align: 'right' },
  { title: 'Saldo (Cant)', key: 'saldo', width: '100px', align: 'right' },
  { title: 'Costo Unit', key: 'costo_unitario', width: '100px', align: 'right' },
  { title: 'Ing Val', key: 'ing_val', width: '100px', align: 'right' },
  { title: 'Sal Val', key: 'sal_val', width: '100px', align: 'right' },
  { title: 'Saldo Val', key: 'saldo_val', width: '100px', align: 'right' },
]

// Computed para obtener los datos del kardex sin filtrar
const kardexCompleto = computed(() => {
  return producto.value?.kardex || []
})

// Computed para obtener el kardex filtrado por rango de fechas
const kardex = computed(() => {
  let items = kardexCompleto.value
  
  // Función auxiliar para obtener solo la fecha en formato YYYY-MM-DD
  const getFechaString = (fecha) => {
    if (!fecha) return null
    // Si viene como string en formato d/m/Y lo parseamos manualmente
    if (typeof fecha === 'string' && fecha.includes('/')) {
      const parts = fecha.split('/')
      if (parts.length === 3) {
        const [dd, mm, yyyy] = parts
        const d = new Date(Number(yyyy), Number(mm) - 1, Number(dd))
        if (!isNaN(d.getTime())) return d.toISOString().split('T')[0]
      }
    }
    // Si ya es Date o ISO compatible
    const d = new Date(fecha)
    if (isNaN(d.getTime())) return null
    return d.toISOString().split('T')[0]
  }
  
  // Filtrar por fecha de inicio
  if (fechaInicio.value) {
    const inicioStr = getFechaString(fechaInicio.value)
    items = items.filter(item => {
      const fechaItemStr = getFechaString(item.fecha)
      return fechaItemStr >= inicioStr
    })
  }
  
  // Filtrar por fecha fin
  if (fechaFin.value) {
    const finStr = getFechaString(fechaFin.value)
    items = items.filter(item => {
      const fechaItemStr = getFechaString(item.fecha)
      return fechaItemStr <= finStr
    })
  }
  
  return items
})

// Formato de moneda
const formatCurrency = (value) => {
  return Number(value || 0).toFixed(2)
}

// Formato de cantidad
const formatQuantity = (value) => {
  return Number(value || 0).toFixed(2)
}

// Formato de fecha (DD/MM/YYYY)
const formatDate = (dateString) => {
  if (!dateString) return '—'
  const date = new Date(dateString)
  return date.toLocaleDateString('es-ES', { year: 'numeric', month: '2-digit', day: '2-digit' })
}

const resetFechas = () => {
  fechaInicio.value = null
  fechaFin.value = null
}

// Función para descargar kardex en PDF
const descargarKardexPDF = async () => {
  try {
    const id = route.params.id
    const toIso = (fecha) => {
      if (!fecha) return ''
      if (typeof fecha === 'string' && fecha.includes('/')) {
        const parts = fecha.split('/')
        if (parts.length === 3) {
          const [dd, mm, yyyy] = parts
          const d = new Date(Number(yyyy), Number(mm) - 1, Number(dd))
          if (!isNaN(d.getTime())) return d.toISOString().split('T')[0]
        }
      }
      const d = new Date(fecha)
      if (isNaN(d.getTime())) return ''
      return d.toISOString().split('T')[0]
    }

    const fechaInicioParam = toIso(fechaInicio.value)
    const fechaFinParam = toIso(fechaFin.value)
    
    let url = `/asignacion-producto/${id}/kardex-pdf`
    const params = new URLSearchParams()
    if (fechaInicioParam) params.append('fecha_inicio', fechaInicioParam)
    if (fechaFinParam) params.append('fecha_fin', fechaFinParam)
    
    if (params.toString()) {
      url += '?' + params.toString()
    }
    
    const response = await fetch(url, {
      credentials: 'same-origin',
    })
    
    if (!response.ok) {
      throw new Error('Error al descargar PDF')
    }
    
    const blob = await response.blob()
    const urlBlob = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = urlBlob
    link.download = `Kardex_${producto.value?.asignacion?.codigo_asignacion || 'reporte'}.pdf`
    document.body.appendChild(link)
    link.click()
    window.URL.revokeObjectURL(urlBlob)
    document.body.removeChild(link)
  } catch (error) {
    console.error('Error descargando PDF:', error)
    alert('Error al descargar el PDF')
  }
}
</script>

<template>
  <VCard>
    <VCardText>
      <VBtn color="primary" class="mb-4" @click="goBack">
        <VIcon start icon="tabler-arrow-left" />
        Volver
      </VBtn>

      <div v-if="loading" class="text-center py-6">
        <VProgressCircular indeterminate color="primary" />
        <p class="mt-2">Cargando...</p>
      </div>

      <VAlert v-else-if="error" type="error" variant="tonal" class="mb-4">
        {{ error }}
      </VAlert>

      <div v-else-if="producto">
        <VAlert
          v-if="producto.asignacion?.estado_dado_baja === 1 && producto.baja"
          type="error"
          variant="tonal"
          class="mb-6"
          border="start"
        >
          <div class="text-subtitle-1 font-weight-bold mb-2">Producto dado de baja</div>
          <div class="mb-1">
            <strong>Fecha de baja: </strong>
            <span>{{ formatDate(producto.baja.fecha_baja) }}</span>
          </div>
          <div class="mb-1">
            <strong>Motivo: </strong>
            <span>{{ producto.baja.motivo || '—' }}</span>
          </div>
          <div>
            <strong>Registrado por: </strong>
            <span>{{ producto.baja.usuario || '—' }}</span>
          </div>
        </VAlert>

        <!-- Información del Producto y Asignación -->
        <div class="mb-6">
          <h2 class="mb-4">Detalles de la Asignación</h2>
          <VRow>
            <VCol cols="12" md="3">
              <div class="mb-3">
                <strong>Código Asignación: </strong> 
                <span>{{ producto.asignacion?.codigo_asignacion || '—' }}</span>
              </div>
              <div class="mb-3">
                <strong>Código de Barras: </strong> 
                <span>{{ producto.producto?.codigo || '—' }}</span>
              </div>
            </VCol>

            <VCol cols="12" md="3">
              <div class="mb-3">
                <strong>Nombre Producto: </strong> 
                <span>{{ producto.producto?.nombre || '—' }}</span>
              </div>
              <div class="mb-3">
                <strong>Tipo: </strong> 
                <span>{{ producto.producto?.tipo || '—' }}</span>
              </div>
            </VCol>

            <VCol cols="12" md="3">
              <div class="mb-3">
                <strong>Área: </strong> 
                <span>{{ producto.asignacion?.nombre_area || '—' }}</span>
              </div>
              <div class="mb-3">
                <strong>Unidad de Medida: </strong> 
                <span>{{ producto.producto?.unidad_medida || '—' }}</span>
              </div>
            </VCol>

            <VCol cols="12" md="3">
              <div class="mb-3">
                <strong>Stock Actual: </strong> 
                <span>{{ formatQuantity(producto.asignacion?.stock) }}</span>
              </div>
              <div class="mb-3">
                <strong>Costo Total: </strong> 
                <span>Bs. {{ formatCurrency(producto.asignacion?.costo_total) }}</span>
              </div>
            </VCol>
          </VRow>

          <VDivider class="my-4" />
        </div>

        <!-- Tabla de Kardex -->
        <div class="mb-6">
          <div class="d-flex justify-space-between align-center mb-4">
            <h3>Kardex de Movimientos</h3>
            <VBtn
              color="primary"
              variant="outlined"
              size="small"
              @click="descargarKardexPDF"
            >
              <VIcon start icon="tabler-download" />
              Descargar PDF
            </VBtn>
          </div>
          
          <!-- Filtros de fecha -->
          <VRow class="mb-4" align="end">
            <VCol cols="12" md="3">
              <AppDateTimePicker
                v-model="fechaInicio"
                label="Fecha Inicio"
                placeholder="Seleccione fecha inicio"
                :config="{ dateFormat: 'd/m/Y', locale: Spanish }"
              />
            </VCol>
            <VCol cols="12" md="3">
              <AppDateTimePicker
                v-model="fechaFin"
                label="Fecha Fin"
                placeholder="Seleccione fecha fin"
                :config="{ dateFormat: 'd/m/Y', locale: Spanish }"
              />
            </VCol>
            <VCol cols="12" md="3" class="d-flex gap-2">
              <VBtn color="secondary" variant="text" @click="resetFechas">
                <VIcon start icon="tabler-refresh" />
                Reset
              </VBtn>
            </VCol>
          </VRow>
          
          <div v-if="kardex.length === 0" class="text-center py-6">
            <VAlert type="info" variant="tonal">
              No hay movimientos registrados para esta asignación
            </VAlert>
          </div>

          <div v-else class="table-responsive">
            <VDataTable
              :headers="headers"
              :items="kardex"
              disable-pagination
              hide-default-footer
              density="compact"
            >
              <!-- Columna Fecha -->
              <template #item.fecha="{ item }">
                {{ formatDate(item.fecha) }}
              </template>

              <!-- Columna Tipo Doc -->
              <template #item.tipo_doc="{ item }">
                <VChip
                  :color="item.tipo_doc === 'NI' ? 'success' : item.tipo_doc === 'SA' ? 'error' : 'info'"
                  size="small"
                  label
                >
                  {{ item.tipo_doc }}
                </VChip>
              </template>

              <!-- Columna Ingreso -->
              <template #item.ingreso="{ item }">
                <span v-if="item.ingreso > 0" class="text-success font-weight-bold">
                  {{ formatQuantity(item.ingreso) }}
                </span>
                <span v-else class="text-muted">—</span>
              </template>

              <!-- Columna Salida -->
              <template #item.salida="{ item }">
                <span v-if="item.salida > 0" class="text-warning font-weight-bold">
                  {{ formatQuantity(item.salida) }}
                </span>
                <span v-else class="text-muted">—</span>
              </template>

              <!-- Columna Saldo -->
              <template #item.saldo="{ item }">
                <span class="font-weight-bold">{{ formatQuantity(item.saldo) }}</span>
              </template>

              <!-- Columna Costo Unitario -->
              <template #item.costo_unitario="{ item }">
                Bs. {{ formatCurrency(item.costo_unitario) }}
              </template>

              <!-- Columna Ing Val -->
              <template #item.ing_val="{ item }">
                <span v-if="item.ing_val > 0" class="text-success">
                  Bs. {{ formatCurrency(item.ing_val) }}
                </span>
                <span v-else class="text-muted">—</span>
              </template>

              <!-- Columna Sal Val -->
              <template #item.sal_val="{ item }">
                <span v-if="item.sal_val > 0" class="text-warning">
                  Bs. {{ formatCurrency(item.sal_val) }}
                </span>
                <span v-else class="text-muted">—</span>
              </template>

              <!-- Columna Saldo Val -->
              <template #item.saldo_val="{ item }">
                <span class="font-weight-bold">Bs. {{ formatCurrency(item.saldo_val) }}</span>
              </template>
            </VDataTable>
          </div>
        </div>
      </div>

      <div v-else>
        <VAlert type="warning" variant="tonal">
          No se encontró información de la asignación
        </VAlert>
      </div>
    </VCardText>
  </VCard>
</template>

<style scoped>
.table-responsive {
  overflow-x: auto;
}
</style>
