<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Gesti�n de Stands',
  },
})

const route = useRoute()
const router = useRouter()

const pabellonId = computed(() => route.params.id)

// Estado
const stands = ref([])
const pabellon = ref(null)
const selectedStand = ref(null)
const loading = ref(false)
const error = ref(null)
const success = ref(null)
const standSuccess = ref(null)
let standSuccessTimeout = null

// Diálogo de creación de stands
const createDialog = ref(false)
const createForm = ref({
  cantidad: 1,
  area_stand: null,
})
const creating = ref(false)

// Diálogo de límites de credenciales
const credencialesDialog = ref(false)
const limitesCredenciales = ref([])
const loadingLimites = ref(false)
const savingLimites = ref(false)
// Indicador de carga por fila al eliminar
const deletingRowIndex = ref(null)

// Formulario de edici�n
const editForm = ref({
  numero_stand: '',
  area_stand: '',
  sup: '',
  izq: '',
  coord: '',
  tipo: 1,
})
const saving = ref(false)
// Estado del mapa e interacción
const pins = ref([])
const cambiosPendientes = ref(false)
const estadoOriginal = ref(null)
const imagenMapa = ref(null)

// Dimensiones para escalado responsive
const originalWidth = ref(0)
const originalHeight = ref(0)
const currentWidth = ref(0)
const currentHeight = ref(0)
const scaleX = computed(() => currentWidth.value && originalWidth.value ? currentWidth.value / originalWidth.value : 1)
const scaleY = computed(() => currentHeight.value && originalHeight.value ? currentHeight.value / originalHeight.value : 1)
const isMapaListo = ref(false)
const vistaPreviaDialog = ref(false)
const imagenVistaPrevia = ref(null)
const dimensionesVistaPrevia = ref({ width: 0, height: 0 })
const errorImagenVistaPrevia = ref(false)
const vistaPreviaDatos = computed(() => {
  const points = []
  let missingStands = 0

  for (const stand of stands.value) {
    let hasCoordinates = false
    const hasReservationCoordinates = stand.izq !== null && stand.izq !== undefined && stand.izq !== ''
      && stand.sup !== null && stand.sup !== undefined && stand.sup !== ''
    const reservationX = Number(stand.izq)
    const reservationY = Number(stand.sup)
    if (hasReservationCoordinates && Number.isFinite(reservationX) && Number.isFinite(reservationY)
      && (reservationX !== 0 || reservationY !== 0)) {
      hasCoordinates = true
      points.push({
        id: `${stand.id_stand}-reservation`,
        x: reservationX,
        y: reservationY,
        kind: 'reservation',
        label: `R${stand.numero_stand}`,
        title: `Stand ${stand.numero_stand} · Reserva`,
      })
    }

    const coordinates = String(stand.coord || '').split(',')
    let paintedPointIndex = 0
    for (let index = 0; index + 1 < coordinates.length; index += 2) {
      const rawX = coordinates[index].trim()
      const rawY = coordinates[index + 1].trim()
      const x = Number(rawX)
      const y = Number(rawY)
      if (rawX && rawY && Number.isFinite(x) && Number.isFinite(y) && (x !== 0 || y !== 0)) {
        hasCoordinates = true
        points.push({
          id: `${stand.id_stand}-painted-${paintedPointIndex}`,
          x,
          y,
          kind: 'painted',
          label: paintedPointIndex === 0 ? `P${stand.numero_stand}` : '',
          title: `Stand ${stand.numero_stand} · Pintado`,
        })
        paintedPointIndex++
      }
    }

    if (!hasCoordinates)
      missingStands++
  }

  return { points, missingStands }
})
const standsFaltantesPorMarcar = computed(() => vistaPreviaDatos.value.missingStands)
const previewPins = computed(() => {
  const { width, height } = dimensionesVistaPrevia.value
  if (!width || !height)
    return []

  return vistaPreviaDatos.value.points.map(point => ({
    ...point,
    left: `${point.x / width * 100}%`,
    top: `${point.y / height * 100}%`,
  }))
})
let resizeObserver = null
const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const abrirVistaPrevia = () => {
  if (imagenVistaPrevia.value?.naturalWidth) {
    onImagenVistaPreviaCargada()
  } else if (!imagenVistaPrevia.value) {
    errorImagenVistaPrevia.value = false
    dimensionesVistaPrevia.value = { width: 0, height: 0 }
  }

  vistaPreviaDialog.value = true
}

const onImagenVistaPreviaCargada = () => {
  if (!imagenVistaPrevia.value)
    return

  dimensionesVistaPrevia.value = {
    width: imagenVistaPrevia.value.naturalWidth,
    height: imagenVistaPrevia.value.naturalHeight,
  }
  errorImagenVistaPrevia.value = false
}

