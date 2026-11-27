import { useQuery } from '@tanstack/react-query'
import { PRIVATE_IMAGE_KEY } from '@/lib/queryClient'
import { getBlob } from '@/services/api'

/**
 * URL `blob:` de una imagen privada de la API (P8, historial 2.106), bajada con el token: un `<img src>` no lo envía.
 * La consulta no se repite al volver a la pantalla ni al enfocar la ventana, porque cada descarga de una firma queda
 * auditada en el backend. `queryClient` libera la URL cuando descarta la consulta.
 */
export function usePrivateImage(url: string | null) {
  const query = useQuery({
    queryKey: [PRIVATE_IMAGE_KEY, url],
    queryFn: async () => URL.createObjectURL(await getBlob(url as string)),
    enabled: url !== null,
    staleTime: Infinity,
    retry: false,
  })

  return { src: query.data ?? null, isLoading: query.isLoading, isError: query.isError }
}
