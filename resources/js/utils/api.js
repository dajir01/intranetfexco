import { ofetch } from 'ofetch'

export const $api = ofetch.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  async onRequest({ options }) {
    const accessToken = useCookie('accessToken').value
    if (accessToken)
      options.headers.append('Authorization', `Bearer ${accessToken}`)
  },
  onResponseError({ response }) {
    if (response && response.status === 401) {
      // Redirige al login si la sesión ha caducado
      window.location.href = '/login?timeout=1';
    }
  },
})
