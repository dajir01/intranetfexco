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
    generarMensajeError
  }
}
