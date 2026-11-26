/**
 * Estado del taller visto desde la API (P9; historial 2.104). Un taller suspendido responde `403 TENANT_SUSPENDED` en
 * todas sus rutas, también el login y el branding: el cliente HTTP lo marca acá y la raíz de la app muestra la pantalla
 * de taller suspendido en lugar del router. No se borra el token: al reactivar el taller, la sesión sigue.
 */
export const TENANT_SUSPENDED = 'TENANT_SUSPENDED'

let suspended = false
const listeners = new Set<() => void>()

function notify(): void {
  listeners.forEach((listener) => listener())
}

export const tenantStatus = {
  isSuspended: (): boolean => suspended,

  markSuspended(): void {
    if (suspended) return
    suspended = true
    notify()
  },

  /** Para `useSyncExternalStore`. */
  subscribe(listener: () => void): () => void {
    listeners.add(listener)
    return () => listeners.delete(listener)
  },

  /** Solo para los tests: el estado vive en el módulo. */
  reset(): void {
    suspended = false
    notify()
  },
}
