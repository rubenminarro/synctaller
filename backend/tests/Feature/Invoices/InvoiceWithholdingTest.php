<?php

namespace Tests\Feature\Invoices;

use App\Exceptions\InvoiceException;
use App\Models\InvoiceStamp;
use App\Models\InvoiceWithholding;

/**
 * Retenciones de IVA o renta sobre facturas a crédito (M12): importe del
 * comprobante cargado por el cajero, con la sugerencia del 30 % del IVA, hasta el
 * saldo y sin movimiento de caja; su efecto en el saldo, el estado de cobro, la
 * anulación de la factura y los reportes.
 */
class InvoiceWithholdingTest extends InvoiceTestCase
{
    /**
     * Factura de completedWorkOrder emitida a crédito (por defecto) o al contado.
     */
    private function issuedInvoice(string $condition = 'credit'): array
    {
        if (! InvoiceStamp::exists()) {
            $this->createStamp()->assertCreated();
        }
        $invoice = $this->readyInvoice();

        if ($condition === 'credit') {
            $this->patchJson("/api/invoices/{$invoice['id']}", [
                'condition' => 'credit',
                'due_date' => now()->addDays(30)->toDateString(),
            ])->assertOk();
        }

        return $this->postJson("/api/invoices/{$invoice['id']}/issue")->assertOk()->json('data');
    }

    private function body(array $overrides = []): array
    {
        return [
            'tax' => 'vat',
            'amount' => 10000,
            'certificate_number' => '001-001-0000123',
            'certificate_stamp_number' => '17654321',
            'withholding_date' => now()->toDateString(),
            ...$overrides,
        ];
    }

    private function register(array $invoice, array $body)
    {
        return $this->postJson("/api/invoices/{$invoice['id']}/withholdings", $body);
    }

    private function show(array $invoice): array
    {
        return $this->getJson("/api/invoices/{$invoice['id']}")->assertOk()->json('data');
    }

    public function test_a_vat_withholding_lowers_the_balance_without_touching_the_cash_summary(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice();
        $total = $invoice['totals']['total'];
        $vat = $invoice['totals']['iva_total'];

        // Sugerencia: el 30 % del IVA de la factura, en guaraníes enteros.
        $suggestion = bcadd(bcdiv(bcadd(bcmul($vat, '30', 2), '50', 2), '100', 0), '0', 2);
        $this->assertSame($suggestion, $invoice['payment_summary']['vat_withholding_suggestion']);

        $withholding = $this->register($invoice, $this->body(['amount' => (int) $suggestion]))
            ->assertCreated()
            ->assertJsonPath('data.tax.value', 'vat')
            ->assertJsonPath('data.tax.label', 'IVA')
            ->assertJsonPath('data.amount', $suggestion)
            ->assertJsonPath('data.certificate_stamp_number', '17654321')
            ->assertJsonPath('data.status.value', 'registered')
            ->json('data');

        $detail = $this->show($invoice);
        $this->assertSame($suggestion, $detail['payment_summary']['withheld_total']);
        $this->assertSame(bcsub($total, $suggestion, 2), $detail['payment_summary']['balance']);
        $this->assertSame('partial', $detail['payment_summary']['status']);
        // Lo sugerido ya está retenido.
        $this->assertSame('0.00', $detail['payment_summary']['vat_withholding_suggestion']);
        $this->assertSame($withholding['id'], $detail['withholdings'][0]['id']);

        // El pago del resto cancela la factura; la caja solo cuenta el pago.
        $rest = (int) bcsub($total, $suggestion, 0);
        $this->postJson("/api/invoices/{$invoice['id']}/payments", [
            'amount' => $rest,
            'method' => 'transfer',
            'reference' => 'TRA-001',
        ])->assertCreated();

        $this->assertSame('paid', $this->show($invoice)['payment_summary']['status']);
        $this->getJson('/api/payments/summary')->assertOk()->assertJsonPath('data.total', bcadd((string) $rest, '0', 2));

        // Ventas: la retención aparte de lo cobrado.
        $this->getJson('/api/reports/sales')
            ->assertOk()
            ->assertJsonPath('data.withholdings.withholdings_count', 1)
            ->assertJsonPath('data.withholdings.total', $suggestion)
            ->assertJsonPath('data.withholdings.vat', $suggestion)
            ->assertJsonPath('data.withholdings.income', '0.00')
            ->assertJsonPath('data.collected.total', bcadd((string) $rest, '0', 2));
        $this->get('/api/reports/sales?format=csv')->assertOk();
    }

