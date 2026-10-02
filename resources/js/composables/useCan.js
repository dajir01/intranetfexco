import { useAuthStore } from '@/stores/auth'

export const useCan = () => {
  const auth = useAuthStore()

  return permission => auth.can(permission)
}
