import { createApp } from 'vue'
import App from '@/App.vue'
import { registerPlugins } from '@core/utils/plugins'
import axios from 'axios'

// Styles
import '@core-scss/template/index.scss'
import '@styles/styles.scss'

// Configurar axios globalmente
axios.defaults.headers.common['Accept'] = 'application/json'
axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest'

let redirectingToLogin = false
const redirectToLogin = () => {
  if (redirectingToLogin || window.location.pathname === '/login')
    return

  redirectingToLogin = true
  window.location.replace('/login?timeout=1')
}

// También cubre las pantallas que usan fetch directamente en lugar de Axios.
const fetchWithSessionHandling = window.fetch.bind(window)
window.fetch = async (...args) => {
  const response = await fetchWithSessionHandling(...args)
  const requestUrl = args[0] instanceof Request ? args[0].url : args[0]

  try {
    const url = new URL(requestUrl, window.location.href)

    if (response.status === 401 && url.origin === window.location.origin && url.pathname !== '/login')
      redirectToLogin()
  }
  catch {
    // Ignore non-URL inputs; fetch will report invalid requests itself.
  }

  return response
}

// Interceptor global: si la sesión expira (401), redirigir al login.
axios.interceptors.response.use(
  response => response,
  error => {
    if (error.response?.status === 401)
      redirectToLogin()

    return Promise.reject(error)
  },
)

// Create vue app
const app = createApp(App)


// Register plugins
registerPlugins(app)

// Mount vue app
app.mount('#app')