    public function test_withholdings_are_only_for_issued_credit_invoices_and_up_to_the_balance(): void
    {
        $this->actingAsAdmin();

        // Al contado no hay retención (V17).
        $cash = $this->issuedInvoice('cash');
        $this->assertNull($cash['payment_summary']['vat_withholding_suggestion']);
        $this->register($cash, $this->body())->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_NOT_WITHHOLDABLE');

        // Un borrador tampoco.
        $draft = $this->readyInvoice();
        $this->register($draft, $this->body())->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_NOT_WITHHOLDABLE');

        $invoice = $this->issuedInvoice();
        $total = (int) $invoice['totals']['total'];

        $this->register($invoice, $this->body(['amount' => $total + 1]))
            ->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_WITHHOLDING_EXCEEDS_BALANCE');
        $this->register($invoice, $this->body(['withholding_date' => now()->subYear()->toDateString()]))
            ->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_WITHHOLDING_DATE_BEFORE_INVOICE');
        $this->register($invoice, $this->body(['withholding_date' => now()->addDay()->toDateString()]))
            ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
        $this->register($invoice, $this->body(['certificate_stamp_number' => null]))
            ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');
        $this->register($invoice, $this->body(['tax' => 'stamp_duty']))
            ->assertStatus(422)->assertJsonPath('error_code', 'VALIDATION_ERROR');

        // IVA y renta del mismo comprobante; el mismo impuesto dos veces, no.
        $this->register($invoice, $this->body())->assertCreated();
        $this->register($invoice, $this->body(['tax' => 'income', 'amount' => 2000]))->assertCreated();
        $this->register($invoice, $this->body())->assertStatus(409)->assertJsonPath('error_code', 'INVOICE_WITHHOLDING_DUPLICATE');

        // Un comprobante cubre varias facturas pagadas en el mismo acto.
        $other = $this->issuedInvoice();
        $this->register($other, $this->body())->assertCreated();

        $this->assertSame('12000.00', $this->show($invoice)['payment_summary']['withheld_total']);
    }

    public function test_cancelling_a_withholding_reopens_the_balance_and_blocks_cancelling_the_invoice_before(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice();
        $total = $invoice['totals']['total'];

        $withholding = $this->register($invoice, $this->body())->assertCreated()->json('data');

        // Con una retención vigente la factura no se anula.
        $this->postJson("/api/invoices/{$invoice['id']}/cancel", ['reason' => 'Se emitió por error.'])
            ->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_HAS_WITHHOLDINGS');

        $this->postJson("/api/invoices/{$invoice['id']}/withholdings/{$withholding['id']}/cancel", ['reason' => 'Se cargó mal el importe.'])
            ->assertOk()
            ->assertJsonPath('data.status.value', 'cancelled')
            ->assertJsonPath('data.cancellation_reason', 'Se cargó mal el importe.');
        $this->postJson("/api/invoices/{$invoice['id']}/withholdings/{$withholding['id']}/cancel", ['reason' => 'Otra vez.'])
            ->assertStatus(422)->assertJsonPath('error_code', 'INVOICE_WITHHOLDING_NOT_CANCELLABLE');

        $detail = $this->show($invoice);
        $this->assertSame('0.00', $detail['payment_summary']['withheld_total']);
        $this->assertSame($total, $detail['payment_summary']['balance']);
        $this->assertSame('unpaid', $detail['payment_summary']['status']);
        // La anulada se sigue listando.
        $this->getJson("/api/invoices/{$invoice['id']}/withholdings")->assertOk()->assertJsonCount(1, 'data');

        // El mismo comprobante se puede volver a cargar bien.
        $this->register($invoice, $this->body(['amount' => 9000]))->assertCreated();

        // El registro no se edita ni se borra.
        $model = InvoiceWithholding::findOrFail($withholding['id']);
        $this->expectException(InvoiceException::class);
        $model->delete();
    }

    public function test_receivables_show_what_was_withheld(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice();

        $this->register($invoice, $this->body())->assertCreated();

        $this->getJson("/api/reports/receivables?client_id={$invoice['client_id']}")
            ->assertOk()
            ->assertJsonPath('data.invoices.0.withheld_total', '10000.00')
            ->assertJsonPath('data.invoices.0.balance', bcsub($invoice['totals']['total'], '10000', 2));

        $this->get("/api/invoices/{$invoice['id']}/pdf")->assertOk();
    }

    public function test_only_the_cashier_registers_and_cancels_withholdings(): void
    {
        $this->actingAsAdmin();
        $invoice = $this->issuedInvoice();

        $this->actingAsRole('recepcionista');
        $this->register($invoice, $this->body())->assertForbidden();

        $this->actingAsRole('cajero');
        $withholding = $this->register($invoice, $this->body())->assertCreated()->json('data');
        $this->getJson("/api/invoices/{$invoice['id']}/withholdings/{$withholding['id']}")->assertOk();

        $this->actingAsRole('jefe-taller');
        $this->postJson("/api/invoices/{$invoice['id']}/withholdings/{$withholding['id']}/cancel", ['reason' => 'Prueba.'])
            ->assertForbidden();

        $this->actingAsRole('cajero');
        $this->postJson("/api/invoices/{$invoice['id']}/withholdings/{$withholding['id']}/cancel", ['reason' => 'Prueba.'])
            ->assertOk();
    }
}
