<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()

// Estado
const loading = ref(false)
const error = ref(null)
const reporteData = ref(null)
const idFeria = ref(null)

// Filtro reactivo
const filtroSeleccionado = ref('todos')

// Obtener el ID de la feria desde los parámetros de la ruta
onMounted(async () => {
  idFeria.value = route.params.id
  await cargarReporte()
})

// Cargar el reporte completo del backend
const cargarReporte = async () => {
  if (!idFeria.value) {
    error.value = 'No se especificó una feria válida'
    return
  }

  loading.value = true
  error.value = null

  try {
    const response = await fetch(`/contratos/reporte/estado-ventas/${idFeria.value}`, {
      headers: { 
        Accept: 'application/json', 
        'X-Requested-With': 'XMLHttpRequest' 
      },
      credentials: 'same-origin',
    })

    const json = await response.json()

    if (json.success) {
      reporteData.value = json.data
    } else {
      error.value = json.message || 'Error al cargar el reporte'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar el reporte'
  } finally {
    loading.value = false
  }
}

// Computed: Feria info
const feriaInfo = computed(() => reporteData.value?.feria || null)

// Computed: Pabellones filtrados según el select
const pabellonesFiltrados = computed(() => {
  if (!reporteData.value?.pabellones) return []
  
  if (filtroSeleccionado.value === 'todos') {
    return reporteData.value.pabellones
  } else if (filtroSeleccionado.value === 'resumen') {
    return [] // Solo mostrar resumen
  } else {
    // Filtrar por pabellón específico
    return reporteData.value.pabellones.filter(
      p => p.id_pabellon === parseInt(filtroSeleccionado.value)
    )
  }
})

// Computed: Mostrar resumen
const mostrarResumen = computed(() => {
  return filtroSeleccionado.value === 'resumen' || filtroSeleccionado.value === 'todos'
})

// Computed: Totales generales
const totales = computed(() => reporteData.value?.totales || null)
const porcentajesTotales = computed(() => reporteData.value?.porcentajes_totales || null)

// Opciones del filtro
const opcionesFiltro = computed(() => {
  const opciones = [
    { title: 'Todos los Pabellones', value: 'todos' },
  ]

  if (reporteData.value?.pabellones) {
    reporteData.value.pabellones.forEach(p => {
      opciones.push({
        title: p.nombre_pabellon,
        value: p.id_pabellon,
      })
    })
  }

  opciones.push({ title: 'Sólo Resumen', value: 'resumen' })

  return opciones
})

// Función para obtener color de badge según estado
const getEstadoColor = (estado) => {
  switch (estado) {
    case 'pagado_100':
      return 'blue-darken-2' // Azul oscuro = Pago completo
    case 'pago_parcial':
      return 'success' // Verde = Pago parcial
    case 'con_contrato':
      return 'warning' // Amarillo = Con contrato
    case 'reservado':
      return 'info' // Info = Reservado
    case 'libre':
      return 'default'
    default:
      return 'default'
  }
}

// Función para obtener texto legible del estado
const getEstadoTexto = (estado) => {
  switch (estado) {
    case 'pagado_100':
      return 'Pagado 100%'
    case 'pago_parcial':
      return 'Pago Parcial'
    case 'con_contrato':
      return 'Con Contrato'
    case 'reservado':
      return 'Reservado'
    case 'libre':
      return 'Libre'
    default:
      return estado
  }
}

// Volver a la lista
const volverALista = () => {
  router.push({ 
    path: '/contrato/list',
    query: { id_feria: idFeria.value }
  })
}

// Manejar error de carga de imagen
const handleImageError = (event) => {
  event.target.style.display = 'none'
}

// El backend entrega una URL versionada para evitar caché en cada carga del reporte
const getImagenUrl = (pabellon) => {
  return pabellon.mapa_url
}
</script>

<template>
  <div>
    <!-- Header Card -->
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div>
          <h4 class="text-h4">
            <VIcon icon="tabler-chart-bar" class="me-2" />
            Estado de Ventas
          </h4>
          <p 
            v-if="feriaInfo" 
            class="text-body-2 text-medium-emphasis mt-1"
          >
            {{ feriaInfo.nombre }}
          </p>
        </div>
        <VBtn
          color="secondary"
          variant="outlined"
          prepend-icon="tabler-arrow-left"
          @click="volverALista"
        >
          Volver
        </VBtn>
      </VCardTitle>
      
      <VDivider />

      <VCardText>
        <!-- Selector de filtro -->
        <VRow v-if="reporteData && !loading">
          <VCol
            cols="12"
            md="6"
          >
            <VSelect
              v-model="filtroSeleccionado"
              :items="opcionesFiltro"
              label="Seleccionar vista"
              prepend-inner-icon="tabler-filter"
              variant="outlined"
              density="comfortable"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Loading -->
    <VCard v-if="loading">
      <VCardText class="text-center py-8">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <p class="text-body-1 mt-4">
          Cargando reporte...
        </p>
      </VCardText>
    </VCard>

    <!-- Error -->
    <VCard v-else-if="error">
      <VCardText>
        <VAlert
          type="error"
          variant="tonal"
        >
          {{ error }}
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Contenido del reporte -->
    <template v-else-if="reporteData">
      <!-- Listado por Pabellón -->
      <VCard
        v-for="pabellon in pabellonesFiltrados"
        :key="pabellon.id_pabellon"
        class="mb-6"
      >
        <VCardTitle class="bg-grey-lighten-4">
          <div class="d-flex align-center justify-space-between flex-wrap gap-4">
            <div>
              <VIcon icon="tabler-building-warehouse" class="me-2" />
              <span class="text-h5">{{ pabellon.nombre_pabellon }}</span>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <VChip
                color="blue-darken-2"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-check" size="16" class="me-1" />
                Pagado: {{ pabellon.estadisticas.pagado_100 }}
              </VChip>
              <VChip
                color="success"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-clock" size="16" class="me-1" />
                Parcial: {{ pabellon.estadisticas.pago_parcial }}
              </VChip>
              <VChip
                color="warning"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-file-text" size="16" class="me-1" />
                Contrato: {{ pabellon.estadisticas.con_contrato }}
              </VChip>
              <VChip
                color="info"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-bookmark" size="16" class="me-1" />
                Reservado: {{ pabellon.estadisticas.reservado }}
              </VChip>
              <VChip
                color="default"
                variant="tonal"
                size="small"
              >
                <VIcon icon="tabler-square" size="16" class="me-1" />
                Libres: {{ pabellon.estadisticas.libres }}
              </VChip>
            </div>
          </div>
        </VCardTitle>

        <VDivider />

        <VCardText>
          <VRow>
            <!-- Tabla de Stands -->
            <VCol
              cols="12"
              md="6"
            >
              <div class="mb-4">
                <h6 class="text-h6 mb-3">
                  Stands y Empresas
                </h6>
                <VTable
                  density="compact"
                  class="reporte-stands-table"
                >
                  <thead>
                    <tr>
                      <th class="stands-columna-nowrap">Stand</th>
                      <th>Empresa</th>
                      <th class="stands-columna-nowrap">Estado</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="contrato in pabellon.stands"
                      :key="contrato.id_contrato"
                    >
                      <td class="stands-columna-nowrap">
                        <span class="text-body-2">
                          {{ contrato.stands_texto }}
                        </span>
                      </td>
                      <td>
                        <span
                          v-if="contrato.empresa"
                          class="empresa-nombre"
                        >
                          {{ contrato.empresa.nombre_empresa }}
                        </span>
                        <span
                          v-else
                          class="text-medium-emphasis"
                        >
                          —
                        </span>
                      </td>
                      <td class="stands-columna-nowrap">
                        <VChip
                          :color="getEstadoColor(contrato.estado)"
                          size="small"
                          variant="tonal"
                        >
                          {{ getEstadoTexto(contrato.estado) }}
                        </VChip>
                      </td>
                    </tr>
                    <tr v-if="pabellon.stands.length === 0">
                      <td
                        colspan="3"
                        class="text-center text-medium-emphasis"
                      >
                        No hay stands ocupados en este pabellón
                      </td>
                    </tr>
                  </tbody>
                </VTable>
              </div>
            </VCol>

            <!-- Imagen del pabellón -->
            <VCol
              cols="12"
              md="6"
            >
              <div class="mb-4">
                <h6 class="text-h6 mb-3">
                  Plano del Pabellón
                </h6>
                <div class="d-flex justify-center">
                  <img
                    :src="getImagenUrl(pabellon)"
                    :alt="`Plano ${pabellon.nombre_pabellon}`"
                    style="max-width: 100%; height: auto; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);"
                    @error="handleImageError"
                  >
                </div>
              </div>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <!-- Cuadro Resumen General -->
      <VCard v-if="mostrarResumen && totales">
        <VCardTitle class="bg-primary text-white">
          <VIcon icon="tabler-file-analytics" class="me-2" />
          <span class="text-h5 text-white">Resumen General</span>
        </VCardTitle>

        <VDivider />

        <VCardText>
          <div class="table-responsive">
            <VTable
              density="comfortable"
              class="text-no-wrap"
            >
              <thead>
                <tr class="bg-grey-lighten-4">
                  <th class="font-weight-bold">
                    Pabellón
                  </th>
                  <th class="text-center font-weight-bold">
                    Pagado 100%
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                  <th class="text-center font-weight-bold">
                    Pago Parcial
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                  <th class="text-center font-weight-bold">
                    Con Contrato
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                  <th class="text-center font-weight-bold">
                    Reservado
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                  <th class="text-center font-weight-bold">
                    Libres
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                  <th class="text-center font-weight-bold">
                    Total
                  </th>
                  <th class="text-center font-weight-bold">
                    %
                  </th>
                </tr>
              </thead>
              <tbody>
                <!-- Fila por cada pabellón -->
                <tr
                  v-for="pabellon in reporteData.pabellones"
                  :key="pabellon.id_pabellon"
                >
                  <td class="font-weight-medium">
                    {{ pabellon.nombre_pabellon }}
                  </td>
                  <td class="text-center">
                    <VChip
                      color="blue-darken-2"
                      size="small"
                         variant="tonal"
                    >
                      {{ pabellon.estadisticas.pagado_100 }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ pabellon.porcentajes.pagado_100 }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="success"
                      size="small"
                      variant="tonal"
                    >
                      {{ pabellon.estadisticas.pago_parcial }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ pabellon.porcentajes.pago_parcial }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="warning"
                      size="small"
                      variant="tonal"
                    >
                      {{ pabellon.estadisticas.con_contrato }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ pabellon.porcentajes.con_contrato }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="info"
                      size="small"
                      variant="tonal"
                    >
                      {{ pabellon.estadisticas.reservado }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ pabellon.porcentajes.reservado }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="default"
                      size="small"
                      variant="tonal"
                    >
                      {{ pabellon.estadisticas.libres }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ pabellon.porcentajes.libres }}%
                  </td>
                  <td class="text-center font-weight-bold">
                    {{ pabellon.estadisticas.total_stands }}
                  </td>
                  <td class="text-center">
                    100%
                  </td>
                </tr>

                <!-- Fila de TOTAL GENERAL -->
                <tr class="bg-grey-lighten-4 font-weight-bold">
                  <td class="text-uppercase">
                    <VIcon icon="tabler-sum" class="me-1" />
                    Total General
                  </td>
                  <td class="text-center">
                    <VChip
                      color="blue-darken-2"
                      size="small"
                    >
                      {{ totales.pagado_100 }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ porcentajesTotales.pagado_100 }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="success"
                      size="small"
                    >
                      {{ totales.pago_parcial }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ porcentajesTotales.pago_parcial }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="warning"
                      size="small"
                    >
                      {{ totales.con_contrato }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ porcentajesTotales.con_contrato }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="info"
                      size="small"
                    >
                      {{ totales.reservado }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ porcentajesTotales.reservado }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="default"
                      size="small"
                    >
                      {{ totales.libres }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    {{ porcentajesTotales.libres }}%
                  </td>
                  <td class="text-center">
                    <VChip
                      color="primary"
                      size="small"
                    >
                      {{ totales.total_stands }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    100%
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>
      </VCard>
    </template>
  </div>
</template>

<style scoped>
.table-responsive {
  overflow-x: auto;
}

.reporte-stands-table {
  table-layout: fixed;
  width: 100%;
}

.stands-columna-nowrap {
  white-space: nowrap;
}

.empresa-nombre {
  display: -webkit-box;
  overflow: hidden;
  white-space: normal;
  word-break: break-word;
  line-height: 1.25rem;
  -webkit-box-orient: vertical;
  -webkit-line-clamp: 4;
}
</style>
