<?php

namespace App\Enums;

/**
 * Estado de un taller en la base central (TD5). `provisioning` es el taller en alta (T5, paso 3; historial 2.103):
 * no atiende hasta pasar a `active`. `cancelled` se agrega en el paso 4. El mantenimiento no es un estado: es un dato
 * aparte (`tenants.maintenance`).
 */
enum TenantStatus: string
{
    case PROVISIONING = 'provisioning';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::PROVISIONING => 'En alta',
            self::ACTIVE => 'Activo',
            self::SUSPENDED => 'Suspendido',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
