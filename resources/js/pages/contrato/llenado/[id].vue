<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import { useContratoAccess } from '@/composables/useContratoAccess'
import { useContratoValidation } from '@/composables/useContratoValidation'

definePage({
  meta: {
    requiresAuth: true,
  },
})

const route = useRoute()
const router = useRouter()
const { canEditContrato } = useContratoAccess()

// Validar acceso
if (!canEditContrato()) {
  router.push({ name: 'acceso-restringido' })
}

// ==================== VALIDACIONES ====================
const {
  requiredRule,
  requiredSelectRule,
  requiredCredentialRule,
  emailRule,
  phoneRule,
  nitRule,
  autocompleteIdRule,
  textareaRequiredRule,
  validarFormularioCompleto,
  generarMensajeError
} = useContratoValidation()

const formularioRef = ref(null)
const isValid = ref(null)

// ==================== ESTADO ====================
const contratoId = ref(route.params.id)
const contrato = ref(null)
const baseUrl = window.location.origin
const empresa = ref(null)
const cargando = ref(true)
const error = ref(null)
const origen = ref('listado') // 'reserva' o 'listado'
const mostrarFormulario = ref(false)
const guardarActivo = ref(false)
const contratoGuardado = ref(false) // Bandera para habilitar "Generar Contrato"

// ==================== MODAL GENERAR CONTRATO ====================
const generandoContrato = ref(false)
const modalGenerado = ref(false)
const codigoContratoGenerado = ref(null)

// ==================== MODAL LINK LLENADO ====================
const modalLinkLlenado = ref(false)

const copiarLink = () => {
  if (!contrato.value) return
  const link = `${baseUrl}/formulario/${contrato.value.id_contrato}/${contrato.value.clave}`

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard.writeText(link)
      .then(() => mostrarNotificacion('¡Link copiado al portapapeles!', 'success'))
      .catch(() => mostrarNotificacion('No se pudo copiar el link', 'error'))
  } else {
    // Fallback para HTTP (sin HTTPS)
    const el = document.createElement('textarea')
    el.value = link
    el.style.position = 'fixed'
    el.style.opacity = '0'
    document.body.appendChild(el)
    el.select()
    const ok = document.execCommand('copy')
    document.body.removeChild(el)
    mostrarNotificacion(ok ? '¡Link copiado al portapapeles!' : 'No se pudo copiar el link', ok ? 'success' : 'error')
  }
}

// ==================== SNACKBAR NOTIFICACIONES ====================
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')
const snackbarIcon = ref('tabler-check')

const mostrarNotificacion = (mensaje, tipo = 'success') => {
  snackbarMessage.value = mensaje
  snackbarColor.value = tipo
  snackbarIcon.value = tipo === 'success' ? 'tabler-check' : tipo === 'error' ? 'tabler-x' : 'tabler-info-circle'
  snackbar.value = true
}

// ==================== PAÍS Y CIUDAD ====================
const paisSeleccionado = ref(null)
const ciudadSeleccionada = ref(null)
const paisesListEmpresa = ref([])
const paisesListMarcas = ref([])
const ciudadesList = ref([])
const buscandoPaises = ref(false)
const buscandoCiudades = ref(false)
const paisSearchInput = ref('')
const ciudadSearchInput = ref('')
let paisDebounce = null
let ciudadDebounce = null

// ==================== RUBROS Y SUBRUBROS ====================
const rubrosList = ref([])
const subrubrosList = ref([])
const cargandoRubros = ref(false)
const cargandoSubrubros = ref(false)

// ==================== DESCUENTOS ====================
const tipoDescuento = ref(null) // 'fepc', 'pronto_pago_1', 'pronto_pago_2', 'otro'
const porcentajeDescuento = ref(0)
const otroDescuentoTexto = ref('')
const modoPrecio = ref('m2')
const precioTotalEspacio = ref(0)

// ==================== FORMULARIO ====================
const formulario = ref({
  // Empresa
  nombre_empresa: '',
  nit: '',
  
  // Dirección/Contacto
  direccion: '',
  telefono: '',
  fax: '',
  email: '',
  web: '',
  
  // País y Ciudad (IDs internos)
  pais_id: null,
  ciudad_id: null,
  
  // Categoría y Aniversario
  categoria: '',
  aniversario: '',
  
  // Escritura y Poder
  nr_escritura: '',
  fecha_nr_escritura: '',
  matricula: '',
  nr_poder: '',
  nr_notaria: '',
  fecha_nr_poder: '',
  distrito: '',
  
  // Actividad Principal y Rubros
  actividad_principal: '0',
  id_rubro: 'No Asignado',
  id_subrubro: 'No Asignado',
  otro_rubro: '',
  
  // Datos legales
  nombre_responsable: '',
  ci_responsable: '',
  exp_ci_responsable: '',
  telefono_responsable: '',
  email_responsable: '',
  
  // Actividad/Rubro
  rubro: '',
  subrubro: '',
  desc_producto: '',
  
  // Representante Legal
  nombre_representante: '',
  ci_representante: '',
  exp_ci_representante: '',
  telefono_representante: '',
  email_representante: '',
  id_tipo_representante: '',
  
  // Contacto Responsable
  nombre_contacto: '',
  cargo_contacto: '',
  telefono_contacto: '',
  email_contacto: '',
  
  // Información Comercial - Gerente/Representante
  nombre_gerente: '',
  ci_gerente: '',
  exp_ci_gerente: 'Cb.',
  fono_gerente: '',
  cargo_gerente: '',
  
  // Información Comercial
  nombre_gerente: '',
  ci_gerente: '',
  exp_ci_gerente: '',
  fono_gerente: '',
  cargo_gerente: '',
  email_gerente: '',
  
  // Marcas
  marcas_nacionales: [{ nombre: '' }],
  marcas_internacionales: [{ nombre: '', pais: '' }],
  marcas_multinacionales: [{ nombre: '', pais: '' }],
  
  // Datos de Feria/Evento
  medio_comunicacion: '',
  como_entero: '',
  tipo_expositor: '',
  tipo_credenciales: '',
  productos: '',
  
  // Perfil del Visitante
  perfil_visitante: '',
  
  // Observaciones
  observaciones: '',
  
  // Información Económica
  precio_por_m2: 0,
  tipo_descuento: 'porcentaje',
  porcentaje_descuento: 0,
  monto_descuento: 0,
  
  // Fechas de Pago
  fecha_primer_pago: '',
  monto_primer_pago: 0,
  fecha_segundo_pago: '',
  monto_segundo_pago: 0,
  fecha_tercer_pago: '',
  monto_tercer_pago: 0,
})

// ==================== CATÁLOGOS ====================
const catalogos = ref({
  paises: [],
  ciudades: [],
  rubros: [],
  subrubros: [],
  categorias: [],
  tiposRepresentante: [],
})

// ==================== COMPUTED ====================
const areaTotal = computed(() => {
  const value = contrato.value?.metraje_total || 0
  return parseFloat(value) || 0
})

const precioTotal = computed(() => {
  let total = areaTotal.value * (formulario.value.precio_por_m2 || 0)
  
  if (formulario.value.tipo_descuento === 'porcentaje') {
    const descuento = (total * (formulario.value.porcentaje_descuento || 0)) / 100
    total -= descuento
  } else if (formulario.value.tipo_descuento === 'monto') {
    total -= (formulario.value.monto_descuento || 0)
  }
  
  return Math.max(0, total)
})

const totalPagos = computed(() => {
  return (formulario.value.monto_primer_pago || 0) +
         (formulario.value.monto_segundo_pago || 0) +
         (formulario.value.monto_tercer_pago || 0)
})

// ==================== COMPUTED PARA SECCIÓN 4 ====================
const redondearDosDecimales = (valor) => {
  const numero = Number(valor)
  if (!Number.isFinite(numero)) return 0
  return Math.round((numero + Number.EPSILON) * 100) / 100
}

