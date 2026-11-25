<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V10: la factura guarda también el teléfono y el correo del cliente, copiados al
 * crear el borrador (editables mientras lo sea). No bloquean la emisión. Las
 * facturas anteriores quedan sin estos datos: no se completan con los del cliente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('customer_phone')->nullable()->after('customer_address');
            $table->string('customer_email')->nullable()->after('customer_phone');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['customer_phone', 'customer_email']);
        });
    }
};