const loadStands = async () => {
  loading.value = true
  error.value = null
  try {
    const res = await fetch(`/pabellones/${pabellonId.value}/stands`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)

    const json = await res.json()

    if (!json.success)
      throw new Error(json.message || 'Error al cargar stands')

    stands.value = json.data?.stands || []
    pabellon.value = json.data?.pabellon || null
  } catch (err) {
    error.value = err.message || 'Error al cargar los stands'
    console.error('Error cargando stands:', err)
  } finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  createForm.value = {
    cantidad: 1,
    area_stand: null,
  }
  createDialog.value = true
}

const abrirLimitesCredenciales = async () => {
  loadingLimites.value = true
  try {
    const res = await fetch(`/pabellones/${pabellonId.value}/limites-credenciales`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)

    const json = await res.json()

    if (!json.success)
      throw new Error(json.message || 'Error al cargar límites')

    // Marcar todos los registros recuperados como existentes (isNew = false)
    // Asegurar que el id esté presente y en número
    limitesCredenciales.value = (json.data || []).map(limite => ({
      ...limite,
      id: limite.id != null ? Number(limite.id) : null,
      isNew: false,
    }))
    credencialesDialog.value = true
  } catch (err) {
    error.value = err.message || 'Error al cargar los límites de credenciales'
    console.error('Error cargando límites:', err)
  } finally {
    loadingLimites.value = false
  }
}

const onStandSelected = (stand) => {
  standSuccess.value = null
  clearTimeout(standSuccessTimeout)
  standSuccessTimeout = null

  if (!stand) {
    editForm.value = {
      numero_stand: '',
      area_stand: '',
      sup: '',
      izq: '',
      coord: '',
      tipo: 1,
    }
    pins.value = []
    cambiosPendientes.value = false
    estadoOriginal.value = null
    return
  }

  editForm.value = {
    numero_stand: String(stand.numero_stand || ''),
    area_stand: Number(stand.area_stand) || 0,
    sup: Number(stand.sup) || 0,
    izq: Number(stand.izq) || 0,
    coord: stand.coord || '',
    tipo: Number(stand.tipo) === 2 ? 2 : 1,
  }
  estadoOriginal.value = { ...editForm.value }

  cambiosPendientes.value = false
  
  // Recalcular mapa después de seleccionar stand para asegurar layout estable
  nextTick(() => {
    if (isMapaListo.value)
      recalcularMapa()
    else
      repintarPines()
  })
}

const cambiarModo = tipo => {
  const nuevoTipo = Number(tipo)
  if (![1, 2].includes(nuevoTipo) || nuevoTipo === editForm.value.tipo) {
    return
  }

  editForm.value.tipo = nuevoTipo
  cambiosPendientes.value = true
  repintarPines()
}

/**
 * Repintar pines en el mapa según el tipo
 * Las coordenadas se escalan según las dimensiones actuales de la imagen
 * Solo renderiza si el mapa está listo para evitar posiciones incorrectas
 */
const repintarPines = () => {
  // No renderizar pines si el mapa no está listo
  if (!isMapaListo.value) {
    pins.value = []
    return
  }

  pins.value = []

  if (editForm.value.tipo === 1) {
    // Modo Reserva: SIEMPRE mostrar un pin usando sup e izq
    const sup = parseInt(editForm.value.sup) || 0
    const izq = parseInt(editForm.value.izq) || 0
    pins.value.push({
      x: izq * scaleX.value, // Coordenada escalada
      y: sup * scaleY.value, // Coordenada escalada
      realX: izq, // Coordenada real guardada en BD
      realY: sup,
      id: 'pin-reserva',
    })
  } else {
    // Modo Pintado: múltiples pines desde coord
    if (editForm.value.coord) {
      const coords = String(editForm.value.coord).split(',')
      for (let i = 0; i < coords.length; i += 2) {
        const realX = parseFloat(coords[i])
        const realY = parseFloat(coords[i + 1])
        if (!isNaN(realX) && !isNaN(realY)) {
          pins.value.push({
            x: realX * scaleX.value, // Coordenada escalada
            y: realY * scaleY.value, // Coordenada escalada
            realX, // Coordenada real guardada en BD
            realY,
            id: `pin-${i / 2}`,
          })
        }
      }
    }
  }
}

/**
 * Manejar click en la imagen del mapa
 * Convierte las coordenadas del click a coordenadas reales (sin escalar)
 */
