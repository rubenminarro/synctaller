<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Retención de IVA o renta registrada sobre una factura a crédito (M12). Importe
 * del comprobante, en guaraníes.
 */
class InvoiceWithholdingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_id' => $this->invoice_id,
            'tax' => [
                'value' => $this->tax->value,
                'label' => $this->tax->label(),
            ],
            'amount' => $this->amount,
            'certificate_number' => $this->certificate_number,
            'certificate_stamp_number' => $this->certificate_stamp_number,
            'withholding_date' => $this->withholding_date?->format('Y-m-d'),
            'status' => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'registered_by' => $this->registrar === null ? null : ['id' => $this->registrar->id, 'name' => $this->registrar->name],
            'cancelled_at' => $this->cancelled_at,
            'cancelled_by' => $this->canceller === null ? null : ['id' => $this->canceller->id, 'name' => $this->canceller->name],
            'cancellation_reason' => $this->cancellation_reason,
            'created_at' => $this->created_at,
        ];
    }
}
