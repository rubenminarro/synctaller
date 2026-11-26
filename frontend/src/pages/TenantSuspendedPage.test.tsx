import { act, render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { tenantStatus } from '@/lib/tenantStatus'
import { TenantSuspendedGate, TenantSuspendedPage } from '@/pages/TenantSuspendedPage'

describe('TenantSuspendedGate', () => {
  afterEach(() => {
    act(() => tenantStatus.reset())
  })

  it('muestra la app mientras el taller atiende', () => {
    render(
      <TenantSuspendedGate>
        <p>Contenido de la app</p>
      </TenantSuspendedGate>,
    )

    expect(screen.getByText('Contenido de la app')).toBeInTheDocument()
    expect(screen.queryByRole('heading', { name: 'Taller suspendido' })).not.toBeInTheDocument()
  })

  it('reemplaza la app por la pantalla cuando la API informa la suspensión', () => {
    render(
      <TenantSuspendedGate>
        <p>Contenido de la app</p>
      </TenantSuspendedGate>,
    )

    act(() => tenantStatus.markSuspended())

    expect(screen.getByRole('heading', { name: 'Taller suspendido' })).toBeInTheDocument()
    expect(screen.queryByText('Contenido de la app')).not.toBeInTheDocument()
  })
})

describe('TenantSuspendedPage', () => {
  it('reintenta al pulsar el botón', async () => {
    const onRetry = vi.fn()
    render(<TenantSuspendedPage onRetry={onRetry} />)

    await userEvent.click(screen.getByRole('button', { name: 'Reintentar' }))

    expect(onRetry).toHaveBeenCalledOnce()
  })
})
