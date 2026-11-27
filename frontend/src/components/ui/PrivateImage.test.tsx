import { QueryClientProvider } from '@tanstack/react-query'
import { act, render, screen } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { PrivateImage } from '@/components/ui/PrivateImage'
import { queryClient } from '@/lib/queryClient'
import * as apiModule from '@/services/api'

const URL_API = 'http://127.0.0.1:8000/api/vehicle-deliveries/1/signature'

function renderImage() {
  return render(
    <QueryClientProvider client={queryClient}>
      <PrivateImage url={URL_API} alt="Firma de Ana Gomez" />
    </QueryClientProvider>,
  )
}

describe('PrivateImage (P8)', () => {
  beforeEach(() => {
    // jsdom no implementa las URL `blob:`.
    URL.createObjectURL = vi.fn(() => 'blob:firma')
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    queryClient.clear()
    vi.restoreAllMocks()
  })

  it('baja la imagen con el cliente de la API (con token) y la muestra como blob', async () => {
    const getBlob = vi.spyOn(apiModule, 'getBlob').mockResolvedValue(new Blob(['png'], { type: 'image/png' }))

    renderImage()

    expect(screen.getByText('Cargando imagen…')).toBeInTheDocument()
    expect(await screen.findByRole('img', { name: 'Firma de Ana Gomez' })).toHaveAttribute('src', 'blob:firma')
    expect(getBlob).toHaveBeenCalledExactlyOnceWith(URL_API)
  })

  it('no repite la descarga al volver a montar la imagen (cada vista de la firma se audita)', async () => {
    const getBlob = vi.spyOn(apiModule, 'getBlob').mockResolvedValue(new Blob(['png']))

    const { unmount } = renderImage()
    await screen.findByRole('img')
    unmount()
    renderImage()

    expect(await screen.findByRole('img')).toHaveAttribute('src', 'blob:firma')
    expect(getBlob).toHaveBeenCalledOnce()
  })

  it('libera la URL blob cuando la caché descarta la consulta (p. ej. al cerrar sesión)', async () => {
    vi.spyOn(apiModule, 'getBlob').mockResolvedValue(new Blob(['png']))

    renderImage()
    await screen.findByRole('img')
    act(() => queryClient.clear())

    expect(URL.revokeObjectURL).toHaveBeenCalledWith('blob:firma')
  })

  it('avisa si la imagen no se pudo cargar, sin reintentar', async () => {
    const getBlob = vi
      .spyOn(apiModule, 'getBlob')
      .mockRejectedValue(new apiModule.ApiError('La entrega no tiene una firma disponible.', 404, 'DELIVERY_SIGNATURE_NOT_FOUND'))

    renderImage()

    expect(await screen.findByText('No se pudo cargar la imagen.')).toBeInTheDocument()
    expect(screen.queryByRole('img')).not.toBeInTheDocument()
    expect(getBlob).toHaveBeenCalledOnce()
  })
})
