import type { ImgHTMLAttributes } from 'react'
import { usePrivateImage } from '@/lib/usePrivateImage'

interface PrivateImageProps extends Omit<ImgHTMLAttributes<HTMLImageElement>, 'src'> {
  /** URL de la API que sirve la imagen (p. ej. `signature_url`). */
  url: string
  alt: string
}

/** `<img>` de un archivo privado (P8): lo baja con el token y avisa si no se pudo cargar. */
export function PrivateImage({ url, alt, ...props }: PrivateImageProps) {
  const { src, isError } = usePrivateImage(url)

  if (isError) {
    return <span className="text-secondary">No se pudo cargar la imagen.</span>
  }

  if (!src) {
    return <span className="text-secondary">Cargando imagen…</span>
  }

  return <img src={src} alt={alt} {...props} />
}
