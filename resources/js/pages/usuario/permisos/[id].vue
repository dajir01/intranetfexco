<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useCan } from '@/composables/useCan'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: {
    requiresAuth: true,
    title: 'Permisos de Usuario',
  },
})

const route = useRoute()
const router = useRouter()
const can = useCan()
const auth = useAuthStore()

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const search = ref('')

const user = ref(null)
const grouped = ref({})
const selected = ref(new Set())
const originalSelection = ref(new Set())
const expandedGroups = ref([])

const canManage = computed(() => can('usuarios.gestionar_permisos'))

const hasUnsavedChanges = computed(() => {
  if (selected.value.size !== originalSelection.value.size)
    return true

  return [...selected.value].some(permission => !originalSelection.value.has(permission))
})

const selectedCount = computed(() => selected.value.size)

const MODULE_ORDER = [
  'ingresos',
  'productos',
  'asignaciones',
  'areas',
  'proveedores',
  'movimientos',
  'usuarios',
  'ferias',
  'ferias_modelo_contrato',
  'ferias_reglamentos',
  'contratos',
  'pagos',
  'noticias',
  'notificaciones',
  'biometrico',
  'controlventas',
  'empresas',
  'home.ferias',
  'correspondencia',
  'solicitudes',
  'presupuesto',
  'partida',
  'cuenta',
  'credenciales.empresas',
  'credenciales.tipos',
  'credenciales.pines',
  'credenciales.acreditados',
  'credenciales.historial',
  'credenciales.cupos',
  'credenciales.modalidad',
  'credenciales.estado',
  'credenciales.qr',
  'credenciales.registro',
  'credenciales.salida_digital',
  'credenciales.salida_fisica',
  'credenciales.seleccionados',
]

const MODULE_LABELS = {
  ingresos: 'Nota de ingreso',
  productos: 'Productos',
  asignaciones: 'Asignaciones',
  areas: 'Áreas',
  proveedores: 'Proveedores',
  movimientos: 'Movimientos',
  usuarios: 'Usuarios',
  ferias: 'Ferias',
  'ferias_modelo_contrato': 'Modelo de contrato de ferias',
  'ferias_reglamentos': 'Reglamentos de Ferias',
  contratos: 'Contratos',
  pagos: 'Pagos',
  noticias: 'Noticias',
  notificaciones: 'Notificaciones por correo',
  biometrico: 'Biométrico',
  controlventas: 'Control de Ventas',
  empresas: 'Empresas',
  'home.ferias': 'Inicio - Ferias',
  correspondencia: 'Correspondencia',
  solicitudes: 'Solicitudes',
  presupuesto: 'Presupuestos',
  partida: 'Partidas',
  cuenta: 'Cuentas',
  'credenciales.empresas': 'Empresas',
  'credenciales.tipos': 'Tipos',
  'credenciales.pines': 'PIN',
  'credenciales.acreditados': 'Acreditados',
  'credenciales.historial': 'Historial',
  'credenciales.cupos': 'Cupos',
  'credenciales.modalidad': 'Modalidad',
  'credenciales.estado': 'Estado',
  'credenciales.qr': 'Códigos QR',
  'credenciales.registro': 'Registros',
  'credenciales.salida_digital': 'Salida digital',
  'credenciales.salida_fisica': 'Impresión física',
  'credenciales.seleccionados': 'Acciones masivas',
}

const MODULE_GROUPS = [
  { key: 'ferias', title: 'Gestión de ferias', moduleKeys: ['ferias', 'ferias_modelo_contrato', 'ferias_reglamentos'] },
  { key: 'contratos', title: 'Gestión de contratos', moduleKeys: ['contratos', 'pagos'] },
  { key: 'empresas', title: 'Gestión de empresas', moduleKeys: ['empresas'] },
  { key: 'credenciales', title: 'Credenciales', modulePrefix: 'credenciales.' },
  { key: 'inventario', title: 'Inventario', moduleKeys: ['ingresos', 'productos', 'asignaciones', 'areas', 'proveedores', 'movimientos'] },
  { key: 'controlventas', title: 'Control de ventas', moduleKeys: ['controlventas'] },
  { key: 'comunicacion', title: 'Comunicación', moduleKeys: ['noticias'] },
  { key: 'biometrico', title: 'Reporte biométrico', moduleKeys: ['biometrico'] },
  { key: 'presupuesto', title: 'Presupuesto', moduleKeys: ['presupuesto', 'partida', 'cuenta'] },
  { key: 'solicitudes', title: 'Solicitudes', moduleKeys: ['solicitudes'] },
  { key: 'correspondencia', title: 'Correspondencia', moduleKeys: ['correspondencia'] },
  { key: 'usuarios', title: 'Usuarios', moduleKeys: ['usuarios'] },
  { key: 'configuracion', title: 'Configuración', moduleKeys: ['notificaciones'] },
  { key: 'inicio', title: 'Inicio', moduleKeys: ['home.ferias'] },
]

