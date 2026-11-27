import { QueryClientProvider } from '@tanstack/react-query'
import { render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { PhotoGallery, type GalleryPhoto } from '@/components/ui/PhotoGallery'
import { queryClient } from '@/lib/queryClient'
import * as apiModule from '@/services/api'

const API = 'http://127.0.0.1:8000/api/receptions/1/photos'

function photo(id: number, overrides: Partial<GalleryPhoto> = {}): GalleryPhoto {
  return { id, url: `${API}/${id}/file`, original_name: `foto-${id}.jpg`, description: `Foto ${id}`, ...overrides }
}

function renderGallery(photos: GalleryPhoto[]) {
  return render(
    <QueryClientProvider client={queryClient}>
      <PhotoGallery photos={photos} invalidate={[['receptions']]} />
    </QueryClientProvider>,
  )
}

describe('PhotoGallery (P8, fotos privadas)', () => {
  beforeEach(() => {
    // jsdom no implementa las URL `blob:`.
    let next = 0
    URL.createObjectURL = vi.fn(() => `blob:foto-${++next}`)
    URL.revokeObjectURL = vi.fn()
  })

  afterEach(() => {
    queryClient.clear()
    vi.restoreAllMocks()
  })

  it('baja cada foto con el cliente de la API y la enlaza como blob, sin usar la URL de la API en el img', async () => {
    const getBlob = vi.spyOn(apiModule, 'getBlob').mockResolvedValue(new Blob(['jpg'], { type: 'image/jpeg' }))

    renderGallery([photo(1), photo(2)])

    expect(screen.getAllByText('Cargando imagen…')).toHaveLength(2)
    await waitFor(() => expect(screen.getAllByRole('img')).toHaveLength(2))
    const images = screen.getAllByRole('img')
    expect(images.map((image) => image.getAttribute('src')).sort()).toEqual(['blob:foto-1', 'blob:foto-2'])
    for (const image of images) {
      expect(image.closest('a')).toHaveAttribute('href', image.getAttribute('src'))
    }
    expect(getBlob).toHaveBeenCalledTimes(2)
    expect(getBlob).toHaveBeenCalledWith(`${API}/1/file`)
    expect(getBlob).toHaveBeenCalledWith(`${API}/2/file`)
  })

  it('avisa en la miniatura si una foto no se pudo cargar, sin afectar a las demás', async () => {
    vi.spyOn(apiModule, 'getBlob').mockImplementation(async (url: string) => {
      if (url.endsWith('/2/file')) {
        throw new apiModule.ApiError('La foto no tiene un archivo disponible.', 404, 'RECEPTION_PHOTO_FILE_NOT_FOUND')
      }
      return new Blob(['jpg'])
    })

    renderGallery([photo(1), photo(2)])

    expect(await screen.findByText('No se pudo cargar la imagen.')).toBeInTheDocument()
    expect(await screen.findByRole('img', { name: 'Foto 1' })).toBeInTheDocument()
    expect(screen.queryByRole('img', { name: 'Foto 2' })).not.toBeInTheDocument()
  })

  it('no pide nada para una foto sin URL', () => {
    const getBlob = vi.spyOn(apiModule, 'getBlob')

    renderGallery([photo(1, { url: null })])

    expect(getBlob).not.toHaveBeenCalled()
    expect(screen.queryByText('Cargando imagen…')).not.toBeInTheDocument()
    expect(screen.getByText('Foto 1')).toBeInTheDocument()
  })
})
