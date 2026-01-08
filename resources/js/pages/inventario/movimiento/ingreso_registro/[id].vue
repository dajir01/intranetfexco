<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { themeConfig } from '@themeConfig'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const movimiento = ref(null)
const ingresoDetails = ref(null)
const detalles = ref([])
const loadError = ref('')
const isLoading = ref(true)
const ingresoData = ref(null)

const fetchIngreso = async () => {
  try {
    const id = Number(route.params.id)
    const res = await fetch(`/inventario/movimientos/ingreso/${id}`)
    
    if (!res.ok) {
      console.error(`Error HTTP: ${res.status}`)
      loadError.value = `Error al obtener el ingreso (HTTP ${res.status})`
      return
    }

    const contentType = res.headers.get('content-type')
    if (!contentType || !contentType.includes('application/json')) {
      console.error('La respuesta no es JSON')
      loadError.value = 'El servidor devolvió contenido no JSON'
      return
    }

    const data = await res.json()

    ingresoData.value = data.movimiento

    const numeroRaw = data.movimiento.codigo
    const numeroPadded = (numeroRaw !== null && numeroRaw !== undefined)
      ? String(numeroRaw).padStart(6, '0')
      : ''
    movimiento.value = {
      id: numeroPadded ? `IA-${numeroPadded}` : '',
      fecha: data.movimiento.fecha,
      areaNombre: data.movimiento.area_nombre || '',
      personaEntrega: data.movimiento.persona_entrega || '',
      personaRecibe: data.movimiento.persona_recibe || '',
      observaciones: data.movimiento.observaciones || '',
      total: data.movimiento.total || 0,
    }
    
    ingresoDetails.value = {
      totalBs: `Bs. ${formatNumber(data.movimiento.total || 0)}`,
      observaciones: data.movimiento.observaciones || '',
    }

    const items = data.detalles || []
    detalles.value = items.map(it => ({
      codigo: it.codigo || '',
      producto_nombre: it.producto_nombre || '',
      cantidad: Number(it.cantidad || 0),
      costo: Number(it.costo || 0),
      total: Number(it.total || 0),
    }))
  } catch (e) {
    console.error('Error al cargar ingreso:', e)
    loadError.value = 'No se pudo cargar el ingreso'
  } finally {
    isLoading.value = false
  }
}

onMounted(fetchIngreso)

const showPdfIframe = ref(false)
const pdfIframeUrl = ref('')
const pdfIframeRef = ref(null)

const printIngreso = async () => {
  try {
    const id = route.params.id
    pdfIframeUrl.value = `/inventario/movimientos/ingreso-almacen/${id}/pdf?view=1`
    showPdfIframe.value = true
    
    await nextTick()
    
    setTimeout(() => {
      if (pdfIframeRef.value?.contentWindow) {
        try {
          pdfIframeRef.value.contentWindow.print()
        } catch (error) {
          console.error('Error al imprimir:', error)
          window.open(`/inventario/movimientos/ingreso-almacen/${id}/pdf?view=1`, '_blank')
          showPdfIframe.value = false
        }
      }
    }, 500)
  } catch (error) {
    console.error('Error en printIngreso:', error)
    showPdfIframe.value = false
  }
}

const onPdfIframeLoad = () => {
  if (pdfIframeRef.value?.contentWindow) {
    try {
      pdfIframeRef.value.contentWindow.print()
    } catch (error) {
      console.error('Error en onPdfIframeLoad:', error)
    }
  }
}

const closePdfIframe = () => {
  showPdfIframe.value = false
  pdfIframeUrl.value = ''
}

const downloadPdf = () => {
  const id = route.params.id
  window.open(`/inventario/movimientos/ingreso-almacen/${id}/pdf`, '_blank')
}

const formatDateTime = (val) => {
  if (!val) return ''
  const normalized = typeof val === 'string' && val.includes(' ') && !val.includes('T')
    ? val.replace(' ', 'T')
    : val
  const d = new Date(normalized)
  if (isNaN(d)) return val
  return d.toLocaleDateString('es-ES') + ' ' + d.toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' })
}

const formatNumber = (val) => {
  const n = Number(val)
  if (!isFinite(n)) return '0'
  return new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n)
}

const goBack = () => {
  router.push('/inventario/movimiento/ingreso')
}
</script>