const ACTION_LABELS = {
  ver: 'Ver',
  crear: 'Crear',
  editar: 'Editar',
  eliminar: 'Eliminar',
  activar: 'Activar',
  importar: 'Importar',
  generar: 'Generar',
  imprimir: 'Imprimir',
  enviar: 'Enviar',
  adicionar: 'Añadir',
  quitar: 'Quitar',
  cambiar: 'Cambiar',
  inhabilitar: 'Inhabilitar',
  habilitar: 'Habilitar',
  anular: 'Anular',
  renovar: 'Renovar',
  detalles: 'Ver detalles',
  historial: 'Ver historial',
  descargar: 'Descargar',
  exportar: 'Exportar',
  aprobar: 'Aprobar',
  rechazar: 'Rechazar',
  recalcular: 'Recalcular',
  derivar: 'Derivar',
  responder: 'Responder',
  respuestas: 'Ver respuestas',
  recibidas: 'Ver recibidas',
  archivos: 'Gestionar archivos',
  devolver: 'Devolver',
  certificacion: 'Certificación inicial',
  'certificacion_presupuestaria': 'Certificación presupuestaria',
  'importar_excel': 'Importar desde Excel',
  'descargar_lote': 'Descargar en lote',
  'imprimir_lote': 'Imprimir en lote',
  'ingresos.ver': 'Lista',
  'ingresos.crear': 'Registrar ingreso',
  'ingresos.editar': 'Editar ingreso',
  'ingresos.eliminar': 'Eliminar ingreso',
  'reportes.ver': 'Reporte',
  'reportes.descargar': 'Descargar reporte',
  'controlventas.ver': 'Ver',
  'controlventas.crear': 'Crear entrada',
  'controlventas.editar': 'Editar entrada',
  'controlventas.eliminar': 'Eliminar entrada',
  'controlventas.registrar': 'Registrar venta',
  'controlventas.editar_ultimo': 'Editar último registro de venta',
  'controlventas.eliminar_ultimo': 'Eliminar último registro de venta',
  'controlventas.exportar': 'Exportar Excel',
  'ferias_reglamentos.ver': 'Ver reglamentos',
  'ferias_reglamentos.crear': 'Añadir reglamento',
  'ferias_reglamentos.eliminar': 'Eliminar reglamento',
  'empresas.ver': 'Ver listado',
  'empresas.detalle': 'Ver detalle',
  'empresas.ferias': 'Ferias participantes',
  'empresas.historial': 'Registro de cambios',
  'correspondencia.ver': 'Ver correspondencia',
  'correspondencia.crear': 'Registrar correspondencia',
  'correspondencia.editar': 'Editar correspondencia',
  'correspondencia.eliminar': 'Eliminar correspondencia',
  'correspondencia.derivar': 'Derivar correspondencia',
  'correspondencia.recibidas': 'Ver correspondencia recibida',
  'correspondencia.responder': 'Responder correspondencia',
  'correspondencia.respuestas': 'Ver respuestas',
  'correspondencia.archivos': 'Ver y descargar archivos',
  'correspondencia.historial': 'Ver historial',
  'notificaciones.ver': 'Ver configuración',
  'notificaciones.crear': 'Crear configuración',
  'notificaciones.editar': 'Editar configuración',
  'solicitudes.ver': 'Ver solicitudes',
  'solicitudes.crear': 'Crear solicitudes',
  'solicitudes.editar': 'Editar solicitudes',
  'solicitudes.eliminar': 'Eliminar solicitudes',
  'solicitudes.detalle': 'Ver detalle y documentos',
  'solicitudes.recibidas': 'Ver recibidas y remitidas',
  'solicitudes.derivar': 'Derivar solicitudes',
  'solicitudes.aprobar': 'Aprobar y avanzar flujo',
  'solicitudes.devolver': 'Devolver solicitudes',
  'solicitudes.certificacion': 'Certificación inicial',
  'solicitudes.certificacion_presupuestaria': 'Certificación presupuestaria',
  'presupuesto.ver': 'Ver presupuestos',
  'presupuesto.crear': 'Crear presupuesto',
  'presupuesto.editar': 'Editar presupuesto',
  'presupuesto.eliminar': 'Eliminar presupuesto',
  'presupuesto.importar_excel': 'Importar partidas desde Excel',
  'partida.crear': 'Crear partida',
  'partida.editar': 'Editar partida',
  'partida.eliminar': 'Eliminar partida',
  'cuenta.crear': 'Crear cuenta',
  'cuenta.editar': 'Editar cuenta',
  'cuenta.eliminar': 'Eliminar cuenta',
  'credenciales.empresas.ver': 'Ver empresas con credenciales',
  'credenciales.empresas.crear': 'Agregar empresas y credenciales',
  'credenciales.empresas.eliminar': 'Eliminar vinculación de empresa',
  'credenciales.empresas.exportar': 'Exportar empresas y credenciales a Excel',
  'credenciales.acreditados.ver': 'Ver acreditados',
  'credenciales.acreditados.crear': 'Agregar acreditados',
  'credenciales.acreditados.editar': 'Editar acreditados',
  'credenciales.acreditados.exportar': 'Exportar acreditados a Excel',
}

