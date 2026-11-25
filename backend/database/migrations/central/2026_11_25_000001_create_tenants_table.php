<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Base central (historial 2.90): registro de talleres. Se corre con
 * `php artisan migrate --database=central --path=database/migrations/central`; el `migrate` de siempre solo ve
 * database/migrations, así que esta tabla nunca se crea en la base de un taller.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('database')->unique();
            $table->string('status', 20)->default('active');
            $table->boolean('maintenance')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
