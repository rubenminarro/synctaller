<?php

namespace Tests;

use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Id del taller de prueba en la base central: lejos de los que crean los tests de `tests/Feature/Tenancy`.
     */
    public const TEST_TENANT_ID = 1000;

    /**
     * Roles y permisos se siembran una sola vez, junto con la migración de RefreshDatabase (que es una por ejecución:
     * cada test corre dentro de una transacción). Antes se sembraban en cada setUp (~0,2 s por test).
     */
    protected $seeder = RolesAndPermissionsSeeder::class;

    private static bool $centralDatabaseMigrated = false;

    /**
     * Todo test corre en un taller de prueba (§3.12 de la especificación; historial 2.93), registrado en la base central
     * con el host de los requests de los tests (el de `APP_URL`) y la base de siempre (`database/testing.sqlite`).
     *
     * Se activa antes que los traits: así RefreshDatabase migra y abre su transacción en la conexión `tenant`, y
     * `IdentifyTenant` encuentra en cada request el taller que ya está activo y no vuelve a conectar.
     */
    protected function setUpTraits()
    {
        $this->setUpCentralDatabase();

        $database = config('database.connections.'.config('database.default').'.database');

        tap((new Tenant)->forceFill([
            'id' => self::TEST_TENANT_ID,
            'name' => 'Taller de prueba',
            'domain' => parse_url(config('app.url'), PHP_URL_HOST),
            'database' => $database,
        ]))->save()->makeCurrent();

        $uses = parent::setUpTraits();

        // Después de la vuelta atrás de RefreshDatabase, que se registra en parent::setUpTraits().
        $this->beforeApplicationDestroyed(fn () => Tenant::forgetCurrent());

        return $uses;
    }

    /**
     * Base central en otro archivo SQLite (`tests/bootstrap.php`), migrada una vez por ejecución con las migraciones
     * de `database/migrations/central`. Cada test corre en una transacción de la central, como RefreshDatabase hace con
     * la del taller.
     */
    private function setUpCentralDatabase(): void
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
