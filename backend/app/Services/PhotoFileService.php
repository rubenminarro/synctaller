<?php

namespace App\Services;

use App\Exceptions\AppException;
use Illuminate\Support\Facades\Storage;

/**
 * Archivos de las fotos de recepción y de diagnóstico (P8, paso 2, historial 2.106): se guardan en el disco privado del
 * taller y solo los sirve la API, con el permiso de la pantalla. Las vistas no se auditan (cada miniatura dejaría un
 * registro).
 */
class PhotoFileService
{
    public const DISK = 'tenant_private';

    /**
     * Contenido de la foto para mostrarla. Sin archivo, lanza `$notFound` (404 con el `error_code` de cada módulo).
     *
     * @return array{content: string, mime: string}
     */
    public function image(?string $path, AppException $notFound): array
    {
        $disk = Storage::disk(self::DISK);

        if ($path === null || ! $disk->exists($path)) {
            throw $notFound;
        }

        return [
            'content' => $disk->get($path),
            'mime' => $disk->mimeType($path) ?: 'application/octet-stream',
        ];
    }
}
