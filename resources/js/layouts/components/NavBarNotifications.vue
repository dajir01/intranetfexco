<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const notifications = ref([])
const loading = ref(false)

const canReceiveAdminNotifications = computed(() => {
  const user = auth.user
  if (!user)
    return false

  const isActive = user.estado === null || Number(user.estado) === 1

  return isActive
})

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

const formatTime = value => {
  if (!value) return ''
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return ''

  return date.toLocaleString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  })
}

const normalizeReadState = value => {
  if (value === true || value === 1) return true
  if (value === false || value === 0 || value === null || value === undefined) return false

  const text = String(value).trim().toLowerCase()
  return text === '1' || text === 'true' || text === 'si' || text === 'sí'
}

const toUiNotification = item => {
  const id = Number(item.id)
  const payload = item.payload || {}

  const monto = payload.monto !== undefined && payload.monto !== null
    ? Number(payload.monto).toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
    : null

  const feriaId = payload.id_feria !== undefined && payload.id_feria !== null
    ? Number(payload.id_feria)
    : null

  return {
    id,
    type: item.tipo || null,
    payload,
    feriaId: Number.isFinite(feriaId) ? feriaId : null,
    title: item.titulo || 'Notificación',
    subtitle: monto ? `${item.mensaje} · Bs ${monto}` : item.mensaje,
    time: formatTime(item.created_at),
    isSeen: normalizeReadState(item.leida),
    color: normalizeReadState(item.leida) ? 'secondary' : 'primary',
  }
}

const loadNotifications = async () => {
  if (!canReceiveAdminNotifications.value) {
    notifications.value = []

    return
  }

  loading.value = true
  try {
    const response = await fetch('/notificaciones/admin?limit=30', {
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin',
    })

    const json = await response.json().catch(() => null)
    if (!response.ok || !json?.success) return

    notifications.value = (json.data?.items || []).map(toUiNotification)
  } finally {
    loading.value = false
  }
}

const playNotificationSound = async isPayment => {
  try {
    const AudioCtx = window.AudioContext || window.webkitAudioContext
    if (!AudioCtx) return
    const ctx = new AudioCtx()

    if (ctx.state === 'suspended')
      await ctx.resume()

    const notes = isPayment
      ? [{ frequency: 660, offset: 0, duration: 0.18 }, { frequency: 880, offset: 0.22, duration: 0.18 }, { frequency: 1100, offset: 0.44, duration: 0.3 }]
      : [{ frequency: 880, offset: 0, duration: 0.25 }]

    notes.forEach(({ frequency, offset, duration }) => {
      const start = ctx.currentTime + offset
      const oscillator = ctx.createOscillator()
      const gain = ctx.createGain()

      oscillator.type = isPayment ? 'triangle' : 'sine'
      oscillator.frequency.setValueAtTime(frequency, start)
      gain.gain.setValueAtTime(0.0001, start)
      gain.gain.exponentialRampToValueAtTime(isPayment ? 0.2 : 0.08, start + 0.01)
      gain.gain.exponentialRampToValueAtTime(0.0001, start + duration)

      oscillator.connect(gain)
      gain.connect(ctx.destination)
      oscillator.start(start)
      oscillator.stop(start + duration + 0.02)
    })

    window.setTimeout(() => {
      ctx.close().catch(() => {})
    }, isPayment ? 900 : 400)
  } catch (e) {
    // El navegador puede bloquear audio sin interacción previa.
  }
}

const markRead = async notificationIds => {
  const ids = Array.isArray(notificationIds) ? notificationIds : [notificationIds]

  await Promise.all(ids.map(id => fetch(`/notificaciones/admin/${id}/leer`, {
    method: 'PATCH',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    credentials: 'same-origin',
  }).catch(() => null)))

  notifications.value = notifications.value.map(item => (
    ids.includes(item.id) ? { ...item, isSeen: true, color: 'secondary' } : item
  ))
}

const markUnRead = async notificationIds => {
  const ids = Array.isArray(notificationIds) ? notificationIds : [notificationIds]

  await Promise.all(ids.map(id => fetch(`/notificaciones/admin/${id}/no-leer`, {
    method: 'PATCH',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': getCsrfToken(),
    },
    credentials: 'same-origin',
  }).catch(() => null)))

  notifications.value = notifications.value.map(item => (
    ids.includes(item.id) ? { ...item, isSeen: false, color: 'primary' } : item
  ))
}

const dismissNotification = async notificationId => {
  await markRead([notificationId])
}

const visibleNotifications = computed(() => {
  return notifications.value.filter(item => !item.isSeen)
})

const handleNotificationClick = async notification => {
  if (!notification.isSeen)
    await markRead([notification.id])

  if (notification.type === 'correspondencia_destinada') {
    const destino = notification.payload?.ruta || `/correspondencia/detalle/${notification.payload?.id_correspondencia}`

    if (destino) {
      window.location.href = destino
    }

    return
  }

  if (String(notification.type || '').startsWith('solicitud_')) {
    const destino = notification.payload?.ruta || `/solicitudes/detalle/${notification.payload?.id_proceso}`

    if (destino) {
      window.location.href = destino
    }

    return
  }

  if (notification.type === 'pago_creado') {
    const destino = notification.feriaId
      ? `/pagos/list?id_feria=${notification.feriaId}`
      : '/pagos/list'

    window.location.href = destino
  }
}

const subscribeAdminChannel = () => {
  if (!canReceiveAdminNotifications.value || !window.Echo) return

  window.Echo
    .private('admin.notifications')
    .listen('.NotificacionAdminCreada', async event => {
      playNotificationSound(event?.notificacion?.tipo === 'pago_creado')
      await loadNotifications()
    })
}

onMounted(async () => {
  await loadNotifications()
  subscribeAdminChannel()
})

onUnmounted(() => {
  if (window.Echo) {
    window.Echo.leaveChannel('private-admin.notifications')
  }
})
</script>

<template>
  <Notifications
    v-if="canReceiveAdminNotifications"
    :notifications="visibleNotifications"
    :is-loading="loading"
    @remove="dismissNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
