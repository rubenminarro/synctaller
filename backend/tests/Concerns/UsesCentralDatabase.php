<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Base central de la línea T en los tests (historial 2.90): otro archivo SQLite (`tests/bootstrap.php`), migrado una
 * vez por ejecución con las migraciones de `database/migrations/central`. Cada test corre en una transacción de la
 * central, como RefreshDatabase hace con la del taller.
 */
trait UsesCentralDatabase
{
    private static bool $centralDatabaseMigrated = false;

    protected function setUpUsesCentralDatabase(): void
    {
        if (! self::$centralDatabaseMigrated) {
            Artisan::call('migrate:fresh', [
                '--database' => 'central',
                '--path' => 'database/migrations/central',
            ]);

            self::$centralDatabaseMigrated = true;
        }

        DB::connection('central')->beginTransaction();

        $this->beforeApplicationDestroyed(function () {
            DB::connection('central')->rollBack();
        });
    }
}
