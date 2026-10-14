<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useEditor, EditorContent } from '@tiptap/vue-3'
import StarterKit from '@tiptap/starter-kit'
import Underline from '@tiptap/extension-underline'
import Link from '@tiptap/extension-link'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()

// Detectar si es edición o creación
const noticiaId = computed(() => route.query.id)
const esEdicion = computed(() => !!noticiaId.value)
const canViewNoticias = computed(() => auth.can('noticias.view'))
const canCreateNoticias = computed(() => auth.can('noticias.create'))
const canUpdateNoticias = computed(() => auth.can('noticias.update'))
const canGuardar = computed(() => (esEdicion.value ? canUpdateNoticias.value : canCreateNoticias.value))

// Form data
const formRef = ref(null)
const loading = ref(false)

const form = ref({
  titulo: '',
  descripcion: '',
  foto: null,
  imagenActual: null,
})

const snackbar = ref({ show: false, color: 'success', text: '' })
const showLinkDialog = ref(false)
const linkUrl = ref('')

// Editor Tiptap
const editor = useEditor({
  extensions: [StarterKit, Underline, Link.configure({
    openOnClick: false,
  })],
  content: '',
  autofocus: false,
  onUpdate: ({ editor }) => {
    form.value.descripcion = editor.getHTML()
  },
})

// Validaciones dinámicas según modo
const rules = computed(() => ({
  titulo: [
    v => !!v || 'El título es obligatorio',
    v => !v || v.length <= 255 || 'Máximo 255 caracteres',
  ],
  foto: esEdicion.value ? [] : [
    v => !!v || 'La imagen de portada es obligatoria',
  ],
}))

// Validación de archivo
const validateFile = file => {
  if (!file) return true

  const validTypes = ['image/jpeg', 'image/png']

  if (!validTypes.includes(file.type)) {
    snackbar.value = { show: true, color: 'error', text: 'Solo se permiten imágenes JPG o PNG' }
    form.value.foto = null

    return false
  }

  const maxSize = 2 * 1024 * 1024 // 2MB

  if (file.size > maxSize) {
    snackbar.value = { show: true, color: 'error', text: 'La imagen no puede exceder 2MB' }
    form.value.foto = null

    return false
  }

  return true
}

const onFileChange = event => {
  const file = event.target.files?.[0]

  if (!file) return

  if (validateFile(file)) {
    form.value.foto = file
  }
}

