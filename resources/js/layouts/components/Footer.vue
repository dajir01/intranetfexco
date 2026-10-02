<template>
  <div class="h-100 d-flex align-center justify-md-space-between justify-center">
    <!-- 👉 Footer: left content -->
    <span class="d-flex align-center text-medium-emphasis">
      &copy;
      {{ new Date().getFullYear() }}
      Desarrollado
      <!-- <VIcon
        icon="tabler-heart-filled"
        color="error"
        size="1.25rem"
        class="mx-1"
      /> -->
      por <a
        href="https://fexco.com.bo"
        target="_blank"
        rel="noopener noreferrer"
        class="text-primary ms-1"
      >FEXCO</a>
    </span>
    <!-- 👉 Footer: right content -->
    <span class="d-md-flex gap-x-4 text-primary d-none">
      <a>Sistema Administracion FEXCO</a>
    </span>
  </div>
</template>