<template>
  <div v-if="showPdfIframe" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9999;background:#fff;">
    <VBtn 
      color="error" 
      style="position:absolute;top:16px;right:16px;z-index:10000;" 
      @click="closePdfIframe"
    >
      Cerrar
      <VIcon end icon="tabler-x" />
    </VBtn>
    <iframe
      ref="pdfIframeRef"
      :src="pdfIframeUrl"
      style="width:100vw;height:100vh;border:none;"
      @load="onPdfIframeLoad"
    />
  </div>

  <section v-if="isLoading">
    <VAlert type="info" variant="tonal">Cargando ingreso…</VAlert>
  </section>

  <section v-else-if="movimiento && ingresoDetails">
    <VRow>
      <VCol cols="12" md="9">
        <VCard class="ingreso-preview-wrapper pa-6 pa-sm-12">
          <div class="ingreso-header-preview d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row bg-var-theme-background gap-6 rounded pa-6 mb-6">
            <div>
              <div class="d-flex align-center app-logo mb-6">
                <VNodeRenderer :nodes="themeConfig.app.logo" />
                <h6 class="app-logo-title">
                  {{ themeConfig.app.title }}
                </h6>
              </div>
            </div>

            <div>
              <h6 class="font-weight-medium text-lg mb-6">
                Ingreso Nº {{ movimiento.id }}
              </h6>

              <h6 class="text-h6 font-weight-regular">
                <span>Fecha Ingreso: </span>
                <span>{{ formatDateTime(movimiento.fecha) }}</span>
              </h6>
            </div>
          </div>

          <VRow class="print-row mb-6">
            <VCol class="text-no-wrap">
              <h6 class="text-h6 mb-4">Área:</h6>
              <p class="mb-0">{{ movimiento.areaNombre || '—' }}</p>
            </VCol>

            <VCol class="text-no-wrap">
              <h6 class="text-h6 mb-4">Persona que Entrega:</h6>
              <p class="mb-0">{{ movimiento.personaEntrega || '—' }}</p>
            </VCol>

            <VCol class="text-no-wrap">
              <h6 class="text-h6 mb-4">Persona que Recibe:</h6>
              <p class="mb-0">{{ movimiento.personaRecibe || '—' }}</p>
            </VCol>
          </VRow>

          <VRow v-if="movimiento.observaciones" class="print-row mb-6">
            <VCol>
              <h6 class="text-h6 mb-2">Observaciones:</h6>
              <p class="mb-0">{{ movimiento.observaciones }}</p>
            </VCol>
          </VRow>

          <VTable class="ingreso-preview-table border text-high-emphasis overflow-hidden mb-6">
            <thead>
              <tr>
                <th scope="col">Código</th>
                <th scope="col">PRODUCTO</th>
                <th scope="col" class="text-center">Cantidad</th>
                <th scope="col" class="text-center">P. Unitario</th>
                <th scope="col" class="text-center">Total Bs</th>
              </tr>
            </thead>

            <tbody class="text-base">
              <tr v-for="item in detalles" :key="item.codigo + '-' + item.producto_nombre">
                <td class="text-no-wrap">{{ item.codigo }}</td>
                <td class="text-no-wrap">{{ item.producto_nombre }}</td>
                <td class="text-center">{{ formatNumber(item.cantidad) }}</td>
                <td class="text-center">{{ formatNumber(item.costo) }}</td>
                <td class="text-center font-weight-medium">{{ formatNumber(item.total) }}</td>
              </tr>
            </tbody>
          </VTable>

          <div class="d-flex justify-end print-row mb-6">
            <div>
              <table class="w-100">
                <tbody>
                  <tr>
                    <td class="pe-16">Total Bs.:</td>
                    <td :class="$vuetify.locale.isRtl ? 'text-start' : 'text-end'">
                      <h6 class="text-base font-weight-medium">{{ ingresoDetails.totalBs }}</h6>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </VCard>
      </VCol>

      <VCol cols="12" md="3" class="d-print-none">
        <VCard>
          <VCardText>
            <VBtn block color="primary" class="mb-4" @click="goBack">
              Volver
              <VIcon end icon="tabler-arrow-left" />
            </VBtn>

            <VBtn block color="success" class="mb-4" @click="downloadPdf">
              Descargar
              <VIcon end icon="tabler-download" />
            </VBtn>

            <VBtn block color="info" @click="printIngreso">
              Imprimir
              <VIcon end icon="tabler-printer" />
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </section>

  <section v-else>
    <VAlert type="error" variant="tonal">
      {{ loadError || `No se encontró el ingreso con ID ${route.params.id}` }}
    </VAlert>
  </section>
</template>

<style lang="scss">
.ingreso-preview-table {
  --v-table-header-color: var(--v-theme-surface);

  &.v-table .v-table__wrapper table thead tr th {
    border-block-end: 1px solid rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}

@media print {
  .v-theme--dark {
    --v-theme-surface: 255, 255, 255;
    --v-theme-on-surface: 47, 43, 61;
    --v-theme-on-background: 47, 43, 61;
  }

  body { background: none !important; }

  .ingreso-header-preview,
  .ingreso-preview-wrapper { padding: 0 !important; }

  .product-buy-now { display: none; }
  .v-navigation-drawer,
  .layout-vertical-nav,
  .app-customizer-toggler,
  .layout-footer,
  .layout-navbar,
  .layout-navbar-and-nav-container { display: none; }

  .v-card { box-shadow: none !important; }
}
</style>