// Cargar noticia para editar
const cargarNoticia = async () => {
  if (!esEdicion.value) return

  loading.value = true
  try {
    const response = await fetch(`/noticias/${noticiaId.value}`, {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const json = await response.json()

    if (!response.ok || !json.success) {
      throw new Error(json.message || 'Error al cargar la noticia')
    }

    const noticia = json.data

    form.value.titulo = noticia.titulo || ''
    form.value.descripcion = noticia.descripcion || ''
    form.value.imagenActual = noticia.imagen || null

    // Cargar contenido en el editor
    if (editor.value) {
      editor.value.setContent(noticia.descripcion || '')
    }
  } catch (error) {
    snackbar.value = { show: true, color: 'error', text: error.message || 'Error al cargar la noticia' }
    setTimeout(() => {
      router.push('/noticia/list')
    }, 2000)
  } finally {
    loading.value = false
  }
}

// Guardar o actualizar noticia
const guardar = async () => {
  if (!canGuardar.value) {
    snackbar.value = { show: true, color: 'error', text: 'No autorizado para esta acción' }

    return
  }

  // Validar formulario
  const { valid } = await formRef.value.validate()
  if (!valid) return

  // Si es creación, validar que haya foto
  if (!esEdicion.value && !form.value.foto) {
    snackbar.value = { show: true, color: 'error', text: 'La imagen de portada es obligatoria' }

    return
  }

  loading.value = true

  try {
    const formData = new FormData()

    formData.append('titulo', form.value.titulo)
    formData.append('descripcion', editor.value?.getHTML?.() || '')
    if (form.value.foto) {
      formData.append('foto', form.value.foto)
    }

    const url = esEdicion.value ? `/noticias/${noticiaId.value}` : '/noticias'
    const method = esEdicion.value ? 'PATCH' : 'POST'

    const response = await fetch(url, {
      method,
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
      },
      credentials: 'same-origin',
      body: formData,
    })

    const json = await response.json()

    if (!response.ok || !json.success) {
      throw new Error(json.message || 'Error al guardar la noticia')
    }

    const mensaje = esEdicion.value ? 'Noticia actualizada correctamente' : 'Noticia creada correctamente'

    snackbar.value = { show: true, color: 'success', text: mensaje }

    // Redirigir después de 1.5 segundos
    setTimeout(() => {
      router.push('/noticia/list')
    }, 1500)
  } catch (error) {
    snackbar.value = { show: true, color: 'error', text: error.message || 'Error al guardar la noticia' }
  } finally {
    loading.value = false
  }
}

// Agregar hipervínculo en el editor
const agregarHipervinculo = () => {
  const url = linkUrl.value.trim()

  if (!url) return

  editor.value
    .chain()
    .focus()
    .insertContent({
      type: 'text',
      text: url,
      marks: [
        {
          type: 'link',
          attrs: {
            href: url,
          },
        },
      ],
    })
    .run()

  linkUrl.value = ''
  showLinkDialog.value = false
}

// Cancelar
const cancelar = () => {
  router.push('/noticia/list')
}

const validarAcceso = () => {
  if (!canViewNoticias.value) {
    router.replace('/')

    return false
  }

  if (esEdicion.value && !canUpdateNoticias.value) {
    router.replace('/noticia/list')

    return false
  }

  if (!esEdicion.value && !canCreateNoticias.value) {
    router.replace('/noticia/list')

    return false
  }

  return true
}

onMounted(() => {
  if (!validarAcceso()) return

  cargarNoticia()
})

watch(esEdicion, () => {
  if (!validarAcceso()) return
})
</script>

<template>
  <div>
    <!-- Encabezado con botón de retorno -->
    <div class="d-flex align-center gap-3 mb-6">
      <VBtn
        icon="tabler-arrow-left"
        size="large"
        variant="tonal"
        color="primary"
        @click="cancelar"
      />
      <h2 class="text-h5 ma-0">
        <VIcon
          :icon="esEdicion ? 'tabler-pencil' : 'tabler-news'"
          class="me-2"
        />
        {{ esEdicion ? 'Editar Noticia' : 'Crear Noticia' }}
      </h2>
    </div>

    <!-- Card del Formulario -->
    <VCard>
      <VCardText>
        <p class="text-body-2 text-medium-emphasis mb-6">
          {{ esEdicion ? 'Actualice los datos de la noticia.' : 'Por favor ingrese los datos de la noticia que desea publicar.' }}
        </p>

        <!-- Loading -->
        <div
          v-if="loading && esEdicion"
          class="text-center py-8"
        >
          <VProgressCircular
            indeterminate
            color="primary"
            size="64"
          />
          <p class="text-body-1 mt-4">
            Cargando noticia...
          </p>
        </div>

        <!-- Formulario -->
        <VForm
          v-if="!loading || !esEdicion"
          ref="formRef"
          @submit.prevent="guardar"
        >
          <VRow>
            <!-- Título -->
            <VCol cols="6">
              <VTextField
                v-model="form.titulo"
                label="Título Noticia"
                placeholder="Ingrese el título de la noticia"
                :rules="rules.titulo"
                counter="255"
                maxlength="255"
                outlined
              />
            </VCol>

            <!-- Portada (Imagen) -->
            <VCol cols="6">
              <!-- Imagen actual (si es edición) -->
              <VRow
                v-if="esEdicion && form.imagenActual"
                class="mb-4"
              >
                <VCol cols="12">
                  <p class="text-subtitle-2 font-weight-medium mb-2">
                    Imagen actual:
                  </p>
                  <VImg
                    :src="form.imagenActual"
                    max-height="300"
                    class="rounded"
                  />
                </VCol>
              </VRow>

              <!-- Input para imagen -->
              <VFileInput
                accept="image/jpeg,image/png"
                :label="esEdicion ? 'Cambiar portada (960x600px - JPG o PNG, máximo 2MB)' : 'Portada (960x600px - JPG o PNG, máximo 2MB)'"
                :rules="rules.foto"
                @change="onFileChange"
              />
              <p class="text-caption text-medium-emphasis mt-2">
                La imagen será redimensionada automáticamente a 960x600 píxeles y comprimida si es necesario.
              </p>
            </VCol>

            <!-- Descripción con Editor -->
            <VCol cols="12">
              <label class="text-subtitle-2 font-weight-medium mb-2">
                Descripción
                <span class="text-error">*</span>
              </label>
              <VCard class="border rounded">
                <!-- Toolbar -->
                <VCardText
                  class="pa-3 bg-light"
                  style="border-bottom: 1px solid #e0e0e0;"
                >
                  <div class="d-flex flex-wrap gap-2 align-center">
                    <!-- Selector de Párrafo -->
                    <VSelect
                      :value="editor?.getAttributes?.('heading')?.level ? `h${editor?.getAttributes?.('heading')?.level}` : 'paragraph'"
                      :items="[
                        { title: 'Párrafo', value: 'paragraph' },
                        { title: 'Encabezado 1', value: 'h1' },
                        { title: 'Encabezado 2', value: 'h2' },
                        { title: 'Encabezado 3', value: 'h3' }
                      ]"
                      :disabled="!editor"
                      density="compact"
                      variant="outlined"
                      hide-details
                      style="width: 150px"
                      @update:model-value="(val) => {
                        if (val === 'paragraph') {
                          editor?.chain().focus().setParagraph().run()
                        } else {
                          const level = parseInt(val.charAt(1))
                          editor?.chain().focus().toggleHeading({ level }).run()
                        }
                      }"
                    />

                    <VDivider
                      vertical
                      class="mx-2"
                    />

                    <!-- Negrilla -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('bold') ? 'tonal' : 'text'"
                      :color="editor?.isActive('bold') ? 'primary' : 'default'"
                      @click="editor?.chain().focus().toggleBold().run()"
                    >
                      <VIcon icon="tabler-bold" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Negrilla (Ctrl+B)
                      </VTooltip>
                    </VBtn>

                    <!-- Itálica -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('italic') ? 'tonal' : 'text'"
                      :color="editor?.isActive('italic') ? 'primary' : 'default'"
                      @click="editor?.chain().focus().toggleItalic().run()"
                    >
                      <VIcon icon="tabler-italic" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Itálica (Ctrl+I)
                      </VTooltip>
                    </VBtn>

                    <!-- Hipervínculo -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('link') ? 'tonal' : 'text'"
                      :color="editor?.isActive('link') ? 'primary' : 'default'"
                      @click="showLinkDialog = true"
                    >
                      <VIcon icon="tabler-link" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Hipervínculo
                      </VTooltip>
                    </VBtn>

                    <VDivider
                      vertical
                      class="mx-2"
                    />

                    <!-- Viñetas -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('bulletList') ? 'tonal' : 'text'"
                      :color="editor?.isActive('bulletList') ? 'primary' : 'default'"
                      @click="editor?.chain().focus().toggleBulletList().run()"
                    >
                      <VIcon icon="tabler-list" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Viñetas
                      </VTooltip>
                    </VBtn>

                    <!-- Lista Numerada -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('orderedList') ? 'tonal' : 'text'"
                      :color="editor?.isActive('orderedList') ? 'primary' : 'default'"
                      @click="editor?.chain().focus().toggleOrderedList().run()"
                    >
                      <VIcon icon="tabler-list-numbers" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Lista Numerada
                      </VTooltip>
                    </VBtn>

                    <!-- Indentación Izquierda -->
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="default"
                      :disabled="!editor"
                      @click="editor?.chain().focus().liftListItem('listItem').run()"
                    >
                      <VIcon icon="tabler-indent-decrease" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Disminuir Sangría
                      </VTooltip>
                    </VBtn>

                    <!-- Indentación Derecha -->
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="default"
                      :disabled="!editor"
                      @click="editor?.chain().focus().sinkListItem('listItem').run()"
                    >
                      <VIcon icon="tabler-indent-increase" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Aumentar Sangría
                      </VTooltip>
                    </VBtn>

                    <VDivider
                      vertical
                      class="mx-2"
                    />

                    <!-- Blockquote -->
                    <VBtn
                      icon
                      size="small"
                      :disabled="!editor"
                      :variant="editor?.isActive('blockquote') ? 'tonal' : 'text'"
                      :color="editor?.isActive('blockquote') ? 'primary' : 'default'"
                      @click="editor?.chain().focus().toggleBlockquote().run()"
                    >
                      <VIcon icon="tabler-blockquote" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Cita
                      </VTooltip>
                    </VBtn>

                    <VDivider
                      vertical
                      class="mx-2"
                    />

                    <!-- Deshacer -->
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="default"
                      :disabled="!editor"
                      @click="editor?.chain().focus().undo().run()"
                    >
                      <VIcon icon="tabler-arrow-back-up" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Deshacer (Ctrl+Z)
                      </VTooltip>
                    </VBtn>

                    <!-- Rehacer -->
                    <VBtn
                      icon
                      size="small"
                      variant="text"
                      color="default"
                      :disabled="!editor"
                      @click="editor?.chain().focus().redo().run()"
                    >
                      <VIcon icon="tabler-arrow-forward-up" />
                      <VTooltip
                        activator="parent"
                        location="top"
                      >
                        Rehacer (Ctrl+Y)
                      </VTooltip>
                    </VBtn>
                  </div>
                </VCardText>

                <!-- Editor -->
                <VCardText
                  class="pa-0"
                  @click="editor?.chain().focus().run()"
                >
                  <EditorContent
                    :editor="editor"
                    class="tiptap-editor"
                  />
                </VCardText>
              </VCard>
              <p class="text-caption text-medium-emphasis mt-2">
                Ingrese la descripción detallada de la noticia
              </p>
            </VCol>
          </VRow>

          <!-- Botones de Acción -->
          <VRow class="mt-6">
            <VCol
              cols="12"
              class="d-flex gap-4 justify-end"
            >
              <VBtn
                color="error"
                variant="outlined"
                :disabled="loading"
                @click="cancelar"
              >
                Cancelar
              </VBtn>
              <VBtn
                color="primary"
                type="submit"
                :loading="loading"
                :disabled="loading || !canGuardar"
              >
                {{ esEdicion ? 'Guardar Cambios' : 'Guardar Noticia' }}
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Diálogo para agregar hipervínculo -->
    <VDialog
      v-model="showLinkDialog"
      max-width="500"
    >
      <VCard>
        <VCardTitle>Agregar Hipervínculo</VCardTitle>
        <VCardText>
          <p class="text-body-2 text-medium-emphasis mb-4">
            Ingrese la URL del hipervínculo
          </p>
          <VTextField
            v-model="linkUrl"
            label="URL"
            placeholder="https://ejemplo.com"
            outlined
            @keyup.enter="agregarHipervinculo"
          />
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn
            color="primary"
            variant="outlined"
            @click="showLinkDialog = false"
          >
            Cancelar
          </VBtn>
          <VBtn
            color="primary"
            :disabled="!linkUrl.trim()"
            @click="agregarHipervinculo"
          >
            Agregar
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Snackbar para mensajes -->
    <VSnackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      timeout="4000"
    >
      {{ snackbar.text }}
    </VSnackbar>
  </div>
