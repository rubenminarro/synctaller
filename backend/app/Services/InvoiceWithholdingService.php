<?php

namespace App\Services;

use App\Enums\InvoiceCondition;
use App\Enums\InvoiceStatus;
use App\Enums\WithholdingStatus;
use App\Enums\WithholdingTax;
use App\Exceptions\InvoiceException;
use App\Models\Invoice;
use App\Models\InvoiceWithholding;
use App\Models\User;
use App\Support\Decimal;
use App\Support\Format;
use Illuminate\Support\Facades\DB;

/**
 * Retenciones de IVA o renta que el cliente practica al pagar una factura emitida
 * a crédito (M12, V17). Se registra el importe del comprobante: cancela parte del
 * saldo sin movimiento de caja (no entra en el resumen de caja). Un comprobante
 * puede cubrir varias facturas: se registra en cada una. Un registro mal cargado
 * se anula con motivo. Orden de bloqueo: factura → retención.
 */
class InvoiceWithholdingService
{
    /**
     * @param  array{tax: string, amount: int|string, certificate_number: string, certificate_stamp_number: string, withholding_date: string}  $data
     */
    public function register(Invoice $invoice, array $data, User $user): InvoiceWithholding
    {
        return DB::transaction(function () use ($invoice, $data, $user) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->firstOrFail();

            if ($invoice->status !== InvoiceStatus::ISSUED || $invoice->condition !== InvoiceCondition::CREDIT) {
                throw new InvoiceException('Solo se registra una retención sobre una factura emitida a crédito.', 422, 'INVOICE_NOT_WITHHOLDABLE');
            }

            if ($data['withholding_date'] < $invoice->issue_date->toDateString()) {
                throw new InvoiceException('La retención no puede ser anterior a la emisión de la factura.', 422, 'INVOICE_WITHHOLDING_DATE_BEFORE_INVOICE');
            }

            $tax = WithholdingTax::from($data['tax']);
            $duplicate = $invoice->withholdings()->registered()
                ->where('tax', $tax)
                ->where('certificate_number', $data['certificate_number'])
                ->exists();

            if ($duplicate) {
                throw new InvoiceException(
                    "El comprobante {$data['certificate_number']} ya tiene una retención de {$tax->label()} vigente en esta factura.",
                    409,
                    'INVOICE_WITHHOLDING_DUPLICATE'
                );
            }

            $amount = Decimal::of($data['amount'], 2);
            $balance = $invoice->balance();

            if (bccomp($amount, $balance, 2) > 0) {
                throw new InvoiceException(
                    'La retención ('.Format::money($amount).') supera el saldo de la factura ('.Format::money($balance).').',
                    422,
                    'INVOICE_WITHHOLDING_EXCEEDS_BALANCE'
                );
            }

            $withholding = InvoiceWithholding::create([
                'invoice_id' => $invoice->id,
                'tax' => $tax,
                'amount' => $amount,
                'certificate_number' => $data['certificate_number'],
                'certificate_stamp_number' => $data['certificate_stamp_number'],
                'withholding_date' => $data['withholding_date'],
                'status' => WithholdingStatus::REGISTERED,
                'registered_by' => $user->id,
            ]);

            return $this->load($withholding);
        });
    }

    public function cancel(InvoiceWithholding $withholding, string $reason, User $user): InvoiceWithholding
    {
        return DB::transaction(function () use ($withholding, $reason, $user) {
            Invoice::whereKey($withholding->invoice_id)->lockForUpdate()->first();
            $withholding = InvoiceWithholding::whereKey($withholding->id)->lockForUpdate()->firstOrFail();

            if ($withholding->status !== WithholdingStatus::REGISTERED) {
                throw new InvoiceException('La retención ya está anulada.', 422, 'INVOICE_WITHHOLDING_NOT_CANCELLABLE');
            }

            $withholding->update([
                'status' => WithholdingStatus::CANCELLED,
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason' => $reason,
            ]);

            return $this->load($withholding);
        });
    }

    public function load(InvoiceWithholding $withholding): InvoiceWithholding
    {
        return $withholding->load(['registrar', 'canceller']);
    }
}
