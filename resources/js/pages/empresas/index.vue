<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

definePage({
  meta: { requiresAuth: true },
})

const router = useRouter()
const auth = useAuthStore()

onMounted(() => {
  if (auth.can('empresas.ver'))
    router.replace({ name: 'empresas-list' })
  else
    router.replace('/')
})
</script>

<template>
  <VProgressCircular indeterminate color="primary" size="28" width="3" class="ma-6" />
</template>
