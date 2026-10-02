<script setup>
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useContratoAccess } from '@/composables/useContratoAccess'
import EchoInit from '@/plugins/4.echo.js'

// =============================
// VARIABLES REACTIVAS PRINCIPALES
// =============================
const intervalId = ref(null)
const standsBloqueadosTemporal = ref({}) // { [id_stand]: { id_usuario, nombre_usuario, expira_en } }
const standsBloqueadosTemporalPlano = computed(() => standsBloqueadosTemporal.value)
const usuarioActual = ref(null)
const route = useRoute()
const router = useRouter()
const { canCreateContrato } = useContratoAccess()
const idFeria = computed(() => route.query.id_feria)
let echoStandsChannel = null;
let echoContratosChannel = null;

// =============================
// FUNCIONES DE BLOQUEO TEMPORAL
// =============================
// Formatea el tiempo en mm:ss
function formatCountdown(idStand) {
  const bloqueo = standsBloqueadosTemporal.value[idStand]
  if (!bloqueo || bloqueo.expira_en == null) return '--:--'
  const total = Math.max(0, bloqueo.expira_en)
  const min = Math.floor(total / 60)
  const sec = total % 60
  return `${min.toString().padStart(2, '0')}:${sec.toString().padStart(2, '0')}`
}

function tickCountdowns() {
  let needsRefresh = false
  for (const [id, bloqueo] of Object.entries(standsBloqueadosTemporal.value)) {
    if (bloqueo.expira_en != null && bloqueo.expira_en > 0) {
      bloqueo.expira_en--
      if (bloqueo.expira_en === 0) needsRefresh = true
    }
  }
  // Forzar reactividad
  standsBloqueadosTemporal.value = { ...standsBloqueadosTemporal.value }
  if (needsRefresh) {
    cargarBloqueosTemporales()
  }
}

function cargarBloqueosTemporales() {
  fetch(`/stands/bloqueos-temporales?id_feria=${idFeria.value}`)
    .then(res => res.json())
    .then(json => {
      if (json.success) standsBloqueadosTemporal.value = json.data || {}
    })
}

// =============================
// INICIALIZACIÓN Y ECHO
// =============================
EchoInit && EchoInit()

onMounted(() => {
  intervalId.value = setInterval(tickCountdowns, 1000)
})

onUnmounted(() => {
  if (intervalId.value) clearInterval(intervalId.value)
})

function subscribeEchoChannels() {
  // Limpiar canales anteriores
  if (echoStandsChannel) {
    echoStandsChannel.stopListening('.StandBloqueadoTemporalmente');
    window.Echo.leave(`private-stands.feria.${idFeria.value}`);
    echoStandsChannel = null;
  }
  if (echoContratosChannel) {
    echoContratosChannel.stopListening('.ContratoActualizado');
    window.Echo.leave(`private-contratos.feria.${idFeria.value}`);
    echoContratosChannel = null;
  }
  if (window.Echo && idFeria.value) {
    // console.log('[Echo] Suscribiendo a canal:', `private-stands.feria.${idFeria.value}`); // Eliminado: ya no se necesita
    echoStandsChannel = window.Echo.private(`stands.feria.${idFeria.value}`)
      .listen('.StandBloqueadoTemporalmente', (e) => {
        // console.log('[Echo] Evento StandBloqueadoTemporalmente recibido:', e)
        if (e && e.id_stand) {
          if (e.bloquear) {
            // Guardar objeto completo: { id_usuario, nombre_usuario, expira_en }
            standsBloqueadosTemporal.value = {
              ...standsBloqueadosTemporal.value,
              [e.id_stand]: {
                id_usuario: e.id_usuario,
                nombre_usuario: e.nombre_usuario,
                expira_en: e.expira_en
              }
            }
            // console.log('[Echo] Stand bloqueado:', e.id_stand, 'por usuario:', e.nombre_usuario, 'expira en:', e.expira_en)
          } else {
            // Eliminar el stand de bloqueados temporales
            const copy = { ...standsBloqueadosTemporal.value }
            delete copy[e.id_stand]
            standsBloqueadosTemporal.value = copy
            // console.log('[Echo] Stand liberado:', e.id_stand)
            // Si el stand estaba seleccionado, quitarlo SIEMPRE de la selección local al desbloquear
            const idx = standsSeleccionados.value.indexOf(e.id_stand)
            if (idx !== -1) {
              standsSeleccionados.value.splice(idx, 1)
              // console.log('[Echo] Stand eliminado de selección local (unlock global):', e.id_stand)
            }
            // Marcar el stand como disponible en el array de stands
            const standObj = stands.value.find(s => s.id_stand === e.id_stand)
            if (standObj) {
              standObj.disponible = true
              // Forzar reactividad
              stands.value = [...stands.value]
              // console.log('[Echo] Stand marcado como disponible y refrescado:', e.id_stand)
            }
          }
          // Forzar refresco visual de stands
          stands.value = [...stands.value]
          // console.log('[Echo] Estado standsBloqueadosTemporal:', standsBloqueadosTemporal.value)
        }
      });
    echoContratosChannel = window.Echo.private(`contratos.feria.${idFeria.value}`)
      .listen('.ContratoActualizado', (e) => {
        if (e && e.accion === 'create' && Array.isArray(e.data.stands)) {
          for (const standId of e.data.stands) {
            const stand = stands.value.find(s => s.id_stand === standId)
            if (stand) stand.disponible = false
            // Liberar bloqueo temporal si existía
            const copy = { ...standsBloqueadosTemporal.value }
            delete copy[standId]
            standsBloqueadosTemporal.value = copy
          }
          stands.value = [...stands.value]
        }
      });
  }
}

