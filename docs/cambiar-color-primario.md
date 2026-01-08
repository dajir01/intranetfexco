# Guía para modificar el color primario

## Valor por defecto actual
- Color principal: `#7367F0`
- Variante oscura utilizada en estados hover/pressed: `#675DD8`
- Ambos valores viven como constantes `staticPrimaryColor` y `staticPrimaryDarkenColor` en `resources/js/plugins/vuetify/theme.js` y alimentan tanto el tema claro como el oscuro.

## Cómo se propaga el color en la app
1. `resources/js/plugins/vuetify/theme.js` define la paleta base de Vuetify.
2. `resources/js/plugins/vuetify/index.js` crea la instancia de Vuetify y mezcla la paleta base con los valores guardados en cookies (`lightThemePrimaryColor`, `darkThemePrimaryColor`, etc.). Si no hay cookie, usa los valores estáticos anteriores.
3. `resources/js/App.vue` expone el color activo como variable CSS `--v-global-theme-primary`, que a su vez consumen los estilos SCSS (`resources/styles/@core/**`) para botones, navegación y componentes personalizados.
4. `resources/js/@core/components/TheCustomizer.vue` permite cambiar el color primario en tiempo real. Al elegir un color:
   - Actualiza `vuetifyTheme.themes.value`.
   - Guarda el color en cookies mediante `cookieRef`.
   - Sincroniza el color del loader inicial con `useStorage(namespaceConfig('initial-loader-color'))`.

## Escenarios de cambio
### 1. Ajustar el color por defecto del proyecto
1. Edita `resources/js/plugins/vuetify/theme.js` y cambia `staticPrimaryColor`/`staticPrimaryDarkenColor`.
2. Verifica si necesitas actualizar otros tonos derivados (por ejemplo, componentes que usan `primary-darken-1`).
3. Guarda el archivo y reinicia Vite si ya estaba corriendo para que tome el nuevo bundle.

### 2. Forzar un color específico por tema (sin depender del customizer)
1. En `resources/js/plugins/vuetify/index.js`, sustituye las llamadas a `cookieRef(..., staticPrimaryColor)` por el valor fijo deseado o por una variable de entorno propia.
2. Si quieres que solo afecte al tema claro u oscuro, ajusta la sección correspondiente dentro de `cookieThemeValues.themes.light` o `.dark`.

### 3. Cambiarlo dinámicamente desde la UI
1. Abre el panel “Theme Customizer” (botón flotante con ícono de engrane).
2. En la sección "Primary Color" elige un color predefinido o define uno personalizado.
3. Vuetify guardará el color en cookies, de modo que al recargar la página `resources/js/plugins/vuetify/index.js` aplicará los nuevos valores.
4. Si quieres volver al color por defecto, usa el botón de reset del customizer; este limpia las cookies y restablece los valores de `theme.js`.

## Puntos a considerar
- Cualquier componente/estilo que necesite el color en CSS puede usar `rgb(var(--v-global-theme-primary))` en lugar de valores hex.
- Si agregas un color nuevo al customizer, recuerda añadir el par `main/darken` en el arreglo `colors` dentro de `TheCustomizer.vue` para que el usuario pueda seleccionarlo.
- El loader inicial toma su color desde `namespaceConfig('initial-loader-color')`. Si necesitas un color fijo para el loader aunque el usuario personalice el tema, define un valor estático en `localStorage` o ajusta la lógica en `TheCustomizer.vue`.
