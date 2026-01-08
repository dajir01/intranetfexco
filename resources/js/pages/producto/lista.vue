<script setup>
import { computed, ref, watch } from 'vue'
import { watchDebounced } from '@vueuse/core'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/auth'

// Estado de tabla y filtros (productos)
const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)

// Controles de servidor
const page = ref(1)
const itemsPerPage = ref(10)
const sortBy = ref([{ key: 'id_producto', order: 'desc' }])
const search = ref('')
const searchCodigo = ref('')
const searchCodigoBarras = ref('')
const router = useRouter()

// Estado para baja de producto (solo Activo Fijo)
const dialogBajaOpen = ref(false)
const motivoBaja = ref('')
const motivoError = ref('')
const selectedItem = ref(null)
const submittingBaja = ref(false)

// Estado para edición de producto
const dialogEditOpen = ref(false)
const editLoading = ref(false)
const editSaving = ref(false)
const editForm = ref({
  id_producto: null,
  codigo_barras: '',
  nombre: '',
  descripcion: '',
  tipo: '',
  unidad_medida: '',
})
const editErrors = ref({})
const snackbar = ref({ show: false, text: '' })

const auth = useAuthStore()
const canViewProducts = computed(() => auth.can('products.view'))
const canCreateIngreso = computed(() => auth.can('ingresos.create'))
const canEditProducts = computed(() => auth.can('products.update'))
const canBajaProducts = computed(() => auth.can('products.baja'))

