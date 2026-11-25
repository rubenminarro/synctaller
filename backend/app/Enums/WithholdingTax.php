<?php

namespace App\Enums;

/**
 * Impuesto retenido por el cliente al pagar una factura a crédito (M12, V17).
 */
enum WithholdingTax: string
{
    case VAT = 'vat';
    case INCOME = 'income';

    public function label(): string
    {
        return match ($this) {
            self::VAT => 'IVA',
            self::INCOME => 'Renta',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
