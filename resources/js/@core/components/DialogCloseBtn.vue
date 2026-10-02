<script setup>
const props = defineProps({
  icon: {
    type: String,
    required: false,
    default: 'tabler-x',
  },
  iconSize: {
    type: String,
    required: false,
    default: '20',
  },
  ariaLabel: {
    type: String,
    required: false,
    default: 'Cerrar diálogo',
  },
})
</script>

<template>
  <IconBtn
    variant="elevated"
    size="30"
    :ripple="false"
    class="v-dialog-close-btn"
    :aria-label="props.ariaLabel"
    :title="props.ariaLabel"
  >
    <VIcon
      :icon="props.icon"
      :size="props.iconSize"
    />
  </IconBtn>
</template>
