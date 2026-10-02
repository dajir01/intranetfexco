<script setup>
import { computed } from 'vue'
import { useAuthStore } from '@/stores/auth'
import avatar1 from '@images/avatars/avatar-1.png'

const router = useRouter()
const authStore = useAuthStore()

const displayName = computed(() => authStore.user?.nombre_usuario || authStore.user?.nick_usuario || 'Usuario')
const displayRole = computed(() => authStore.user?.nivel_usuario || 'Usuario')
const displayArea = computed(() => authStore.user?.area || authStore.user?.area_nombre || 'Sin área asignada')

const handleLogout = async () => {
  try {
    await authStore.logout()
    await router.push('/login')
  }
  catch (error) {
    console.error('Logout failed', error)
  }
}
</script>

<template>
  <VBadge
    dot
    location="bottom right"
    offset-x="3"
    offset-y="3"
    bordered
    color="success"
  >
    <VAvatar
      class="cursor-pointer"
      color="primary"
      variant="tonal"
    >
      <!-- <VImg :src="avatar1" /> -->
      <VAvatar
        color="info"
        icon="tabler-user"
      />

      <!-- SECTION Menu -->
      <VMenu
        activator="parent"
        width="230"
        location="bottom end"
        offset="14px"
      >
        <VList>
          <!-- 👉 User Avatar & Name -->
          <VListItem>
            <template #prepend>
              <VListItemAction start>
                <VBadge
                  dot
                  location="bottom right"
                  offset-x="3"
                  offset-y="3"
                  color="success"
                >
                  <VAvatar
                    color="info"
                    icon="tabler-user"
                  />
                </VBadge>
              </VListItemAction>
            </template>

            <VListItemTitle class="font-weight-semibold">
              {{ displayName }}
            </VListItemTitle>
            <VListItemSubtitle>
              {{ displayArea }}
            </VListItemSubtitle>
          </VListItem>

          <VDivider class="my-2" />

          <!-- Divider -->
          <VDivider class="my-2" />

          <!-- 👉 Logout -->
          <VListItem @click="handleLogout">
            <template #prepend>
              <VIcon
                class="me-2"
                icon="tabler-logout"
                size="22"
              />
            </template>

            <VListItemTitle>Cerrar sesión</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <!-- !SECTION -->
    </VAvatar>
  </VBadge>
</template>
