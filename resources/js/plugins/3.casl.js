import { createMongoAbility } from '@casl/ability'
import { abilitiesPlugin } from '@casl/vue'
import { canUser } from '@/utils/permissions'
import { useAuthStore } from '@/stores/auth'

/**
 * Plugin CASL para integrar el sistema de permisos custom
 * Convierte las abilities del proyecto en formato CASL
 */
export default function (app) {
  // Crear una ability vacía inicialmente
  const ability = createMongoAbility([])

  // Agregar el método can personalizado al prototipo
  app.config.globalProperties.$can = (action, subject) => {
    // Si no hay acción o sujeto, retornar true (sin restricciones)
    if (!action && !subject) {
      return true
    }

    // Obtener el usuario del store de auth usando el composable
    try {
      const authStore = useAuthStore()
      const user = authStore?.user

      if (!user) {
        return false
      }

      // Si action es una ability completa (ej: 'contratos.view')
      // usarla directamente con nuestro sistema de permisos
      if (action && !subject) {
        return canUser(user, action)
      }

      // Si viene en formato action/subject (legacy CASL)
      // convertir a nuestro formato: subject.action
      const abilityName = subject ? `${subject}.${action}` : action
      
      return canUser(user, abilityName)
    }
    catch (error) {
      // Si hay error al acceder al store (ej: aún no inicializado), denegar acceso
      return false
    }
  }

  // Instalar el plugin de CASL para que $can esté disponible
  app.use(abilitiesPlugin, ability)
}
