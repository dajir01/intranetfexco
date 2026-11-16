<script setup>
/* eslint-disable camelcase */
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { watchDebounced } from '@vueuse/core'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Control de Accesos',
  },
})

const router = useRouter()
const auth = useAuthStore()
const puedeVer = computed(() => auth.can('controlaccesos.ingresos.ver'))
const puedeVerRegistros = computed(() => auth.can('controlaccesos.registros.ver'))
const puedeExportar = computed(() => auth.can('controlaccesos.ingresos.exportar'))

if (!puedeVer.value)
  router.replace('/')

const eventos = ref([])
const operadores = ref([])
const resumen = ref({ ingresos: 0, credenciales_unicas: 0, por_credencial: [], ingresos_por_dia: [] })
const registros = ref([])
const totalRegistros = ref(0)
const pagina = ref(1)
const porPagina = ref(25)
const busqueda = ref('')
const cargandoEventos = ref(false)
const cargandoResumen = ref(false)
const cargandoRegistros = ref(false)
const exportando = ref(false)
const error = ref('')
const filtros = reactive({ id_feria: null, id_usuario_control: null, tipo_credencial: null, desde: '', hasta: '' })
let pausarWatchFiltros = false
let solicitudesResumen = 0
let solicitudesRegistros = 0

const tiposCredencial = [
  { title: 'Expositor', value: 1 },
  { title: 'Prensa', value: 2 },
  { title: 'Oficial', value: 3 },
  { title: 'Servicios', value: 4 },
  { title: 'Negocios', value: 5 },
]

const datosIngresosDiarios = computed(() => {
  const registrosDiarios = resumen.value.ingresos_por_dia || []
  const fechas = [...new Set(registrosDiarios.map(registro => registro.fecha))]
  const nombresPorTipo = new Map()
  const ingresosPorFechaYTipo = new Map()

  registrosDiarios.forEach(registro => {
    const tipo = Number(registro.tipo_credencial_codigo)

    nombresPorTipo.set(tipo, registro.tipo_credencial)
    ingresosPorFechaYTipo.set(`${registro.fecha}-${tipo}`, Number(registro.ingresos))
  })

  const series = [...nombresPorTipo.entries()]
    .sort(([tipoA], [tipoB]) => tipoA - tipoB)
    .map(([tipo, nombre]) => ({
      name: nombre,
      data: fechas.map(fecha => ingresosPorFechaYTipo.get(`${fecha}-${tipo}`) || 0),
    }))

  return { fechas, series }
})

const opcionesGraficoIngresos = computed(() => ({
  chart: {
    type: 'bar',
    height: 320,
    stacked: true,
    toolbar: { show: false },
  },
  plotOptions: {
    bar: {
      horizontal: false,
      columnWidth: '60%',
      borderRadius: 4,
    },
  },
  colors: [
    'rgba(var(--v-theme-primary), 1)',
    'rgba(var(--v-theme-success), 1)',
    'rgba(var(--v-theme-warning), 1)',
    'rgba(var(--v-theme-info), 1)',
    'rgba(var(--v-theme-secondary), 1)',
  ],
  dataLabels: { enabled: false },
  grid: {
    borderColor: 'rgba(var(--v-border-color), var(--v-border-opacity))',
    strokeDashArray: 10,
  },
  xaxis: {
    categories: datosIngresosDiarios.value.fechas,
    labels: {
      formatter: value => {
        const [year, month, day] = String(value).split('-')

        return year && month && day ? `${day}/${month}` : value
      },
      rotate: -45,
      hideOverlappingLabels: true,
      style: {
        colors: 'rgba(var(--v-theme-on-background), var(--v-disabled-opacity))',
        fontSize: '12px',
      },
    },
  },
  yaxis: {
    min: 0,
    forceNiceScale: true,
    title: { text: 'Ingresos' },
    labels: {
      formatter: value => Math.round(value),
      style: {
        colors: 'rgba(var(--v-theme-on-background), var(--v-disabled-opacity))',
        fontSize: '12px',
      },
    },
  },
  tooltip: {
    shared: true,
    intersect: false,
    y: { formatter: value => `${value} ingresos` },
  },
  legend: { position: 'bottom' },
}))

