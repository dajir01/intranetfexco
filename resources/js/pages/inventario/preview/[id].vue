<script setup>
/* eslint-disable */
import { ref, onMounted, nextTick } from 'vue'
import { themeConfig } from '@themeConfig'
import { VNodeRenderer } from '@layouts/components/VNodeRenderer'
import { useRoute, useRouter } from 'vue-router'

const route = useRoute()
const router = useRouter()
const invoice = ref(null)
const paymentDetails = ref(null)
const purchasedProducts = ref([])
const loadError = ref('')
const isLoading = ref(true)
const ingresoData = ref(null)

const fetchIngreso = async () => {
  try {
    const id = Number(route.params.id)
    const res = await fetch(`/inventario/ingresos/${id}`)
    
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
    console.log('Datos recibidos:', data)

    // Guardar datos completos del ingreso
    ingresoData.value = data

    // Mapear según estructura real del backend
    const numeroRaw = data.numero
    const numeroPadded = (numeroRaw !== null && numeroRaw !== undefined)
      ? String(numeroRaw).padStart(6, '0')
      : ''
    invoice.value = {
      id: numeroPadded ? `NI-${numeroPadded}` : '',
      issuedDate: data.fecha_ingreso,
      dueDate: data.fecha_factura || data.fecha_ingreso,
      facturaNumero: data.factura_numero || null,
      facturaFecha: data.fecha_factura || null,
      recibidoPor: data.persona_recibe || '',
      entregadoPor: data.persona_entrega || '',
      client: {
        name: data.proveedor_nombre || '',
      },
    }
    
    paymentDetails.value = {
      totalDue: `Bs. ${formatNumber(data.importe || 0)}`,
      bankName: data.persona_recibe || '',
      country: '',
      iban: '',
      swiftCode: data.Observaciones || '',
    }

    // Mapear detalles desde data.detalles o data.items
    const items = data.detalles || data.items || []
    purchasedProducts.value = items.map(it => ({
      codigo: it.codigo || '',
      descripcion: it.descripcion || it.producto_nombre || '',
      cantidad: Number(it.cantidad || 0),
      precio: Number(it.precio || 0),
      costo: Number(it.costo || 0),
      importe: Number(it.importe || 0),
      total: Number(it.importe || 0),
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

const printInvoice = async () => {
  try {
    const id = route.params.id
    pdfIframeUrl.value = `/inventario/ingresos/${id}/pdf?view=1`
    showPdfIframe.value = true
    
    await nextTick()
    
    // Esperar un poco más para asegurar que el PDF se cargue
    setTimeout(() => {
      if (pdfIframeRef.value?.contentWindow) {
        try {
          pdfIframeRef.value.contentWindow.print()
        } catch (error) {
          console.error('Error al imprimir:', error)
          // Si falla, abrir en nueva pestaña
          window.open(`/inventario/ingresos/${id}/pdf?view=1`, '_blank')
          showPdfIframe.value = false
        }
      }
    }, 500)
  } catch (error) {
    console.error('Error en printInvoice:', error)
    showPdfIframe.value = false
  }
}

const onPdfIframeLoad = () => {
  // Intentar imprimir cuando el iframe termine de cargar
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
  window.open(`/inventario/ingresos/${id}/pdf`, '_blank')
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
  <section v-else-if="invoice && paymentDetails">
    <VRow>
      <VCol
        cols="12"
        md="9"
      >
        <VCard class="invoice-preview-wrapper pa-6 pa-sm-12">
          <!-- SECTION Header -->
          <div class="invoice-header-preview d-flex flex-wrap justify-space-between flex-column flex-sm-row print-row bg-var-theme-background gap-6 rounded pa-6 mb-6">
            <!-- 👉 Left Content -->
            <div>
              <div class="d-flex align-center app-logo mb-6">
                <!-- 👉 Logo -->
                <VNodeRenderer :nodes="themeConfig.app.logo" />

                <!-- 👉 Title -->
                <h6 class="app-logo-title">
                  {{ themeConfig.app.title }}
                </h6>
              </div>
            </div>

            <!-- 👉 Right Content -->
            <div>
              <!-- 👉 Número de Ingreso -->
              <h6 class="font-weight-medium text-lg mb-6">
                Ingreso Nº {{ invoice.id }}
              </h6>

              <!-- 👉 Fecha de Ingreso -->
              <h6 class="text-h6 font-weight-regular">
                <span>Fecha Ingreso: </span>
                <span>{{ formatDateTime(invoice.issuedDate) }}</span>
              </h6>
            </div>
          </div>
          <!-- !SECTION -->

          <!-- 👉 Alert de Ingreso Anulado -->
          <VAlert 
            v-if="ingresoData && Number(ingresoData.estado) === 0"
            type="error" 
            class="mb-6"
          >
            <template #title>
              ⚠️ INGRESO ANULADO
            </template>
            <p class="mb-0">
              <strong>Motivo:</strong> {{ ingresoData.anulacion_motivo || 'No especificado' }}
            </p>
          </VAlert>

          <!-- 👉 Payment Details -->
          <VRow class="print-row mb-6">
            <VCol class="text-no-wrap">
              <h6 class="text-h6 mb-4">
                Proveedor:
              </h6>

              <p class="mb-0">
                {{ invoice.client.name }}
              </p>
            </VCol>

            <VCol class="text-no-wrap">
              <h6 class="text-h6 mb-4">
                Factura:
              </h6>
              <template v-if="invoice.facturaNumero">
                <p class="mb-1">
                  <strong>N°:</strong> {{ invoice.facturaNumero }}
                </p>
                <p class="mb-0">
                  <strong>Fecha:</strong> {{ formatDateTime(invoice.facturaFecha) }}
                </p>
              </template>
              <template v-else>
                <p class="mb-0 text-medium-emphasis">Sin factura registrada</p>
              </template>
            </VCol>
          </VRow>

          <!-- 👉 invoice Table -->
          <VTable class="invoice-preview-table border text-high-emphasis overflow-hidden mb-6">
            <thead>
              <tr>
                <th scope="col">
                  Código
                </th>
                <th scope="col">
                  DESCRIPCIÓN
                </th>
                <th
                  scope="col"
                  class="text-center"
                >
                  Cantidad
                </th>
                <th
                  scope="col"
                  class="text-center"
                >
                  Precio
                </th>
                <th
                  scope="col"
                  class="text-center"
                >
                  P. Costo
                </th>
                <th
                  scope="col"
                  class="text-center"
                >
                  Importe
                </th>
                <th
                  scope="col"
                  class="text-center"
                >
                  Total Bs
                </th>
              </tr>
            </thead>

            <tbody class="text-base">
              <tr
                v-for="item in purchasedProducts"
                :key="item.codigo + '-' + item.descripcion"
              >
                <td class="text-no-wrap">
                  {{ item.codigo }}
                </td>
                <td class="text-no-wrap">
                  {{ item.descripcion }}
                </td>
                <td class="text-center">
                  {{ item.cantidad }}
                </td>
                <td class="text-center">
                  {{ formatNumber(item.precio) }}
                </td>
                <td class="text-center">
                  {{ formatNumber(item.costo) }}
                </td>
                <td class="text-center">
                  {{ formatNumber(item.importe) }}
                </td>
                <td class="text-center font-weight-medium">
                  {{ formatNumber(item.total) }}
                </td>
              </tr>
            </tbody>
          </VTable>

          <!-- 👉 Total -->
          <div class="d-flex justify-space-between flex-column flex-sm-row print-row">
            <div class="mb-2">
              <div class="d-flex align-center mb-1">
                <h6 class="text-h6 me-2">
                  Recibido por:
                </h6>
                <span>{{ invoice.recibidoPor || '—' }}</span>
              </div>
            </div>
            <div class="mb-2">
              <div class="d-flex align-center mb-1">
                <h6 class="text-h6 me-2">
                  Entregado por:
                </h6>
                <span>{{ invoice.entregadoPor || '—' }}</span>
              </div>
            </div>

            <div>
              <table class="w-100">
                <tbody>
                  <tr>
                    <td class="pe-16">
                      Total Bs.:
                    </td>
                    <td :class="$vuetify.locale.isRtl ? 'text-start' : 'text-end'">
                      <h6 class="text-base font-weight-medium">
                        {{ paymentDetails.totalDue }}
                      </h6>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </VCard>
      </VCol>

      <VCol
        cols="12"
        md="3"
        class="d-print-none"
      >
      <VCard>
          <VCardText>
            <VBtn
              block
              color="primary"
              class="mb-4"
              :disabled="ingresoData && Number(ingresoData.estado) === 0"
              @click="router.push(`/inventario/edit/${route.params.id}`)"
            >
              Editar
              <VIcon
                end
                icon="tabler-edit"
              />

            </VBtn>
            <VBtn
              block
              color="success"
              class="mb-4"
              @click="downloadPdf"
            >
              Descargar
              <VIcon
                end
                icon="tabler-download"
              />
            </VBtn>
            <VBtn
              block
              color="info"
              class="mb-4"
              @click="printInvoice"
            >
              Imprimir
              <VIcon
                end
                icon="tabler-printer"
              />
            </VBtn>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>
  </section>
  <section v-else>
    <VAlert
      type="error"
      variant="tonal"
    >
      {{ loadError || `No se encontró el ingreso con ID ${route.params.id}` }}
    </VAlert>
  </section>
</template>

<style lang="scss">
.invoice-preview-table {
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

  body {
    background: none !important;
  }

  .invoice-header-preview,
  .invoice-preview-wrapper {
    padding: 0 !important;
  }

  .product-buy-now {
    display: none;
  }

  .v-navigation-drawer,
  .layout-vertical-nav,
  .app-customizer-toggler,
  .layout-footer,
  .layout-navbar,
  .layout-navbar-and-nav-container {
    display: none;
  }

  .v-card {
    box-shadow: none !important;

    .print-row {
      flex-direction: row !important;
    }
  }

  .layout-content-wrapper {
    padding-inline-start: 0 !important;
  }

  .v-table__wrapper {
    overflow: hidden !important;
  }

  .vue-devtools__anchor {
    display: none;
  }
}
</style>
