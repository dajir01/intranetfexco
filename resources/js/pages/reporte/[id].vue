<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import VueApexCharts from 'vue3-apexcharts'
import CardStadisticsStandsRadialCharts from '@core/components/cards/CardStadisticsStandsRadialCharts.vue'
import CardStadiscsPrecioRadialCharts from '@core/components/cards/CardStadiscsPrecioRadialCharts.vue'
import CardGraficoStandOverview from '@core/components/cards/CardGraficoStandOverview.vue'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
  },
})

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const hasCheckedAuth = computed(() => auth.hasCheckedAuth)
const canViewReportes = computed(() => auth.can('home.ferias.detalles'))

const validarPermiso = () => {
  if (!hasCheckedAuth.value)
    return

  if (!canViewReportes.value)
    router.push('/')
}

const loading = ref(false)
const error = ref(null)
const reporteData = ref(null)

const feriaId = computed(() => route.params.id)
const pabellonId = computed(() => (route.query.pabellon_id ? Number(route.query.pabellon_id) : null))

const borderColor = 'rgba(var(--v-border-color), var(--v-border-opacity))'

const resumenStandsBase = {
  total_stands: 0,
  reservados: 0,
  contrato: 0,
  pago_parcial: 0,
  pagado_total: 0,
  libres: 0,
  porcentajes: {
    reservados: 0,
    contrato: 0,
    pago_parcial: 0,
    pagado_total: 0,
    libres: 0,
  },
}

const resumenPagosBase = {
  total_contrato_generado: 0,
  ingreso_parcial: 0,
  pendiente_cobro: 0,
  porcentajes: {
    ingreso_parcial: 0,
    pendiente_cobro: 0,
  },
}

const feriaInfo = computed(() => reporteData.value?.feria || null)
const titulo = computed(() => reporteData.value?.titulo || 'Reporte Total')
const pabellones = computed(() => reporteData.value?.pabellones || [])
const resumenStands = computed(() => reporteData.value?.resumen_stands || resumenStandsBase)
const resumenPagos = computed(() => reporteData.value?.resumen_pagos || resumenPagosBase)

