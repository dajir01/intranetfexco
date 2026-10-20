/**
 * Composable de validaciones para formularios de contratos
 * Reusable entre el formulario de llenado interno y el formulario público del expositor
 */
export function useContratoValidation() {
  // ==================== REGLAS BÁSICAS ====================
  
  const requiredRule = v => {
    if (!v || String(v).trim() === '') return 'Este campo es obligatorio'
    return true
  }
  
  const requiredSelectRule = v => (v !== null && v !== undefined && v !== '' && v !== '0') || 'Debe seleccionar una opción'

  const requiredCredentialRule = v => (v !== null && v !== undefined && v !== '') || 'Debe seleccionar una opción'
  
  const emailOptionalRule = v => {
    if (!v || String(v).trim() === '') return true
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return pattern.test(String(v).trim()) || 'Debe ingresar un email válido'
  }
  
  const phoneRule = v => {
    if (!v || String(v).trim() === '') return 'El teléfono es obligatorio'
    const cleaned = String(v).replace(/\D/g, '')
    return cleaned.length >= 7 || 'El teléfono debe tener al menos 7 dígitos'
  }
  
  const nitRule = v => {
    if (!v || String(v).trim() === '') return 'El NIT es obligatorio'
    return true
  }
  
  const autocompleteIdRule = v => {
    return (v !== null && v !== undefined && v !== '') || 'Debe seleccionar una opción válida'
  }
  
  const textareaRequiredRule = v => {
    if (!v || String(v).trim() === '') return 'Este campo es obligatorio'
    return true
  }

  // ==================== VALIDACIONES COMPUESTAS ====================
  
  /**
   * Validar todos los campos obligatorios de la Sección 1: Datos de la Empresa
   */
  const validarSeccion1 = (formulario) => {
    const errores = []
    
    if (!formulario.nombre_empresa || String(formulario.nombre_empresa).trim() === '') errores.push('Nombre de la Empresa')
    if (!formulario.direccion || String(formulario.direccion).trim() === '') errores.push('Dirección')
    if (!formulario.nit || String(formulario.nit).trim() === '') errores.push('NIT')
    if (!formulario.telefono || String(formulario.telefono).trim() === '') errores.push('Teléfono')
    if (!formulario.pais_id) errores.push('País')
    if (!formulario.ciudad_id) errores.push('Ciudad')
    if (!formulario.categoria || formulario.categoria === '0') errores.push('Categoría de la Empresa')
    if (!formulario.actividad_principal || formulario.actividad_principal === '0') errores.push('Actividad Principal')
    if (!formulario.id_rubro || formulario.id_rubro === '0' || formulario.id_rubro === 'No Asignado') errores.push('Rubro')
    if (!formulario.id_subrubro || formulario.id_subrubro === '0' || formulario.id_subrubro === 'No Asignado') errores.push('Sub Rubro Principal')
    if (!formulario.otro_rubro || String(formulario.otro_rubro).trim() === '') errores.push('Otros Rubros Adicionales')
    
    return errores
  }
  
  /**
   * Validar todos los campos obligatorios de la Sección 2: Datos Responsable
   */
  const validarSeccion2 = (formulario) => {
    const errores = []
    
    if (!formulario.nombre_gerente || String(formulario.nombre_gerente).trim() === '') errores.push('Nombre del Representante')
    if (!formulario.ci_gerente || String(formulario.ci_gerente).trim() === '') errores.push('Documento de Identidad del Representante')
    if (!formulario.exp_ci_gerente || String(formulario.exp_ci_gerente).trim() === '') errores.push('Expedido CI')
    if (!formulario.fono_gerente || String(formulario.fono_gerente).trim() === '') errores.push('Teléfono del Representante')
    if (!formulario.cargo_gerente || String(formulario.cargo_gerente).trim() === '') errores.push('Cargo del Representante')
    if (!formulario.nombre_contacto || String(formulario.nombre_contacto).trim() === '') errores.push('Nombre del Contacto')
    if (!formulario.telefono_contacto || String(formulario.telefono_contacto).trim() === '') errores.push('Teléfono del Contacto')
    
    return errores
  }
  
  /**
   * Validar todos los campos obligatorios de la Sección 3: Datos Evento
   */
  const validarSeccion3 = (formulario) => {
    const errores = []
    
    if (!formulario.medio_comunicacion || formulario.medio_comunicacion === '0') errores.push('Por qué medio fue aproximado')
    if (!formulario.como_entero || formulario.como_entero === '0') errores.push('Cómo se enteró de la feria')
    if (!formulario.tipo_expositor || formulario.tipo_expositor === '0') errores.push('Tipo de Expositor')
    if (formulario.tipo_credenciales === null || formulario.tipo_credenciales === undefined || formulario.tipo_credenciales === '') errores.push('Tipo de Credencial')
    if (!formulario.productos || String(formulario.productos).trim() === '') errores.push('Productos/Servicios')
    
    return errores
  }
  
  /**
   * Validar todos los campos obligatorios del formulario completo
   */
  const validarFormularioCompleto = (formulario) => {
    const erroresSeccion1 = validarSeccion1(formulario)
    const erroresSeccion2 = validarSeccion2(formulario)
    const erroresSeccion3 = validarSeccion3(formulario)
    
    const todosErrores = [
      ...erroresSeccion1,
      ...erroresSeccion2,
      ...erroresSeccion3
    ]
    
    return {
      valido: todosErrores.length === 0,
      errores: todosErrores,
      erroresSeccion1,
      erroresSeccion2,
      erroresSeccion3
    }
  }
  
  /**
   * Generar mensaje de error amigable
   */
  const generarMensajeError = (errores) => {
    if (errores.length === 0) return ''
    
    if (errores.length === 1) {
      return `Falta completar el campo: ${errores[0]}`
    }
    
    if (errores.length <= 3) {
      return `Faltan completar los siguientes campos: ${errores.join(', ')}`
    }
    
    return `Faltan completar ${errores.length} campos obligatorios. Por favor, revise el formulario.`
  }

  const formatearErroresCliente = (errores = []) => errores.flatMap(error => {
    const { id, errorMessages = [] } = error
    const input = typeof document !== 'undefined' && id ? document.getElementById(id) : null
    const label = input?.getAttribute('aria-label')
      || input?.closest('.v-input')?.querySelector('.v-label')?.textContent?.replace(/\s*\*$/, '').trim()

    return errorMessages.map(message => {
      return label ? `${label}: ${message}` : message
    })
  })

  const formatearErroresServidor = (errores = {}) => {
    const nombresCampos = new Map([
      ['actividad_principal', 'Actividad principal'],
      ['aniversario', 'Aniversario'],
      ['cargo_gerente', 'Cargo del representante'],
      ['ciudad_id', 'Ciudad'],
      ['ci_gerente', 'Documento del representante'],
      ['cluster', 'Categoría de empresa'],
      ['como_entero', 'Cómo se enteró de la feria'],
      ['descuento', 'Descuento'],
      ['direccion', 'Dirección'],
      ['email', 'Correo de la empresa'],
      ['email_representante', 'Correo del contacto'],
      ['exp_ci_gerente', 'Expedición del documento'],
      ['fax', 'Fax'],
      ['fecha_final', 'Fecha del pago final'],
      ['fecha_inicial', 'Fecha del pago inicial'],
      ['fecha_nr_escritura', 'Fecha de escritura'],
      ['fecha_nr_poder', 'Fecha de poder'],
      ['fono_gerente', 'Teléfono del representante'],
      ['marca_principal', 'Cantidad de marcas nacionales'],
      ['marcas_secundarios', 'Cantidad de marcas multinacionales'],
      ['matricula', 'Matrícula'],
      ['medio_comunicacion', 'Medio de comunicación'],
      ['mn', 'Marcas nacionales'],
      ['modo_precio', 'Modo de precio'],
      ['monto_inicial', 'Monto inicial'],
      ['nombre_empresa', 'Nombre de la empresa'],
      ['nombre_gerente', 'Nombre del representante'],
      ['nombre_responsable', 'Nombre del contacto'],
      ['nit', 'NIT'],
      ['nr_escritura', 'Número de escritura'],
      ['nr_notaria', 'Número de notaría'],
      ['nr_poder', 'Número de poder'],
      ['otro_rubro', 'Otros rubros adicionales'],
      ['pais_id', 'País'],
      ['pais_mu', 'País de marca multinacional'],
      ['pais_pp', 'País de marca internacional'],
      ['pais_principal', 'Cantidad de marcas internacionales'],
      ['perfil_visitante', 'Perfil del visitante'],
      ['pp', 'Marcas internacionales'],
      ['productos', 'Productos o servicios'],
      ['precio_total', 'Precio total'],
      ['precio_unit', 'Precio por metro cuadrado'],
      ['rubro', 'Rubro'],
      ['subrubro', 'Subrubro'],
      ['telefono', 'Teléfono de la empresa'],
      ['telefono_responsable', 'Teléfono del contacto'],
      ['tipo_credenciales', 'Tipo de credencial'],
      ['tipo_desc', 'Tipo de descuento'],
      ['tipo_expositor', 'Tipo de expositor'],
      ['web', 'Sitio web'],
      ['mu', 'Marcas multinacionales'],
    ])

    return Object.entries(errores).flatMap(([campo, mensajes]) => {
      const campoBase = campo.replace(/\.\d+$/, '')
      const indice = campo.match(/\.(\d+)$/)?.[1]
      const label = nombresCampos.get(campoBase) || campoBase.replace(/_/g, ' ')
      const nombreCampo = indice ? `${label} (elemento ${Number(indice) + 1})` : label

      return (Array.isArray(mensajes) ? mensajes : [mensajes])
        .map(message => {
          return `${nombreCampo}: ${message}`
        })
    })
  }

  return {
    // Reglas individuales
    requiredRule,
    requiredSelectRule,
    requiredCredentialRule,
    emailOptionalRule,
    phoneRule,
    nitRule,
    autocompleteIdRule,
    textareaRequiredRule,
    
    // Validaciones por sección
    validarSeccion1,
    validarSeccion2,
    validarSeccion3,
    
    // Validación completa
    validarFormularioCompleto,
    generarMensajeError,
    formatearErroresCliente,
    formatearErroresServidor,
  }
}
