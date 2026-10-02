<script setup>
import { watchDebounced } from '@vueuse/core'
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { Spanish } from 'flatpickr/dist/l10n/es.js'
import { useSafePagination } from '@/composables/useSafePagination'
import { useAuthStore } from '@/stores/auth'

definePage({
	meta: {
		requiresAuth: true,
		title: 'Modelo Contrato de Ferias',
	},
})

const router = useRouter()
const auth = useAuthStore()

const canViewModeloContrato = computed(() => auth.can('ferias.modelo_contrato.view'))
if (!canViewModeloContrato.value)
	router.replace('/')

// Frontend listo para integración futura.
const API_READY = true

const getNowLocal = () => {
	const now = new Date()
	const year = now.getFullYear()
	const month = String(now.getMonth() + 1).padStart(2, '0')
	const day = String(now.getDate()).padStart(2, '0')
	const hours = String(now.getHours()).padStart(2, '0')
	const minutes = String(now.getMinutes()).padStart(2, '0')

	return `${year}-${month}-${day} ${hours}:${minutes}`
}

// ── Tabla ────────────────────────────────────────────────────────────────────
const items = ref([])
const total = ref(0)
const loading = ref(false)
const error = ref(null)

const { itemsPerPageOptions, defaultItemsPerPage, sanitizeItemsPerPage } = useSafePagination()
const page = ref(1)
const itemsPerPage = ref(defaultItemsPerPage)
const sortBy = ref([{ key: 'fecha_creacion', order: 'desc' }])
const search = ref('')

const headers = [
	{ title: 'Nombre', key: 'nombre', sortable: true },
	{ title: 'Tipo', key: 'tipo_nombre', sortable: true },
	{ title: 'Fecha Creación', key: 'fecha_creacion', sortable: true },
	{ title: 'Acciones', key: 'actions', sortable: false },
]

const normalizeText = value => String(value ?? '').toLowerCase()

const filteredItems = computed(() => {
	const term = normalizeText(search.value)
	if (!term)
		return items.value

	return items.value.filter(item => {
		const values = [
			item.nombre,
			String(item.fecha_creacion ?? ''),
			item.tipo_nombre ?? '',
		]

		return values.some(value => normalizeText(value).includes(term))
	})
})

const sortedItems = computed(() => {
	const list = [...filteredItems.value]
	const sort = sortBy.value?.[0]
	if (!sort?.key)
		return list

	const direction = sort.order === 'desc' ? -1 : 1
	return list.sort((a, b) => String(a[sort.key] ?? '').localeCompare(String(b[sort.key] ?? '')) * direction)
})

const pagedItems = computed(() => {
	const start = (page.value - 1) * itemsPerPage.value
	return sortedItems.value.slice(start, start + itemsPerPage.value)
})

const buildQuery = () => {
	const params = new URLSearchParams()
	params.set('page', String(page.value))
	params.set('per_page', String(itemsPerPage.value))
	if (search.value)
		params.set('search', search.value)

	const s = sortBy.value?.[0]
	if (s?.key)
		params.set('sort_by', s.key)
	if (s?.order)
		params.set('sort_dir', s.order)

	return params.toString()
}

const load = async () => {
	loading.value = true
	error.value = null

	try {
		if (!API_READY) {
			items.value = []
			total.value = 0
			return
		}

		const response = await fetch(`/modelo-contrato?${buildQuery()}`, {
			headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
			credentials: 'same-origin',
		})

		const json = await response.json()
		if (!response.ok)
			throw new Error(json?.message || `HTTP ${response.status}`)

		items.value = json?.data?.data || json?.data || []
		total.value = json?.data?.total || items.value.length
	}
	catch (e) {
		error.value = e?.message || 'No se pudo cargar el listado de modelos de contrato.'
		items.value = []
		total.value = 0
	}
	finally {
		loading.value = false
	}
}

load()

watchDebounced(search, () => {
	page.value = 1
}, { debounce: 350 })

watch([page, itemsPerPage, sortBy], () => {
	page.value = Math.max(1, page.value)
})

// ── Dialog + formulario dinámico (Modelo_Contrato) ─────────────────────────
const dialogRegistrar = ref(false)
const guardando = ref(false)
const errorDialog = ref(null)
const successDialog = ref('')
const formRef = ref(null)
const archivoModelo = ref(null)
const preparedSubmission = ref(null)
const csrfToken = ref('')
const accionEnCursoId = ref(null)
const dialogConfirmEstado = ref(false)
const modeloPendienteEstado = ref(null)
const estadoPendiente = ref(0)
const dialogConfirmEliminar = ref(false)
const modeloPendienteEliminar = ref(null)