const onMapaClick = (event) => {
  if (!imagenMapa.value || !selectedStand.value)
    return

  const rect = imagenMapa.value.getBoundingClientRect()
  const clickX = event.clientX - rect.left
  const clickY = event.clientY - rect.top

  // Convertir a coordenadas REALES (diviendo por el factor de escala)
  const realX = Math.round(clickX / scaleX.value)
  const realY = Math.round(clickY / scaleY.value)

  if (editForm.value.tipo === 1) {
    // Modo Reserva: guardar coordenadas REALES
    editForm.value = {
      ...editForm.value,
      sup: realY,
      izq: realX,
    }
    cambiosPendientes.value = true
  } else {
    // Modo Pintado: agregar punto con coordenadas REALES
    const nuevosPuntos = editForm.value.coord ? `${editForm.value.coord},${realX},${realY}` : `${realX},${realY}`
    editForm.value = {
      ...editForm.value,
      coord: nuevosPuntos,
    }
    cambiosPendientes.value = true
  }

  repintarPines()
}

/**
 * Limpiar coordenadas pintadas
 */
const limpiarCoordenadas = () => {
  if (editForm.value.tipo === 1) {
    editForm.value.sup = ''
    editForm.value.izq = ''
  } else {
    editForm.value.coord = ''
  }
  cambiosPendientes.value = true
  repintarPines()
}

/**
 * Handler cuando la imagen del mapa se carga
 * Captura las dimensiones originales de la imagen
 * Usa nextTick y requestAnimationFrame para asegurar layout estable
 */
const onImagenCargada = async () => {
  if (!imagenMapa.value) return
  
  // Esperar a que Vue termine de actualizar el DOM
  await nextTick()
  
  // Esperar un frame adicional para que el navegador termine el layout
  requestAnimationFrame(() => {
    if (!imagenMapa.value) return
    
    originalWidth.value = imagenMapa.value.naturalWidth
    originalHeight.value = imagenMapa.value.naturalHeight
    currentWidth.value = imagenMapa.value.clientWidth
    currentHeight.value = imagenMapa.value.clientHeight
    
    isMapaListo.value = true
    repintarPines()
  })
}

/**
 * Recalcular dimensiones del mapa y reposicionar pines
 * Se ejecuta cuando el layout cambia (resize, header toggle, etc)
 * Usa nextTick y requestAnimationFrame para asegurar layout estable
 */
const recalcularMapa = async () => {
  if (!imagenMapa.value || !isMapaListo.value) return
  
  // Esperar a que Vue termine de actualizar el DOM
  await nextTick()
  
  // Esperar un frame adicional para que el navegador termine el layout
  requestAnimationFrame(() => {
    if (!imagenMapa.value) return
    
    // Leer dimensiones reales actuales
    const rect = imagenMapa.value.getBoundingClientRect()
    currentWidth.value = rect.width
    currentHeight.value = rect.height
    
    // Reposicionar todos los pines
    repintarPines()
  })
}

/**
 * Cancelar cambios pendientes
 */
const cancelarCambios = () => {
  if (!estadoOriginal.value)
    return

  editForm.value = { ...estadoOriginal.value }
  cambiosPendientes.value = false
  repintarPines()
}

/**
 * Computado: URL de la imagen del mapa
 */
const rutaImagenMapa = computed(() => {
  if (!pabellon.value)
    return ''
  return `/img/pabellones/${pabellon.value.feria}_${pabellonId.value}.png`
})

