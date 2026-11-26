import { useSyncExternalStore, type ReactNode } from 'react'
import { IconLock } from '@tabler/icons-react'
import { tenantStatus } from '@/lib/tenantStatus'

function reload(): void {
  window.location.reload()
}

/**
 * Pantalla del taller suspendido (P9, §7.3; historial 2.104). Va fuera del router: el taller no atiende ninguna ruta,
 * ni el login ni el branding. "Reintentar" recarga la app; si el taller ya se reactivó, la sesión guardada sigue.
 */
export function TenantSuspendedPage({ onRetry = reload }: { onRetry?: () => void }) {
  return (
    <div className="page page-center">
      <div className="container container-tight py-4">
        <div className="empty">
          <div className="empty-icon">
            <IconLock size={48} stroke={1.5} aria-hidden="true" />
          </div>
          <h1 className="empty-title">Taller suspendido</h1>
          <p className="empty-subtitle text-secondary">
            El acceso a este taller está suspendido. Para más información, comuníquese con el proveedor del servicio.
          </p>
          <div className="empty-action">
            <button type="button" className="btn btn-primary" onClick={onRetry}>
              Reintentar
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

/** Muestra la app o, si la API informó que el taller está suspendido, la pantalla del taller suspendido. */
export function TenantSuspendedGate({ children }: { children: ReactNode }) {
  const suspended = useSyncExternalStore(tenantStatus.subscribe, tenantStatus.isSuspended)
  return suspended ? <TenantSuspendedPage /> : children
}