onMounted(async () => {
  cargarFeria()
  cargarEmpresas()
  window.addEventListener('resize', recalcularMapa)
  // Obtener usuario actual (ajusta según tu auth)
  try {
    const res = await fetch('/me')
    const json = await res.json()
    usuarioActual.value = json.id_usuario || null
  } catch {}
  subscribeEchoChannels();
});

watch(idFeria, (nuevo, anterior) => {
  if (nuevo && nuevo !== anterior) {
    subscribeEchoChannels();
  }
});
onUnmounted(() => {
  window.removeEventListener('resize', recalcularMapa)
  if (echoStandsChannel) {
    echoStandsChannel.stopListening('.StandBloqueadoTemporalmente');
    window.Echo.leave(`private-stands.feria.${idFeria.value}`);
    echoStandsChannel = null;
  }
  if (echoContratosChannel) {
    echoContratosChannel.stopListening('.ContratoActualizado');
    window.Echo.leave(`private-contratos.feria.${idFeria.value}`);
    echoContratosChannel = null;
  }
  // Liberar bloqueos temporales de stands seleccionados al salir
  if (standsSeleccionados.value.length > 0 && idFeria.value) {
    standsSeleccionados.value.forEach(async (id_stand) => {
      await fetch('/stands/bloquear', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id_feria: idFeria.value, id_stand, bloquear: false }),
      })
    })
  }
});

// Liberar bloqueos temporales si el usuario cierra o recarga la pestaña
if (typeof window !== 'undefined') {
  window.addEventListener('beforeunload', () => {
    if (standsSeleccionados.value && standsSeleccionados.value.length > 0 && idFeria.value) {
      standsSeleccionados.value.forEach((id_stand) => {
        const data = JSON.stringify({ id_feria: idFeria.value, id_stand, bloquear: false })
        if (navigator.sendBeacon) {
          navigator.sendBeacon('/stands/bloquear', data)
        }
      })
    }
  })
}

// Validar acceso
if (!canCreateContrato()) {
  router.push({ name: 'acceso-restringido' })
}

// Estado
const loading = ref(false)
const loadingEmpresas = ref(false)
const loadingPabellones = ref(false)
const loadingStands = ref(false)
const guardandoReserva = ref(false)
const empresas = ref([])
const empresaSeleccionada = ref(null)
const pabellones = ref([])
const pabellonSeleccionado = ref(null)
const stands = ref([])
const standsSeleccionados = ref([])
const feria = ref(null)
const error = ref(null)
const success = ref(null)

// Imagen y escalado (replica del mapeo de Feria)
const imagenMapa = ref(null)
const originalWidth = ref(0)
const originalHeight = ref(0)
const currentWidth = ref(0)
const currentHeight = ref(0)
const isMapaListo = ref(false)
const scaleX = computed(() => currentWidth.value && originalWidth.value ? currentWidth.value / originalWidth.value : 1)
const scaleY = computed(() => currentHeight.value && originalHeight.value ? currentHeight.value / originalHeight.value : 1)

// Configuración
const MAX_STANDS = 12 // Máximo de stands permitidos

// Estado económico de la reserva
const precioUnitario = ref(0)
const modoPrecio = ref('m2')
const precioTotalEspacio = ref(0)
const tipoDescuento = ref(null) // 'fepc', 'pronto_pago_1', 'pronto_pago_2', 'otro'
const porcentajeDescuento = ref(0)
const otroDescuentoTexto = ref('Otro Descuento')

// const idFeria = computed(() => route.query.id_feria) // Eliminada duplicada

