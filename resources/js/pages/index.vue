<script setup>
definePage({
  meta: {
    requiresAuth: true,
  },
})
</script>

<template>
  <div>
    <VCard
      class="mb-6"
      title="Bienvenidos al Sistema Intranet de FEXCO"
    >
      <VCardText>
        Este sistema está diseñado para el control y gestión integral del inventario de FEXCO, 
        permitiendo un mejor seguimiento de productos, ingresos, salidas y movimientos de activos fijos y consumibles.
      </VCardText>
      <VCardText>
        Utilice el menú lateral para acceder a las diferentes funcionalidades del sistema y mantener 
        un control eficiente de todos los recursos de la organización.
      </VCardText>
    </VCard>
  </div>
</template>
