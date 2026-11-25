<?php

namespace App\Enums;

/**
 * Estado de un taller en la base central (TD5). `provisioning` y `cancelled` se agregan en T5. El mantenimiento no es
 * un estado: es un dato aparte (`tenants.maintenance`).
 */
enum TenantStatus: string
{
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Activo',
            self::SUSPENDED => 'Suspendido',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
