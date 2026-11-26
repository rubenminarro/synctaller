<?php

namespace App\Enums;

/**
 * Estado de un taller en la base central (TD5). `provisioning` es el taller en alta (T5, paso 3; historial 2.103):
 * no atiende hasta pasar a `active`. `cancelled` es el taller dado de baja (paso 4; historial 2.104): estado final, su
 * base se conserva 5 años y responde como inexistente (TD11). El mantenimiento no es un estado: es un dato aparte
 * (`tenants.maintenance`).
 */
enum TenantStatus: string
{
    case PROVISIONING = 'provisioning';
    case ACTIVE = 'active';
    case SUSPENDED = 'suspended';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PROVISIONING => 'En alta',
            self::ACTIVE => 'Activo',
            self::SUSPENDED => 'Suspendido',
            self::CANCELLED => 'Dado de baja',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