const saveStand = async () => {
  if (!selectedStand.value) {
    error.value = 'Debe seleccionar un stand para editar.'
    return
  }

  error.value = null
  success.value = null
  standSuccess.value = null
  clearTimeout(standSuccessTimeout)
  saving.value = true

  try {
    const payload = {
      numero_stand: editForm.value.numero_stand,
      area_stand: parseFloat(editForm.value.area_stand),
      sup: parseInt(editForm.value.sup) || 0,
      izq: parseInt(editForm.value.izq) || 0,
      coord: editForm.value.coord || '',
      tipo: editForm.value.tipo,
    }

    const res = await fetch(`/pabellones/${pabellonId.value}/stands/${selectedStand.value.id_stand}`, {
      method: 'PATCH',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const json = await res.json()

    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validaci�n')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    const updatedStand = json?.data
    if (!updatedStand)
      throw new Error('El servidor guardó los cambios, pero no devolvió el stand actualizado.')

    const updatedStandId = Number(updatedStand.id_stand)
    stands.value = stands.value.map(stand => Number(stand.id_stand) === updatedStandId ? updatedStand : stand)
    selectedStand.value = updatedStand
    onStandSelected(updatedStand)
    standSuccess.value = json.message || 'Stand actualizado correctamente.'
    standSuccessTimeout = setTimeout(() => {
      standSuccess.value = null
      standSuccessTimeout = null
    }, 3000)
  } catch (err) {
    error.value = err.message || 'No se pudo guardar el stand.'
    console.error('Error guardando stand:', err)
  } finally {
    saving.value = false
  }
}

const createStands = async () => {
  error.value = null
  success.value = null

  if (!createForm.value.cantidad || createForm.value.cantidad < 1) {
    error.value = 'La cantidad de stands debe ser al menos 1.'
    return
  }

  if (!createForm.value.area_stand || createForm.value.area_stand <= 0) {
    error.value = 'El Area del stand es obligatoria y debe ser mayor a 0.'
    return
  }

  creating.value = true
  try {
    const payload = {
      cantidad: createForm.value.cantidad,
      area_stand: createForm.value.area_stand,
    }

    const res = await fetch(`/pabellones/${pabellonId.value}/stands`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const json = await res.json()

    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validacion')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Stands creados correctamente.'
    createDialog.value = false
    await loadStands()
  } catch (err) {
    error.value = err.message || 'No se pudieron crear los stands.'
    console.error('Error creando stands:', err)
  } finally {
    creating.value = false
  }
}

const backToPabellones = () => {
  router.back()
}

const agregarFijaLimite = () => {
  const nuevaFila = {
    id: null,
    limite_sup: null,
    cant_credenciales: null,
    isNew: true,
  }
  limitesCredenciales.value.push(nuevaFila)
}

const guardarLimitesCredenciales = async () => {
  savingLimites.value = true
  try {
    // Filtrar SOLO los registros nuevos (isNew === true)
    const limitesNuevos = limitesCredenciales.value.filter(l => l.isNew === true)

    // Si no hay registros nuevos, no hacer nada
    if (limitesNuevos.length === 0) {
      success.value = 'No hay cambios para guardar.'
      credencialesDialog.value = false
      return
    }

    const payload = {
      limites: limitesNuevos,
    }

    const res = await fetch(`/pabellones/${pabellonId.value}/limites-credenciales`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const json = await res.json()

    if (!res.ok) {
      if (res.status === 422 && json.errors) {
        const messages = Object.values(json.errors).flat().join(' ')
        throw new Error(messages || 'Errores de validacion')
      }
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    success.value = json.message || 'Límites de credenciales guardados correctamente.'
    credencialesDialog.value = false
  } catch (err) {
    error.value = err.message || 'Error al guardar los límites de credenciales.'
    console.error('Error guardando límites:', err)
  } finally {
    savingLimites.value = false
  }
}

const eliminarFilaLimite = async (index) => {
  const limite = limitesCredenciales.value[index]

  if (!limite) return
  // Marcar fila como en eliminación
  deletingRowIndex.value = index

  try {
    // Si es nuevo (no guardado en BD), eliminar solo del estado local
    if (limite.isNew === true) {
      limitesCredenciales.value.splice(index, 1)
      return
    }

    // Validar que tenga id
    if (!limite.id) {
      // Fallback: eliminar por campos
      const payload = {
        limite_sup: Number(limite.limite_sup),
        cant_credenciales: Number(limite.cant_credenciales),
      }
      const resAlt = await fetch(`/pabellones/${pabellonId.value}/limites-credenciales/eliminar`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
        body: JSON.stringify(payload),
        credentials: 'same-origin',
      })
      const jsonAlt = await resAlt.json().catch(() => ({}))
      if (!resAlt.ok || jsonAlt.success === false) {
        throw new Error(jsonAlt.message || `HTTP ${resAlt.status}`)
      }
      limitesCredenciales.value.splice(index, 1)
      success.value = jsonAlt.message || 'Límite eliminado.'
      return
    }

    const res = await fetch(`/pabellones/${pabellonId.value}/limites-credenciales/${limite.id}`, {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
    })

    const json = await res.json().catch(() => ({}))
    if (!res.ok || json.success === false) {
      throw new Error(json.message || `HTTP ${res.status}`)
    }

    // Eliminar del estado al confirmar backend
    limitesCredenciales.value.splice(index, 1)
    success.value = json.message || 'Límite eliminado.'
  } catch (err) {
    error.value = err.message || 'No se pudo eliminar el límite.'
    console.error('Error al eliminar límite:', err)
  } finally {
    deletingRowIndex.value = null
  }
}

watch(
  () => [
    editForm.value.numero_stand,
    editForm.value.area_stand,
    editForm.value.sup,
    editForm.value.izq,
    editForm.value.coord,
    editForm.value.tipo,
  ],
  values => {
    if (!selectedStand.value || !estadoOriginal.value)
      return

    const originalValues = [
      estadoOriginal.value.numero_stand,
      estadoOriginal.value.area_stand,
      estadoOriginal.value.sup,
      estadoOriginal.value.izq,
      estadoOriginal.value.coord,
      estadoOriginal.value.tipo,
    ]

    cambiosPendientes.value = values.some((value, index) => value !== originalValues[index])
  },
)

onMounted(async () => {
  await loadStands()
  
  // Esperar a que el DOM esté listo y el header se haya ajustado
  await nextTick()
  
  // Configurar ResizeObserver para detectar cambios de layout automáticamente
  if (imagenMapa.value) {
    resizeObserver = new ResizeObserver((entries) => {
      for (const entry of entries) {
        // Solo recalcular si el mapa ya está listo
        if (isMapaListo.value) {
          recalcularMapa()
        }
      }
    })
    
    resizeObserver.observe(imagenMapa.value)
  }
  
  // Listener adicional para resize de ventana (por si acaso)
  window.addEventListener('resize', recalcularMapa)
})

onUnmounted(() => {
  clearTimeout(standSuccessTimeout)

  // Limpiar ResizeObserver
  if (resizeObserver) {
    resizeObserver.disconnect()
    resizeObserver = null
  }
  
  // Limpiar listener de ventana
  window.removeEventListener('resize', recalcularMapa)
})
</script>

<template>
  <section>
    <VCard class="stand-management-card">
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-2">
          <VBtn 
            icon variant="text" 
            size="small" 
            @click="backToPabellones"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <VIcon icon="tabler-layout-grid" size="28" />
          <div class="d-flex flex-column">
            <span class="text-h5">Gestion de Stands</span>
            <span v-if="pabellon" class="text-caption text-medium-emphasis">
              {{ pabellon.nombre_pabellon }}
            </span>
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <VBtn color="secondary" prepend-icon="tabler-eye" :disabled="loading || !pabellon" @click="abrirVistaPrevia">
            Vista previa
          </VBtn>
          <VBtn color="primary" prepend-icon="tabler-plus" @click="openCreateDialog">
            Agregar Stands
          </VBtn>
          <VBtn color="info" prepend-icon="tabler-license" @click="abrirLimitesCredenciales">
            Límites de Credenciales
          </VBtn>
        </div>
      </VCardTitle>
      <VDivider />
      <VCardText v-if="error && !loading">
        <VAlert type="error" variant="tonal" closable icon="tabler-alert-circle" @click:close="error = null">
          <div class="text-body-2">
            <strong>Error:</strong> {{ error }}
          </div>
        </VAlert>
      </VCardText>
      <VCardText v-if="success && !loading">
        <VAlert type="success" variant="tonal" closable icon="tabler-circle-check" @click:close="success = null">
          <div class="text-body-2">{{ success }}</div>
        </VAlert>
      </VCardText>
      <VCardText class="pt-6">
        <div v-if="selectedStand" class="stand-map-workspace">
          <div class="stand-map-sticky-controls">
            <VCard>
              <VCardText class="pb-2">
                <VAlert
                  v-if="standSuccess"
                  type="success"
                  variant="tonal"
                  closable
                  icon="tabler-circle-check"
                  class="mb-4"
                  @click:close="standSuccess = null"
                >
                  <div class="text-body-2">{{ standSuccess }}</div>
                </VAlert>
                <VRow>
                  <VCol cols="12" md="6">
                    <VSelect v-model="selectedStand" :items="stands" item-title="numero_stand" item-value="id_stand" label="Seleccionar Stand" placeholder="Seleccione un stand" :loading="loading" :disabled="cambiosPendientes" return-object clearable @update:model-value="onStandSelected">
                      <template #item="{ props, item }">
                        <VListItem v-bind="props" :title="`Stand ${item.raw.numero_stand}`" :subtitle="`Area: ${item.raw.area_stand} m2`" />
                      </template>
                    </VSelect>
                  </VCol>
                </VRow>
              </VCardText>
              <VCardTitle class="d-flex align-center gap-2">
                <VIcon icon="tabler-edit" size="24" />
                Editar Stand {{ selectedStand.numero_stand }}
              </VCardTitle>
              <VDivider />
              <VCardText>
                <VRow>
                  <VCol cols="12" md="2">
                    <VTextField v-model="editForm.numero_stand" label="Numero de Stand" type="text" :disabled="saving" outlined />
                  </VCol>
                  <VCol cols="12" md="2">
                    <VTextField v-model.number="editForm.area_stand" label="Metraje (m)" type="number" step="0.01" min="0.01" :disabled="saving" outlined />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField v-model.number="editForm.sup" label="Sup (Y)" type="number" step="1" :readonly="editForm.tipo !== 1 || saving" outlined hint="Coordenada Y (actualizado al hacer click)" persistent-hint />
                    <VTextField v-model.number="editForm.izq" label="Izq (X)" type="number" step="1" :readonly="editForm.tipo !== 1 || saving" outlined hint="Coordenada X (actualizado al hacer click)" persistent-hint />
                  </VCol>
                  <VCol cols="12" md="3">
                    <VTextField v-model="editForm.coord" label="Coordenadas Pintado" type="text" :readonly="editForm.tipo !== 2 || saving" outlined persistent-hint hint="x1,y1,x2,y2,... (actualizado al hacer click)" />
                  </VCol>
                  <VCol cols="12" md="2">
                    <VSelect
                      :model-value="editForm.tipo"
                      :items="[{ title: 'Reserva', value: 1 }, { title: 'Pintado', value: 2 }]"
                      label="Acciones"
                      :disabled="saving"
                      outlined
                      @update:model-value="cambiarModo"
                    />
                    <p class="text-caption text-medium-emphasis mt-2">
                      {{ editForm.tipo === 1 ? '1 pin, click para mover' : 'Múltiples pins, click para agregar' }}
                    </p>
                  </VCol>
                </VRow>
              </VCardText>
              <VDivider />
              <VCardActions class="justify-end">
                <VBtn v-if="cambiosPendientes" variant="tonal" color="secondary" :disabled="saving" @click="cancelarCambios">
                  Cancelar
                </VBtn>
                <VBtn v-if="cambiosPendientes" color="warning" prepend-icon="tabler-eraser" :disabled="saving" @click="limpiarCoordenadas">
                  Limpiar Coordenadas
                </VBtn>
                <VBtn color="primary" prepend-icon="tabler-device-floppy" :loading="saving" :disabled="saving || !cambiosPendientes" @click="saveStand">
                  Guardar Cambios
                </VBtn>
              </VCardActions>
            </VCard>
          </div>

          <VCard class="mt-6">
            <VDivider />
            <VCardText class="pt-4">
              <div class="mb-4">
                <p class="text-subtitle-2 font-weight-bold">Mapa del Pabellón</p>
                <p class="text-caption text-medium-emphasis">
                  {{ editForm.tipo === 1
                    ? 'Modo Reserva: Haz clic para marcar UN punto'
                    : 'Modo Pintado: Haz clic múltiples veces para marcar puntos'
                  }}
                </p>
              </div>
              <div style="position: relative; display: inline-block; max-width: 100%; width: 100%;">
                <img
                  ref="imagenMapa"
                  :src="rutaImagenMapa"
                  alt="Mapa del Pabellón"
                  style="max-width: 100%; cursor: crosshair; border: 2px solid #ddd; border-radius: 4px; display: block;"
                  @click="onMapaClick"
                  @load="onImagenCargada"
                >
                <div
                  v-if="!isMapaListo"
                  style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.8); pointer-events: none;"
                >
                  <VProgressCircular indeterminate color="primary" size="48" />
                </div>
                <div
                  v-if="isMapaListo"
                  ref="contenedorPuntos"
                  style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none;"
                >
                  <div
                    v-for="pin in pins"
                    :key="pin.id"
                    style="position: absolute; transform: translate(-50%, -50%);"
                    :style="{ left: pin.x + 'px', top: pin.y + 'px' }"
                  >
                    <div style="width: 12px; height: 12px; background-color: #e91e63; border-radius: 50%; border: 1px solid white; box-shadow: 0 1px 3px rgba(0,0,0,0.3); cursor: pointer;" />
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>
        <div v-else>
          <VRow>
            <VCol cols="12" md="6">
              <VSelect v-model="selectedStand" :items="stands" item-title="numero_stand" item-value="id_stand" label="Seleccionar Stand" placeholder="Seleccione un stand" :loading="loading" :disabled="cambiosPendientes" return-object clearable @update:model-value="onStandSelected">
                <template #item="{ props, item }">
                  <VListItem v-bind="props" :title="`Stand ${item.raw.numero_stand}`" :subtitle="`Area: ${item.raw.area_stand} m2`" />
                </template>
              </VSelect>
            </VCol>
          </VRow>
          <div v-if="!loading && stands.length === 0" class="text-center pa-8">
            <VIcon icon="tabler-layout-grid-add" size="64" color="disabled" class="mb-4" />
            <div class="text-h6 text-disabled">No hay stands registrados</div>
            <div class="text-body-2 text-disabled mt-2">Haz clic en "Agregar Stands" para comenzar</div>
          </div>
        </div>
      </VCardText>
      <VDialog v-model="createDialog" width="600" persistent>
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-plus" size="24" />
            Agregar Stands
          </VCardTitle>
          <VDivider />
          <VCardText class="pt-4">
            <VForm @submit.prevent="createStands">
              <VRow>
                <VCol cols="12">
                  <VTextField v-model.number="createForm.cantidad" label="Cantidad de Stands" placeholder="Ej: 5" type="number" min="1" required :disabled="creating" hint="Numero de stands a crear" persistent-hint />
                </VCol>
                <VCol cols="12">
                  <VTextField v-model.number="createForm.area_stand" label="Area del Stand (m)" placeholder="Ej: 12.5" type="number" step="0.01" min="0.01" required :disabled="creating" hint="Área en metros cuadrados" persistent-hint />
                </VCol>
              </VRow>
            </VForm>
          </VCardText>
          <VDivider />
          <VCardActions class="justify-end">
            <VBtn variant="tonal" color="secondary" :disabled="creating" @click="createDialog = false">Cancelar</VBtn>
            <VBtn color="primary" prepend-icon="tabler-device-floppy" :loading="creating" :disabled="creating" @click="createStands">Guardar</VBtn>
          </VCardActions>
          <VDivider />
        </VCard>
      </VDialog>

      <!-- VDialog para Límites de Credenciales -->
      <VDialog v-model="credencialesDialog" width="900" persistent>
        <VCard>
          <VCardTitle class="d-flex align-center gap-2">
            <VIcon icon="tabler-license" size="24" />
            Límites de Credenciales
          </VCardTitle>
          <VDivider />
          <VCardText class="pt-4">
            <VTable v-if="limitesCredenciales.length > 0" class="border rounded-lg">
              <thead>
                <tr class="bg-primary-container">
                  <th class="pa-3">Límite de Superficie (m²)</th>
                  <th class="pa-3">Cantidad de Credenciales</th>
                  <th class="pa-3 text-center">Acciones</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(limite, index) in limitesCredenciales" :key="index" class="border-b">
                  <td class="pa-3">
                    <VTextField v-model.number="limite.limite_sup" type="number" step="0.01" min="0" :readonly="!limite.isNew" :disabled="savingLimites" variant="outlined" density="compact" />
                  </td>
                  <td class="pa-3">
                    <VTextField v-model.number="limite.cant_credenciales" type="number" min="0" :readonly="!limite.isNew" :disabled="savingLimites" variant="outlined" density="compact" />
                  </td>
                  <td class="pa-3 text-center">
                    <VBtn icon variant="text" size="small" color="error" :disabled="savingLimites || deletingRowIndex === index" :loading="deletingRowIndex === index" @click="eliminarFilaLimite(index)">
                      <VIcon icon="tabler-trash" size="20" />
                    </VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <div v-else class="text-center pa-8">
              <VIcon icon="tabler-inbox" size="48" color="disabled" class="mb-2" />
              <p class="text-disabled">No hay límites de credenciales registrados</p>
            </div>
          </VCardText>
          <VDivider />
          <VCardActions class="justify-space-between">
            <VBtn color="primary" variant="tonal" prepend-icon="tabler-plus" :disabled="savingLimites" @click="agregarFijaLimite">
              Agregar Límite
            </VBtn>
            <div class="d-flex gap-2">
              <VBtn variant="tonal" color="secondary" :disabled="savingLimites" @click="credencialesDialog = false">Cancelar</VBtn>
              <VBtn color="primary" prepend-icon="tabler-device-floppy" :loading="savingLimites" :disabled="savingLimites" @click="guardarLimitesCredenciales">Guardar</VBtn>
            </div>
          </VCardActions>
        </VCard>
      </VDialog>

      <VDialog v-model="vistaPreviaDialog" width="95vw" max-width="1800" scrollable>
        <VCard class="stand-preview-dialog">
          <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-3">
            <div class="d-flex align-center gap-2">
              <VIcon icon="tabler-map-2" size="24" />
              <div>
                <div class="text-h6">Vista previa del pabellón</div>
                <div v-if="pabellon" class="text-caption text-medium-emphasis">
                  {{ pabellon.nombre_pabellon }} · {{ stands.length }} stands
                </div>
              </div>
            </div>
            <VBtn icon variant="text" aria-label="Cerrar vista previa" @click="vistaPreviaDialog = false">
              <VIcon icon="tabler-x" />
            </VBtn>
          </VCardTitle>
          <VDivider />
          <VCardText class="stand-preview-content">
            <div class="d-flex flex-wrap ga-4 mb-4">
              <div class="d-flex align-center ga-2">
                <span class="stand-preview-legend-dot reservation" />
                <span class="text-body-2">Reserva</span>
              </div>
              <div class="d-flex align-center ga-2">
                <span class="stand-preview-legend-dot painted" />
                <span class="text-body-2">Pintado</span>
              </div>
              <span class="text-body-2 text-medium-emphasis align-self-center">
                Puntos visibles: {{ previewPins.length }}
              </span>
              <span
                class="text-body-2 align-self-center"
                :class="standsFaltantesPorMarcar ? 'text-warning' : 'text-success'"
              >
                Stands faltantes por marcar: <strong>{{ standsFaltantesPorMarcar }}</strong>
              </span>
            </div>
            <VAlert v-if="errorImagenVistaPrevia" type="error" variant="tonal" class="mb-4">
              No se pudo cargar el mapa de este pabellón.
            </VAlert>
            <div v-else-if="rutaImagenMapa" class="stand-preview-map">
              <img
                ref="imagenVistaPrevia"
                :src="rutaImagenMapa"
                :alt="`Mapa de ${pabellon?.nombre_pabellon || 'pabellón'}`"
                @load="onImagenVistaPreviaCargada"
                @error="errorImagenVistaPrevia = true"
              >
              <div v-if="dimensionesVistaPrevia.width" class="stand-preview-pins">
                <div
                  v-for="pin in previewPins"
                  :key="pin.id"
                  class="stand-preview-pin"
                  :class="pin.kind"
                  :style="{ left: pin.left, top: pin.top }"
                  :title="pin.title"
                >
                  <span v-if="pin.label" class="stand-preview-pin-label">{{ pin.label }}</span>
                </div>
              </div>
            </div>
            <div v-else class="text-center pa-8 text-medium-emphasis">
              No hay un mapa asociado a este pabellón.
            </div>
          </VCardText>
          <VDivider />
          <VCardActions class="justify-end">
            <VBtn color="primary" variant="tonal" @click="vistaPreviaDialog = false">Cerrar</VBtn>
          </VCardActions>
        </VCard>
      </VDialog>
    </VCard>
  </section>
</template>

<style scoped>
.stand-preview-dialog {
  max-block-size: 92vh;
}

.stand-preview-content {
  overflow: auto;
}

.stand-preview-map {
  position: relative;
  inline-size: 100%;
}

.stand-preview-map img {
  display: block;
  inline-size: 100%;
  block-size: auto;
}

.stand-preview-pins {
  position: absolute;
  inset: 0;
  pointer-events: none;
}

.stand-preview-pin {
  position: absolute;
  inline-size: 13px;
  block-size: 13px;
  border: 2px solid white;
  border-radius: 50%;
  box-shadow: 0 1px 4px rgb(0 0 0 / 65%);
  transform: translate(-50%, -50%);
}

.stand-preview-pin.reservation,
.stand-preview-legend-dot.reservation {
  background: #e53935;
}

.stand-preview-pin.painted,
.stand-preview-legend-dot.painted {
  background: #1976d2;
}

.stand-preview-pin-label {
  position: absolute;
  inset-block-end: 100%;
  inset-inline-start: 50%;
  padding: 1px 4px;
  border-radius: 4px;
  background: rgb(0 0 0 / 78%);
  color: white;
  font-size: 10px;
  line-height: 1.3;
  transform: translateX(-50%);
}

.stand-preview-legend-dot {
  inline-size: 12px;
  block-size: 12px;
  border: 1px solid white;
  border-radius: 50%;
  box-shadow: 0 1px 3px rgb(0 0 0 / 30%);
}

.stand-management-card {
  overflow: visible;
}

.stand-map-workspace {
  overflow: visible;
}

@media (min-width: 960px) {
  .stand-map-sticky-controls {
    position: sticky;
    inset-block-start: 72px;
    z-index: 10;
    padding: 8px 12px 12px;
    margin: -8px -12px 0;
    background: rgb(var(--v-theme-background));
    border-radius: 12px;
    box-shadow: 0 8px 18px rgb(0 0 0 / 16%);
  }
}

.mapa-container {
  position: relative;
  display: inline-block;
  max-width: 100%;
}

.mapa-container img {
  max-width: 100%;
  cursor: crosshair;
  border: 2px solid #ddd;
  border-radius: 4px;
  display: block;
}

.pin {
  width: 20px;
  height: 20px;
  background-color: #e91e63;
  border-radius: 50%;
  border: 2px solid white;
  box-shadow: 0 2px 4px rgba(0,0,0,0.3);
  cursor: pointer;
  transition: all 0.2s ease-in-out;
}

.pin:hover {
  transform: scale(1.2);
  box-shadow: 0 4px 8px rgba(0,0,0,0.4);
}
</style>
