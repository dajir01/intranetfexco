<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue'
import navItems from '@/navigation/vertical'
import { themeConfig } from '@themeConfig'
import { useAuthStore } from '@/stores/auth'

// Components
import Footer from '@/layouts/components/Footer.vue'
import NavbarThemeSwitcher from '@/layouts/components/NavbarThemeSwitcher.vue'
import UserProfile from '@/layouts/components/UserProfile.vue'
import NavBarNotifications from '@/layouts/components/NavBarNotifications.vue'
import NavBarI18n from '@core/components/I18n.vue'

// @layouts plugin
import { VerticalNavLayout } from '@layouts'

const auth = useAuthStore()

const receivedRequestsPendingCount = ref(0)
const receivedRequestsPendingCountKey = 'solicitudes-recibidas-pending-count'

const canViewReceivedRequests = computed(() => auth.can('solicitudes.recibidas'))

const readStoredReceivedRequestsPendingCount = () => {
  if (typeof window === 'undefined')
    return 0

  return Number(window.sessionStorage.getItem(receivedRequestsPendingCountKey) || 0)
}

const syncReceivedRequestsPendingCount = event => {
  if (!canViewReceivedRequests.value) {
    receivedRequestsPendingCount.value = 0

    return
  }

  const nextCount = Number(event?.detail?.count ?? readStoredReceivedRequestsPendingCount())
  receivedRequestsPendingCount.value = Number.isFinite(nextCount) && nextCount > 0 ? nextCount : 0
}

watch(() => auth.user, () => {
  syncReceivedRequestsPendingCount()
}, { immediate: true, deep: true })

onMounted(() => {
  syncReceivedRequestsPendingCount()
  window.addEventListener('solicitudes-recibidas-count-updated', syncReceivedRequestsPendingCount)
})

onUnmounted(() => {
  window.removeEventListener('solicitudes-recibidas-count-updated', syncReceivedRequestsPendingCount)
})

const filterNavItems = items => {
  const filtered = items
    .map(item => {
      if (item.meta?.ability && !auth.can(item.meta.ability)) return null

      if (item.children) {
        const children = filterNavItems(item.children)
        if (!children.length) return null
        return { ...item, children }
      }

      return item
    })
    .filter(Boolean)

  // Eliminar headings que no preceden a ningún ítem válido
  const cleaned = []
  let pendingHeading = null

  filtered.forEach(entry => {
    if (entry.heading) {
      pendingHeading = entry
      return
    }

    if (pendingHeading) {
      cleaned.push(pendingHeading)
      pendingHeading = null
    }

    cleaned.push(entry)
  })

  return cleaned
}

const decorateNavItems = items => items.map(item => {
  if (item.heading)
    return item

  const badgeContent = item.title === 'Solicitudes Recibidas' && receivedRequestsPendingCount.value > 0
    ? String(receivedRequestsPendingCount.value)
    : item.badgeContent

  const badgeClass = item.title === 'Solicitudes Recibidas' && receivedRequestsPendingCount.value > 0
    ? 'bg-error text-white'
    : item.badgeClass

  if (item.children) {
    return {
      ...item,
      children: decorateNavItems(item.children),
      badgeContent,
      badgeClass,
    }
  }

  return {
    ...item,
    badgeContent,
    badgeClass,
  }
})

const filteredNavItems = computed(() => decorateNavItems(filterNavItems(navItems)))
</script>

<template>
  <VerticalNavLayout :nav-items="filteredNavItems">
    <!-- 👉 navbar -->
    <template #navbar="{ toggleVerticalOverlayNavActive }">
      <div class="d-flex h-100 align-center">
        <IconBtn
          id="vertical-nav-toggle-btn"
          class="ms-n3 d-lg-none"
          @click="toggleVerticalOverlayNavActive(true)"
        >
          <VIcon
            size="26"
            icon="tabler-menu-2"
          />
        </IconBtn>

        <NavbarThemeSwitcher />

        <VSpacer />

        <NavBarI18n
          v-if="themeConfig.app.i18n.enable && themeConfig.app.i18n.langConfig?.length"
          :languages="themeConfig.app.i18n.langConfig"
        />
        <NavBarNotifications class="me-1" />
        <UserProfile />
      </div>
    </template>

    <!-- 👉 Pages -->
    <slot />

    <!-- 👉 Footer -->
    <template #footer>
      <Footer />
    </template>

    <!-- 👉 Customizer -->
    <!-- <TheCustomizer /> -->
  </VerticalNavLayout>
</template>