const form = reactive({
	nombre: '',
	fecha_creacion: getNowLocal(),
	tipo_documento: 1,
})

const tiposDocumento = [
	{ value: 1, title: 'Contrato' },
	{ value: 2, title: 'Adenda' },
]

const fieldConfig = [
	{
		key: 'tipo_documento',
		label: 'Tipo de Documento',
		type: 'select',
		required: true,
		cols: { cols: 12, md: 4 },
		items: tiposDocumento,
	},
	{
		key: 'nombre',
		label: 'Nombre',
		type: 'text',
		required: true,
		max: 255,
		cols: { cols: 12, md: 8 },
		placeholder: 'Ej: Modelo Contrato Feria 2026',
	},
	{
		key: 'fecha_creacion',
		label: 'Fecha Creación',
		type: 'datetime',
		required: true,
		cols: { cols: 12, md: 4 },
	},
]

const rulesByField = {
	tipo_documento: [
		value => !!value || 'El tipo de documento es requerido.',
	],
	nombre: [
		value => !!String(value ?? '').trim() || 'El nombre es requerido.',
		value => String(value ?? '').trim().length <= 255 || 'Máximo 255 caracteres.',
	],
	fecha_creacion: [
		value => !!String(value ?? '').trim() || 'La fecha de creación es requerida.',
	],
}

const estadoLabel = value => Number(value) === 1 ? 'Activo' : 'Inactivo'
const estadoAccionLabel = value => Number(value) === 1 ? 'activar' : 'desactivar'

const resetForm = () => {
	form.tipo_documento = 1
	form.nombre = ''
	form.fecha_creacion = getNowLocal()
	archivoModelo.value = null
	errorDialog.value = null
	successDialog.value = ''
	preparedSubmission.value = null
}

const openDialog = () => {
	resetForm()
	dialogRegistrar.value = true
}

const closeDialog = () => {
	if (guardando.value)
		return
	dialogRegistrar.value = false
}

const normalizeFile = fileRef => {
	if (!fileRef)
		return null
	if (Array.isArray(fileRef))
		return fileRef[0] || null
	return fileRef
}

const isWordFile = file => {
	if (!file)
		return true

	const allowedMimeTypes = [
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
	]

	const fileName = String(file.name || '').toLowerCase()
	const hasValidExtension = fileName.endsWith('.doc') || fileName.endsWith('.docx')
	const hasValidMimeType = allowedMimeTypes.includes(String(file.type || '').toLowerCase())

	return hasValidExtension || hasValidMimeType
}

const validateWordFile = value => {
	const selectedFile = normalizeFile(value)
	return isWordFile(selectedFile) || 'Solo se permiten archivos Word (.doc o .docx).'
}

const validateRequiredWordFile = value => {
	const selectedFile = normalizeFile(value)
	return !!selectedFile || 'Debe seleccionar un archivo Word.'
}

const ensureCsrfToken = async () => {
	if (csrfToken.value)
		return csrfToken.value

	const response = await fetch('/csrf-token', {
		credentials: 'same-origin',
		headers: {
			Accept: 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
	})

	if (!response.ok)
		throw new Error('No se pudo obtener el token CSRF.')

	const json = await response.json()
	csrfToken.value = String(json?.token || '')

	if (!csrfToken.value)
		throw new Error('No se pudo obtener el token CSRF.')

	return csrfToken.value
}

const openEstadoDialog = (item, nextValue) => {
	error.value = null
	if (Number(nextValue) !== 1) {
		error.value = 'Siempre debe existir un modelo de contrato activo. No se puede inhabilitar.'
		return
	}

	modeloPendienteEstado.value = item
	estadoPendiente.value = Number(nextValue) === 1 ? 1 : 0
	dialogConfirmEstado.value = true
}

const closeEstadoDialog = () => {
	if (accionEnCursoId.value)
		return

	dialogConfirmEstado.value = false
	modeloPendienteEstado.value = null
	estadoPendiente.value = 0
}

const confirmarCambioEstado = async () => {
	if (!modeloPendienteEstado.value)
		return

	error.value = null
	const id = modeloPendienteEstado.value.id_modelo_contrato
	accionEnCursoId.value = id

	try {
		const token = await ensureCsrfToken()
		const response = await fetch(`/modelo-contrato/${id}/estado`, {
			method: 'PATCH',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': token,
			},
			body: JSON.stringify({ estado: estadoPendiente.value }),
		})

		const json = await response.json()
		if (!response.ok)
			throw new Error(json?.message || 'No se pudo actualizar el estado del modelo.')

		await load()
		accionEnCursoId.value = null
		closeEstadoDialog()
	}
	catch (e) {
		error.value = e?.message || 'No se pudo actualizar el estado del modelo.'
	}
	finally {
		if (accionEnCursoId.value === id)
			accionEnCursoId.value = null
	}
}