const permissionLabel = permission => {
  return ACTION_LABELS[permission.name]
    || ACTION_LABELS[permission.action]
    || permission.action.replaceAll('_', ' ').replace(/^./, character => character.toLocaleUpperCase('es'))
}

const moduleEntries = computed(() => {
  const merged = { ...grouped.value }

  // Para Inventario > Nota de Ingreso mostramos también reporte en el mismo bloque.
  if (Array.isArray(merged.ingresos)) {
    const reportes = Array.isArray(merged.reportes) ? merged.reportes : []

    merged.ingresos = [...merged.ingresos, ...reportes]
    delete merged.reportes
  }

  const entries = Object.entries(merged).map(([moduleKey, permissions]) => ({
    key: moduleKey,
    title: MODULE_LABELS[moduleKey] || moduleKey.replaceAll('.', ' ').replaceAll('_', ' '),
    permissions: Array.isArray(permissions) ? permissions : [],
  }))

  return entries.sort((a, b) => {
    const idxA = MODULE_ORDER.indexOf(a.key)
    const idxB = MODULE_ORDER.indexOf(b.key)
    const orderA = idxA === -1 ? Number.MAX_SAFE_INTEGER : idxA
    const orderB = idxB === -1 ? Number.MAX_SAFE_INTEGER : idxB

    if (orderA !== orderB)
      return orderA - orderB

    return a.title.localeCompare(b.title)
  })
})

const moduleGroups = computed(() => {
  const remaining = new Map(moduleEntries.value.map(moduleEntry => [moduleEntry.key, moduleEntry]))
  const groups = []

  for (const group of MODULE_GROUPS) {

    const moduleKeys = group.modulePrefix
      ? [...remaining.keys()].filter(moduleKey => moduleKey.startsWith(group.modulePrefix))
      : group.moduleKeys

    const modules = moduleKeys.map(moduleKey => remaining.get(moduleKey)).filter(Boolean)

    for (const moduleEntry of modules)
      remaining.delete(moduleEntry.key)

    if (modules.length)
      groups.push({ ...group, modules })
  }

  if (remaining.size)
    groups.push({ key: 'otros', title: 'Otros', modules: [...remaining.values()] })

  return groups
})

const visibleModuleEntries = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('es')
  if (!query)
    return moduleEntries.value

  return moduleEntries.value.map(moduleEntry => {
    const moduleMatches = moduleEntry.title.toLocaleLowerCase('es').includes(query)

    const permissions = moduleMatches
      ? moduleEntry.permissions
      : moduleEntry.permissions.filter(permission => permissionLabel(permission).toLocaleLowerCase('es').includes(query))

    return { ...moduleEntry, permissions }
  }).filter(moduleEntry => moduleEntry.permissions.length)
})

