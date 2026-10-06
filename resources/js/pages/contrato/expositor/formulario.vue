<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'
import { useContratoValidation } from '@/composables/useContratoValidation'
import logoFexco from '@images/fexco.png'

const route = useRoute()

// ==================== VALIDACIONES ====================
const {
  requiredRule,
  requiredSelectRule,
  requiredCredentialRule,
  emailOptionalRule,
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
const idContrato = ref(route.params.id_contrato)
const clave = ref(route.params.clave)
const contrato = ref(null)
const empresa = ref(null)
const requiereTipoCredencial = computed(() => Number(contrato.value?.id_feria) >= 21)
const cargando = ref(true)
const error = ref(null)
const guardandoInfo = ref(false)
const formularioGuardado = ref(false)
const contratoYaGenerado = ref(false)
const codigoContrato = ref(null)

// ==================== SNACKBAR NOTIFICACIONES ====================
const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

const mostrarNotificacion = (mensaje, tipo = 'success') => {
  snackbarMessage.value = mensaje
  snackbarColor.value = tipo
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
  
  // País y Ciudad
  pais_id: null,
  ciudad_id: null,
  
  // Contacto
  nombre_responsable: '',
  ci_responsable: '',
  exp_ci_responsable: '',
  telefono_responsable: '',
  
  nombre_gerente: '',
  ci_gerente: '',
  exp_ci_gerente: '',
  fono_gerente: '',
  cargo_gerente: '',
  
  // Contacto adicional
  nombre_contacto: '',
  telefono_contacto: '',
  email_contacto: '',
  
  // Rubros y Categoría
  id_rubro: '0',
  id_subrubro: '0',
  otro_rubro: '',
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
  actividad_principal: '0',
  
  // Información del Evento
  medio_comunicacion: '',
  como_entero: '',
  tipo_expositor: '',
  tipo_credenciales: '',
  productos: '',
  perfil_visitante: '',
  
  // Marcas
  marcas_nacionales: [{ nombre: '' }],
  marcas_internacionales: [{ nombre: '', pais: '' }],
  marcas_multinacionales: [{ nombre: '', pais: '' }],
})

// ==================== MÉTODOS ====================

