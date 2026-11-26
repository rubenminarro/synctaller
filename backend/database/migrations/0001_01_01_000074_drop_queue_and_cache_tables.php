<?php

use Illuminate\Database\Connection;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quita de la base del taller las tablas de cola y cache (T5, paso 2; historial 2.102; deuda #20). Desde T3, paso 4
 * (2.96), viven en la central (`database/migrations/central/2026_11_26_000001`) y nada las usa en el taller. Las
 * migraciones `000001` y `000002` siguen creándolas: un taller nuevo las crea y las borra en la misma corrida.
 *
 * Se niega a correr si la conexión apunta a la base central (por ejemplo, un `migrate` con `DB_DATABASE` mal puesto):
 * ahí borraría la cola y la cache reales. `down()` las vuelve a crear con el esquema original.
 */
return new class extends Migration
{
    private const TABLES = ['jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'];

    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($this->isCentral($connection)) {
            throw new RuntimeException(
                'La migración 000074 quita las tablas de cola y cache de la base de un taller y no corre sobre la base central.'
            );
        }

        foreach (self::TABLES as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * La conexión es la central por su nombre o porque apunta a la misma base: mismo motor y, en SQLite, el mismo
     * archivo; en los demás, el mismo servidor, puerto y nombre de base.
     */
    private function isCentral(Connection $connection): bool
    {
        if ($connection->getName() === 'central') {
            return true;
        }

        $central = config('database.connections.central');

        if (! is_array($central) || ($central['driver'] ?? null) !== $connection->getDriverName()) {
            return false;
        }

        if ($connection->getDriverName() === 'sqlite') {
            $path = realpath((string) $connection->getDatabaseName());

            return $path !== false && $path === realpath((string) ($central['database'] ?? ''));
        }

        return strcasecmp((string) $connection->getDatabaseName(), (string) ($central['database'] ?? '')) === 0
            && strcasecmp((string) $connection->getConfig('host'), (string) ($central['host'] ?? '')) === 0
            && (string) $connection->getConfig('port') === (string) ($central['port'] ?? '');
    }
};