// Cargar feria
const cargarFeria = async () => {
  if (!idFeria.value) {
    error.value = 'No se especificó una feria'
    return
  }

  loading.value = true
  
  try {
    const response = await fetch(`/contratos/ferias/${idFeria.value}`)
    const json = await response.json()
    
    if (json.success) {
      feria.value = json.data.feria
    } else {
      error.value = json.message || 'Error al cargar la feria'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar la feria'
  } finally {
    loading.value = false
  }
}

// Cargar empresas
const cargarEmpresas = async () => {
  loadingEmpresas.value = true
  
  try {
    const response = await fetch('/contratos/empresas')
    const json = await response.json()
    
    if (json.success) {
      empresas.value = json.data
    } else {
      error.value = json.message || 'Error al cargar las empresas'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar las empresas'
  } finally {
    loadingEmpresas.value = false
  }
}

// Cargar pabellones de la feria
const cargarPabellones = async () => {
  if (!idFeria.value) return
  
  loadingPabellones.value = true
  
  try {
    const response = await fetch(`/contratos/pabellones/${idFeria.value}`)
    const json = await response.json()
    
    if (json.success) {
      pabellones.value = json.data
    } else {
      error.value = json.message || 'Error al cargar los pabellones'
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar los pabellones'
  } finally {
    loadingPabellones.value = false
  }
}

// Cargar stands del pabellón
const cargarStands = async () => {
  if (!idFeria.value || !pabellonSeleccionado.value) return

  loadingStands.value = true
  standsSeleccionados.value = []

  try {
    const [standsRes, bloqueosRes] = await Promise.all([
      fetch(`/contratos/stands/${idFeria.value}/${pabellonSeleccionado.value}`),
      fetch(`/stands/bloqueos-temporales?id_feria=${idFeria.value}`)
    ])
    const standsJson = await standsRes.json()
    const bloqueosJson = await bloqueosRes.json()

    if (standsJson.success) {
      stands.value = standsJson.data
    } else {
      error.value = standsJson.message || 'Error al cargar los stands'
    }
    if (bloqueosJson.success) {
      // Guardar el objeto completo para cada stand bloqueado
      standsBloqueadosTemporal.value = bloqueosJson.data || {}
      // Refuerzo: limpiar selección local de stands que ya no están bloqueados por el usuario actual
      const nuevosSeleccionados = standsSeleccionados.value.filter(
        idStand => standsBloqueadosTemporal.value[idStand] && standsBloqueadosTemporal.value[idStand].id_usuario === usuarioActual.value
      )
      if (nuevosSeleccionados.length !== standsSeleccionados.value.length) {
        standsSeleccionados.value = nuevosSeleccionados
        // Forzar refresco visual
        stands.value = [...stands.value]
      }
    }
  } catch (err) {
    error.value = 'Error de conexión al cargar los stands'
  } finally {
    loadingStands.value = false
  }
}

// Computada para determinar si la empresa es nueva (texto) o existente (ID)
const esEmpresaNueva = computed(() => {
  return empresaSeleccionada.value && typeof empresaSeleccionada.value === 'string'
})

const checkEmpresa = computed(() => {
  if (!empresaSeleccionada.value) return null
  
  // Si es un número (ID), es empresa existente
  if (typeof empresaSeleccionada.value === 'number') {
    return 'A'
  }
  
  // Si es texto, es empresa nueva
  return empresaSeleccionada.value
})

// Habilitar select de pabellón solo si hay empresa seleccionada
const pabellonHabilitado = computed(() => {
  return empresaSeleccionada.value !== null && empresaSeleccionada.value !== ''
})

// Información del pabellón seleccionado
const pabellonActual = computed(() => {
  if (!pabellonSeleccionado.value) return null
  return pabellones.value.find(p => p.id_pabellon === pabellonSeleccionado.value)
})

// URL de la imagen del mapa del pabellón
const mapaUrl = computed(() => {
  return pabellonActual.value?.mapa_url || null
})

// Stands disponibles (no ocupados)
const standsDisponibles = computed(() => {
  return stands.value.filter(s => s.disponible)
})

// Metraje total de stands seleccionados
const metrajeTotal = computed(() => {
  return standsSeleccionados.value.reduce((total, idStand) => {
    const stand = stands.value.find(s => s.id_stand === idStand)
    return total + (stand?.area_stand || 0)
  }, 0)
})

// Cálculos económicos
const redondearDosDecimales = (valor) => {
  const numero = Number(valor)
  if (!Number.isFinite(numero)) return 0
  return Math.round((numero + Number.EPSILON) * 100) / 100
}

const precioUnitarioCalculado = computed(() => {
  if (modoPrecio.value === 'total') {
    return metrajeTotal.value > 0
      ? redondearDosDecimales(precioTotalEspacio.value / metrajeTotal.value)
      : 0
  }

  return redondearDosDecimales(precioUnitario.value)
})

const montoInicial = computed(() => {
  if (modoPrecio.value === 'total') return redondearDosDecimales(precioTotalEspacio.value)
  return redondearDosDecimales(metrajeTotal.value * precioUnitarioCalculado.value)
})

const importeDescuento = computed(() => {
  return redondearDosDecimales((montoInicial.value * porcentajeDescuento.value) / 100)
})

const totalAPagar = computed(() => {
  return redondearDosDecimales(montoInicial.value - importeDescuento.value)
})

const cambiarModoPrecio = (modo) => {
  if (modo === modoPrecio.value) return

  if (modo === 'total') {
    precioTotalEspacio.value = montoInicial.value
  } else {
    precioUnitario.value = metrajeTotal.value > 0
      ? redondearDosDecimales(precioTotalEspacio.value / metrajeTotal.value)
      : 0
  }

  modoPrecio.value = modo
}

const bloquearDecimalesExtra = (event) => {
  if (event.ctrlKey || event.metaKey || event.key.length !== 1) return

  const input = event.target
  const inicio = input.selectionStart ?? input.value.length
  const fin = input.selectionEnd ?? inicio
  const valorSiguiente = `${input.value.slice(0, inicio)}${event.key}${input.value.slice(fin)}`

  if (!/^\d*(?:[.,]\d{0,2})?$/.test(valorSiguiente)) event.preventDefault()
}

const bloquearPegadoConMasDeDosDecimales = (event) => {
  const valorPegado = event.clipboardData?.getData('text').trim()
  if (valorPegado && !/^\d*(?:[.,]\d{0,2})?$/.test(valorPegado)) event.preventDefault()
}

const normalizarPrecioUnitario = () => {
  precioUnitario.value = redondearDosDecimales(precioUnitario.value)
}

const actualizarPrecioUnitario = (valor) => {
  if (modoPrecio.value === 'm2') precioUnitario.value = String(valor).replace(',', '.')
}

const normalizarPrecioTotal = () => {
  precioTotalEspacio.value = redondearDosDecimales(precioTotalEspacio.value)
}

const actualizarPrecioTotal = (valor) => {
  if (modoPrecio.value === 'total') precioTotalEspacio.value = String(valor).replace(',', '.')
}

// Verificar si hay descuento activo
const hayDescuentoActivo = computed(() => {
  return tipoDescuento.value !== null
})

// Verificar si mostrar bloque económico
const mostrarBloqueEconomico = computed(() => {
  return standsSeleccionados.value.length > 0
})

// Verificar si se alcanzó el máximo de stands
const maxStandsAlcanzado = computed(() => {
  return standsSeleccionados.value.length >= MAX_STANDS
})

// Verificar si un stand está seleccionado
const isStandSeleccionado = (idStand) => {
  return standsSeleccionados.value.includes(idStand)
}

// Verificar si un stand está deshabilitado
const isStandDeshabilitado = (stand) => {
  // Deshabilitado si está ocupado
  if (!stand.disponible) return true
  // Deshabilitado si está bloqueado temporalmente por otro usuario Y no está seleccionado por mí
  if (
    standsBloqueadosTemporal.value[stand.id_stand] &&
    standsBloqueadosTemporal.value[stand.id_stand] !== usuarioActual.value &&
    !isStandSeleccionado(stand.id_stand)
  ) return true
  // Deshabilitado si se alcanzó el máximo y no está seleccionado
  if (maxStandsAlcanzado.value && !isStandSeleccionado(stand.id_stand)) {
    return true
  }
  return false
}
// Mostrar mensaje si el stand está bloqueado temporalmente
const getStandBloqueoMsg = (stand) => {
  if (standsBloqueadosTemporal.value[stand.id_stand] && standsBloqueadosTemporal.value[stand.id_stand] !== usuarioActual.value) {
    return 'En proceso de reserva por otro usuario';
  }
  return '';
}

// Seleccionar tipo de descuento (mutuamente excluyente)
const seleccionarDescuento = (tipo) => {
  if (tipoDescuento.value === tipo) {
    // Si ya está seleccionado, deseleccionar
    tipoDescuento.value = null
    porcentajeDescuento.value = 0
  } else {
    // Seleccionar nuevo tipo
    tipoDescuento.value = tipo
    porcentajeDescuento.value = 0
  }
}

// Verificar si un descuento está activo
const isDescuentoActivo = (tipo) => {
  return tipoDescuento.value === tipo
}

// Toggle selección de stand con bloqueo temporal
const bloquearStandTemporal = async (stand, bloquear) => {
  try {
    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    if (!csrfToken) {
      const csrfResponse = await fetch('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })
      if (csrfResponse.ok) {
        const csrfJson = await csrfResponse.json().catch(() => null)
        csrfToken = csrfJson?.token || ''
        if (csrfToken) {
          const meta = document.querySelector('meta[name="csrf-token"]')
          if (meta)
            meta.setAttribute('content', csrfToken)
        }
      }
    }
    await fetch('/stands/bloquear', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      credentials: 'same-origin',
      body: JSON.stringify({ id_feria: idFeria.value, id_stand: stand.id_stand, bloquear }),
    })
  } catch {}
}
const toggleStand = async (stand) => {
  // console.log('toggleStand llamado', stand.id_stand, 'standsSeleccionados:', standsSeleccionados.value)
  if (!stand.disponible) return
  if (standsBloqueadosTemporal.value[stand.id_stand] && standsBloqueadosTemporal.value[stand.id_stand] !== usuarioActual.value) return
  const index = standsSeleccionados.value.indexOf(stand.id_stand)
  if (index === -1) {
    if (!maxStandsAlcanzado.value) {
      standsSeleccionados.value.push(stand.id_stand)
      await bloquearStandTemporal(stand, true)
    }
  } else {
    standsSeleccionados.value.splice(index, 1)
    // console.log('Deseleccionando stand', stand.id_stand)
    await bloquearStandTemporal(stand, false)
  }
}

// Estilo absoluto del stand respetando escala de la imagen (igual a Feria)
const getStandStyle = (stand) => {
  const x = (stand.izq || 0) * scaleX.value
  const y = (stand.sup || 0) * scaleY.value
  return {
    position: 'absolute',
    top: `${y}px`,
    left: `${x}px`,
  }
}

// Al cargar la imagen, capturar dimensiones reales y actuales
const onImagenCargada = async () => {
  if (!imagenMapa.value) return
  await nextTick()
  requestAnimationFrame(() => {
    if (!imagenMapa.value) return
    originalWidth.value = imagenMapa.value.naturalWidth
    originalHeight.value = imagenMapa.value.naturalHeight
    currentWidth.value = imagenMapa.value.clientWidth
    currentHeight.value = imagenMapa.value.clientHeight
    isMapaListo.value = true
  })
}

// Recalcular escala al cambiar tamaño del contenedor
const recalcularMapa = async () => {
  if (!imagenMapa.value || !isMapaListo.value) return
  await nextTick()
  requestAnimationFrame(() => {
    if (!imagenMapa.value) return
    const rect = imagenMapa.value.getBoundingClientRect()
    currentWidth.value = rect.width
    currentHeight.value = rect.height
  })
}

// Watchers
watch(empresaSeleccionada, (newVal) => {
  // Si se cambia la empresa, resetear pabellón y stands
  if (newVal) {
    // Limpiar bloqueos temporales propios antes de cambiar
    standsSeleccionados.value.forEach(async (id_stand) => {
      await fetch('/stands/bloquear', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id_feria: idFeria.value, id_stand, bloquear: false }),
      })
    })
    standsSeleccionados.value = []
    // Cargar pabellones cuando se selecciona empresa
    if (pabellones.value.length === 0) {
      cargarPabellones()
    }
  } else {
    pabellonSeleccionado.value = null
    stands.value = []
    standsSeleccionados.value = []
  }
})

