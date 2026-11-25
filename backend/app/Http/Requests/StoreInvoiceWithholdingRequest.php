<?php

namespace App\Http\Requests;

use App\Enums\WithholdingTax;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Registro de una retención sobre una factura a crédito (M12): impuesto, importe
 * del comprobante (guaraníes enteros), número y timbrado del comprobante y fecha.
 * El tope por saldo y la condición de la factura se verifican en
 * InvoiceWithholdingService.
 */
class StoreInvoiceWithholdingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tax' => ['required', Rule::enum(WithholdingTax::class)],
            'amount' => ['required', 'integer', 'min:1', 'max:999999999999'],
            'certificate_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/.]+$/'],
            'certificate_stamp_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/.]+$/'],
            'withholding_date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
        ];
    }

    public function messages(): array
    {
        return [
            'tax.required' => 'Indique el impuesto retenido.',
            'tax.enum' => 'El impuesto debe ser vat (IVA) o income (renta).',
            'amount.required' => 'El importe retenido es obligatorio.',
            'amount.integer' => 'El importe retenido debe ser un número entero de guaraníes.',
            'amount.min' => 'El importe retenido debe ser mayor a cero.',
            'amount.max' => 'El importe retenido es demasiado alto.',
            'certificate_number.required' => 'El número del comprobante de retención es obligatorio.',
            'certificate_number.max' => 'El número del comprobante no debe superar los 30 caracteres.',
            'certificate_number.regex' => 'El número del comprobante solo admite letras, números, guiones, barras y puntos.',
            'certificate_stamp_number.required' => 'El timbrado del comprobante de retención es obligatorio.',
            'certificate_stamp_number.max' => 'El timbrado no debe superar los 30 caracteres.',
            'certificate_stamp_number.regex' => 'El timbrado solo admite letras, números, guiones, barras y puntos.',
            'withholding_date.required' => 'La fecha de la retención es obligatoria.',
            'withholding_date.date_format' => 'La fecha debe tener el formato AAAA-MM-DD.',
            'withholding_date.before_or_equal' => 'La fecha de la retención no puede ser futura.',
        ];
    }
}
