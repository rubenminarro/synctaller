<?php

namespace Database\Seeders;

use App\Enums\InvoiceCondition;
use App\Enums\InvoiceStatus;
use App\Enums\WithholdingTax;
use App\Models\Invoice;
use App\Models\WorkOrder;
use App\Services\InvoiceWithholdingService;
use Illuminate\Database\Seeder;

/**
 * Retenciones registradas por el cajero (M12). La factura de la aseguradora de
 * C05 (a crédito, impaga) recibe la retención de IVA sugerida (30 % del IVA) y
 * una de renta cargada por error y anulada. Va después de las facturas por
 * responsable. Idempotente: omite la factura que ya tiene retenciones.
 */
class WithholdingSeeder extends Seeder
{
    private const CASHIER = 'caja1@mail.com.py';

    public function run(InvoiceWithholdingService $withholdingService): void
    {
        $cashier = ExampleScenarios::user(self::CASHIER);

        foreach (ExampleScenarios::chronological() as $scenario) {
            $workOrder = isset($scenario['work_order']) ? BudgetSeeder::of($scenario)?->workOrder : null;

            if (! $workOrder instanceof WorkOrder) {
                continue;
            }

            $invoice = Invoice::where('work_order_id', $workOrder->id)
                ->whereNotNull('allocation_id')
                ->where('status', InvoiceStatus::ISSUED)
                ->where('condition', InvoiceCondition::CREDIT)
                ->first();

            if (! $invoice || $invoice->withholdings()->exists()) {
                continue;
            }

            $certificate = '001-001-'.str_pad((string) ($invoice->id * 10 + 1), 7, '0', STR_PAD_LEFT);

            ExampleScenarios::travel(ExampleScenarios::afterCompletion($scenario, 2, 16), function () use ($withholdingService, $invoice, $cashier, $certificate) {
                $withholdingService->register($invoice, [
                    'tax' => WithholdingTax::VAT->value,
                    'amount' => (int) $invoice->refresh()->vatWithholdingSuggestion(),
                    'certificate_number' => $certificate,
                    'certificate_stamp_number' => '17654321',
                    'withholding_date' => now()->toDateString(),
                ], $cashier);

                $wrong = $withholdingService->register($invoice->refresh(), [
                    'tax' => WithholdingTax::INCOME->value,
                    'amount' => 1000,
                    'certificate_number' => $certificate,
                    'certificate_stamp_number' => '17654321',
                    'withholding_date' => now()->toDateString(),
                ], $cashier);

                $withholdingService->cancel($wrong, 'El comprobante no trae retención de renta.', $cashier);
            });
        }
    }
}
