<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Registro de las corridas de `tenants:migrate` (§3.8 de la especificación; T5, paso 1, historial 2.101): una fila por
 * taller y corrida. `--failed` repite los talleres cuya última corrida falló.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_migration_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants');
            $table->string('status', 20);
            $table->json('migrations')->nullable();
            $table->text('error')->nullable();
            $table->boolean('maintenance_set')->default(false);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->index(['tenant_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_migration_runs');
    }
};
