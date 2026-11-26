<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Fecha en que se borró la base de un taller dado de baja, a los 5 años de `cancelled_at` (T5, paso 5, historial 2.105).
 * La completa a mano el runbook del borrado: la fila queda `cancelled` y así se distingue una base conservada de una
 * borrada. `tenants:restore` rechaza un taller con la base borrada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->timestamp('database_deleted_at')->nullable()->after('cancelled_at');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('database_deleted_at');
        });
    }
};
