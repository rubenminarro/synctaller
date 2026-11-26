<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Error del último intento de alta de un taller (`tenants:create`; T5, paso 3, historial 2.103). Queda mientras el
 * taller está en `provisioning` y se borra cuando el alta termina. El detalle completo va también al log.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->text('provisioning_error')->nullable()->after('maintenance');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('provisioning_error');
        });
    }
};