const descargarModelo = item => {
	const id = item?.id_modelo_contrato
	if (!id)
		return

	window.location.href = `/modelo-contrato/${id}/download`
}

const openEliminarDialog = item => {
	modeloPendienteEliminar.value = item
	dialogConfirmEliminar.value = true
}

const closeEliminarDialog = () => {
	if (accionEnCursoId.value)
		return

	dialogConfirmEliminar.value = false
	modeloPendienteEliminar.value = null
}

const confirmarEliminar = async () => {
	if (!modeloPendienteEliminar.value)
		return

	error.value = null
	const id = modeloPendienteEliminar.value.id_modelo_contrato
	accionEnCursoId.value = id

	try {
		const token = await ensureCsrfToken()
		const response = await fetch(`/modelo-contrato/${id}`, {
			method: 'DELETE',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': token,
			},
		})

		const json = await response.json()
		if (!response.ok)
			throw new Error(json?.message || 'No se pudo eliminar el modelo de contrato.')

		await load()
		accionEnCursoId.value = null
		closeEliminarDialog()
	}
	catch (e) {
		error.value = e?.message || 'No se pudo eliminar el modelo de contrato.'
	}
	finally {
		if (accionEnCursoId.value === id)
			accionEnCursoId.value = null
	}
}

const formatDate = value => {
	if (!value)
		return '—'
	try {
		return new Date(value).toLocaleString('es-BO', {
			day: '2-digit',
			month: '2-digit',
			year: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		})
	}
	catch {
		return value
	}
}

const formatPayloadDate = value => {
	if (!value)
		return null
	return String(value).replace('T', ' ')
}

const prepareFormObject = () => {
	const selectedFile = normalizeFile(archivoModelo.value)

	const payload = {
		nombre: String(form.nombre || '').trim(),
		fecha_creacion: formatPayloadDate(form.fecha_creacion),
		estado: Number(form.estado) === 1 ? 1 : 0,
	}

	return {
		payload,
		file: selectedFile
			? {
				name: selectedFile.name,
				size: selectedFile.size,
				type: selectedFile.type,
			}
			: null,
		generatedAt: new Date().toISOString(),
	}
}

const guardarModelo = async () => {
	errorDialog.value = null
	successDialog.value = ''

	const { valid } = await formRef.value.validate()
	if (!valid)
		return

	guardando.value = true
	try {
		const selectedFile = normalizeFile(archivoModelo.value)
		if (!selectedFile) {
			errorDialog.value = 'Debe seleccionar un archivo Word.'
			return
		}

		const token = await ensureCsrfToken()

		const formData = new FormData()
		formData.append('nombre', String(form.nombre || '').trim())
		formData.append('fecha_creacion', form.fecha_creacion)
		formData.append('tipo_documento', form.tipo_documento)
		formData.append('archivo_modelo', selectedFile)

		const response = await fetch('/modelo-contrato', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'X-CSRF-TOKEN': token,
			},
			body: formData,
		})

		let json = null
		const contentType = response.headers.get('content-type') || ''
		if (contentType.includes('application/json'))
			json = await response.json()

		if (!response.ok)
			throw new Error(json?.message || (response.status === 419 ? 'La sesión expiró. Recargue la página e intente nuevamente.' : 'No se pudo registrar el modelo de contrato.'))

		preparedSubmission.value = prepareFormObject()
		successDialog.value = json?.message || 'Modelo de contrato registrado correctamente.'
		await load()
		resetForm()
		dialogRegistrar.value = false
	}
	catch (e) {
		errorDialog.value = e?.message || 'No se pudo preparar la información del formulario.'
	}
	finally {
		guardando.value = false
	}
}

