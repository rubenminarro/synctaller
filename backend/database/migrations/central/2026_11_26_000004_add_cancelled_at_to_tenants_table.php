<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fecha de la baja de un taller (`tenants:cancel`; T5, paso 4, historial 2.104). Desde ahí corren los 5 años en que la
 * plataforma conserva su base (historial 2.94). Queda vacía en los demás estados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('cancelled_at')->nullable()->after('provisioning_error');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('cancelled_at');
        });
    }
};
