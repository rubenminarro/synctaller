<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retenciones de IVA o de renta que el cliente practica sobre una factura emitida
 * a crédito (M12, V17). Cancelan parte del saldo sin movimiento de caja. Un mismo
 * comprobante puede cubrir varias facturas: una fila por factura. Inmutables: un
 * registro mal cargado se anula con motivo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_withholdings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('tax', 20);
            // Importe del comprobante, en guaraníes enteros.
            $table->decimal('amount', 14, 2);
            // Número y timbrado del comprobante de retención (DNIT), tal como se emitió.
            $table->string('certificate_number', 30);
            $table->string('certificate_stamp_number', 30);
            $table->date('withholding_date');
            $table->string('status', 20)->default('registered');
            $table->foreignId('registered_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index('certificate_number');
            $table->index('withholding_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_withholdings');
    }
};
