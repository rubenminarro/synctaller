<?php

namespace App\Models;

use App\Enums\WithholdingStatus;
use App\Enums\WithholdingTax;
use App\Exceptions\InvoiceException;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retención de IVA o de renta registrada sobre una factura emitida a crédito
 * (M12). Cancela parte del saldo sin movimiento de caja. No se edita ni se
 * elimina: un registro mal cargado se anula.
 */
class InvoiceWithholding extends Model
{
    use Auditable;

    private const CANCELLATION_FIELDS = ['status', 'cancelled_at', 'cancelled_by', 'cancellation_reason', 'updated_at'];

    protected $fillable = [
        'invoice_id',
        'tax',
        'amount',
        'certificate_number',
        'certificate_stamp_number',
        'withholding_date',
        'status',
        'registered_by',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'tax' => WithholdingTax::class,
            'status' => WithholdingStatus::class,
            'amount' => 'decimal:2',
            'withholding_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $withholding) {
            $original = WithholdingStatus::from($withholding->getRawOriginal('status'));
            $changed = array_diff(array_keys($withholding->getDirty()), self::CANCELLATION_FIELDS);

            if ($original === WithholdingStatus::CANCELLED || $changed !== []) {
                self::immutable();
            }
        });

        static::deleting(fn () => self::immutable());
    }

    public static function immutable(): never
    {
        throw new InvoiceException(
            'Una retención registrada no se modifica ni se elimina; solo puede anularse.',
            409,
            'INVOICE_WITHHOLDING_IMMUTABLE'
        );
    }

    public function scopeRegistered(Builder $query): Builder
    {
        return $query->where('status', WithholdingStatus::REGISTERED);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by')->withTrashed();
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }
}