watch(pabellonSeleccionado, (newVal) => {
  // Cargar stands cuando se selecciona pabellón
  if (newVal) {
    isMapaListo.value = false
    // Limpiar bloqueos temporales propios antes de cambiar
    standsSeleccionados.value.forEach(async (id_stand) => {
      await fetch('/stands/bloquear', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'same-origin',
        body: JSON.stringify({ id_feria: idFeria.value, id_stand, bloquear: false }),
      })
    })
    standsSeleccionados.value = []
    cargarStands()
  } else {
    stands.value = []
    standsSeleccionados.value = []
    isMapaListo.value = false
    originalWidth.value = 0
    originalHeight.value = 0
    currentWidth.value = 0
    currentHeight.value = 0
  }
})

// Guardar reserva completa
const guardarReserva = async () => {
  if (!empresaSeleccionada.value || standsSeleccionados.value.length === 0) {
    error.value = 'Debe seleccionar una empresa y al menos un stand'
    return
  }

  if (montoInicial.value <= 0 || precioUnitarioCalculado.value <= 0) {
    error.value = modoPrecio.value === 'total'
      ? 'Debe ingresar un total del espacio válido'
      : 'Debe ingresar un precio por m² válido'
    return
  }


  // Refuerzo: deshabilitar botón y loader
  guardandoReserva.value = true
  error.value = null
  success.value = null

  try {
    // Determinar tipo de descuento en formato backend
    let tipoDescTexto = 'Sin Descuento'
    if (tipoDescuento.value === 'fepc') {
      tipoDescTexto = 'FEPC'
    } else if (tipoDescuento.value === 'pronto_pago_1') {
      tipoDescTexto = 'Pronto Pago 1'
    } else if (tipoDescuento.value === 'pronto_pago_2') {
      tipoDescTexto = 'Pronto Pago 2'
    } else if (tipoDescuento.value === 'otro') {
      tipoDescTexto = otroDescuentoTexto.value
    }

    const payload = {
      id_feria: idFeria.value,
      check_empresa: checkEmpresa.value,
      id_empresa: typeof empresaSeleccionada.value === 'number' ? empresaSeleccionada.value : null,
      id_pabellon: pabellonSeleccionado.value,
      stands: standsSeleccionados.value,
      metraje_total: metrajeTotal.value,
      modo_precio: modoPrecio.value,
      precio_unit: modoPrecio.value === 'm2' ? precioUnitarioCalculado.value : null,
      precio_total: modoPrecio.value === 'total' ? montoInicial.value : null,
      tipo_desc: tipoDescTexto,
      porcentaje_desc: porcentajeDescuento.value,
    }

    let csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

    if (!csrfToken) {
      const csrfResponse = await fetch('/csrf-token', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      })

      if (csrfResponse.ok) {
        const csrfJson = await csrfResponse.json().catch(() => null)
        csrfToken = csrfJson?.token || ''

        if (csrfToken) {
          const meta = document.querySelector('meta[name="csrf-token"]')
          if (meta)
            meta.setAttribute('content', csrfToken)
        }
      }
    }

    const response = await fetch('/contratos/reserva', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })

    const json = await response.json()

    if (response.status === 409) {
      error.value = json.message || 'El stand acaba de ser reservado por otro usuario.'
      return
    }

    if (!response.ok) {
      throw new Error(json.message || 'Error al guardar la reserva')
    }

    if (json.success) {
      success.value = 'Reserva creada exitosamente'
      setTimeout(() => {
        router.push(`/contrato/llenado/${json.contrato_id}`)
      }, 1500)
    } else {
      error.value = json.message || 'Error al crear la reserva'
    }
  } catch (err) {
    error.value = err.message || 'Error de conexión al guardar la reserva'
  } finally {
    guardandoReserva.value = false
  }
}

