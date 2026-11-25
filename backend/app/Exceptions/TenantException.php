<?php

namespace App\Exceptions;

/**
 * Errores al identificar el taller por el host (T2, paso 2; reglas de negocio 6.34). Ningún mensaje lleva detalles
 * técnicos: el de la base que no responde va al log.
 */
class TenantException extends AppException
{
    public static function notFound(): self
    {
        return new self('No se encontró el taller.', 404, 'TENANT_NOT_FOUND');
    }

    public static function suspended(): self
    {
        return new self('El taller está suspendido.', 403, 'TENANT_SUSPENDED');
    }

    public static function maintenance(): self
    {
        return new self('El taller está en mantenimiento. Intente más tarde.', 503, 'TENANT_MAINTENANCE');
    }

    public static function unavailable(): self
    {
        return new self('El servicio del taller no está disponible.', 503, 'TENANT_UNAVAILABLE');
    }
}
