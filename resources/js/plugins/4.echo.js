import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

const getCsrfToken = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''

export default function () {
  if (typeof window === 'undefined' || window.Echo)
    return

  const reverbKey = import.meta.env.VITE_REVERB_APP_KEY
  const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY
  const echoEnabledEnv = String(import.meta.env.VITE_ECHO_ENABLED ?? '').toLowerCase()
  const hasExplicitEchoFlag = echoEnabledEnv !== ''
  const isEchoEnabled = hasExplicitEchoFlag
    ? ['1', 'true', 'yes', 'on'].includes(echoEnabledEnv)
    : Boolean(reverbKey || pusherKey)

  if (!isEchoEnabled)
    return

  const scheme = import.meta.env.VITE_REVERB_SCHEME || window.location.protocol.replace(':', '') || 'http'
  const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname
  const port = Number(import.meta.env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80))
  const forceTLS = scheme === 'https'

  window.Pusher = Pusher

  if (reverbKey) {
    window.Echo = new Echo({
      broadcaster: 'reverb',
      key: reverbKey,
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS,
      enabledTransports: ['ws', 'wss'],
      withCredentials: true,
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      },
    })

    return
  }

  if (pusherKey) {
    window.Echo = new Echo({
      broadcaster: 'pusher',
      key: pusherKey,
      cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
      wsHost: import.meta.env.VITE_PUSHER_HOST || host,
      wsPort: Number(import.meta.env.VITE_PUSHER_PORT || 80),
      wssPort: Number(import.meta.env.VITE_PUSHER_PORT || 443),
      forceTLS: (import.meta.env.VITE_PUSHER_SCHEME || 'https') === 'https',
      enabledTransports: ['ws', 'wss'],
      withCredentials: true,
      authEndpoint: '/broadcasting/auth',
      auth: {
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': getCsrfToken(),
        },
      },
    })
  }
}