const tablaHeaders = [
  { title: 'Fecha y hora', key: 'fecha_ingreso' },
  { title: 'Persona', key: 'nombre_persona' },
  { title: 'CI', key: 'ci_persona' },
  { title: 'Operador', key: 'nombre_operador' },
  { title: 'Tipo de credencial', key: 'tipo_credencial' },
  { title: 'Modalidad', key: 'modalidad_credencial' },
  { title: 'Punto de acceso', key: 'punto_acceso' },
]

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const paramsReporte = () => {
  const params = new URLSearchParams()
  if (filtros.id_feria)
    params.set('id_feria', String(filtros.id_feria))
  if (filtros.id_usuario_control)
    params.set('id_usuario_control', String(filtros.id_usuario_control))
  if (filtros.tipo_credencial)
    params.set('tipo_credencial', String(filtros.tipo_credencial))
  if (filtros.desde)
    params.set('desde', filtros.desde)
  if (filtros.hasta)
    params.set('hasta', filtros.hasta)

  return params
}

const pedirJson = async (url, options = {}) => {
  const response = await fetch(url, {
    credentials: 'same-origin',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(options.body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() } : {}),
      ...options.headers,
    },
    ...options,
  })

  const json = await response.json()
  if (!response.ok || json.success === false)
    throw new Error(json.message || 'No se pudo completar la solicitud.')

  return json
}

const cargarEventos = async () => {
  cargandoEventos.value = true
  try {
    const json = await pedirJson('/control-accesos/eventos')

    eventos.value = json.data || []
  } catch (exception) {
    error.value = exception.message
  } finally {
    cargandoEventos.value = false
  }
}

const cargarOperadores = async () => {
  const eventId = filtros.id_feria
  if (!eventId) {
    operadores.value = []

    return
  }

  try {
    const params = new URLSearchParams({ id_feria: String(eventId) })
    const json = await pedirJson(`/control-accesos/operadores-filtro?${params}`)

    if (Number(filtros.id_feria) === Number(eventId))
      operadores.value = json.data || []
  } catch (exception) {
    error.value = exception.message
  }
}

const limpiarReporte = () => {
  solicitudesResumen++
  solicitudesRegistros++
  resumen.value = { ingresos: 0, credenciales_unicas: 0, por_credencial: [], ingresos_por_dia: [] }
  registros.value = []
  totalRegistros.value = 0
  cargandoResumen.value = false
  cargandoRegistros.value = false
}

const seleccionarEvento = async eventId => {
  pausarWatchFiltros = true
  filtros.id_feria = eventId || null
  filtros.id_usuario_control = null
  filtros.tipo_credencial = null
  filtros.desde = ''
  filtros.hasta = ''
  pagina.value = 1
  busqueda.value = ''
  operadores.value = []
  error.value = ''

  if (!filtros.id_feria)
    limpiarReporte()

  await nextTick()
  pausarWatchFiltros = false

  if (!filtros.id_feria)
    return

  await Promise.all([cargarOperadores(), cargarResumen(), cargarRegistros()])
}

const cargarResumen = async () => {
  if (!filtros.id_feria)
    return

  const solicitud = ++solicitudesResumen

  cargandoResumen.value = true
  error.value = ''
  try {
    const json = await pedirJson(`/control-accesos/resumen?${paramsReporte()}`)

    if (solicitud === solicitudesResumen)
      resumen.value = json.data
  } catch (exception) {
    if (solicitud === solicitudesResumen)
      error.value = exception.message
  } finally {
    if (solicitud === solicitudesResumen)
      cargandoResumen.value = false
  }
}

const cargarRegistros = async () => {
  if (!puedeVerRegistros.value || !filtros.id_feria)
    return

  const solicitud = ++solicitudesRegistros

  cargandoRegistros.value = true
  error.value = ''
  try {
    const params = paramsReporte()

    params.set('page', String(pagina.value))
    params.set('per_page', String(porPagina.value))
    if (busqueda.value.trim())
      params.set('search', busqueda.value.trim())
    const json = await pedirJson(`/control-accesos/registros?${params}`)

    if (solicitud === solicitudesRegistros) {
      registros.value = json.data.data || []
      totalRegistros.value = json.data.total || 0
    }
  } catch (exception) {
    if (solicitud === solicitudesRegistros)
      error.value = exception.message
  } finally {
    if (solicitud === solicitudesRegistros)
      cargandoRegistros.value = false
  }
}