const openEditDialog = async (item) => {
  editErrors.value = {}
  editForm.value = { id_producto: item.id_producto, codigo_barras: '', nombre: '', descripcion: '', tipo: '', unidad_medida: '' }
  dialogEditOpen.value = true
  editLoading.value = true
  try {
    const res = await fetch(`/inventario/productos/${item.id_producto}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })
    if (!res.ok) throw new Error(`HTTP ${res.status}`)
    const data = await res.json()
    editForm.value.codigo_barras = data.codigo_barras || ''
    editForm.value.nombre = data.nombre || ''
    editForm.value.descripcion = data.descripcion || ''
    editForm.value.tipo = data.tipo || ''
    editForm.value.unidad_medida = data.unidad_medida || ''
  } catch (e) {
    snackbar.value = { show: true, text: `Error cargando producto: ${e.message || e}` }
  } finally {
    editLoading.value = false
  }
}

const closeEditDialog = () => {
  dialogEditOpen.value = false
  editErrors.value = {}
}

const validateEditForm = () => {
  const errs = {}
  if (!editForm.value.nombre || String(editForm.value.nombre).trim() === '') {
    errs.nombre = 'El nombre es obligatorio'
  }
  editErrors.value = errs
  return Object.keys(errs).length === 0
}

const saveEdit = async () => {
  if (!validateEditForm()) return
  editSaving.value = true
  try {
    const csrf = getCsrfToken()
    const payload = {
      nombre: String(editForm.value.nombre || '').trim(),
      codigo_barras: editForm.value.codigo_barras ?? null,
      descripcion: editForm.value.descripcion ?? null,
      tipo: editForm.value.tipo ?? null,
      unidad_medida: editForm.value.unidad_medida ?? null,
    }
    const res = await fetch(`/inventario/productos/${editForm.value.id_producto}/editar`, {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      },
      credentials: 'same-origin',
      body: JSON.stringify(payload),
    })
    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message || `HTTP ${res.status}`)
    }
    snackbar.value = { show: true, text: 'Producto actualizado correctamente' }
    closeEditDialog()
    await load()
  } catch (e) {
    snackbar.value = { show: true, text: e.message || 'Error al actualizar producto' }
  } finally {
    editSaving.value = false
  }
}

const getEstadoLabel = (estadoDadoBaja) => Number(estadoDadoBaja || 0) === 1 ? 'Dado de baja' : 'Activo'
const getEstadoColor = (estadoDadoBaja) => Number(estadoDadoBaja || 0) === 1 ? 'error' : 'success'

const openBajaDialog = (item) => {
  selectedItem.value = item
  motivoBaja.value = ''
  motivoError.value = ''
  dialogBajaOpen.value = true
}

const closeBajaDialog = () => {
  dialogBajaOpen.value = false
  motivoBaja.value = ''
  motivoError.value = ''
  selectedItem.value = null
}

const getCsrfToken = () => {
  const el = document.querySelector('meta[name="csrf-token"]')
  return el?.getAttribute('content') || undefined
}

const confirmarBaja = async () => {
  motivoError.value = ''
  const motivo = (motivoBaja.value || '').trim()
  if (!motivo) {
    motivoError.value = 'El motivo de baja es obligatorio'
    return
  }
  if (!selectedItem.value?.id_asignacion) {
    motivoError.value = 'Asignación inválida'
    return
  }

  submittingBaja.value = true
  try {
    const csrf = getCsrfToken()
    const res = await fetch('/inventario/productos/baja', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(csrf ? { 'X-CSRF-TOKEN': csrf } : {}),
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        asignacion_id: selectedItem.value.id_asignacion,
        motivo,
      }),
    })

    if (!res.ok) {
      const err = await res.json().catch(() => ({}))
      throw new Error(err.message || `HTTP ${res.status}`)
    }

    // Éxito: cerrar diálogo y refrescar lista
    closeBajaDialog()
    await load()
  } catch (e) {
    motivoError.value = e.message || 'Error al dar de baja'
  } finally {
    submittingBaja.value = false
  }
}

// Agregar columna de área al inicio y columna de acciones al final
const headers = [
  { title: 'Área', key: 'area' },
  { title: 'Código', key: 'codigo' },
  { title: 'Nombre', key: 'nombre' },
  { title: 'Tipo', key: 'tipo' },
  { title: 'Unidad', key: 'unidad_medida' },
  { title: 'Stock', key: 'stock' },
  { title: 'Costo Total', key: 'costo_total' },
  { title: 'Estado', key: 'estado', sortable: false },
  { title: 'Acciones', key: 'actions', sortable: false },
]

// Filtro de áreas
const areaOptions = ref([])
const selectedArea = ref([]) // selección múltiple de IDs de áreas

// Filtro de tipo de producto
const selectedTipoProducto = ref(null)
const tipoOptions = ref([
  { label: 'Consumible', value: 'Consumible' },
  { label: 'Activo Fijo', value: 'Activo Fijo' },
])

// Opciones de Unidad de Medida (usadas en el diálogo de edición)
const unidadMedidaOptions = ref([
  { label: 'Unidad', value: 'Unidad' },
  { label: 'Metro', value: 'Metro' },
  { label: 'Litro', value: 'Litro' },
  { label: 'Caja', value: 'Caja' },
  { label: 'Paquete', value: 'Paquete' },
  { label: 'Kilogramo', value: 'Kilogramo' },
  { label: 'Bolsa', value: 'Bolsa' },
  { label: 'M3', value: 'M3' },
  { label: 'Rollo', value: 'Rollo' },
  { label: 'Kit', value: 'Kit' },
  { label: 'Pieza', value: 'Pieza' },
])

const loadAreas = async () => {
  try {
    const res = await fetch('/inventario/areas', {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error('HTTP '+res.status)
    
    const json = await res.json()
    
    // El endpoint devuelve directamente el array, no dentro de 'data'
    const areasData = Array.isArray(json) ? json : (json.data || [])
    areaOptions.value = areasData.map(a => ({
      label: a.codigo ? `${a.codigo} - ${a.nombre}` : a.nombre,
      value: a.id_area,
    }))
    console.log('Áreas cargadas:', areaOptions.value)
  } catch (err) {
    console.error('Error cargando áreas', err)
  }
}

const buildQuery = () => {
  const params = new URLSearchParams()

  params.set('page', String(page.value))
  params.set('per_page', String(itemsPerPage.value))
  if (search.value)
    params.set('q', search.value)
  if (searchCodigo.value)
    params.set('codigo', searchCodigo.value)
  if (searchCodigoBarras.value)
    params.set('codigo_barras', searchCodigoBarras.value)

  const s = sortBy.value?.[0]
  if (s?.key)
    params.set('sort_by', s.key)
  if (s?.order)
    params.set('sort_dir', s.order === 'asc' ? 'asc' : 'desc')

  // Verificar y agregar el área seleccionada
  if (Array.isArray(selectedArea.value) && selectedArea.value.length > 0) {
    const validAreas = selectedArea.value.filter(v => areaOptions.value.some(a => a.value === v));
    if (validAreas.length > 0) {
      params.set('area_ids', validAreas.map(v => String(v)).join(','));
    }
  }

  // Agregar filtro de tipo de producto
  if (selectedTipoProducto.value !== null && selectedTipoProducto.value !== '') {
    params.set('tipo', selectedTipoProducto.value)
  }

  return params.toString()
}

const load = async () => {
  loading.value = true
  error.value = null
  try {
    const qs = buildQuery()

    const res = await fetch(`/inventario/productos?${qs}`, {
      headers: { Accept: 'application/json' },
      credentials: 'same-origin',
    })

    if (!res.ok)
      throw new Error(`HTTP ${res.status}`)
    const json = await res.json()

    items.value = json.data || []
    total.value = json.meta?.total ?? 0; // Asegurarse de que el total sea un número válido
  } catch (err) {
    error.value = err
    console.error('Error cargando productos', err)
  } finally {
    loading.value = false
  }
}

// Cargar inicial
await Promise.all([loadAreas(), load()])

// Reactividad: búsqueda con debounce
watchDebounced(search, () => { page.value = 1; load() }, { debounce: 400 })
watchDebounced(searchCodigo, () => { page.value = 1; load() }, { debounce: 400 })
watchDebounced(searchCodigoBarras, () => { page.value = 1; load() }, { debounce: 400 })

// Reactividad: paginación y orden
watch([page, itemsPerPage, sortBy], () => {
  page.value = Math.max(1, page.value); // Evitar valores de página inválidos
  load();
});

watch(selectedArea, (newValue) => {
  console.log('Áreas seleccionadas:', newValue);
  page.value = 1;
  load();
})

watch(selectedTipoProducto, (newValue) => {
  console.log('Tipo seleccionado:', newValue);
  page.value = 1;
  load();
})

// Handler para actualizar opciones de ordenamiento desde la tabla
const updateOptions = (options) => {
  if (options.sortBy && options.sortBy.length > 0) {
    sortBy.value = options.sortBy
  }
  if (options.page) {
    page.value = options.page
  }
  if (options.itemsPerPage) {
    itemsPerPage.value = options.itemsPerPage
  }
}
</script>

<template>
  <section>
    <VAlert
      v-if="!canViewProducts"
      type="error"
      variant="tonal"
      class="mb-4"
    >
      No tienes permisos para ver productos.
    </VAlert>

    <template v-else>
      <VCard id="producto-list">
      <VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
        <div class="d-flex gap-4 align-center flex-wrap">
          <div class="d-flex align-center gap-2">
            <span>Mostrar</span>
            <AppSelect
              :model-value="itemsPerPage"
              :items="[
                { value: 10, title: '10' },
                { value: 25, title: '25' },
                { value: 50, title: '50' },
                { value: 100, title: '100' },
                { value: -1, title: 'Todos' },
              ]"
              style="inline-size: 5.5rem;"
              @update:model-value="itemsPerPage = parseInt($event, 10)"
            />
          </div>
          <VBtn
            prepend-icon="tabler-plus"
            v-if="canCreateIngreso"
            @click="router.push('/inventario/ingreso-register')"
          >
            Registrar Nuevo Ingreso
          </VBtn>
        </div>

        <div class="d-flex align-center flex-wrap gap-4">
          <div class="producto-list-filter">
            <VSelect
              v-model="selectedArea"
              :items="areaOptions"
              multiple
              chips
              variant="outlined"
              placeholder="Todas Las Áreas"
              label="Área"
              item-title="label"
              item-value="value"
              clearable
            />
          </div>
          <div class="producto-list-filter">
            <VSelect
              v-model="selectedTipoProducto"
              :items="tipoOptions"
              placeholder="Todos Los Tipos"
              label="Tipo Producto"
              item-title="label"
              item-value="value"
              clearable
            />
          </div>
          <div class="producto-list-filter">
            <AppTextField
              v-model="searchCodigo"
              placeholder="Buscar por código..."
              append-inner-icon="tabler-barcode"
              single-line
              hide-details
              dense
              outlined
            />
          </div>
          <div class="producto-list-filter">
            <AppTextField
              v-model="search"
              placeholder="Buscar productos..."
              append-inner-icon="tabler-tags"
              single-line
              hide-details
              dense
              outlined
            />
          </div>
        </div>
      </VCardText>
      <VDivider />

      <VDataTableServer
        v-model:items-per-page="itemsPerPage"
        v-model:page="page"
        :items-length="total"
        :headers="headers"
        :items="items"
        :loading="loading"
        item-value="id_producto"
        class="text-no-wrap"
        @update:options="updateOptions"
      >
        <template #item.area="{ item }">
          {{ item.area || '—' }}
        </template>

        <template #item.codigo="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            {{ item.codigo || '—' }}
          </span>
        </template>

        <template #item.nombre="{ item }">
          {{ item.nombre || '—' }}
        </template>

        <template #item.tipo="{ item }">
          {{ item.tipo || '—' }}
        </template>

        <template #item.unidad_medida="{ item }">
          {{ item.unidad_medida || '—' }}
        </template>

        <template #item.stock="{ item }">
          {{ item.stock || '—' }}
        </template>

        <template #item.costo_total="{ item }">
          <span class="text-high-emphasis font-weight-medium">
            Bs. {{ Number(item.costo_total || 0).toFixed(2) }}
          </span>
        </template>

        <template #item.estado="{ item }">
          <VChip
            :color="getEstadoColor(item.estado_dado_baja)"
            size="small"
          >
            {{ getEstadoLabel(item.estado_dado_baja) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VTooltip text="Ver Detalles">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="router.push(`/producto/${item.id_asignacion}`)">
                  <VIcon icon="tabler-eye" color="info" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>
          <VTooltip v-if="canEditProducts" text="Editar producto">
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="openEditDialog(item)">
                  <VIcon icon="tabler-edit" color="success"/>
                </IconBtn>
              </span>
            </template>
          </VTooltip>
          <VTooltip
            v-if="canBajaProducts && item.tipo === 'Activo Fijo' && Number(item.estado_dado_baja || 0) !== 1"
            text="Dar de baja"
          >
            <template #activator="{ props }">
              <span v-bind="props">
                <IconBtn @click="openBajaDialog(item)">
                  <VIcon icon="tabler-trash" color="error" />
                </IconBtn>
              </span>
            </template>
          </VTooltip>
        </template>
      </VDataTableServer>
    </VCard>
    <VDialog v-model="dialogBajaOpen" max-width="560">
      <VCard>
        <VCardTitle class="text-h6">Dar de baja producto</VCardTitle>
        <VCardText>
          <div class="mb-4">
            <div><strong>Producto:</strong> {{ selectedItem?.nombre || '—' }}</div>
            <div><strong>Código:</strong> {{ selectedItem?.codigo || '—' }}</div>
            <div><strong>Área:</strong> {{ selectedItem?.area || '—' }}</div>
            <div><strong>Tipo:</strong> {{ selectedItem?.tipo || '—' }}</div>
          </div>
          <AppTextField
            v-model="motivoBaja"
            label="Motivo de baja"
            placeholder="Ingrese el motivo..."
            :error="!!motivoError"
            :error-messages="motivoError ? [motivoError] : []"
            clearable
          />
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" @click="closeBajaDialog" :disabled="submittingBaja">Cancelar</VBtn>
          <VBtn color="error" @click="confirmarBaja" :loading="submittingBaja">Confirmar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VDialog v-model="dialogEditOpen" max-width="640">
      <VCard>
        <VCardTitle class="text-h6">Editar producto</VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            Al realizar cambios en este producto, estos afectarán a todas las asignaciones asociadas.
          </VAlert>
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="editForm.codigo_barras"
                label="Código"
                placeholder="Código de barras o interno"
                clearable
                :disabled="editLoading"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="editForm.nombre"
                label="Nombre"
                :error="!!editErrors.nombre"
                :error-messages="editErrors.nombre ? [editErrors.nombre] : []"
                :disabled="editLoading"
                clearable
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="editForm.descripcion"
                label="Descripción"
                clearable
                :disabled="editLoading"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="editForm.tipo"
                :items="tipoOptions"
                label="Tipo"
                item-title="label"
                item-value="value"
                clearable
                :disabled="editLoading"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSelect
                v-model="editForm.unidad_medida"
                :items="unidadMedidaOptions"
                label="Unidad de Medida"
                item-title="label"
                item-value="value"
                placeholder="Seleccione la unidad"
                clearable
                :disabled="editLoading"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" @click="closeEditDialog" :disabled="editSaving">Cancelar</VBtn>
          <VBtn color="primary" @click="saveEdit" :loading="editSaving">Guardar cambios</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <VSnackbar v-model="snackbar.show" timeout="2500">
      {{ snackbar.text }}
    </VSnackbar>
    </template>
  </section>
</template>

<style lang="scss">
#producto-list {
  .producto-list-filter {
    inline-size: 12rem;
  }
}
</style>