// Volver al listado manteniendo la feria seleccionada
const volver = () => {
  const feriaId = idFeria.value
  if (feriaId) {
    router.push({ name: 'contrato-list', query: { id_feria: String(feriaId) } })
  } else {
    router.push({ name: 'contrato-list' })
  }
}

onMounted(() => {
  cargarFeria()
  cargarEmpresas()
  window.addEventListener('resize', recalcularMapa)
})

onUnmounted(() => {
  window.removeEventListener('resize', recalcularMapa)
})
</script>

<template>
  <div>
    <!-- Encabezado -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4">
          <div>
            <div class="d-flex align-center gap-2 mb-2">
              <VBtn
                icon
                variant="text"
                size="small"
                @click="volver"
              >
                <VIcon icon="tabler-arrow-left" />
              </VBtn>
              <h4 class="text-h4">
                Nueva Reserva de Contrato
              </h4>
            </div>
            <p
              v-if="feria"
              class="text-body-1 mb-0"
            >
              Feria: <strong>{{ feria.nombre }}</strong>
            </p>
          </div>
        </div>
      </VCardText>
    </VCard>

    <!-- Mensajes -->
    <VAlert
      v-if="error"
      type="error"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="error = null"
    >
      {{ error }}
    </VAlert>

    <VAlert
      v-if="success"
      type="success"
      variant="tonal"
      class="mb-4"
      closable
      @click:close="success = null"
    >
      {{ success }}
    </VAlert>

    <!-- PASO 1: Selección de Empresa -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon
            icon="tabler-number-1-small"
            color="primary"
            size="32"
          />
          <h5 class="text-h5">
            Seleccionar o Escribir Empresa
          </h5>
        </div>

        <VRow>
          <!-- Combobox de Empresas (permite texto libre) -->
          <VCol cols="12">
            <VCombobox
              v-model="empresaSeleccionada"
              :items="empresas"
              :loading="loadingEmpresas"
              item-title="nombre_empresa"
              item-value="id_empresa"
              label="Buscar empresa existente o escribir nueva"
              placeholder="Escribe o selecciona el nombre de la empresa..."
              clearable
              no-data-text="No se encontraron empresas"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-building" />
              </template>

              <!-- Template para cada item en la lista -->
              <template #item="{ props, item }">
                <VListItem
                  v-bind="props"
                  :title="item.raw.nombre_empresa"
                >
                  <template #subtitle>
                    <div class="text-caption">
                      <span v-if="item.raw.nombre_responsable">
                        Responsable: {{ item.raw.nombre_responsable }}
                      </span>
                      <span
                        v-if="item.raw.nombre_gerente"
                        class="ml-2"
                      >
                        | Gerente: {{ item.raw.nombre_gerente }}
                      </span>
                    </div>
                  </template>
                </VListItem>
              </template>
            </VCombobox>

            <!-- Ayuda -->
            <div class="text-caption text-medium-emphasis mt-2">
              <VIcon
                icon="tabler-info-circle"
                size="small"
              />
              Puedes seleccionar una empresa existente o escribir el nombre de una nueva empresa
            </div>
          </VCol>

          <!-- Indicador de empresa nueva o existente -->
          <VCol
            v-if="empresaSeleccionada"
            cols="12"
          >
            <VAlert
              :type="esEmpresaNueva ? 'info' : 'success'"
              variant="tonal"
            >
              <div class="d-flex align-center gap-2">
                <VIcon :icon="esEmpresaNueva ? 'tabler-plus' : 'tabler-check'" />
                <div>
                  <strong v-if="esEmpresaNueva">Nueva empresa</strong>
                  <strong v-else>Empresa existente</strong>
                  <div class="text-caption">
                    <span v-if="esEmpresaNueva">
                      Se creará automáticamente: "{{ empresaSeleccionada }}"
                    </span>
                  </div>
                </div>
              </div>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- PASO 2: Selección de Pabellón -->
    <VCard class="mb-6">
      <VCardText>
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon
            icon="tabler-number-2-small"
            :color="pabellonHabilitado ? 'primary' : 'disabled'"
            size="32"
          />
          <h5 class="text-h5">
            Seleccionar Pabellón
          </h5>
        </div>

        <VRow>
          <VCol cols="12" md="6">
            <VSelect
              v-model="pabellonSeleccionado"
              :items="pabellones"
              :loading="loadingPabellones"
              :disabled="!pabellonHabilitado"
              item-title="nombre_pabellon"
              item-value="id_pabellon"
              label="Seleccionar pabellón"
              placeholder="Elige un pabellón"
              clearable
            >
              <template #prepend-inner>
                <VIcon icon="tabler-building-warehouse" />
              </template>
            </VSelect>

            <div
              v-if="!pabellonHabilitado"
              class="text-caption text-medium-emphasis mt-2"
            >
              <VIcon
                icon="tabler-alert-circle"
                size="small"
              />
              Primero debes seleccionar una empresa
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- PASO 3: Selección Visual de Stands -->
    <VCard
      v-if="pabellonSeleccionado && mapaUrl"
      class="mb-6"
    >
      <VCardText>
        <div class="d-flex align-center justify-space-between flex-wrap gap-4 mb-4">
          <div class="d-flex align-center gap-2">
            <VIcon
              icon="tabler-number-3-small"
              color="primary"
              size="32"
            />
            <h5 class="text-h5">
              Seleccionar Stands
            </h5>
          </div>

          <!-- Indicadores -->
          <div class="d-flex gap-4 flex-wrap">
            <VChip
              color="primary"
              variant="tonal"
            >
              <VIcon
                start
                icon="tabler-checkup-list"
              />
              {{ standsSeleccionados.length }} / {{ MAX_STANDS }} stands
            </VChip>
            <VChip
              color="success"
              variant="tonal"
            >
              <VIcon
                start
                icon="tabler-ruler"
              />
              {{ metrajeTotal.toFixed(2) }} m²
            </VChip>
          </div>
        </div>

        <!-- Mapa del Pabellón con Stands -->
        <VRow>
          <VCol cols="12">
            <!-- Contenedor responsivo de la imagen -->
            <div class="pabellon-mapa-container">
              <VProgressLinear
                v-if="loadingStands"
                indeterminate
                color="primary"
                class="mb-4"
              />

              <div
                v-else
                class="pabellon-mapa-wrapper"
              >
                <!-- Imagen del pabellón -->
                <img
                  ref="imagenMapa"
                  :src="mapaUrl"
                  :alt="`Mapa ${pabellonActual?.nombre_pabellon}`"
                  class="pabellon-mapa-imagen"
                  @load="onImagenCargada"
                  @error="error = 'No se pudo cargar la imagen del pabellón'"
                >

                <!-- Capa de stands (solo checkboxes) -->
                <div class="stands-layer">
                  <template v-for="stand in stands" :key="stand.id_stand">
                    <input
                      type="checkbox"
                      class="stand-checkbox"
                      :style="getStandStyle(stand)"
                      :checked="isStandSeleccionado(stand.id_stand)"
                      :disabled="isStandDeshabilitado(stand)"
                      @change="toggleStand(stand)"
                    >
                    <div
                      v-if="standsBloqueadosTemporalPlano && standsBloqueadosTemporalPlano[stand.id_stand] && standsBloqueadosTemporalPlano[stand.id_stand].expira_en > 0"
                      class="bloqueo-temporal-info"
                      :style="getStandStyle(stand)"
                    >
                      <span>
                        Bloqueado por: {{ standsBloqueadosTemporalPlano[stand.id_stand].nombre_usuario || 'Usuario' }}
                      </span>
                      <span>
                        (Se libera en: {{ formatCountdown(stand.id_stand) }})
                      </span>
                      <span v-if="!standsBloqueadosTemporalPlano[stand.id_stand].nombre_usuario || standsBloqueadosTemporalPlano[stand.id_stand].expira_en === undefined" style="color:red;font-size:0.7em;">[Debug: Falta info de bloqueo]</span>
                    </div>
                  </template>
                </div>
              </div>

              <!-- Instrucciones -->
              <div class="mt-4">
                <VAlert
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  <div class="text-caption">
                    <VIcon
                      icon="tabler-click"
                      size="small"
                      class="mr-1"
                    />
                    Haz clic en los checkboxes para seleccionar los stands.
                    <span v-if="maxStandsAlcanzado"> Has alcanzado el máximo de {{ MAX_STANDS }} stands.</span>
                  </div>
                </VAlert>
              </div>

              <!-- Leyenda -->
              <div class="mt-4 d-flex gap-4 flex-wrap">
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-square-check"
                    color="primary"
                  />
                  <span class="text-caption">Disponible</span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-square-x"
                    color="error"
                  />
                  <span class="text-caption">Ocupado</span>
                </div>
                <div class="d-flex align-center gap-2">
                  <VIcon
                    icon="tabler-square"
                    color="disabled"
                  />
                  <span class="text-caption">No seleccionable</span>
                </div>
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- PASO 4: Cálculo Económico de la Reserva -->
    <VCard
      v-if="mostrarBloqueEconomico"
      class="mb-6"
    >
      <VCardText>
        <div class="d-flex align-center gap-2 mb-4">
          <VIcon
            icon="tabler-number-4-small"
            color="primary"
            size="32"
          />
          <h5 class="text-h5">
            Precio y Descuentos
          </h5>
        </div>

        <VRow>
          <!-- Superficie Total (readonly) -->
          <VCol cols="12" md="2">
            <VTextField
              :model-value="metrajeTotal.toFixed(2)"
              label="Superficie total (m²)"
              readonly
              variant="outlined"
              density="comfortable"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-ruler" />
              </template>
            </VTextField>
          </VCol>

          <!-- Modo de precio y valores vinculados -->
          <VCol cols="12" md="3">
            <VSelect
              :model-value="modoPrecio"
              :items="[
                { title: 'Precio por m²', value: 'm2' },
                { title: 'Total del espacio', value: 'total' },
              ]"
              label="Calcular precio por"
              variant="outlined"
              density="comfortable"
              class="mb-3"
              @update:model-value="cambiarModoPrecio"
            />
            <VTextField
              :model-value="modoPrecio === 'm2' ? precioUnitario : precioUnitarioCalculado"
              label="Precio por m² (Bs.)"
              type="text"
              inputmode="decimal"
              :readonly="modoPrecio === 'total'"
              variant="outlined"
              density="comfortable"
              placeholder="0.00"
              @blur="normalizarPrecioUnitario"
              @keydown="bloquearDecimalesExtra"
              @paste="bloquearPegadoConMasDeDosDecimales"
              @update:model-value="actualizarPrecioUnitario"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-currency-dollar" />
              </template>
            </VTextField>
            <VTextField
              :model-value="modoPrecio === 'total' ? precioTotalEspacio : montoInicial"
              label="Total del espacio (Bs.)"
              class="mt-3"
              type="text"
              inputmode="decimal"
              :readonly="modoPrecio === 'm2'"
              variant="outlined"
              density="comfortable"
              placeholder="0.00"
              @blur="normalizarPrecioTotal"
              @keydown="bloquearDecimalesExtra"
              @paste="bloquearPegadoConMasDeDosDecimales"
              @update:model-value="actualizarPrecioTotal"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-currency-dollar" />
              </template>
            </VTextField>
          </VCol>

          <!-- Asignar Descuento -->
          <VCol cols="12" md="2">
            <div class="mb-2">
              <strong class="text-body-2">Asignar un Descuento:</strong>
            </div>
            <VCheckbox
              :model-value="isDescuentoActivo('fepc')"
              label="FEPC"
              density="compact"
              hide-details
              @update:model-value="seleccionarDescuento('fepc')"
            />
            <VCheckbox
              :model-value="isDescuentoActivo('pronto_pago_1')"
              label="Pronto Pago 1"
              density="compact"
              hide-details
              @update:model-value="seleccionarDescuento('pronto_pago_1')"
            />
            <VCheckbox
              :model-value="isDescuentoActivo('pronto_pago_2')"
              label="Pronto Pago 2"
              density="compact"
              hide-details
              @update:model-value="seleccionarDescuento('pronto_pago_2')"
            />
            <div class="d-flex align-center gap-2">
              <VCheckbox
                :model-value="isDescuentoActivo('otro')"
                label="Otro"
                density="compact"
                hide-details
                @update:model-value="seleccionarDescuento('otro')"
              />
              <VTextField
                v-model="otroDescuentoTexto"
                :readonly="!isDescuentoActivo('otro')"
                density="compact"
                variant="outlined"
                hide-details
                placeholder="Otro Descuento"
              />
            </div>
          </VCol>

          <!-- Porcentaje de Descuento -->
          <VCol
            v-if="hayDescuentoActivo"
            cols="12"
            md="2"
          >
            <VTextField
              v-model.number="porcentajeDescuento"
              label="Porcentaje de Descuento %"
              type="number"
              step="0.01"
              min="0"
              max="100"
              variant="outlined"
              density="comfortable"
              placeholder="0"
            >
              <template #prepend-inner>
                <VIcon icon="tabler-percentage" />
              </template>
            </VTextField>
          </VCol>

          <!-- Total a Pagar -->
          <VCol cols="12" md="3">
            <div class="d-flex flex-column gap-2">
              <!-- Información de cálculo -->
              <VAlert
                type="info"
                variant="tonal"
                density="compact"
              >
                <div class="text-caption">
                  <div><strong>Monto inicial:</strong> Bs. {{ montoInicial.toFixed(2) }}</div>
                  <div v-if="hayDescuentoActivo && porcentajeDescuento > 0">
                    <strong>Descuento ({{ porcentajeDescuento }}%):</strong> Bs. {{ importeDescuento.toFixed(2) }}
                  </div>
                </div>
              </VAlert>

              <!-- Total -->
              <VTextField
                :model-value="totalAPagar.toFixed(2)"
                label="Total a Pagar (Bs.)"
                readonly
                variant="filled"
                density="comfortable"
                color="success"
              >
                <template #prepend-inner>
                  <VIcon
                    icon="tabler-coin"
                    color="success"
                  />
                </template>
              </VTextField>
            </div>
          </VCol>
        </VRow>

        <!-- Resumen -->
        <VDivider class="my-4" />
        
        <VRow>
          <VCol cols="12">
            <VAlert
              v-if="totalAPagar > 0"
              type="success"
              variant="tonal"
            >
              <div class="d-flex align-center justify-space-between flex-wrap gap-4">
                <div>
                  <strong>Resumen de la Reserva</strong>
                  <div class="text-caption mt-1">
                    {{ standsSeleccionados.length }} stand(s) • {{ metrajeTotal.toFixed(2) }} m² • Bs. {{ precioUnitarioCalculado.toFixed(2) }}/m²
                    <span v-if="hayDescuentoActivo && porcentajeDescuento > 0">
                      • Descuento: {{ porcentajeDescuento }}%
                    </span>
                  </div>
                </div>
                <div class="text-h5">
                  <strong>Total: Bs. {{ totalAPagar.toFixed(2) }}</strong>
                </div>
              </div>
            </VAlert>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Acciones -->
    <VCard>
      <VCardActions class="d-flex justify-end gap-2 pa-4">
        <VBtn
          color="secondary"
          variant="outlined"
          @click="volver"
        >
          Cancelar
        </VBtn>
        <VBtn
          color="primary"
          :disabled="!empresaSeleccionada || standsSeleccionados.length === 0 || montoInicial <= 0 || precioUnitarioCalculado <= 0 || totalAPagar <= 0 || guardandoReserva"
          :loading="guardandoReserva"
          @click="guardarReserva"
        >
          Generar Reserva
          <VIcon
            end
            icon="tabler-device-floppy"
          />
        </VBtn>
      </VCardActions>
    </VCard>
  </div>
</template>

<style scoped>
.pabellon-mapa-container {
  width: 100%;
}

.pabellon-mapa-wrapper {
  position: relative;
  display: inline-block;
  max-width: 100%;
}

.pabellon-mapa-imagen {
  display: block;
  max-width: 100%;
  height: auto;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}
.stands-layer {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 5;
}

.stand-checkbox {
  position: absolute;
  margin: 0;
  padding: 0;
  width: 18px;
  height: 18px;
}
.bloqueo-temporal-info {
  background: #ffe0e0;
  color: #b71c1c;
  font-size: 0.8em;
  border-radius: 4px;
  padding: 2px 6px;
  margin-top: 2px;
  display: inline-block;
  position: absolute;
  z-index: 10;
}
</style>