const actualizarTabla = options => {
  const nuevaPagina = options.page || 1
  const nuevoTamano = options.itemsPerPage || 25
  if (nuevaPagina === pagina.value && nuevoTamano === porPagina.value)
    return

  pagina.value = nuevaPagina
  porPagina.value = nuevoTamano
  cargarRegistros()
}

const actualizarFiltros = () => {
  if (pausarWatchFiltros || !filtros.id_feria)
    return

  pagina.value = 1
  cargarResumen()
  cargarRegistros()
}

watch(() => [filtros.id_usuario_control, filtros.tipo_credencial, filtros.desde, filtros.hasta], actualizarFiltros)
watchDebounced(busqueda, () => {
  if (!filtros.id_feria)
    return

  pagina.value = 1
  cargarRegistros()
}, { debounce: 350 })

const formatearFecha = value => {
  if (!value)
    return '—'
  const fecha = new Date(String(value).replace(' ', 'T'))

  return Number.isNaN(fecha.getTime()) ? value : fecha.toLocaleString('es-BO')
}

const descargarReporte = async () => {
  if (!filtros.id_feria)
    return

  exportando.value = true
  error.value = ''
  try {
    const params = paramsReporte()
    if (busqueda.value.trim())
      params.set('search', busqueda.value.trim())

    const response = await fetch(`/control-accesos/exportar?${params}`, {
      credentials: 'same-origin',
      headers: {
        Accept: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'X-Requested-With': 'XMLHttpRequest',
      },
    })

    if (!response.ok) {
      const json = await response.json().catch(() => null)
      throw new Error(json?.message || 'No se pudo exportar el Excel.')
    }

    const disposition = response.headers.get('content-disposition') || ''
    const filename = disposition.match(/filename="?([^"]+)"?/i)?.[1] || 'control_accesos.xlsx'
    const url = URL.createObjectURL(await response.blob())
    const enlace = document.createElement('a')

    enlace.href = url
    enlace.download = filename
    document.body.appendChild(enlace)
    enlace.click()
    enlace.remove()
    window.setTimeout(() => URL.revokeObjectURL(url), 1000)
  } catch (exception) {
    error.value = exception.message
  } finally {
    exportando.value = false
  }
}

onMounted(async () => {
  await cargarEventos()
})
</script>