const visibleModuleGroups = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('es')
  const visibleByKey = new Map(visibleModuleEntries.value.map(moduleEntry => [moduleEntry.key, moduleEntry]))

  return moduleGroups.value.map(group => {
    const groupMatches = query && group.title.toLocaleLowerCase('es').includes(query)

    const modules = groupMatches
      ? group.modules
      : group.modules.map(moduleEntry => visibleByKey.get(moduleEntry.key)).filter(Boolean)

    return { ...group, modules }
  }).filter(group => group.modules.length)
})

watch(search, value => {
  const matchingGroups = visibleModuleGroups.value

  expandedGroups.value = value.trim() && matchingGroups.length <= 3
    ? matchingGroups.map(group => group.key)
    : []
})

const groupSummary = group => {
  const moduleCount = group.modules.length
  const permissionCount = group.modules.reduce((count, moduleEntry) => count + moduleEntry.permissions.length, 0)

  return `${moduleCount} ${moduleCount === 1 ? 'módulo' : 'módulos'} · ${permissionCount} ${permissionCount === 1 ? 'permiso' : 'permisos'}`
}

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const isChecked = permissionName => selected.value.has(permissionName)

const delegablePermissions = permissions => permissions.filter(permission => can(permission.name))

const isModuleSelected = permissions => permissions.length > 0 && permissions.every(permission => selected.value.has(permission.name))

const isModulePartiallySelected = permissions => {
  const delegable = delegablePermissions(permissions)

  return delegable.some(permission => selected.value.has(permission.name)) && !isModuleSelected(delegable)
}

const togglePermission = (permissionName, checked) => {
  const next = new Set(selected.value)

  if (checked)
    next.add(permissionName)
  else
    next.delete(permissionName)

  selected.value = next
}

const toggleModule = (permissions, checked) => {
  const next = new Set(selected.value)

  for (const permission of delegablePermissions(permissions)) {
    if (checked)
      next.add(permission.name)
    else
      next.delete(permission.name)
  }

  selected.value = next
}

const discardChanges = () => {
  selected.value = new Set(originalSelection.value)
}

const loadCatalog = async () => {
  const response = await fetch('/permissions', {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })

  const payload = await response.json().catch(() => null)
  if (!response.ok)
    throw new Error(payload?.message || 'No se pudo cargar catálogo de permisos')

  grouped.value = payload?.data?.grouped || {}
}

const loadUserPermissions = async () => {
  const response = await fetch(`/usuarios/${route.params.id}/permissions`, {
    headers: { Accept: 'application/json' },
    credentials: 'same-origin',
  })

  const payload = await response.json().catch(() => null)
  if (!response.ok)
    throw new Error(payload?.message || 'No se pudo cargar permisos del usuario')

  user.value = payload?.data?.user || null
  selected.value = new Set(payload?.data?.permissions || [])
  originalSelection.value = new Set(selected.value)
}

const savePermissions = async () => {
  if (!canManage.value)
    return

  error.value = ''
  success.value = ''
  saving.value = true

  try {
    const response = await fetch(`/usuarios/${route.params.id}/permissions`, {
      method: 'PUT',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify({ permissions: Array.from(selected.value) }),
    })

    const payload = await response.json().catch(() => null)
    if (!response.ok)
      throw new Error(payload?.message || 'No se pudo guardar permisos')

    selected.value = new Set(payload?.data?.permissions || [])
    originalSelection.value = new Set(selected.value)
    if (String(auth.user?.id_usuario) === String(route.params.id))
      await auth.fetchUser()
    success.value = payload?.message || 'Permisos actualizados'
  }
  catch (e) {
    error.value = e?.message || 'Error al guardar'
  }
  finally {
    saving.value = false
  }
}

onMounted(async () => {
  if (!canManage.value) {
    router.replace('/')

    return
  }

  loading.value = true
  error.value = ''

  try {
    await Promise.all([
      loadCatalog(),
      loadUserPermissions(),
    ])
  }
  catch (e) {
    error.value = e?.message || 'Error al cargar la pantalla'
  }
  finally {
    loading.value = false
  }
})
</script>