</template>

<style scoped lang="scss">
.tiptap-editor {
  padding: 12px 16px;
  min-height: 300px;
  outline: none;
  cursor: text;

  :deep() {
    .ProseMirror {
      outline: none;
      padding: 0;
      cursor: text;

      p {
        margin-bottom: 8px;

        &:last-child {
          margin-bottom: 0;
        }
      }

      h1, h2, h3, h4, h5, h6 {
        font-weight: 600;
        margin-bottom: 8px;
        margin-top: 16px;
      }

      ul, ol {
        margin-bottom: 8px;
        padding-left: 24px;
      }

      li {
        margin-bottom: 4px;
      }

      blockquote {
        border-left: 3px solid #ccc;
        padding-left: 12px;
        margin-left: 0;
        font-style: italic;
        color: #666;
      }

      code {
        background-color: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: monospace;
      }

      pre {
        background-color: #f5f5f5;
        padding: 12px;
        border-radius: 4px;
        overflow-x: auto;

        code {
          background-color: transparent;
          padding: 0;
          color: #000;
        }
      }

      img {
        max-width: 100%;
        height: auto;
        border-radius: 4px;
        margin: 12px 0;
      }

      a {
        color: #1976d2;
        cursor: pointer;
      }

      hr {
        margin: 16px 0;
        border: none;
        border-top: 1px solid #e0e0e0;
      }
    }
  }
}

.border-error {
  border-color: #d63031 !important;
}
</style>