const updateOptions = ({ page: p, itemsPerPage: pp, sortBy: sb }) => {
	if (p)
		page.value = p
	if (pp)
		itemsPerPage.value = sanitizeItemsPerPage(pp)
	if (sb?.length)
		sortBy.value = sb
}
</script>

<template>
	<section>
		<VCard id="feria-modelo-contrato-list">
			<VCardText class="d-flex justify-space-between align-center flex-wrap gap-4">
				<div class="d-flex align-center gap-4 flex-wrap">
					<div class="d-flex align-center gap-2">
						<span>Mostrar</span>
						<AppSelect
							:model-value="itemsPerPage"
							:items="itemsPerPageOptions.map(value => ({ value, title: String(value) }))"
							style="inline-size: 5.5rem;"
							@update:model-value="itemsPerPage = sanitizeItemsPerPage($event)"
						/>
					</div>
				</div>

				<div class="d-flex align-center flex-wrap gap-4">
					<AppTextField
						v-model="search"
						placeholder="Buscar modelo..."
						append-inner-icon="tabler-search"
						single-line
						hide-details
						dense
						outlined
						style="min-inline-size: 240px;"
					/>

					<VBtn
						color="primary"
						prepend-icon="tabler-plus"
						@click="openDialog"
					>
						Registrar Modelo
					</VBtn>
				</div>
			</VCardText>

			<VDivider />

			<VAlert
				v-if="error"
				type="error"
				class="ma-4"
				closable
				@click:close="error = null"
			>
				{{ error }}
			</VAlert>

			<VDataTableServer
				v-model:items-per-page="itemsPerPage"
				v-model:page="page"
				:items-length="filteredItems.length"
				:items-per-page-options="itemsPerPageOptions"
				:headers="headers"
				:items="pagedItems"
				:loading="loading"
				item-value="id_modelo_contrato"
				class="text-no-wrap"
				@update:options="updateOptions"
			>
				<template #item.nombre="{ item }">
					<span class="font-weight-medium text-high-emphasis">{{ item.nombre || '—' }}</span>
				</template>

				<template #item.tipo_nombre="{ item }">
					<VChip
						:color="item.tipo === 1 ? 'info' : 'warning'"
						variant="tonal"
						size="small"
					>
						{{ item.tipo_nombre || 'Desconocido' }}
					</VChip>
				</template>

				<template #item.fecha_creacion="{ item }">
					{{ formatDate(item.fecha_creacion) }}
				</template>

				<template #item.actions="{ item }">
					<VBtn
						icon
						size="small"
						variant="text"
						color="primary"
						@click="descargarModelo(item)"
					>
						<VIcon icon="tabler-download" />
						<VTooltip activator="parent">Descargar documento</VTooltip>
					</VBtn>

					<VBtn
						icon
						size="small"
						variant="text"
						color="error"
						:loading="accionEnCursoId === item.id_modelo_contrato"
						:disabled="accionEnCursoId === item.id_modelo_contrato"
						@click="openEliminarDialog(item)"
					>
						<VIcon icon="tabler-trash" />
						<VTooltip activator="parent">Eliminar modelo</VTooltip>
					</VBtn>
				</template>

				<template #no-data>
					<div class="py-6 text-center text-medium-emphasis">
						<VIcon icon="tabler-file-off" size="32" class="mb-2 d-block mx-auto" />
						No existen modelos de contrato registrados
					</div>
				</template>
			</VDataTableServer>
		</VCard>

		<VDialog v-model="dialogRegistrar" max-width="900" persistent scrollable>
			<VCard class="solicitud-dialog-card">
				<VCardTitle class="solicitud-dialog-header d-flex align-center pa-5">
					<div class="solicitud-dialog-icon me-3">
						<VIcon icon="tabler-file-description" size="24" />
					</div>
					<div>
						<div class="text-h5 font-weight-bold">Registrar Modelo de Contrato</div>
						<div class="text-caption text-medium-emphasis mt-1">Configuración inicial del modelo</div>
					</div>
					<VSpacer />
					<VBtn icon variant="tonal" size="small" :disabled="guardando" @click="closeDialog">
						<VIcon icon="tabler-x" />
					</VBtn>
				</VCardTitle>

				<VDivider />

				<VCardText class="pa-5 pt-4 solicitud-dialog-body" style="max-height: 78vh; overflow-y: auto;">
					<VAlert
						v-if="errorDialog"
						type="error"
						density="compact"
						class="mb-4"
						closable
						@click:close="errorDialog = null"
					>
						{{ errorDialog }}
					</VAlert>

					<VAlert
						v-if="successDialog"
						type="success"
						density="compact"
						class="mb-4"
						closable
						@click:close="successDialog = ''"
					>
						{{ successDialog }}
					</VAlert>

					<VForm ref="formRef" @submit.prevent="guardarModelo">
						<VRow>
							<VCol
								v-for="field in fieldConfig"
								:key="field.key"
								:cols="field.cols.cols"
								:md="field.cols.md"
							>
								<AppTextField
									v-if="field.type === 'text'"
									v-model="form[field.key]"
									:label="`${field.label}${field.required ? ' *' : ''}`"
									:placeholder="field.placeholder"
									:maxlength="field.max"
									:counter="field.max"
									:rules="rulesByField[field.key] || []"
									:disabled="guardando"
								/>

								<AppSelect
									v-else-if="field.type === 'select'"
									v-model="form[field.key]"
									:label="`${field.label}${field.required ? ' *' : ''}`"
									:items="field.items"
									:rules="rulesByField[field.key] || []"
									:disabled="guardando"
									variant="outlined"
									density="comfortable"
								/>

								<AppDateTimePicker
									v-else-if="field.type === 'datetime'"
									v-model="form[field.key]"
									placeholder="YYYY-MM-DD HH:mm"
									:label="`${field.label}${field.required ? ' *' : ''}`"
									:rules="rulesByField[field.key] || []"
									:config="{ enableTime: true, time_24hr: true, dateFormat: 'Y-m-d H:i', position: 'auto right', locale: Spanish }"
									variant="outlined"
									density="comfortable"
									:disabled="guardando"
								/>
							</VCol>
							<VCol cols="12">
                <VFileInput
									v-model="archivoModelo"
									label="Archivo del modelo (Word .doc / .docx)"
									prepend-icon="tabler-paperclip"
									accept=".doc,.docx,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
									show-size
									clearable
									variant="outlined"
									density="comfortable"
									:rules="[validateRequiredWordFile, validateWordFile]"
									hint="Solo se permiten archivos Word (.doc y .docx)."
									persistent-hint
									:disabled="guardando"
								/>
							</VCol>
						</VRow>
					</VForm>

					<VCard v-if="preparedSubmission" variant="tonal" class="mt-4">
						<VCardText>
							<p class="text-caption mb-2">Objeto preparado para integración:</p>
							<pre class="text-caption mb-0">{{ JSON.stringify(preparedSubmission, null, 2) }}</pre>
						</VCardText>
					</VCard>
				</VCardText>

				<VDivider />

				<VCardActions class="solicitud-dialog-actions pa-5 d-flex justify-end gap-2">
					<VBtn variant="tonal" color="secondary" :disabled="guardando" @click="closeDialog">
						Cancelar
					</VBtn>

					<VBtn color="primary" prepend-icon="tabler-device-floppy" :loading="guardando" @click="guardarModelo">
						Guardar
					</VBtn>
				</VCardActions>
			</VCard>
		</VDialog>

		<VDialog v-model="dialogConfirmEstado" max-width="560" persistent>
			<VCard class="confirm-dialog-card">
				<VCardTitle class="confirm-dialog-header d-flex align-center pa-5">
					<div class="confirm-dialog-icon me-3" :class="estadoPendiente === 1 ? 'is-success' : 'is-warning'">
						<VIcon :icon="estadoPendiente === 1 ? 'tabler-toggle-right' : 'tabler-toggle-left'" size="22" />
					</div>
					<div>
						<div class="text-h6 font-weight-bold">Confirmar cambio de estado</div>
						<div class="text-caption text-medium-emphasis mt-1">Acción sobre disponibilidad del modelo</div>
					</div>
				</VCardTitle>
				<VDivider />
				<VCardText class="confirm-dialog-body pa-5">
					<p class="mb-2">
						Se va a {{ estadoAccionLabel(estadoPendiente) }} el modelo
						<strong>{{ modeloPendienteEstado?.nombre || 'este modelo' }}</strong>
						y quedará en estado
						<strong>{{ estadoPendiente === 1 ? 'Activo' : 'Inactivo' }}</strong>.
					</p>
					<p class="mb-2">
						{{ estadoPendiente === 1
							? 'Al activar este modelo, será el disponible para la elaboración de nuevos contratos según la feria.'
							: 'Al desactivar este modelo, dejará de estar disponible para nuevos contratos.' }}
					</p>
					<p class="mb-0 text-medium-emphasis">¿Desea continuar con esta acción?</p>
				</VCardText>
				<VDivider />
				<VCardActions class="confirm-dialog-actions pa-5 d-flex justify-end gap-2">
					<VBtn variant="tonal" color="secondary" :disabled="!!accionEnCursoId" @click="closeEstadoDialog">
						Cancelar
					</VBtn>
					<VBtn :color="estadoPendiente === 1 ? 'success' : 'warning'" :loading="!!accionEnCursoId" @click="confirmarCambioEstado">
						{{ estadoPendiente === 1 ? 'Activar' : 'Desactivar' }}
					</VBtn>
				</VCardActions>
			</VCard>
		</VDialog>

		<VDialog v-model="dialogConfirmEliminar" max-width="560" persistent>
			<VCard class="confirm-dialog-card">
				<VCardTitle class="confirm-dialog-header d-flex align-center pa-5">
					<div class="confirm-dialog-icon is-danger me-3">
						<VIcon icon="tabler-alert-triangle" size="22" />
					</div>
					<div>
						<div class="text-h6 font-weight-bold">Confirmar eliminación</div>
						<div class="text-caption text-medium-emphasis mt-1">Acción irreversible</div>
					</div>
				</VCardTitle>
				<VDivider />
				<VCardText class="confirm-dialog-body pa-5">
					Se eliminará el modelo
					<strong>{{ modeloPendienteEliminar?.nombre || '' }}</strong>
					junto con su archivo.
					Si este modelo ya está asignado a una feria, la operación será bloqueada.
				</VCardText>
				<VDivider />
				<VCardActions class="confirm-dialog-actions pa-5 d-flex justify-end gap-2">
					<VBtn variant="tonal" color="secondary" :disabled="!!accionEnCursoId" @click="closeEliminarDialog">
						Cancelar
					</VBtn>
					<VBtn color="error" :loading="!!accionEnCursoId" @click="confirmarEliminar">
						Eliminar
					</VBtn>
				</VCardActions>
			</VCard>
		</VDialog>
	</section>