<template>
  <section id="usuario-permisos-page">
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-3">
        <div>
          <div class="text-h6">Permisos granulares</div>
          <div class="text-caption text-medium-emphasis">
            {{ user ? `${user.nombre_usuario} (@${user.nick_usuario})` : 'Usuario' }}
          </div>
        </div>

        <div class="d-flex gap-2">
          <VBtn variant="tonal" color="secondary" prepend-icon="tabler-arrow-left" @click="router.push('/usuario/list')">
            Volver
          </VBtn>
          <VBtn
            v-if="canManage"
            color="primary"
            prepend-icon="tabler-device-floppy"
            :loading="saving"
            :disabled="loading || saving || !hasUnsavedChanges"
            @click="savePermissions"
          >
            Guardar permisos
          </VBtn>
        </div>
      </VCardTitle>

      <VCardText>
        <VAlert v-if="error" type="error" variant="tonal" class="mb-4" closable @click:close="error = ''">
          {{ error }}
        </VAlert>

        <VAlert v-if="success" type="success" variant="tonal" class="mb-4" closable @click:close="success = ''">
          {{ success }}
        </VAlert>

        <VProgressLinear v-if="loading" indeterminate color="primary" class="mb-6" />

        <template v-else>
          <VAlert v-if="!moduleEntries.length" type="info" variant="tonal">
            No existen permisos configurados. Ejecuta el seeder de permisos.
          </VAlert>

          <template v-else>
            <div class="d-flex flex-wrap align-center justify-space-between gap-3 mb-4">
              <AppTextField
                v-model="search"
                label="Buscar módulos o permisos"
                prepend-inner-icon="tabler-search"
                clearable
                hide-details
                density="compact"
                class="permission-search"
              />
              <div class="d-flex align-center gap-3">
                <span class="text-body-2 text-medium-emphasis">
                  {{ selectedCount }} permisos asignados
                </span>
                <VBtn
                  variant="text"
                  size="small"
                  :disabled="!hasUnsavedChanges || saving"
                  @click="discardChanges"
                >
                  Descartar cambios
                </VBtn>
              </div>
            </div>

            <VExpansionPanels
              v-if="visibleModuleGroups.length"
              v-model="expandedGroups"
              multiple
              variant="accordion"
              class="permission-groups"
            >
              <VExpansionPanel v-for="group in visibleModuleGroups" :key="group.key" :value="group.key">
                <VExpansionPanelTitle>
                  <div class="d-flex align-center justify-space-between flex-wrap gap-2 pe-2">
                    <span class="text-subtitle-1">{{ group.title }}</span>
                    <span class="text-caption text-medium-emphasis">{{ groupSummary(group) }}</span>
                  </div>
                </VExpansionPanelTitle>
                <VExpansionPanelText>
                  <VRow>
                    <VCol v-for="moduleEntry in group.modules" :key="moduleEntry.key" cols="12" md="6" lg="4">
                      <VCard variant="outlined" class="h-100">
                        <VCardTitle class="d-flex align-center justify-space-between gap-2 text-subtitle-1 text-capitalize">
                          <span>{{ moduleEntry.title }}</span>
                          <VTooltip :text="`Seleccionar todos los permisos de ${moduleEntry.title}`">
                            <template #activator="{ props }">
                              <VCheckbox
                                v-bind="props"
                                :model-value="isModuleSelected(delegablePermissions(moduleEntry.permissions))"
                                :indeterminate="isModulePartiallySelected(moduleEntry.permissions)"
                                :disabled="delegablePermissions(moduleEntry.permissions).length === 0"
                                :aria-label="`Seleccionar todos los permisos de ${moduleEntry.title}`"
                                density="compact"
                                hide-details
                                @update:model-value="val => toggleModule(moduleEntry.permissions, val)"
                              />
                            </template>
                          </VTooltip>
                        </VCardTitle>
                        <VDivider />
                        <VCardText class="pt-4">
                          <VCheckbox
                            v-for="permission in moduleEntry.permissions"
                            :key="permission.name"
                            :model-value="isChecked(permission.name)"
                            :label="permissionLabel(permission)"
                            :disabled="!can(permission.name)"
                            density="compact"
                            hide-details
                            @update:model-value="val => togglePermission(permission.name, val)"
                          />
                        </VCardText>
                      </VCard>
                    </VCol>
                  </VRow>
                </VExpansionPanelText>
              </VExpansionPanel>
            </VExpansionPanels>

            <VAlert
              v-else
              type="info"
              variant="tonal"
            >
              No hay permisos que coincidan con la búsqueda.
            </VAlert>
          </template>
        </template>
      </VCardText>
    </VCard>
  </section>
</template>

<style scoped>
.permission-search {
  flex: 1 1 20rem;
  max-inline-size: 28rem;
}

.permission-groups {
  gap: 0.5rem;
}
</style>
