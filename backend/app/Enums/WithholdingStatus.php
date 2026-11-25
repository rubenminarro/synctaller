<?php

namespace App\Enums;

/**
 * Registro de una retención (M12): vigente o anulado por haberse cargado mal.
 */
enum WithholdingStatus: string
{
    case REGISTERED = 'registered';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::REGISTERED => 'Registrada',
            self::CANCELLED => 'Anulada',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
