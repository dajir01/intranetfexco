<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

const router = useRouter()
const auth = useAuthStore()

// Verificar si el usuario puede ver reportes de feria
const puedeVerReportes = computed(() => auth.can('reportes.ferias.view'))

// Verificar si el usuario puede ver el botón "Más Detalles" en el home
const puedeVerDetallesFeria = computed(() => auth.can('home.ferias.detalles'))

// Estado
const loading = ref(false)
const error = ref(null)
const feriasResumen = ref([])

const borderColor = 'rgba(var(--v-border-color), var(--v-border-opacity))'

// Cargar resumen de todas las ferias activas
const cargarResumenFerias = async () => {
  loading.value = true
  error.value = null

  try {
    const response = await fetch('/dashboard/resumen-ferias', {
      headers: { 
        Accept: 'application/json', 
        'X-Requested-With': 'XMLHttpRequest' 
      },
      credentials: 'same-origin',
    })

    const json = await response.json()

    if (json.success) {
      feriasResumen.value = json.data
    } else {
      error.value = json.message || 'Error al cargar el resumen'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar el resumen'
  } finally {
    loading.value = false
  }
}

// Función para generar configuración de gráfico para una feria específica
const getChartConfig = (feria) => {
  return {
    chart: {
      height: 270,
      type: 'bar',
      toolbar: { show: false },
    },
    plotOptions: {
      bar: {
        horizontal: true,
        barHeight: '70%',
        distributed: true,
        borderRadius: 7,
        borderRadiusApplication: 'end',
      },
    },
    colors: [
      '#1835c1',
      'rgba(var(--v-theme-success), 1)',
      'rgba(var(--v-theme-warning), 1)',
      'rgba(var(--v-theme-info), 1)',
      'rgba(var(--v-theme-secondary), 1)',
    ],
    grid: {
      borderColor,
      strokeDashArray: 10,
      xaxis: { lines: { show: true } },
      yaxis: { lines: { show: false } },
      padding: {
        top: -35,
        bottom: -12,
      },
    },
    dataLabels: {
      enabled: true,
      style: {
        colors: ['#fff'],
        fontWeight: 500,
        fontSize: '13px',
      },
      offsetX: 0,
      dropShadow: { enabled: false },
    },
    labels: [
      'Pagados 100%',
      'Pagos Parciales',
      'Con Contrato',
      'Reservados',
      'Libres',
    ],
    xaxis: {
      categories: [
        String(feria.pagado_100),
        String(feria.pago_parcial),
        String(feria.con_contrato),
        String(feria.reservado),
        String(feria.libres),
      ],
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: {
          colors: 'rgba(var(--v-theme-on-background), var(--v-disabled-opacity))',
          fontSize: '13px',
        },
      },
    },
    yaxis: {
      labels: {
        style: {
          colors: 'rgba(var(--v-theme-on-background), var(--v-disabled-opacity))',
          fontSize: '13px',
        },
      },
    },
    tooltip: {
      enabled: true,
      style: { fontSize: '12px' },
      y: {
        formatter(val) {
          return `${val} stands`
        },
      },
    },
    legend: { show: false },
  }
}

// Función para generar series de una feria específica
const getChartSeries = (feria) => {
  return [{
    name: 'Cantidad',
    data: [
      feria.pagado_100,
      feria.pago_parcial,
      feria.con_contrato,
      feria.reservado,
      feria.libres,
    ],
  }]
}

// Función para generar datos de estadísticas de una feria
const getEstadisticasData = (feria) => {
  return [
    {
      title: 'Pagados al 100%',
      value: feria.pagado_100,
      color: '#1835c1',
      icon: 'tabler-check',
    },
    {
      title: 'Pagos Parciales',
      value: feria.pago_parcial,
      color: 'success',
      icon: 'tabler-clock',
    },
    {
      title: 'Con Contrato',
      value: feria.con_contrato,
      color: 'warning',
      icon: 'tabler-file-text',
    },
    {
      title: 'Reservados',
      value: feria.reservado,
      color: 'info',
      icon: 'tabler-bookmark',
    },
    {
      title: 'Libres',
      value: feria.libres,
      color: 'secondary',
      icon: 'tabler-square',
    },
  ]
}

// Cargar datos al montar el componente
onMounted(async () => {
  if (puedeVerDetallesFeria.value)
    await cargarResumenFerias()
})
</script>

<template>
  <div>
    <VCard
      class="mb-6"
      title="Bienvenidos al Sistema Intranet de FEXCO"
    >
      <VCardText>
        Este sistema está diseñado para la gestión integral de las operaciones de FEXCO, 
        integrando módulos de inventario, ferias, reportes, pagos y control de activos organizacionales.
      </VCardText>
      <VCardText>
        Utilice el menú lateral para acceder a las diferentes funcionalidades del sistema y mantener 
        un control eficiente de todos los procesos y recursos de la organización.
      </VCardText>
    </VCard>

    <VCard v-if="!puedeVerDetallesFeria">
      <VCardText>
        <VAlert type="info" variant="tonal">
          No tienes permiso para ver los resúmenes de ferias.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Loading -->
    <VCard v-else-if="loading">
      <VCardText class="text-center py-8">
        <VProgressCircular
          indeterminate
          color="primary"
          size="64"
        />
        <p class="text-body-1 mt-4">
          Cargando datos...
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

    <!-- Sin ferias activas -->
    <VCard v-else-if="feriasResumen.length === 0">
      <VCardText>
        <VAlert
          type="info"
          variant="tonal"
        >
          No hay ferias activas para mostrar
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Card individual por cada feria -->
    <VCard
      v-for="feria in feriasResumen"
      :key="feria.id_feria"
      class="mb-6"
    >
      <VCardItem>
        <VCardTitle class="d-flex align-center gap-2">
          <VIcon icon="tabler-calendar-event" />
          <span>{{ feria.nombre_feria }}</span>
        </VCardTitle>
        <template #append>
          <VChip
            color="primary"
            size="small"
          >
            <VIcon icon="tabler-building-warehouse" size="16" class="me-1" />
            Total: {{ feria.total_stands }}
          </VChip>
        </template>
      </VCardItem>

      <VDivider />

      <VCardText>
        <VRow>
          <VCol
            cols="12"
            md="6"
            xl="8"
            lg="7"
          >
            <div>
              <VueApexCharts
                type="bar"
                height="270"
                :options="getChartConfig(feria)"
                :series="getChartSeries(feria)"
              />
            </div>
          </VCol>

          <VCol
            cols="12"
            md="6"
            lg="5"
            xl="4"
          >
            <div class="estadisticas-feria d-flex flex-column gap-6 ms-auto">
              <div
                v-for="stat in getEstadisticasData(feria)"
                :key="stat.title"
                class="d-flex gap-x-3 align-center"
              >
                <VBadge
                  dot
                  inline
                  :color="stat.color"
                />
                <div class="d-flex align-center gap-1">
                  <div class="text-body-2">
                    {{ stat.title }}:
                  </div>
                  <h5 class="text-h5 mb-0">
                    {{ stat.value }}
                  </h5>
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
      
      <VDivider />
      
      <VCardActions v-if="puedeVerDetallesFeria">
        <VSpacer />
        <VBtn
          color="primary"
          @click="router.push({ path: `/reporte/${feria.id_feria}` })"
        >
          Más Detalles
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>

<style lang="scss" scoped>
@media screen and (min-width: 960px) {
  .estadisticas-feria {
    inline-size: auto;
  }
}
</style>