</template>

<style scoped>
.solicitud-dialog-card {
	overflow: hidden;
	border-radius: 16px;
}

.solicitud-dialog-header {
	background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.solicitud-dialog-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	border-radius: 12px;
	background: rgba(var(--v-theme-primary), 0.16);
	color: rgb(var(--v-theme-primary));
}

.solicitud-dialog-body {
	background: rgba(var(--v-theme-on-surface), 0.01);
}

.solicitud-section-title {
	display: flex;
	align-items: center;
	font-size: 0.88rem;
	font-weight: 700;
	letter-spacing: 0.02em;
	text-transform: uppercase;
	color: rgba(var(--v-theme-on-surface), 0.7);
}

.solicitud-dialog-actions {
	background: rgba(var(--v-theme-primary), 0.03);
}

.confirm-dialog-card {
	overflow: hidden;
	border-radius: 14px;
}

.confirm-dialog-header {
	background: linear-gradient(135deg, rgba(var(--v-theme-primary), 0.11), rgba(var(--v-theme-primary), 0.04));
}

.confirm-dialog-body {
	background: rgba(var(--v-theme-on-surface), 0.01);
}

.confirm-dialog-actions {
	background: rgba(var(--v-theme-primary), 0.03);
}

.confirm-dialog-icon {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 40px;
	height: 40px;
	border-radius: 10px;
	color: rgb(var(--v-theme-primary));
	background: rgba(var(--v-theme-primary), 0.14);
}

.confirm-dialog-icon.is-success {
	color: rgb(var(--v-theme-success));
	background: rgba(var(--v-theme-success), 0.14);
}

.confirm-dialog-icon.is-warning {
	color: rgb(var(--v-theme-warning));
	background: rgba(var(--v-theme-warning), 0.14);
}

.confirm-dialog-icon.is-danger {
	color: rgb(var(--v-theme-error));
	background: rgba(var(--v-theme-error), 0.14);
}

@media (max-width: 640px) {
	.solicitud-dialog-header,
	.solicitud-dialog-body,
	.solicitud-dialog-actions,
	.confirm-dialog-header,
	.confirm-dialog-body,
	.confirm-dialog-actions {
		padding: 16px !important;
	}
}
</style>