<template>
  <div>
    <VCard class="mb-6">
      <VCardItem>
        <template #title>
          Control de Accesos
        </template>
        <template #subtitle>
          Ingresos registrados por credenciales físicas y digitales
        </template>
      </VCardItem>

      <VCardText>
        <VAlert
          v-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          @click:close="error = ''"
        >
          {{ error }}
        </VAlert>
        <VRow>
          <VCol
            cols="12"
            md="5"
          >
            <VSelect
              :model-value="filtros.id_feria"
              :items="eventos"
              item-title="nombre_feria"
              item-value="id_feria"
              label="Feria / evento"
              clearable
              :loading="cargandoEventos"
              @update:model-value="seleccionarEvento"
            />
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <VCard
      v-if="!filtros.id_feria"
      class="mb-6"
    >
      <VCardText class="d-flex flex-column align-center justify-center text-center pa-8">
        <VIcon
          icon="tabler-calendar-event"
          size="48"
          color="disabled"
          class="mb-4"
        />
        <h3 class="text-h6 mb-2">
          Selecciona un evento
        </h3>
        <p class="text-body-2 text-medium-emphasis mb-0">
          Elige primero una feria o evento para consultar sus ingresos y operadores.
        </p>
      </VCardText>
    </VCard>

    <template v-if="filtros.id_feria">
      <VCard class="mb-6">
        <VCardText>
          <VRow>
            <VCol
              cols="12"
              sm="6"
              md="3"
            >
              <VTextField
                v-model="filtros.desde"
                type="date"
                label="Desde"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VTextField
                v-model="filtros.hasta"
                type="date"
                label="Hasta"
                clearable
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VSelect
                v-model="filtros.id_usuario_control"
                :items="operadores"
                item-title="nombre"
                item-value="id_usuario_control"
                label="Operador"
                clearable
              >
                <template #item="{ props, item }">
                  <VListItem
                    v-bind="props"
                    :subtitle="item.raw.usuario"
                  />
                </template>
              </VSelect>
            </VCol>
            <VCol
              cols="12"
              sm="6"
              md="2"
            >
              <VSelect
                v-model="filtros.tipo_credencial"
                :items="tiposCredencial"
                label="Tipo de credencial"
                clearable
              />
            </VCol>
            <VCol
              v-if="puedeExportar"
              cols="12"
              md="3"
              class="d-flex align-center justify-end"
            >
              <VBtn
                :loading="exportando"
                prepend-icon="tabler-download"
                @click="descargarReporte"
              >
                Exportar Excel
              </VBtn>
            </VCol>
          </VRow>
        </VCardText>
      </VCard>

      <VRow class="mb-2">
        <VCol
          cols="12"
          sm="6"
        >
          <VCard :loading="cargandoResumen">
            <VCardText>
              <div class="text-body-1 mb-2">
                Ingresos aceptados
              </div>
              <div class="text-h4 font-weight-bold">
                {{ resumen.ingresos.toLocaleString('es-BO') }}
              </div>
              <div class="text-caption">
                Cada reingreso cuenta como un ingreso adicional.
              </div>
            </VCardText>
          </VCard>
        </VCol>
        <VCol
          cols="12"
          sm="6"
        >
          <VCard :loading="cargandoResumen">
            <VCardText>
              <div class="text-body-1 mb-2">
                Credenciales únicas
              </div>
              <div class="text-h4 font-weight-bold">
                {{ resumen.credenciales_unicas.toLocaleString('es-BO') }}
              </div>
              <div class="text-caption">
                Credenciales distintas con ingreso en el período.
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <VCard class="mb-6">
        <VCardItem title="Ingresos por tipo de credencial" />
        <VCardText>
          <VTable
            v-if="resumen.por_credencial.length"
            density="comfortable"
          >
            <thead>
              <tr>
                <th>Tipo</th>
                <th>Modalidad</th>
                <th class="text-end">
                  Ingresos
                </th>
                <th class="text-end">
                  Credenciales únicas
                </th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="fila in resumen.por_credencial"
                :key="`${fila.tipo_credencial_codigo}-${fila.modalidad_credencial_codigo}`"
              >
                <td>{{ fila.tipo_credencial }}</td>
                <td>{{ fila.modalidad_credencial }}</td>
                <td class="text-end">
                  {{ fila.ingresos }}
                </td>
                <td class="text-end">
                  {{ fila.credenciales_unicas }}
                </td>
              </tr>
            </tbody>
          </VTable>
          <div
            v-else
            class="text-medium-emphasis"
          >
            No hay ingresos para los filtros seleccionados.
          </div>
        </VCardText>
      </VCard>

      <VCard
        class="mb-6"
        :loading="cargandoResumen"
      >
        <VCardItem title="Ingresos por día y tipo de credencial" />
        <VDivider />
        <VCardText>
          <VueApexCharts
            v-if="datosIngresosDiarios.series.length"
            :options="opcionesGraficoIngresos"
            :series="datosIngresosDiarios.series"
            height="320"
          />
          <div
            v-else
            class="text-medium-emphasis"
          >
            No hay ingresos para mostrar en el período seleccionado.
          </div>
        </VCardText>
      </VCard>

      <VCard v-if="puedeVerRegistros">
        <VCardItem title="Detalle de ingresos">
          <template #append>
            <VTextField
              v-model="busqueda"
              label="Buscar persona o CI"
              prepend-inner-icon="tabler-search"
              density="compact"
              hide-details
              style="min-inline-size: 250px"
            />
          </template>
        </VCardItem>
        <VDataTableServer
          :headers="tablaHeaders"
          :items="registros"
          :items-length="totalRegistros"
          :loading="cargandoRegistros"
          :items-per-page="porPagina"
          :items-per-page-options="[10, 25, 50, 100]"
          :page="pagina"
          @update:options="actualizarTabla"
        >
          <template #item.fecha_ingreso="{ item }">
            {{ formatearFecha(item.fecha_ingreso) }}
          </template>
          <template #item.nombre_persona="{ item }">
            {{ item.nombre_persona || '—' }}
          </template>
          <template #item.ci_persona="{ item }">
            {{ item.ci_persona || '—' }}
          </template>
          <template #item.punto_acceso="{ item }">
            {{ item.punto_acceso || '—' }}
          </template>
          <template #item.nombre_operador="{ item }">
            {{ item.nombre_operador || 'No registrado' }}
          </template>
          <template #no-data>
            <div class="pa-6 text-medium-emphasis">
              No hay registros de ingreso para los filtros seleccionados.
            </div>
          </template>
        </VDataTableServer>
      </VCard>
    </template>
  </div>
</template>
