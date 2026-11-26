<?php

namespace App\Enums;

/**
 * Resultado de una corrida de `tenants:migrate` en un taller (T5, paso 1; historial 2.101). `running` queda si el
 * proceso se cortó antes de terminar: no cuenta como fallida para `--failed`.
 */
enum TenantMigrationRunStatus: string
{
    case RUNNING = 'running';
    case SUCCEEDED = 'succeeded';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::RUNNING => 'En curso',
            self::SUCCEEDED => 'Correcta',
            self::FAILED => 'Fallida',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