const cargarDatos = async () => {
  if (!idContrato.value || !clave.value) {
    error.value = 'No se especificó contrato o clave'
    cargando.value = false
    return
  }

  try {
    cargando.value = true
    
    // Obtener contrato con validación de clave
    const responseContrato = await axios.get(`/api/formulario/contrato/${idContrato.value}/${clave.value}`)
    
    if (responseContrato.data.success) {
      // Verificar si el contrato ya tiene código generado
      contratoYaGenerado.value = responseContrato.data.contrato_ya_generado || false
      codigoContrato.value = responseContrato.data.codigo_contrato || null
      
      // Si el contrato ya fue generado, no cargar más datos
      if (contratoYaGenerado.value) {
        cargando.value = false
        return
      }
      
      contrato.value = responseContrato.data.data
      empresa.value = contrato.value.empresa
      
      // Popular formulario con datos existentes
      if (empresa.value) {
        formulario.value.nombre_empresa = empresa.value.nombre_empresa || ''
        formulario.value.nit = empresa.value.nit || ''
        formulario.value.direccion = empresa.value.direccion || ''
        formulario.value.telefono = empresa.value.telefono || ''
        formulario.value.fax = empresa.value.fax || ''
        formulario.value.email = empresa.value.email || ''
        formulario.value.web = empresa.value.web || ''
        
        formulario.value.pais_id = empresa.value.pais || null
        formulario.value.ciudad_id = empresa.value.ciudad || null
        
        formulario.value.nombre_responsable = empresa.value.nombre_responsable || ''
        formulario.value.ci_responsable = empresa.value.ci_responsable || ''
        formulario.value.exp_ci_responsable = empresa.value.exp_ci_responsable || ''
        
        formulario.value.nombre_gerente = empresa.value.nombre_gerente || ''
        formulario.value.ci_gerente = empresa.value.ci_gerente || ''
        formulario.value.exp_ci_gerente = empresa.value.exp_ci_gerente || ''
        formulario.value.fono_gerente = empresa.value.fono_gerente || ''
        formulario.value.cargo_gerente = empresa.value.cargo_gerente || ''
        
        formulario.value.nombre_contacto = empresa.value.nombre_responsable || ''
        formulario.value.telefono_contacto = empresa.value.telefono_responsable || ''
        formulario.value.email_contacto = empresa.value.email_representante || ''
        
        formulario.value.id_rubro = empresa.value.rubro ? String(empresa.value.rubro) : '0'
        formulario.value.id_subrubro = empresa.value.subrubro ? String(empresa.value.subrubro) : '0'
        formulario.value.otro_rubro = empresa.value.otro_rubro || ''
        formulario.value.categoria = empresa.value.cluster ? String(empresa.value.cluster) : ''
        formulario.value.aniversario = empresa.value.aniversario || ''
        
        formulario.value.nr_escritura = empresa.value.nr_escritura || ''
        formulario.value.fecha_nr_escritura = empresa.value.fecha_nr_escritura || ''
        formulario.value.matricula = empresa.value.matricula || ''
        formulario.value.nr_poder = empresa.value.nr_poder || ''
        formulario.value.nr_notaria = empresa.value.nr_notario || ''
        formulario.value.fecha_nr_poder = empresa.value.fecha_nr_poder || ''
        formulario.value.distrito = empresa.value.distrito || ''
        formulario.value.actividad_principal = empresa.value.id_tipo_representante 
          ? String(empresa.value.id_tipo_representante).split('00')[0] 
          : '0'
      }
      
      // Popular contrato
      if (contrato.value) {
        formulario.value.perfil_visitante = contrato.value.perfil_visitante || ''
        formulario.value.medio_comunicacion = Number(contrato.value.medio_comunicacion) > 0 ? String(contrato.value.medio_comunicacion) : ''
        formulario.value.como_entero = Number(contrato.value.como_entero) > 0 ? String(contrato.value.como_entero) : ''
        formulario.value.tipo_expositor = Number(contrato.value.tipo_expositor) > 0 ? String(contrato.value.tipo_expositor) : ''
        formulario.value.tipo_credenciales = !requiereTipoCredencial.value
          ? '0'
          : contrato.value.tipo_credenciales !== null && contrato.value.tipo_credenciales !== undefined
            ? String(contrato.value.tipo_credenciales)
            : ''
        formulario.value.productos = contrato.value.productos || ''
        
        // Marcas nacionales
        if (contrato.value.marca_principal) {
          const marcasNacionales = contrato.value.marca_principal.split(';').filter(m => m.trim())
          formulario.value.marcas_nacionales = marcasNacionales.map(m => ({ nombre: m.trim() }))
        }
        
        // Marcas internacionales
        if (contrato.value.pais_principal) {
          const pares = contrato.value.pais_principal.split(';').filter(p => p.trim())
          const marcasInt = []
          for (let i = 0; i < pares.length; i += 2) {
            if (pares[i]) {
              const paisIdONombre = pares[i + 1] ? pares[i + 1].trim() : ''
              const paisValue = /^\d+$/.test(paisIdONombre) ? parseInt(paisIdONombre) : paisIdONombre
              marcasInt.push({
                nombre: pares[i].trim(),
                pais: paisValue
              })
            }
          }
          formulario.value.marcas_internacionales = marcasInt.length > 0 ? marcasInt : [{ nombre: '', pais: '' }]
        }
        
        // Marcas multinacionales
        if (contrato.value.marcas_secundarios) {
          const pares = contrato.value.marcas_secundarios.split(';').filter(p => p.trim())
          const marcasMulti = []
          for (let i = 0; i < pares.length; i += 2) {
            if (pares[i]) {
              const paisIdONombre = pares[i + 1] ? pares[i + 1].trim() : ''
              const paisValue = /^\d+$/.test(paisIdONombre) ? parseInt(paisIdONombre) : paisIdONombre
              marcasMulti.push({
                nombre: pares[i].trim(),
                pais: paisValue
              })
            }
          }
          formulario.value.marcas_multinacionales = marcasMulti.length > 0 ? marcasMulti : [{ nombre: '', pais: '' }]
        }
      }
      
      // Cargar catálogos
      await Promise.all([
        cargarPaises(),
        cargarTodosPaises(),
        cargarRubros(),
        cargarSubrubros()
      ])
      await cargarNombresPaisYCiudad()
    } else {
      error.value = responseContrato.data.message || 'No se pudo cargar el contrato'
    }
  } catch (err) {
    error.value = 'Error al cargar los datos: ' + (err.message || 'Error desconocido')
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

const cargarPaises = async () => {
  try {
    const response = await axios.get('/api/autocomplete/paises')
    paisesListEmpresa.value = response.data || []
  } catch (err) {
    // Error silencioso - no afecta funcionalidad
  }
}

const cargarTodosPaises = async () => {
  try {
    const response = await axios.get('/api/paises')
    
    if (response.data && response.data.length > 0) {
      paisesListMarcas.value = response.data.map(pais => ({
        id: parseInt(pais.id) || pais.id,
        label: pais.nombre_pais || pais.nombre || pais.label,
        value: pais.nombre_pais || pais.nombre || pais.label
      }))
    } else {
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
      ]
    }
  } catch (err) {
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

const cargarCiudades = async (paisId) => {
  if (!paisId) {
    ciudadesList.value = []
    return
  }

  try {
    buscandoCiudades.value = true
    const response = await axios.get(`/api/ciudades/${paisId}`)
    ciudadesList.value = response.data.map(c => ({
      id: c.id,
      value: c.nombre,
      label: c.nombre
    })) || []
  } catch (err) {
    // Error silencioso - no afecta funcionalidad
  } finally {
    buscandoCiudades.value = false
  }
}

const cargarNombresPaisYCiudad = async () => {
  const paisId = formulario.value.pais_id
  const ciudadId = formulario.value.ciudad_id

  if (paisId) {
    try {
      const resPais = await axios.get(`/api/paises/${paisId}/nombre`)
      if (resPais.data.nombre) {
        paisSeleccionado.value = resPais.data.nombre
      }
    } catch (err) {
      // Error silencioso
    }
    
    await cargarCiudades(paisId)

    if (ciudadId) {
      try {
        const resCiudad = await axios.get(`/api/ciudades/${ciudadId}/nombre`)
        if (resCiudad.data.nombre) {
          ciudadSeleccionada.value = resCiudad.data.nombre
        }
      } catch (err) {
        // Error silencioso
      }
    }
  }
}

const cargarRubros = async () => {
  try {
    cargandoRubros.value = true
    const response = await axios.get('/api/rubros')
    const mapped = (response.data || []).map(r => ({
      value: r.id_rubro ? String(r.id_rubro) : '0',
      title: r.nombre_rubro || ''
    }))
    rubrosList.value = [{ value: '0', title: 'No asignado' }, ...mapped]
  } catch (err) {
    rubrosList.value = [{ value: '0', title: 'No asignado' }]
  } finally {
    cargandoRubros.value = false
  }
}

const cargarSubrubros = async () => {
  try {
    cargandoSubrubros.value = true
    const response = await axios.get('/api/subrubros')
    const mapped = (response.data || []).map(s => ({
      value: s.id_subrubro ? String(s.id_subrubro) : '0',
      title: s.nombre_subrubro || ''
    }))
    subrubrosList.value = [{ value: '0', title: 'No asignado' }, ...mapped]
  } catch (err) {
    subrubrosList.value = [{ value: '0', title: 'No asignado' }]
  } finally {
    cargandoSubrubros.value = false
  }
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
    formulario.value.ciudad_id = null
    ciudadSeleccionada.value = null
    ciudadesList.value = []
  }
}

const onCiudadSelect = (nombreCiudad) => {
  if (nombreCiudad) {
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

const guardarInformacion = async () => {
  if (guardandoInfo.value) return

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
    
    guardandoInfo.value = true

    // Preparar los datos a enviar
    const datos = {
      ...formulario.value,
      email: formulario.value.email.trim(),
      email_contacto: formulario.value.email_contacto.trim(),
      tipo_credenciales: requiereTipoCredencial.value ? formulario.value.tipo_credenciales : '0',
      // El backend conserva los nombres del formulario histórico.
      cluster: formulario.value.categoria,
      rubro: formulario.value.id_rubro,
      subrubro: formulario.value.id_subrubro,
      nombre_responsable: formulario.value.nombre_contacto,
      telefono_responsable: formulario.value.telefono_contacto,
      email_representante: formulario.value.email_contacto,
      marca_principal: formulario.value.marcas_nacionales.filter(marca => marca.nombre?.trim()).length,
      mn: formulario.value.marcas_nacionales
        .filter(marca => marca.nombre?.trim())
        .map(marca => marca.nombre.trim()),
      pais_principal: formulario.value.marcas_internacionales.filter(marca => marca.nombre?.trim()).length,
      pp: formulario.value.marcas_internacionales
        .filter(marca => marca.nombre?.trim())
        .map(marca => marca.nombre.trim()),
      pais_pp: formulario.value.marcas_internacionales
        .filter(marca => marca.nombre?.trim())
        .map(marca => marca.pais || ''),
      marcas_secundarios: formulario.value.marcas_multinacionales.filter(marca => marca.nombre?.trim()).length,
      mu: formulario.value.marcas_multinacionales
        .filter(marca => marca.nombre?.trim())
        .map(marca => marca.nombre.trim()),
      pais_mu: formulario.value.marcas_multinacionales
        .filter(marca => marca.nombre?.trim())
        .map(marca => marca.pais || ''),
    }

    // Enviar al backend
    const response = await axios.post(
      `/api/formulario/guardar/${idContrato.value}/${clave.value}`,
      datos
    )

    if (response.data.success) {
      // Guardar datos del éxito
      formularioGuardado.value = true
      
      // Mostrar notificación
      mostrarNotificacion('¡Formulario guardado exitosamente!', 'success')
    } else {
      mostrarNotificacion(response.data.message || 'Error al guardar el formulario', 'error')
    }
  } catch (err) {
    console.error('Error al guardar:', err)
    
    const mensaje = err.response?.data?.message || 
                    err.message || 
                    'Error desconocido al guardar'
    
    mostrarNotificacion(mensaje, 'error')
  } finally {
    guardandoInfo.value = false
  }
}

onMounted(() => {
  cargarDatos()
})
</script>

<template>
  <VContainer>
    <VRow class="justify-center">
      <VCol cols="12" lg="12">
        <!-- CARGANDO -->
        <VCard v-if="cargando" variant="outlined" class="pa-8">
          <VProgressLinear
            indeterminate
            color="primary"
            class="mb-4"
          />
          <div class="text-center text-body-2 text-medium-emphasis">
            Cargando formulario...
          </div>
        </VCard>

        <!-- ERROR -->
        <VAlert
          v-else-if="error"
          type="error"
          variant="tonal"
          class="mb-4"
          closable
          @input="error = null"
        >
          {{ error }}
        </VAlert>

        <!-- CONTRATO YA GENERADO -->
        <div v-if="contratoYaGenerado" class="d-flex justify-center align-center" style="min-height: 400px;">
          <VCard class="text-center pa-12" style="max-width: 550px;">
            <!-- Logo FEXCO -->
            <div class="mb-8">
              <img 
                :src="logoFexco" 
                alt="FEXCO Logo" 
                style="height: 80px; object-fit: contain;"
              />
            </div>
            
            <!-- Icono de advertencia -->
            <VIcon icon="tabler-alert-circle" size="80" color="warning" class="mb-6 d-block mx-auto" />
            
            <!-- Contenido -->
            <h2 class="text-h4 font-weight-bold mb-4">Contrato ya generado</h2>
            <p class="text-body2 text-grey-darken-1 mb-4">
              Este contrato ya ha sido procesado y generado.
            </p>
            <p class="text-body2 text-grey-darken-1 mb-4">
              Si necesita realizar alguna modificación o tiene alguna consulta,
              por favor comuníquese con el área comercial de FEXCO.
            </p>
            <VBtn
              color="success"
              size="large"
              prepend-icon="tabler-brand-whatsapp"
              href="https://wa.me/59171420091"
              target="_blank"
            >
              Contactar por WhatsApp
            </VBtn>
          </VCard>
        </div>

        <!-- FORMULARIO -->
        <div v-else-if="!formularioGuardado">
          <VCard class="mb-6">
            <VCardText>
              <h3 class="text-h5 mb-6">Formulario de Llenado - Información de la Empresa</h3>

              <VForm ref="formularioRef" v-model="isValid" validate-on="submit">
                <!-- ========== SECCIÓN 1: INFORMACIÓN DE EMPRESA ========== -->
                <VCard variant="outlined" class="mb-6">
                  <VCardText>
                    <div class="text-h6 mb-4 font-weight-bold">
                      1. Información de Empresa
                    </div>
                    <VRow>
                      <VCol cols="12" md="5">
                        <VTextField
                          v-model="formulario.nombre_empresa"
                          label="Nombre de Empresa *"
                          variant="outlined"
                          :rules="[requiredRule]"
                        />
                      </VCol>
                      <VCol cols="12" md="4">
                        <VTextField
                          v-model="formulario.direccion"
                          label="Dirección *"
                          variant="outlined"
                          :rules="[requiredRule]"
                        />
                      </VCol>
                      <VCol cols="12" md="3">
                        <VTextField
                          v-model="formulario.nit"
                          label="N.I.T. *"
                          variant="outlined"
                          :rules="[nitRule]"
                        />
                      </VCol>
                      <VCol cols="12" md="3">
                        <VTextField
                          v-model="formulario.telefono"
                          label="Teléfono *"
                          variant="outlined"
                          :rules="[phoneRule]"
                        />
                      </VCol>
                      <VCol cols="12" md="3">
                        <VTextField
                          v-model="formulario.fax"
                          label="Fax"
                          variant="outlined"
                        />
                      </VCol>
                      <VCol cols="12" md="3">
                        <VTextField
                          v-model="formulario.email"
                          label="Email"
                          type="email"
                          variant="outlined"
                          :rules="[emailOptionalRule]"
                        />
                      </VCol>
                      <VCol cols="12" md="3">
                        <VTextField
                          v-model="formulario.web"
                          label="Sitio Web"
                          variant="outlined"
                        />
                      </VCol>                    
                      <VCol cols="12" md="3">
                        <VAutocomplete
                          v-model="paisSeleccionado"
                          :items="paisesListEmpresa"
                          item-title="label"
                          item-value="label"
                          label="País *"
                          placeholder="Escribe para buscar..."
                          variant="outlined"
                          clearable
                          no-filter
                          :loading="buscandoPaises"
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
                          item-title="label"
                          item-value="label"
                          label="Ciudad *"
                          variant="outlined"
                          clearable
                          no-filter
                          :loading="buscandoCiudades"
                          :disabled="!formulario.pais_id"
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
                    <VCol cols="12" md="4">
                      <VTextField
                        v-model="formulario.nr_escritura"
                        label="Nro. Escritura de Constitución o Documento de personalidad Jurídica"
                        variant="outlined"
                      />
                    </VCol>
                    <VCol cols="12" md="4">
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

              <!-- ========== SECCIÓN 2: DATOS DE CONTACTO ========== -->
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
                        :rules="[emailOptionalRule]"
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
                    <VCol v-if="requiereTipoCredencial" cols="12" md="3">
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
              </VForm>

              <!-- ========== BOTÓN GUARDAR ========== -->
              <VRow>
                <VCol cols="12" class="text-right">
                  <VBtn
                    color="primary"
                    size="large"
                    prepend-icon="tabler-check"
                    @click="guardarInformacion"
                    :loading="guardandoInfo"
                    :disabled="guardandoInfo"
                  >
                    Guardar Información
                  </VBtn>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </div>

        <!-- MENSAJE DE ÉXITO -->
        <div v-else class="d-flex justify-center align-center" style="min-height: 400px;">
          <VCard class="text-center pa-12" style="max-width: 550px;">
            <!-- Logo FEXCO -->
            <div class="mb-8">
              <img 
                :src="logoFexco" 
                alt="FEXCO Logo" 
                style="height: 50px; object-fit: contain;"
              />
            </div>
            
            <!-- Icono de éxito -->
            <VIcon icon="tabler-check" size="80" color="success" class="mb-6 d-block mx-auto" />
            
            <!-- Contenido -->
            <h2 class="text-h4 font-weight-bold mb-4">Formulario enviado con éxito</h2>
            <p class="text-body1 font-weight-medium mb-6">{{ formulario.nombre_empresa || 'Su empresa' }}</p>
            <p class="text-body2 text-grey-darken-1 mb-2">
              Muchas gracias por la información brindada.
            </p>
            <p class="text-body2 text-grey-darken-1">
              Los datos proporcionados serán utilizados para la elaboración del contrato y la facturación correspondiente.
            </p>
          </VCard>
        </div>

        <!-- ========== SNACKBAR NOTIFICACIONES ========== -->
        <VSnackbar
          v-model="snackbar"
          :color="snackbarColor"
          :timeout="4000"
          location="top right"
        >
          {{ snackbarMessage }}
        </VSnackbar>
      </VCol>
    </VRow>
  </VContainer>
</template>

<route lang="yaml">
meta:
  public: true
</route>