const cargarReporte = async () => {
  if (!feriaId.value) {
    error.value = 'No se especifico una feria valida'
    return
  }

  loading.value = true
  error.value = null

  try {
    const params = pabellonId.value ? `?pabellon_id=${pabellonId.value}` : ''
    const response = await fetch(`/api/reporte-feria/${feriaId.value}${params}`, {
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
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
    error.value = 'Error de conexion al cargar el reporte'
  } finally {
    loading.value = false
  }
}

const seleccionarPabellon = (id) => {
  if (id === pabellonId.value) return

  router.push({
    path: `/reporte/${feriaId.value}`,
    query: id ? { pabellon_id: id } : {},
  })
}

const formatMonto = (value) => {
  const monto = Number(value || 0)
  return monto.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

const toNumber = (value) => Number(value ?? 0)

const descargarPDF = async () => {
  if (!feriaId.value) return

  try {
    const params = pabellonId.value ? `?pabellon_id=${pabellonId.value}` : ''
    const response = await fetch(`/api/reporte-feria/${feriaId.value}/pdf${params}`, {
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
      },
      credentials: 'same-origin',
    })

    if (!response.ok) throw new Error('Error al descargar PDF')

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `reporte_${titulo.value.replace(/\s+/g, '_').toLowerCase()}_${new Date().toISOString().slice(0, 10)}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    error.value = 'Error al descargar el PDF'
  }
}


const standOverviewItems = computed(() => [
  {
    icon: 'tabler-bookmark',
    title: 'Reservados',
    countLabel: `${toNumber(resumenStands.value.reservados)} stands`,
    percentage: toNumber(resumenStands.value.porcentajes.reservados),
    progressColor: 'rgba(var(--v-theme-info), 1)',
    progressTextClass: 'text-white text-sm font-weight-medium text-start',
  },
  {
    icon: 'tabler-file-text',
    title: 'Contrato',
    countLabel: `${toNumber(resumenStands.value.contrato)} stands`,
    percentage: toNumber(resumenStands.value.porcentajes.contrato),
    progressColor: 'rgba(var(--v-theme-warning), 1)',
    progressTextClass: 'text-white text-sm font-weight-medium text-start',
  },
  {
    icon: 'tabler-clock',
    title: 'Pago Parcial',
    countLabel: `${toNumber(resumenStands.value.pago_parcial)} stands`,
    percentage: toNumber(resumenStands.value.porcentajes.pago_parcial),
    progressColor: 'rgba(var(--v-theme-success), 1)',
    progressTextClass: 'text-white text-sm font-weight-medium text-start',
  },
  {
    icon: 'tabler-check',
    title: 'Pagado 100%',
    countLabel: `${toNumber(resumenStands.value.pagado_total)} stands`,
    percentage: toNumber(resumenStands.value.porcentajes.pagado_total),
    progressColor: '#1835c1',
    progressTextClass: 'text-white text-sm font-weight-medium text-start',
  },
  {
    icon: 'tabler-square',
    title: 'Libres',
    countLabel: `${toNumber(resumenStands.value.libres)} stands`,
    percentage: toNumber(resumenStands.value.porcentajes.libres),
    progressColor: 'rgba(var(--v-theme-secondary), 1)',
    progressTextClass: 'text-sm text-surface font-weight-medium text-start',
  },
])

const balanceSeries = computed(() => [{
  name: 'Monto Bs',
  data: [
    toNumber(resumenPagos.value.total_contrato_generado),
    toNumber(resumenPagos.value.ingreso_parcial),
    toNumber(resumenPagos.value.pendiente_cobro),
  ],
}])

const balanceEstadisticasData = computed(() => [
  {
    title: 'Total Contrato',
    value: `${formatMonto(resumenPagos.value.total_contrato_generado)} Bs`,
    color: 'warning',
    colorCode: 'rgba(var(--v-theme-warning), 1)',
  },
  {
    title: 'Ingreso Parcial',
    value: `${formatMonto(resumenPagos.value.ingreso_parcial)} Bs`,
    color: 'success',
    colorCode: 'rgba(var(--v-theme-success), 1)',
  },
  {
    title: 'Pendiente Cobro',
    value: `${formatMonto(resumenPagos.value.pendiente_cobro)} Bs`,
    color: 'error',
    colorCode: 'rgba(var(--v-theme-error), 1)',
  },
])

const balanceOptions = computed(() => ({
  chart: {
    height: 280,
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
    'rgba(var(--v-theme-warning), 1)',
    'rgba(var(--v-theme-success), 1)',
    'rgba(var(--v-theme-error), 1)',
  ],
  grid: {
    borderColor,
    strokeDashArray: 10,
    xaxis: { lines: { show: true } },
    yaxis: { lines: { show: false } },
    padding: {
      top: -10,
      bottom: -10,
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
    'Total Contrato',
    'Ingreso Parcial',
    'Pendiente Cobro',
  ],
  xaxis: {
    categories: [
      String(formatMonto(toNumber(resumenPagos.value.total_contrato_generado))),
      String(formatMonto(toNumber(resumenPagos.value.ingreso_parcial))),
      String(formatMonto(toNumber(resumenPagos.value.pendiente_cobro))),
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
        return `${formatMonto(val)} Bs`
      },
    },
  },
  legend: { show: false },
}))

onMounted(() => {
  validarPermiso()
  cargarReporte()
})

watch(
  () => [route.params.id, route.query.pabellon_id],
  () => {
    cargarReporte()
  },
)

watch([hasCheckedAuth, canViewReportes], () => {
  validarPermiso()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-3">
          <VBtn
            color="default"
            variant="text"
            icon="tabler-arrow-left"
            @click="$router.push('/')"
          />
          <div>
            <h4 class="text-h4">
              <VIcon icon="tabler-chart-bar" class="me-2" />
              {{ titulo }}
            </h4>
            <p class="text-body-2 text-medium-emphasis mt-1">
              Evento > {{ feriaInfo?.nombre_feria || '---' }}
            </p>
          </div>
        </div>
        <VBtn
          size="small"
          color="primary"
          variant="flat"
          @click="descargarPDF"
          prepend-icon="tabler-download"
        >
          Descargar Reporte
        </VBtn>
      </VCardTitle>

      <VDivider />

      <VCardText>
        <VRow class="align-center">
          <VCol cols="12">
            <div class="d-flex flex-wrap gap-3">
              <VBtn
                :color="pabellonId ? 'secondary' : 'primary'"
                :variant="pabellonId ? 'outlined' : 'flat'"
                @click="seleccionarPabellon(null)"
              >
                Reporte Total
              </VBtn>
              <VBtn
                v-for="pabellon in pabellones"
                :key="pabellon.id_pabellon"
                :color="pabellonId === pabellon.id_pabellon ? 'primary' : 'secondary'"
                :variant="pabellonId === pabellon.id_pabellon ? 'flat' : 'outlined'"
                @click="seleccionarPabellon(pabellon.id_pabellon)"
              >
                {{ pabellon.nombre_pabellon }}
              </VBtn>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

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

    <template v-else-if="reporteData">
      <VRow class="mb-6">
        <VCol cols="12">
          <VCard>
            <VCardTitle>
              Resumen Stands
            </VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.total_stands"
                    label="Total Stands"
                    :percent="100"
                    color="rgba(var(--v-theme-primary),1)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.reservados"
                    label="Reservados"
                    :percent="toNumber(resumenStands.porcentajes.reservados)"
                    color="rgba(var(--v-theme-info),1)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.contrato"
                    label="Contrato"
                    :percent="toNumber(resumenStands.porcentajes.contrato)"
                    color="rgba(var(--v-theme-warning),1)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.pago_parcial"
                    label="Pago Parcial"
                    :percent="toNumber(resumenStands.porcentajes.pago_parcial)"
                    color="rgba(var(--v-theme-success),1)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.pagado_total"
                    label="Pagado 100%"
                    :percent="toNumber(resumenStands.porcentajes.pagado_total)"
                    color="rgba(var(--v-theme-primary),1)"
                  />
                </VCol>

                <VCol
                  cols="12"
                  sm="6"
                  md="4"
                  lg="2"
                >
                  <CardStadisticsStandsRadialCharts
                    :value="resumenStands.libres"
                    label="Libres"
                    :percent="toNumber(resumenStands.porcentajes.libres)"
                    color="rgba(var(--v-theme-secondary),1)"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow class="mb-6">
        <VCol
          cols="12"
        >
          <CardGraficoStandOverview
            title="Grafico de Stands"
            :items="standOverviewItems"
          />
        </VCol>
      </VRow>
      <VRow class="mb-6">
        <VCol cols="12">
          <VCard>
            <VCardTitle>Resumen Balance General</VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol cols="12" md="4">
                  <CardStadiscsPrecioRadialCharts
                    :value="resumenPagos.total_contrato_generado"
                    label="Total Contrato"
                    :percent="100"
                    color="rgba(var(--v-theme-warning),1)"
                    currency="Bs"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <CardStadiscsPrecioRadialCharts
                    :value="resumenPagos.ingreso_parcial"
                    label="Ingreso Parcial"
                    :percent="toNumber(resumenPagos.porcentajes.ingreso_parcial)"
                    color="rgba(var(--v-theme-success),1)"
                    currency="Bs"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <CardStadiscsPrecioRadialCharts
                    :value="resumenPagos.pendiente_cobro"
                    label="Pendiente Cobro"
                    :percent="toNumber(resumenPagos.porcentajes.pendiente_cobro)"
                    color="rgba(var(--v-theme-error),1)"
                    currency="Bs"
                  />
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VRow>
        <VCol cols="12">
          <VCard>
            <VCardTitle>Grafico de Balance</VCardTitle>
            <VDivider />
            <VCardText>
              <VRow>
                <VCol
                  cols="12"
                  md="6"
                  xl="8"
                  lg="7"
                >
                  <VueApexCharts
                    :options="balanceOptions"
                    :series="balanceSeries"
                    height="280"
                  />
                </VCol>

                <VCol
                  cols="12"
                  md="6"
                  lg="5"
                  xl="4"
                >
                  <div class="d-flex flex-column gap-6 ms-auto">
                    <div
                      v-for="stat in balanceEstadisticasData"
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
          </VCard>
        </VCol>
      </VRow>
    </template>
    <VCard v-else>
      <VCardText class="text-center py-8 text-medium-emphasis">
        No hay datos disponibles para este reporte.
      </VCardText>
    </VCard>
  </div>
</template>
