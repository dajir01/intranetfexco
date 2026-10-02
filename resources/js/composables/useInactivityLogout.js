import { watch } from 'vue'
import { useIdle } from '@vueuse/core'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

export const useInactivityLogout = timeoutMinutes => {
  const auth = useAuthStore()
  const router = useRouter()
  const timeoutMs = Math.max(1, Number(timeoutMinutes || 0)) * 60 * 1000
  const idleResult = useIdle(timeoutMs, { controls: true, immediate: false })
  const idle = idleResult?.idle ?? idleResult
  const reset = typeof idleResult?.reset === 'function' ? idleResult.reset : () => {}
  const pause = typeof idleResult?.pause === 'function' ? idleResult.pause : () => {}
  const resume = typeof idleResult?.resume === 'function' ? idleResult.resume : () => {}

  watch(() => auth.isAuthenticated, isAuthenticated => {
    if (isAuthenticated) {
      reset()
      resume()
    } else {
      pause()
    }
  }, { immediate: true })

  watch(idle, async isIdle => {
    if (!isIdle || !auth.isAuthenticated)
      return

    await auth.logout()
    router.push({ path: '/login', query: { timeout: 1 } })
  })
}