const precioUnitarioCalculado = computed(() => {
  if (modoPrecio.value === 'total') {
    return areaTotal.value > 0
      ? redondearDosDecimales(precioTotalEspacio.value / areaTotal.value)
      : 0
  }

  return redondearDosDecimales(contrato.value?.precio_unit || 0)
})

const montoInicial = computed(() => {
  if (modoPrecio.value === 'total') return redondearDosDecimales(precioTotalEspacio.value)
  return redondearDosDecimales(areaTotal.value * precioUnitarioCalculado.value)
})

const importeDescuento = computed(() => {
  return redondearDosDecimales((montoInicial.value * (porcentajeDescuento.value || 0)) / 100)
})

const totalAPagar = computed(() => {
  return redondearDosDecimales(montoInicial.value - importeDescuento.value)
})

const cambiarModoPrecio = (modo) => {
  if (modo === modoPrecio.value) return

  if (modo === 'total') {
    precioTotalEspacio.value = montoInicial.value
  } else if (contrato.value) {
    contrato.value.precio_unit = precioUnitarioCalculado.value
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

const actualizarPrecioUnitario = (valor) => {
  if (modoPrecio.value === 'm2' && contrato.value) {
    contrato.value.precio_unit = String(valor).replace(',', '.')
  }
}

const normalizarPrecioUnitario = () => {
  if (modoPrecio.value === 'm2' && contrato.value) {
    contrato.value.precio_unit = redondearDosDecimales(contrato.value.precio_unit)
  }
}

const actualizarPrecioTotal = (valor) => {
  if (modoPrecio.value === 'total') precioTotalEspacio.value = String(valor).replace(',', '.')
}

const normalizarPrecioTotal = () => {
  if (modoPrecio.value === 'total') {
    precioTotalEspacio.value = redondearDosDecimales(precioTotalEspacio.value)
  }
}

// ==================== MÉTODOS ====================

const cargarDatos = async () => {
  if (!contratoId.value) {
    error.value = 'No se especificó ID de contrato'
    cargando.value = false
    return
  }

  try {
    cargando.value = true
    
    // Obtener contrato
    const responseContrato = await axios.get(`/contratos/${contratoId.value}`)
    
    if (responseContrato.data.success) {
      contrato.value = responseContrato.data.data
      empresa.value = contrato.value.empresa

      const precioUnitarioGuardado = redondearDosDecimales(contrato.value.precio_unit)
      const precioTotalGuardado = redondearDosDecimales(contrato.value.precio_total)
      precioTotalEspacio.value = precioTotalGuardado
      modoPrecio.value = redondearDosDecimales(areaTotal.value * precioUnitarioGuardado) === precioTotalGuardado
        ? 'm2'
        : 'total'
      
      // Determinar origen basado en estado_reserva
      origen.value = contrato.value.estado_reserva === 2 ? 'reserva' : 'listado'
      
      // Si es reserva, mostrar alert
      if (origen.value === 'reserva') {
        mostrarFormulario.value = false
      } else {
        mostrarFormulario.value = true
      }
      
      // Popular formulario con datos existentes
      if (empresa.value) {
        formulario.value.nombre_empresa = empresa.value.nombre_empresa || ''
        formulario.value.nit = empresa.value.nit || ''
        formulario.value.direccion = empresa.value.direccion || ''
        formulario.value.telefono = empresa.value.telefono || ''
        formulario.value.fax = empresa.value.fax || ''
        formulario.value.email = empresa.value.email || ''
        formulario.value.web = empresa.value.web || ''
        
        // ✅ CORRECCIÓN: Cargar país y ciudad usando los nombres reales de BD
        formulario.value.pais_id = empresa.value.pais || null
        formulario.value.ciudad_id = empresa.value.ciudad || null
        
        // ✅ CORRECCIÓN: Mapeo correcto de datos de contacto/responsable
        // Los nombres reales en BD son: nombre_responsable, telefono_responsable, email_representante
        formulario.value.nombre_contacto = empresa.value.nombre_responsable || ''
        formulario.value.telefono_contacto = empresa.value.telefono_responsable || ''
        formulario.value.email_contacto = empresa.value.email_representante || ''
        
        // Otros campos que sí están correctamente mapeados
        formulario.value.nombre_responsable = empresa.value.nombre_responsable || ''
        formulario.value.ci_responsable = empresa.value.ci_responsable || ''
        formulario.value.exp_ci_responsable = empresa.value.exp_ci_responsable || ''
        formulario.value.nombre_representante = empresa.value.nombre_representante || ''
        formulario.value.ci_representante = empresa.value.ci_representante || ''
        formulario.value.exp_ci_representante = empresa.value.exp_ci_representante || ''
        formulario.value.nombre_gerente = empresa.value.nombre_gerente || ''
        formulario.value.ci_gerente = empresa.value.ci_gerente || ''
        formulario.value.exp_ci_gerente = empresa.value.exp_ci_gerente || ''
        formulario.value.fono_gerente = empresa.value.fono_gerente || ''
        formulario.value.cargo_gerente = empresa.value.cargo_gerente || ''
        formulario.value.desc_producto = empresa.value.desc_producto || ''
        
        // ✅ CORRECCIÓN: Recuperar actividad principal y rubros
        formulario.value.actividad_principal = empresa.value.id_tipo_representante 
          ? String(empresa.value.id_tipo_representante).split('00')[0] 
          : '0'
        formulario.value.id_rubro = empresa.value.rubro ? String(empresa.value.rubro) : '0'
        formulario.value.id_subrubro = empresa.value.subrubro ? String(empresa.value.subrubro) : '0'
        formulario.value.otro_rubro = empresa.value.otro_rubro || ''
        
        // ✅ CORRECCIÓN: Recuperar información adicional de empresa
        formulario.value.aniversario = empresa.value.aniversario || ''
        formulario.value.categoria = empresa.value.cluster ? String(empresa.value.cluster) : ''
        formulario.value.nr_escritura = empresa.value.nr_escritura || ''
        formulario.value.fecha_nr_escritura = empresa.value.fecha_nr_escritura || ''
        formulario.value.matricula = empresa.value.matricula || ''
        formulario.value.nr_poder = empresa.value.nr_poder || ''
        formulario.value.nr_notaria = empresa.value.nr_notaria || ''
        formulario.value.fecha_nr_poder = empresa.value.fecha_nr_poder || ''
        formulario.value.distrito = empresa.value.distrito || ''
      }
      
      // Popular contrato específico
      if (contrato.value) {
        // ✅ NOTA: categoria ya fue cargada desde empresa.cluster arriba
        // aquí solo cargamos los campos específicos del contrato
        formulario.value.perfil_visitante = contrato.value.perfil_visitante || ''
        formulario.value.observaciones = contrato.value.observaciones || ''
        
        // ✅ CORRECCIÓN P1: Recuperar campos de información del evento (convertir a string)
        formulario.value.medio_comunicacion = Number(contrato.value.medio_comunicacion) > 0 ? String(contrato.value.medio_comunicacion) : ''
        formulario.value.como_entero = Number(contrato.value.como_entero) > 0 ? String(contrato.value.como_entero) : ''
        formulario.value.tipo_expositor = Number(contrato.value.tipo_expositor) > 0 ? String(contrato.value.tipo_expositor) : ''
        formulario.value.tipo_credenciales = contrato.value.tipo_credenciales !== null && contrato.value.tipo_credenciales !== undefined
          ? String(contrato.value.tipo_credenciales)
          : ''
        
        // ✅ CORRECCIÓN P3: Recuperar productos/servicios
        formulario.value.productos = contrato.value.productos || ''
        
        // ✅ CORRECCIÓN P4: Parsear y reconstruir marcas nacionales
        if (contrato.value.marca_principal) {
          const marcasNacionales = contrato.value.marca_principal.split(';').filter(m => m.trim())
          formulario.value.marcas_nacionales = marcasNacionales.map(m => ({ nombre: m.trim() }))
        } else {
          formulario.value.marcas_nacionales = [{ nombre: '' }]
        }
        
        // ✅ CORRECCIÓN P5: Parsear y reconstruir marcas internacionales
        if (contrato.value.pais_principal) {
          const pares = contrato.value.pais_principal.split(';').filter(p => p.trim())
          const marcasInt = []
          for (let i = 0; i < pares.length; i += 2) {
            if (pares[i]) {
              const paisIdONombre = pares[i + 1] ? pares[i + 1].trim() : ''
              // Convertir a número si es numérico, sino dejar como está
              const paisValue = /^\d+$/.test(paisIdONombre) ? parseInt(paisIdONombre) : paisIdONombre
              marcasInt.push({
                nombre: pares[i].trim(),
                pais: paisValue
              })
            }
          }
          formulario.value.marcas_internacionales = marcasInt.length > 0 ? marcasInt : [{ nombre: '', pais: '' }]
        } else {
          formulario.value.marcas_internacionales = [{ nombre: '', pais: '' }]
        }
        
        // ✅ CORRECCIÓN P5: Parsear y reconstruir marcas multinacionales/secundarias
        if (contrato.value.marcas_secundarios) {
          const pares = contrato.value.marcas_secundarios.split(';').filter(p => p.trim())
          const marcasMulti = []
          for (let i = 0; i < pares.length; i += 2) {
            if (pares[i]) {
              const paisIdONombre = pares[i + 1] ? pares[i + 1].trim() : ''
              // Convertir a número si es numérico, sino dejar como está
              const paisValue = /^\d+$/.test(paisIdONombre) ? parseInt(paisIdONombre) : paisIdONombre
              marcasMulti.push({
                nombre: pares[i].trim(),
                pais: paisValue
              })
            }
          }
          formulario.value.marcas_multinacionales = marcasMulti.length > 0 ? marcasMulti : [{ nombre: '', pais: '' }]
        } else {
          formulario.value.marcas_multinacionales = [{ nombre: '', pais: '' }]
        }
        
        // Inicializar descuentos desde el contrato
        if (contrato.value.descuento && contrato.value.descuento > 0) {
          porcentajeDescuento.value = contrato.value.descuento
          
          // Determinar el tipo de descuento
          if (contrato.value.tipo_desc === 'FEPC') {
            tipoDescuento.value = 'fepc'
          } else if (contrato.value.tipo_desc === 'Pronto Pago 1') {
            tipoDescuento.value = 'pronto_pago_1'
          } else if (contrato.value.tipo_desc === 'Pronto Pago 2') {
            tipoDescuento.value = 'pronto_pago_2'
          } else if (contrato.value.tipo_desc && contrato.value.tipo_desc !== 'Sin Descuento') {
            tipoDescuento.value = 'otro'
            otroDescuentoTexto.value = contrato.value.tipo_desc
          }
        }
      }
      
      // Cargar nombres de país y ciudad desde IDs
      await cargarNombresPaisYCiudad()
      
      // ✅ CORRECCIÓN P5: Cargar todos los países para los selects de marcas
      await cargarTodosPaises()
      
      // Cargar catálogos de rubros y subrubros
      await cargarRubros()
      await cargarSubrubros()
      
    } else {
      error.value = responseContrato.data.message || 'No se pudo cargar el contrato'
    }
  } catch (err) {
    error.value = 'Error al cargar la información del contrato'
  } finally {
    cargando.value = false
  }
}

// ==================== MÉTODOS PAÍS Y CIUDAD ====================
const buscarPaises = async (termino) => {
  if (!termino || termino.length < 2) {
    paisesListEmpresa.value = []
    return
  }
  
  buscandoPaises.value = true
  try {
    const response = await axios.get('/api/autocomplete/paises', {
      params: { term: termino }
    })
    paisesListEmpresa.value = response.data
  } catch (err) {
    paisesListEmpresa.value = []
  } finally {
    buscandoPaises.value = false
  }
}

const onPaisInput = (value) => {
  paisSearchInput.value = value
  
  // Limpiar debounce anterior
  if (paisDebounce) clearTimeout(paisDebounce)
  
  // Buscar con debounce
  if (value && value.length >= 2) {
    paisDebounce = setTimeout(() => {
      buscarPaises(value)
    }, 300)
  } else {
    paisesListEmpresa.value = []
  }
}

const onPaisSelect = async (nombrePais) => {
  if (nombrePais) {
    // Buscar el país en la lista para obtener su ID
    const paisEncontrado = paisesListEmpresa.value.find(p => p.label === nombrePais)
    
    if (paisEncontrado) {
      formulario.value.pais_id = paisEncontrado.id
      paisSeleccionado.value = nombrePais
      
      // Limpiar ciudad
      formulario.value.ciudad_id = null
      ciudadSeleccionada.value = null
      ciudadesList.value = []
      
      // Cargar ciudades del país seleccionado
      await cargarCiudadesPorPais(paisEncontrado.id)
    }
  } else {
    formulario.value.pais_id = null
    paisSeleccionado.value = null
    ciudadesList.value = []
  }
}

const cargarCiudadesPorPais = async (idPais) => {
  if (!idPais) {
    ciudadesList.value = []
    return
  }
  
  try {
    const response = await axios.get(`/api/ciudades/${idPais}`)
    ciudadesList.value = response.data.map(c => ({
      id: c.id,
      value: c.nombre,
      label: c.nombre
    }))
  } catch (err) {
    ciudadesList.value = []
  }
}

const buscarCiudades = async (termino) => {
  if (!termino || termino.length < 2) {
    return
  }
  
  if (!formulario.value.pais_id) {
    return
  }
  
  buscandoCiudades.value = true
  try {
    const response = await axios.get('/api/autocomplete/ciudades', {
      params: {
        term: termino,
        id_pais: formulario.value.pais_id
      }
    })
    ciudadesList.value = response.data
  } catch (err) {
  } finally {
    buscandoCiudades.value = false
  }
}

const onCiudadInput = (value) => {
  ciudadSearchInput.value = value
  
  // Limpiar debounce anterior
  if (ciudadDebounce) clearTimeout(ciudadDebounce)
  
  // Buscar con debounce
  if (value && value.length >= 2) {
    ciudadDebounce = setTimeout(() => {
      buscarCiudades(value)
    }, 300)
  }
}

const onCiudadSelect = (nombreCiudad) => {
  if (nombreCiudad) {
    // Buscar la ciudad en la lista para obtener su ID
    const ciudadEncontrada = ciudadesList.value.find(c => c.label === nombreCiudad)
    
    if (ciudadEncontrada) {
      formulario.value.ciudad_id = ciudadEncontrada.id
      ciudadSeleccionada.value = nombreCiudad
    }
  } else {
    formulario.value.ciudad_id = null
    ciudadSeleccionada.value = null
  }
}

const cargarRubros = async () => {
  cargandoRubros.value = true
  try {
    const response = await axios.get('/api/rubros')
    const mapped = (response.data || []).map(r => ({
      value: r.id_rubro ? String(r.id_rubro) : '0',
      title: r.nombre_rubro || ''
    }))
    // Siempre incluir opción "No asignado" al inicio
    rubrosList.value = [{ value: '0', title: 'No asignado' }, ...mapped]
  } catch (err) {
    rubrosList.value = [
      { value: '0', title: 'No asignado' }
    ]
  } finally {
    cargandoRubros.value = false
  }
}

const cargarSubrubros = async () => {
  cargandoSubrubros.value = true
  try {
    const response = await axios.get('/api/subrubros')
    const mapped = (response.data || []).map(s => ({
      value: s.id_subrubro ? String(s.id_subrubro) : '0',
      title: s.nombre_subrubro || ''
    }))
    // Siempre incluir opción "No asignado" al inicio
    subrubrosList.value = [{ value: '0', title: 'No asignado' }, ...mapped]
  } catch (err) {
    subrubrosList.value = [
      { value: '0', title: 'No asignado' }
    ]
  } finally {
    cargandoSubrubros.value = false
  }
}

// ✅ NUEVA FUNCIÓN: Cargar todos los países para los selects de marcas
const cargarTodosPaises = async () => {
  try {
    // Intentar cargar todos los países de la base de datos
    const response = await axios.get('/api/paises')
    
    if (response.data && response.data.length > 0) {
      // Mapear los países a formato {id, label, value}
      paisesListMarcas.value = response.data.map(pais => ({
        id: parseInt(pais.id) || pais.id,
        label: pais.nombre_pais || pais.nombre || pais.label,
        value: pais.nombre_pais || pais.nombre || pais.label
      }))
    } else {
      // Fallback si no devuelve datos
      paisesListMarcas.value = [
        { id: 1, label: 'Bolivia', value: 'Bolivia' },
        { id: 2, label: 'Argentina', value: 'Argentina' },
        { id: 3, label: 'Brasil', value: 'Brasil' },
        { id: 4, label: 'Chile', value: 'Chile' },
        { id: 5, label: 'Perú', value: 'Perú' },
        { id: 6, label: 'Colombia', value: 'Colombia' },
        { id: 7, label: 'Ecuador', value: 'Ecuador' },
        { id: 8, label: 'Paraguay', value: 'Paraguay' },
        { id: 9, label: 'Uruguay', value: 'Uruguay' },
        { id: 10, label: 'Venezuela', value: 'Venezuela' },
        { id: 11, label: 'México', value: 'México' },
        { id: 12, label: 'Estados Unidos', value: 'Estados Unidos' },
        { id: 13, label: 'España', value: 'España' },
        { id: 14, label: 'China', value: 'China' },
        { id: 15, label: 'Alemania', value: 'Alemania' },
        { id: 16, label: 'Italia', value: 'Italia' },
        { id: 17, label: 'Francia', value: 'Francia' },
        { id: 18, label: 'Reino Unido', value: 'Reino Unido' },
      ]
    }
  } catch (err) {
    // Fallback con países comunes si falla la API
    paisesListMarcas.value = [
      { id: 1, label: 'Bolivia', value: 'Bolivia' },
      { id: 2, label: 'Argentina', value: 'Argentina' },
      { id: 3, label: 'Brasil', value: 'Brasil' },
      { id: 4, label: 'Chile', value: 'Chile' },
      { id: 5, label: 'Perú', value: 'Perú' },
      { id: 6, label: 'Colombia', value: 'Colombia' },
      { id: 7, label: 'México', value: 'México' },
      { id: 8, label: 'Estados Unidos', value: 'Estados Unidos' },
    ]
  }
}

const cargarNombresPaisYCiudad = async () => {
  // Cargar nombre del país si existe pais_id
  if (formulario.value.pais_id) {
    try {
      const responsePais = await axios.get(`/api/paises/${formulario.value.pais_id}/nombre`)
      if (responsePais.data.nombre) {
        paisSeleccionado.value = responsePais.data.nombre
        
        // Cargar ciudades de este país
        await cargarCiudadesPorPais(formulario.value.pais_id)
      }
    } catch (err) {
      // Error silencioso: no afecta funcionalidad principal
    }
  }
  
  // Cargar nombre de ciudad si existe ciudad_id
  if (formulario.value.ciudad_id) {
    try {
      const responseCiudad = await axios.get(`/api/ciudades/${formulario.value.ciudad_id}/nombre`)
      if (responseCiudad.data.nombre) {
        ciudadSeleccionada.value = responseCiudad.data.nombre
      }
    } catch (err) {
      // Error silencioso: no afecta funcionalidad principal
    }
  }
}

const abrirFormulario = () => {
  mostrarFormulario.value = true
  guardarActivo.value = true
}

const agregarMarcaNacional = () => {
  formulario.value.marcas_nacionales.push({ nombre: '' })
}

const eliminarMarcaNacional = (index) => {
  formulario.value.marcas_nacionales.splice(index, 1)
}

const agregarMarcaInternacional = () => {
  formulario.value.marcas_internacionales.push({ nombre: '', pais: '' })
}

const eliminarMarcaInternacional = (index) => {
  formulario.value.marcas_internacionales.splice(index, 1)
}

const agregarMarcaMultinacional = () => {
  formulario.value.marcas_multinacionales.push({ nombre: '', pais: '' })
}

const eliminarMarcaMultinacional = (index) => {
  formulario.value.marcas_multinacionales.splice(index, 1)
}

const copiarRepresentante = () => {
  formulario.value.nombre_contacto = formulario.value.nombre_gerente
  formulario.value.telefono_contacto = formulario.value.fono_gerente
}

// ==================== FUNCIONES DE DESCUENTO ====================
const seleccionarDescuento = (tipo) => {
  if (tipoDescuento.value === tipo) {
    // Si ya está seleccionado, deseleccionar
    tipoDescuento.value = null
    porcentajeDescuento.value = 0
    contrato.value.tipo_desc = null
    contrato.value.descuento = 0
  } else {
    // Seleccionar nuevo tipo
    tipoDescuento.value = tipo
    porcentajeDescuento.value = 0
    
    // Actualizar contrato según el tipo
    if (tipo === 'fepc') {
      contrato.value.tipo_desc = 'FEPC'
    } else if (tipo === 'pronto_pago_1') {
      contrato.value.tipo_desc = 'Pronto Pago 1'
    } else if (tipo === 'pronto_pago_2') {
      contrato.value.tipo_desc = 'Pronto Pago 2'
    } else if (tipo === 'otro') {
      contrato.value.tipo_desc = otroDescuentoTexto.value
    }
    contrato.value.descuento = 0
  }
}

// Verificar si un descuento está activo
const isDescuentoActivo = (tipo) => {
  return tipoDescuento.value === tipo
}

// Verificar si hay descuento activo
const hayDescuentoActivo = computed(() => {
  return tipoDescuento.value !== null
})

// Actualizar porcentaje y guardarlo en contrato
const actualizarPorcentaje = (valor) => {
  porcentajeDescuento.value = valor
  contrato.value.descuento = valor
}

// ==================== CONTROL DE FECHAS DE PAGO ====================
// Computed: Habilitar fechas solo si existe monto inicial
const fechasHabilitadas = computed(() => {
  return contrato.value && contrato.value.monto_inicial > 0
})

// Watch: Asignar fecha actual cuando se ingresa monto inicial por primera vez
watch(
  () => contrato.value?.monto_inicial,
  (nuevoMonto, montoAnterior) => {
    // Solo actuar cuando cambia de 0 o null a un valor > 0
    if (contrato.value && nuevoMonto > 0 && (!montoAnterior || montoAnterior === 0)) {
      const fechaHoy = new Date().toISOString().slice(0, 10)
      
      // Asignar fecha inicial solo si está vacía o es la fecha por defecto
      if (!contrato.value.fecha_inicial || 
          contrato.value.fecha_inicial === '' || 
          contrato.value.fecha_inicial === '1900-01-01') {
        contrato.value.fecha_inicial = fechaHoy
      }
      
      // Asignar fecha final solo si está vacía o es la fecha por defecto
      if (!contrato.value.fecha_final || 
          contrato.value.fecha_final === '' || 
          contrato.value.fecha_final === '1900-01-01') {
        contrato.value.fecha_final = fechaHoy
      }
    }
  }
)

const calcularTotal = () => {
  // El computed ya lo calcula automáticamente
}

const guardarContrato = async () => {
  try {
    // ==================== VALIDACIÓN ANTES DE GUARDAR ====================
    if (formularioRef.value) {
      const { valid } = await formularioRef.value.validate()
      
      if (!valid) {
        mostrarNotificacion('Por favor, complete todos los campos obligatorios marcados con *', 'error')
        // Hacer scroll al primer error
        const firstError = document.querySelector('.v-input--error')
        if (firstError) {
          firstError.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
        return
      }
    }
    
    // Validación adicional programática
    const validacion = validarFormularioCompleto(formulario.value)
    if (!validacion.valido) {
      const mensaje = generarMensajeError(validacion.errores)
      mostrarNotificacion(mensaje, 'error')
      return
    }
    
    guardarActivo.value = true

    // Preparar datos para enviar
    const datos = {
      // Datos del contrato
      productos: formulario.value.productos || '',
      perfil_visitante: formulario.value.perfil_visitante || '',
      observaciones: (contrato.value?.observaciones && contrato.value.observaciones.trim() !== '')
        ? contrato.value.observaciones
        : 'N/A',
      metraje_total: contrato.value.metraje_total,
      modo_precio: modoPrecio.value,
      precio_unit: modoPrecio.value === 'm2' ? precioUnitarioCalculado.value : null,
      precio_total: modoPrecio.value === 'total' ? montoInicial.value : null,
      descuento: porcentajeDescuento.value,
      tipo_desc: contrato.value.tipo_desc || 'Sin Descuento',
      monto_inicial: contrato.value.monto_inicial || 0,
      fecha_inicial: contrato.value.fecha_inicial || '1900-01-01',
      fecha_final: contrato.value.fecha_final || '1900-01-01',
      medio_comunicacion: formulario.value.medio_comunicacion || '0',
      como_entero: formulario.value.como_entero || '0',
      tipo_expositor: formulario.value.tipo_expositor || '0',
      tipo_credenciales: formulario.value.tipo_credenciales,

      // Marcas - Formato compatible con sistema antiguo
      // Guarda: "Marca1;Marca2;Marca3"
      marca_principal: formulario.value.marcas_nacionales.filter(m => m.nombre?.trim()).length,
      mn: formulario.value.marcas_nacionales
        .filter(m => m.nombre?.trim())
        .map(m => m.nombre.trim()),
      
      // Guarda: "Marca1;PaisID1;Marca2;PaisID2"
      pais_principal: formulario.value.marcas_internacionales.filter(m => m.nombre?.trim()).length,
      pp: formulario.value.marcas_internacionales
        .filter(m => m.nombre?.trim())
        .map(m => m.nombre.trim()),
      pais_pp: formulario.value.marcas_internacionales
        .filter(m => m.nombre?.trim())
        .map(m => m.pais || ''),
      
      // Guarda: "Marca1;PaisID1;Marca2;PaisID2"
      marcas_secundarios: formulario.value.marcas_multinacionales.filter(m => m.nombre?.trim()).length,
      mu: formulario.value.marcas_multinacionales
        .filter(m => m.nombre?.trim())
        .map(m => m.nombre.trim()),
      pais_mu: formulario.value.marcas_multinacionales
        .filter(m => m.nombre?.trim())
        .map(m => m.pais || ''),

      // Datos de la empresa
      nombre_empresa: formulario.value.nombre_empresa || '',
      nit: formulario.value.nit || '',
      direccion: formulario.value.direccion || '',
      telefono: formulario.value.telefono || '',
      fax: formulario.value.fax || '',
      email: formulario.value.email || '',
      web: formulario.value.web || '',
      aniversario: formulario.value.aniversario || '1900-01-01',
      nr_escritura: formulario.value.nr_escritura || 'N/A',
      fecha_nr_escritura: formulario.value.fecha_nr_escritura || '1900-01-01',
      matricula: formulario.value.matricula || 'N/A',
      nr_poder: formulario.value.nr_poder || 'N/A',
      nr_notaria: formulario.value.nr_notaria || 'N/A',
      fecha_nr_poder: formulario.value.fecha_nr_poder || '1900-01-01',
      distrito: formulario.value.distrito || 'N/A',
      cluster: formulario.value.categoria || '0',
      pais_id: formulario.value.pais_id || '0',
      ciudad_id: formulario.value.ciudad_id || '0',
      rubro: formulario.value.id_rubro || '0',
      subrubro: formulario.value.id_subrubro || '0',
      otro_rubro: formulario.value.otro_rubro || '',
      actividad_principal: formulario.value.actividad_principal || '0',

      // Representante
      nombre_gerente: formulario.value.nombre_gerente || '',
      ci_gerente: formulario.value.ci_gerente || '',
      exp_ci_gerente: formulario.value.exp_ci_gerente || '',
      fono_gerente: formulario.value.fono_gerente || '',
      cargo_gerente: formulario.value.cargo_gerente || '',

      // Contacto
      nombre_responsable: formulario.value.nombre_contacto || '',
      telefono_responsable: formulario.value.telefono_contacto || '',
      email_representante: formulario.value.email_contacto || '',
    }

    // Agregar timeout de 30 segundos
    const controller = new AbortController()
    const timeoutId = setTimeout(() => controller.abort(), 30000)
    
    const response = await axios.put(
      `/contratos/${contratoId.value}/llenar`, 
      datos,
      { signal: controller.signal }
    )

    clearTimeout(timeoutId)

    if (response.data.success) {
      // Actualizar estado del contrato
      contrato.value = response.data.data.contrato
      empresa.value = response.data.data.empresa
      contratoGuardado.value = true
      mostrarNotificacion('Contrato guardado exitosamente', 'success')
    } else {
      mostrarNotificacion('Error al guardar: ' + (response.data.message || 'Error desconocido'), 'error')
    }
  } catch (err) {
    if (err.name === 'AbortError') {
      mostrarNotificacion('Timeout: El servidor tardó demasiado tiempo en responder', 'error')
    } else if (err.response) {
      mostrarNotificacion('Error ' + err.response.status + ': ' + (err.response.data?.message || err.message), 'error')
    } else if (err.request) {
      mostrarNotificacion('Error: No hubo respuesta del servidor', 'error')
    } else {
      mostrarNotificacion('Error: ' + err.message, 'error')
    }
  } finally {
    guardarActivo.value = false
  }
}

const generarContrato = async () => {
  try {
    generandoContrato.value = true

    const response = await axios.post(`/contratos/${contratoId.value}/generar`)

    if (response.data.success) {
      codigoContratoGenerado.value = response.data.data.codigo_contrato
      
      // Actualizar el contrato local con los nuevos datos
      if (response.data.data.contrato) {
        contrato.value = response.data.data.contrato
      }
      
      // Mostrar modal de confirmación
      modalGenerado.value = true
    } else {
      mostrarNotificacion('Error: ' + (response.data.message || 'No se pudo generar el contrato'), 'error')
    }
  } catch (err) {
    if (err.response) {
      mostrarNotificacion(err.response.data?.message || err.message, 'error')
    } else if (err.request) {
      mostrarNotificacion('Error: No hubo respuesta del servidor', 'error')
    } else {
      mostrarNotificacion('Error: ' + err.message, 'error')
    }
  } finally {
    generandoContrato.value = false
  }
}

const cerrarModalYVolver = () => {
  modalGenerado.value = false
  volver()
}

const volver = () => {
  // Preservar id_feria si existe en query params o en el contrato
  const idFeria = route.query.id_feria || contrato.value?.id_feria
  if (idFeria) {
    router.push({ 
      name: 'contrato-list',
      query: { id_feria: idFeria }
    })
  } else {
    router.push({ name: 'contrato-list' })
  }
}

// ==================== FUNCIONES DE IMPRESIÓN ====================
const imprimirContrato = () => {
  if (!contratoId.value) {
    mostrarNotificacion('No se pudo obtener el ID del contrato', 'error')
    return
  }
  const url = `/contratos/${contratoId.value}/imprimir`
  window.open(url, '_blank')
}

const imprimirAdendum = () => {
  if (!contratoId.value) {
    mostrarNotificacion('No se pudo obtener el ID del contrato', 'error')
    return
  }
  const url = `/imprimir/adendum/${contratoId.value}`
  window.open(url, '_blank')
}

onMounted(() => {
  cargarDatos()
})
</script>

<template>
  <VRow>
    <VCol cols="12">
      <VCard>
        <!-- Header -->
        <VCardTitle class="d-flex align-center">
          <VBtn
            icon="tabler-arrow-left"
            variant="text"
            @click="volver"
          />
          <span class="ml-2">
            {{ origen === 'reserva' ? 'Llenado de Contrato - Reserva' : 'Editar Contrato' }}
          </span>
        </VCardTitle>

        <VCardText>
          <!-- CARGANDO -->
          <div v-if="cargando" class="text-center py-10">
            <VProgressCircular indeterminate color="primary" />
            <div class="mt-4 text-body2">Cargando información...</div>
          </div>

          <!-- ERROR -->
          <VAlert v-else-if="error" type="error" variant="tonal" class="mb-4">
            {{ error }}
          </VAlert>

          <!-- CONTENIDO -->
          <div v-else-if="contrato">
            <!-- ============================================ -->
            <!-- ALERTA DE RESERVA (si origen === 'reserva') -->
            <!-- ============================================ -->
            <VAlert
              v-if="origen === 'reserva' && !mostrarFormulario"
              type="success"
              variant="tonal"
              class="mb-6"
            >
              <div>
                <div class="font-weight-bold mb-3">¡Reserva Confirmada!</div>
                <div class="text-body2 mb-3">
                  La reserva ha sido realizada correctamente.<br>
                  El link para el llenado ha sido generado.
                  <VBtn
                    color="success"
                    variant="tonal"
                    size="small"
                    prepend-icon="tabler-link"
                    class="mt-2"
                    @click="modalLinkLlenado = true"
                  >
                    Ver / Copiar Link
                  </VBtn>
                </div>
                
                <!-- Botones de acción -->
                <VRow class="mt-4 gap-2">
                  <VCol cols="12" sm="auto">
                    <VBtn
                      color="success"
                      @click="abrirFormulario"
                      prepend-icon="tabler-check"
                    >
                      Llenar datos de contrato
                    </VBtn>
                  </VCol>
                  <VCol cols="12" sm="auto">
                    <VBtn
                      color="default"
                      variant="outlined"
                      @click="volver"
                    >
                      Volver al listado
                    </VBtn>
                  </VCol>
                </VRow>
              </div>
            </VAlert>

            <!-- ============================================ -->
            <!-- FORMULARIO (CONDICIONALMENTE VISIBLE) -->
            <!-- ============================================ -->
            <VForm v-if="mostrarFormulario" ref="formularioRef" v-model="isValid" lazy-validation>
              <!-- ========== SECCIÓN 1: DATOS DE LA EMPRESA ========== -->
              <VCard variant="outlined" class="mb-6">
                <VCardText>
                  <div class="text-h6 mb-4 font-weight-bold">
                    1. Datos de la Empresa
                  </div>
                  <VRow>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nombre_empresa"
                        label="Nombre de la Empresa *"
                        variant="outlined"
                        dense
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="5">
                      <VTextField
                        v-model="formulario.direccion"
                        label="Dirección *"
                        variant="outlined"
                        dense
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nit"
                        label="NIT *"
                        variant="outlined"
                        dense
                        :rules="[nitRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.telefono"
                        label="Teléfono *"
                        variant="outlined"
                        dense
                        :rules="[phoneRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.fax"
                        label="Fax"
                        variant="outlined"
                        dense
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.email"
                        label="Email *"
                        type="email"
                        variant="outlined"
                        dense
                        :rules="[emailRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.web"
                        label="Página Web"
                        type="url"
                        variant="outlined"
                        dense
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VAutocomplete
                        v-model="paisSeleccionado"
                        :items="paisesListEmpresa"
                        :loading="buscandoPaises"
                        label="País *"
                        placeholder="Escribe para buscar..."
                        variant="outlined"
                        item-title="label"
                        item-value="label"
                        clearable
                        no-filter
                        :rules="[autocompleteIdRule]"
                        @update:search="onPaisInput"
                        @update:model-value="onPaisSelect"
                      >
                        <template #no-data>
                          <div class="px-4 py-2 text-caption">
                            {{ paisSearchInput && paisSearchInput.length >= 2 ? 'No se encontraron países' : 'Escribe al menos 2 caracteres' }}
                          </div>
                        </template>
                      </VAutocomplete>
                    </VCol>
                    <VCol cols="12" md="3">
                      <VAutocomplete
                        v-model="ciudadSeleccionada"
                        :items="ciudadesList"
                        :loading="buscandoCiudades"
                        :disabled="!formulario.pais_id"
                        label="Ciudad *"
                        placeholder="Selecciona un país primero"
                        variant="outlined"
                        item-title="label"
                        item-value="label"
                        clearable
                        no-filter
                        :rules="[autocompleteIdRule]"
                        @update:search="onCiudadInput"
                        @update:model-value="onCiudadSelect"
                      >
                        <template #no-data>
                          <div class="px-4 py-2 text-caption">
                            {{ formulario.pais_id ? (ciudadSearchInput && ciudadSearchInput.length >= 2 ? 'No se encontraron ciudades' : 'Escribe al menos 2 caracteres') : 'Selecciona un país primero' }}
                          </div>
                        </template>
                      </VAutocomplete>
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.categoria"
                        :items="[
                          { value: '1', title: 'Grande' },
                          { value: '2', title: 'Mediana' },
                          { value: '3', title: 'Pequeña' },
                          { value: '4', title: 'Artesano' }
                        ]"
                        label="Categoria de la Empresa *"
                        placeholder="Seleccione"
                        variant="outlined"
                        clearable
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.aniversario"
                        label="Fecha de Aniversario"
                        type="date"
                        variant="outlined"
                      />
                    </VCol>
                    
                    <!-- Datos de Escritura y Matrícula -->
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nr_escritura"
                        label="Nro. Escritura de Constitución o Documento de personalidad Jurídica"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.fecha_nr_escritura"
                        label="Fecha de Escritura de Constitución o Documento de personalidad Jurídica"
                        type="date"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="formulario.matricula"
                        label="Matricula de Comercio (S/A)"
                        variant="outlined"
                      />
                    </VCol>
                    
                    <!-- Datos de Poder -->
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nr_poder"
                        label="Nro de Poder"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nr_notaria"
                        label="Nro. Notaría"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.fecha_nr_poder"
                        label="Fecha Nro de Poder"
                        type="date"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.distrito"
                        label="Distrito"
                        variant="outlined"
                      />
                    </VCol>
                    
                    <!-- Actividad Principal y Rubros -->
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.actividad_principal"
                        :items="[
                          { value: '0', title: '=== Elegir Actividad ===' },
                          { value: '1', title: 'Industria Manufacturera' },
                          { value: '2', title: 'Comercio' },
                          { value: '3', title: 'Servicios' }
                        ]"
                        label="Actividad Principal *"
                        variant="outlined"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.id_rubro"
                        :items="rubrosList"
                        :loading="cargandoRubros"
                        item-title="title"
                        item-value="value"
                        label="Rubro *"
                        variant="outlined"
                        clearable
                        placeholder="No asignado"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.id_subrubro"
                        :items="subrubrosList"
                        :loading="cargandoSubrubros"
                        item-title="title"
                        item-value="value"
                        label="Sub Rubro Principal *"
                        variant="outlined"
                        clearable
                        placeholder="No asignado"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.otro_rubro"
                        label="Otros Rubros Adicionales *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- ========== SECCIÓN 2: Datos Responsable / Contacto ========== -->
              <VCard variant="outlined" class="mb-6">
                <VCardText>
                  <div class="text-h6 mb-4 font-weight-bold">
                    2. Datos Responsable / Contacto
                  </div>
                  <VRow>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nombre_gerente"
                        label="Nombre del Representante *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.ci_gerente"
                        label="Documento de Identidad del Representante *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.exp_ci_gerente"
                        :items="[
                          { value: 'Cb.', title: 'Cochabamba' },
                          { value: 'Lp.', title: 'La Paz' },
                          { value: 'Sc.', title: 'Santa Cruz' },
                          { value: 'Or.', title: 'Oruro' },
                          { value: 'Tj.', title: 'Tarija' },
                          { value: 'Pt.', title: 'Potosí' },
                          { value: 'Ch.', title: 'Chuquisaca' },
                          { value: 'Bn.', title: 'Beni' },
                          { value: 'Pn.', title: 'Pando' },
                          { value: 'Ex.', title: 'Otro (Extranjero)' }
                        ]"
                        label="Exp. CI *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.fono_gerente"
                        label="Teléfono del Representante *"
                        variant="outlined"
                        :rules="[phoneRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.cargo_gerente"
                        label="Cargo del Representante *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VBtn
                        @click="copiarRepresentante"
                        variant="outlined"
                        size="small"
                        prepend-icon="tabler-copy"
                      >
                        Copiar datos
                      </VBtn>
                    </VCol>
                    <VDivider class="my-4" />
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.nombre_contacto"
                        label="Nombre del Contacto *"
                        variant="outlined"
                        :rules="[requiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.telefono_contacto"
                        label="Teléfono del Contacto *"
                        variant="outlined"
                        :rules="[phoneRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="formulario.email_contacto"
                        label="Correo Electrónico del Contacto"
                        type="email"
                        variant="outlined"
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- ========== SECCIÓN 3: DATOS DE FERIA ========== -->
              <VCard variant="outlined" class="mb-6">
                <VCardText>
                  <div class="text-h6 mb-4 font-weight-bold">
                    3. Datos - Evento
                  </div>
                  <VRow>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.medio_comunicacion"
                        :items="[
                          { value: '', title: '---Seleccionar---' },
                          { value: '1', title: 'Correo Electrónico' },
                          { value: '2', title: 'Teléfono' },
                          { value: '3', title: 'Whatsapp' },
                          { value: '4', title: 'Carta de Invitación' },
                          { value: '5', title: 'Referencias' }
                        ]"
                        label="Por qué medio fué aproximado *"
                        variant="outlined"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.como_entero"
                        :items="[
                          { value: '', title: '---Seleccionar---' },
                          { value: '1', title: 'Por la carta de invitación' },
                          { value: '2', title: 'Por Redes Sociales' },
                          { value: '3', title: 'Por prospección' },
                          { value: '4', title: 'Por referencia' }
                        ]"
                        label="Como se enteró de la feria *"
                        variant="outlined"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.tipo_expositor"
                        :items="[
                          { value: '', title: '---Seleccionar---' },
                          { value: '1', title: 'Expositor nuevo' },
                          { value: '2', title: 'Expositor antiguo' },
                          { value: '3', title: 'Expositor recuperado' }
                        ]"
                        label="Tipo de Expositor *"
                        variant="outlined"
                        :rules="[requiredSelectRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <VSelect
                        v-model="formulario.tipo_credenciales"
                        :items="[
                          { value: '', title: '---Seleccionar---' },
                          { value: '0', title: 'Física' },
                          { value: '1', title: 'Digital' }
                        ]"
                        label="Tipo de Credencial *"
                        variant="outlined"
                        :rules="[requiredCredentialRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <VTextarea
                        v-model="formulario.productos"
                        label="Productos/Servicios que ofrecerá su empresa en la Feria *"
                        variant="outlined"
                        rows="5"
                        :rules="[textareaRequiredRule]"
                      />
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="d-flex align-center justify-space-between mb-2">
                        <label class="text-body-2 font-weight-medium">Marcas Nacionales</label>
                        <VBtn
                          @click="agregarMarcaNacional"
                          size="small"
                          icon="tabler-plus"
                          variant="tonal"
                          color="success"
                        />
                      </div>
                      <div v-for="(marca, index) in formulario.marcas_nacionales" :key="index" class="mb-2">
                        <div class="d-flex gap-2">
                          <VTextField
                            v-model="marca.nombre"
                            label="Nombre de Marca"
                            variant="outlined"
                            dense
                            class="flex-grow-1"
                          />
                          <VBtn
                            @click="eliminarMarcaNacional(index)"
                            size="small"
                            icon="tabler-trash"
                            color="error"
                            variant="text"
                          />
                        </div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="d-flex align-center justify-space-between mb-2">
                        <label class="text-body-2 font-weight-medium">Marcas Internacionales</label>
                        <VBtn
                          @click="agregarMarcaInternacional"
                          size="small"
                          icon="tabler-plus"
                          variant="tonal"
                          color="success"
                        />
                      </div>
                      <div v-for="(marca, index) in formulario.marcas_internacionales" :key="index" class="mb-2">
                        <div class="d-flex gap-2">
                          <VTextField
                            v-model="marca.nombre"
                            label="Nombre de Marca"
                            variant="outlined"
                            dense
                            style="width: 60%"
                          />
                          <VSelect
                            v-model="marca.pais"
                            :items="paisesListMarcas"
                            item-title="label"
                            item-value="id"
                            label="País"
                            variant="outlined"
                            dense
                            style="width: 35%"
                          />
                          <VBtn
                            @click="eliminarMarcaInternacional(index)"
                            size="small"
                            icon="tabler-trash"
                            color="error"
                            variant="text"
                          />
                        </div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="6">
                      <div class="d-flex align-center justify-space-between mb-2">
                        <label class="text-body-2 font-weight-medium">Marcas Multinacionales</label>
                        <VBtn
                          @click="agregarMarcaMultinacional"
                          size="small"
                          icon="tabler-plus"
                          variant="tonal"
                          color="success"
                        />
                      </div>
                      <div v-for="(marca, index) in formulario.marcas_multinacionales" :key="index" class="mb-2">
                        <div class="d-flex gap-2">
                          <VTextField
                            v-model="marca.nombre"
                            label="Nombre de Marca"
                            variant="outlined"
                            dense
                            style="width: 60%"
                          />
                          <VSelect
                            v-model="marca.pais"
                            :items="paisesListMarcas"
                            item-title="label"
                            item-value="id"
                            label="País"
                            variant="outlined"
                            dense
                            style="width: 35%"
                          />
                          <VBtn
                            @click="eliminarMarcaMultinacional(index)"
                            size="small"
                            icon="tabler-trash"
                            color="error"
                            variant="text"
                          />
                        </div>
                      </div>
                    </VCol>
                    <VCol cols="12" md="12">
                      <VTextarea
                        v-model="formulario.perfil_visitante"
                        label="Perfil del visitante que interesa a su empresa en la Feria"
                        variant="outlined"
                        rows="5"
                      />
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- ========== SECCIÓN 4: INFORMACION DE CONTRATO ========== -->
              <VCard v-if="contrato" variant="outlined" class="mb-6">
                <VCardText>
                  <div class="text-h6 mb-4 font-weight-bold">
                    4. Información De Contrato
                  </div>

                  <VRow>
                    <!-- Observaciones -->
                    <VCol cols="12" md="12">
                      <VTextarea
                        v-model="contrato.observaciones"
                        label="Observaciones del Contrato"
                        rows="3"
                        variant="outlined"
                      />
                    </VCol>
                    <!-- Superficie Total (readonly) -->
                    <VCol cols="12" md="2">
                      <VTextField
                        :model-value="contrato && contrato.metraje_total ? Number(contrato.metraje_total).toFixed(2) : '0.00'"
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

                    <!-- Modo y valores de precio -->
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
                        :model-value="modoPrecio === 'm2' ? contrato.precio_unit : precioUnitarioCalculado"
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
                        type="text"
                        inputmode="decimal"
                        :readonly="modoPrecio === 'm2'"
                        class="mt-3"
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
                          @update:model-value="(val) => { contrato.tipo_desc = val }"
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
                        :model-value="porcentajeDescuento"
                        label="Porcentaje de Descuento %"
                        type="number"
                        step="0.0001"
                        min="0"
                        max="100"
                        variant="outlined"
                        density="comfortable"
                        placeholder="0"
                        @update:model-value="actualizarPorcentaje"
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
                            <div><strong>Precio Total:</strong> Bs. {{ montoInicial.toFixed(2) }}</div>
                            <div v-if="porcentajeDescuento > 0">
                              <strong>Descuento ({{ porcentajeDescuento }}%):</strong> Bs. {{ importeDescuento.toFixed(2) }}
                            </div>
                          </div>
                        </VAlert>

                        <!-- Total con descuento -->
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

                  <!-- Divider -->
                  <VDivider class="my-4" />

                  <!-- Campos Adicionales del Sistema Antiguo -->
                  <VRow>
                    <!-- Monto Inicial -->
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model.number="contrato.monto_inicial"
                        label="Monto Inicial (Bs.)"
                        type="number"
                        step="0.01"
                        min="0"
                        variant="outlined"
                        density="comfortable"
                        placeholder="0.00"
                      >
                        <template #prepend-inner>
                          <VIcon icon="tabler-coin" />
                        </template>
                      </VTextField>
                    </VCol>

                    <!-- Fecha de Primer Pago -->
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="contrato.fecha_inicial"
                        label="Fecha de Primer Pago"
                        type="date"
                        variant="outlined"
                        density="comfortable"
                        :disabled="!fechasHabilitadas"
                      >
                        <template #prepend-inner>
                          <VIcon icon="tabler-calendar" />
                        </template>
                      </VTextField>
                    </VCol>

                    <!-- Fecha de Segundo Pago -->
                    <VCol cols="12" md="3">
                      <VTextField
                        v-model="contrato.fecha_final"
                        label="Fecha de Segundo Pago"
                        type="date"
                        variant="outlined"
                        density="comfortable"
                        :disabled="!fechasHabilitadas"
                      >
                        <template #prepend-inner>
                          <VIcon icon="tabler-calendar" />
                        </template>
                      </VTextField>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>

              <!-- ========== BOTONES FINALES ========== -->
              <VRow class="mb-6">
                <VCol cols="12" md="3">
                  <VBtn
                    @click="volver"
                    variant="outlined"
                    block
                  >
                    Cancelar
                  </VBtn>
                </VCol>
                <VCol cols="12" md="3">
                  <VBtn
                    variant="tonal"
                    color="info"
                    block
                    prepend-icon="tabler-link"
                    @click="modalLinkLlenado = true"
                  >
                    Ver Link
                  </VBtn>
                </VCol>
                <VCol cols="12" md="3">
                  <VBtn
                    @click="guardarContrato"
                    color="success"
                    block
                    prepend-icon="tabler-check"
                  >
                    Guardar Contrato
                  </VBtn>
                </VCol>
                <VCol v-if="contrato && contrato.codigo_contrato === 0" cols="12" md="3">
                  <VBtn
                    @click="generarContrato"
                    color="primary"
                    block
                    prepend-icon="tabler-file-text"
                    :disabled="!contratoGuardado || generandoContrato"
                    :loading="generandoContrato"
                  >
                    Generar Contrato
                  </VBtn>
                </VCol>
                <VCol v-else-if="contrato && contrato.codigo_contrato > 0" cols="12" md="3">
                  <VAlert
                    type="success"
                    variant="tonal"
                    class="ma-0"
                  >
                    <div class="d-flex align-center gap-2">
                      <VIcon icon="tabler-circle-check" />
                      <span>Código asignado: <strong>{{ contrato.codigo_contrato }}</strong></span>
                    </div>
                  </VAlert>
                </VCol>
              </VRow>
            </VForm>
          </div>

          <!-- SIN DATOS -->
          <VAlert
            v-else
            type="warning"
            variant="tonal"
          >
            No se encontraron datos de contrato
          </VAlert>
        </VCardText>
      </VCard>
    </VCol>

    <!-- ========== MODAL LINK LLENADO PÚBLICO ========== -->
    <VDialog
      v-model="modalLinkLlenado"
      max-width="520"
    >
      <VCard v-if="contrato">
        <VCardTitle class="d-flex align-center pa-5 pb-3">
          <VIcon icon="tabler-link" size="24" class="me-2" />
          Link de Llenado Público
        </VCardTitle>

        <VCardText class="pa-5 pt-2">
          <div class="text-body-2 text-medium-emphasis mb-1">
            <strong>Empresa:</strong> {{ empresa?.nombre_empresa ?? contrato.nombre_empresa ?? '—' }}
          </div>
          <div class="text-body-2 text-medium-emphasis mb-4">
            Comparte este link con la empresa para que complete su formulario sin necesidad de login:
          </div>

          <div class="d-flex align-center gap-2">
            <VTextField
              :model-value="`${baseUrl}/formulario/${contrato.id_contrato}/${contrato.clave}`"
              readonly
              density="compact"
              variant="outlined"
              hide-details
              class="flex-grow-1"
              style="font-size: 0.85rem;"
            />
            <VBtn
              icon
              variant="tonal"
              color="default"
              size="small"
              @click="copiarLink"
            >
              <VIcon icon="tabler-copy" />
            </VBtn>
          </div>
        </VCardText>

        <VCardActions class="pa-5 pt-2 justify-end gap-2">
          <VBtn
            variant="text"
            color="secondary"
            @click="modalLinkLlenado = false"
          >
            Cerrar
          </VBtn>
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-external-link"
            :href="`${baseUrl}/formulario/${contrato.id_contrato}/${contrato.clave}`"
            target="_blank"
            @click="modalLinkLlenado = false"
          >
            Abrir en nueva pestaña
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ========== MODAL DE CONFIRMACIÓN - CONTRATO GENERADO ========== -->
    <VDialog
      v-model="modalGenerado"
      max-width="600"
      persistent
    >
      <VCard>
        <VCardTitle class="d-flex align-center bg-success pa-4">
          <VIcon icon="tabler-circle-check" size="32" class="me-3" />
          <span class="text-h5">Contrato Generado Correctamente</span>
        </VCardTitle>

        <VCardText class="pa-6">
          <VAlert
            type="success"
            variant="tonal"
            class="mb-4"
          >
            <div class="d-flex flex-column gap-2">
              <div class="text-h6">
                ✅ El contrato ha sido generado exitosamente
              </div>
              <div class="text-body-1">
                <strong>Código de Contrato:</strong>
                <VChip
                  color="success"
                  class="ml-2"
                  size="large"
                >
                  {{ codigoContratoGenerado }}
                </VChip>
              </div>
            </div>
          </VAlert>

          <div class="text-body-2 text-medium-emphasis">
            El contrato ha sido registrado correctamente en el sistema.
            Ahora puede proceder a imprimir los documentos o volver al listado.
          </div>
        </VCardText>

        <VCardActions class="pa-4">
          <VRow class="w-100">
            <VCol cols="12" md="4">
              <VBtn
                variant="outlined"
                color="secondary"
                block
                @click="imprimirContrato"
              >
                Imprimir Contrato
              </VBtn>
            </VCol>
            <VCol cols="12" md="4">
              <VBtn
                variant="outlined"
                color="secondary"
                block
                @click="imprimirAdendum"
              >
                Imprimir Adendum #2
              </VBtn>
            </VCol>
            <VCol cols="12" md="4">
              <VBtn
                color="primary"
                block
                prepend-icon="tabler-arrow-back"
                @click="cerrarModalYVolver"
              >
                Volver al Listado
              </VBtn>
            </VCol>
          </VRow>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- ========== SNACKBAR NOTIFICACIONES ========== -->
    <VSnackbar
      v-model="snackbar"
      :color="snackbarColor"
      :timeout="4000"
      location="top right"
      vertical
    >
      <div class="d-flex align-center gap-3">
        <VIcon :icon="snackbarIcon" size="24" />
        <span class="text-body-1">{{ snackbarMessage }}</span>
      </div>
      
      <template #actions>
        <VBtn
          variant="text"
          icon="tabler-x"
          size="small"
          @click="snackbar = false"
        />
      </template>
    </VSnackbar>
  </VRow>
</template>
