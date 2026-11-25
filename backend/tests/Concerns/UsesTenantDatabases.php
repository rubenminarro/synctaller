<?php

namespace Tests\Concerns;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Bases de taller extra para los tests con más de un taller (historial 2.93): un archivo SQLite por nombre
 * (`database/testing-tenant-{nombre}.sqlite`), migrado y sembrado con roles y permisos una vez por ejecución.
 *
 * No van en una transacción: cambiar de taller vuelve a conectar `tenant` y la perdería. Lo que un test guarda queda
 * hasta la ejecución siguiente, así que los tests no cuentan filas ni reutilizan datos fijos como un email.
 */
trait UsesTenantDatabases
{
    /** @var array<string, string> */
    private static array $tenantDatabases = [];

    protected function tenantDatabase(string $name): string
    {
        if (isset(self::$tenantDatabases[$name])) {
            return self::$tenantDatabases[$name];
        }

        $path = database_path("testing-tenant-{$name}.sqlite");

        if (! file_exists($path)) {
            touch($path);
        }

        $path = realpath($path);

        // Una conexión propia, fuera de `tenant`: migrar no cambia el taller activo ni su transacción.
        $connection = "testing_tenant_{$name}";
        config(["database.connections.{$connection}" => array_merge(config('database.connections.sqlite'), ['database' => $path])]);

        Artisan::call('migrate:fresh', [
            '--database' => $connection,
            '--seed' => true,
            '--seeder' => RolesAndPermissionsSeeder::class,
        ]);

        DB::purge($connection);

        return self::$tenantDatabases[$name] = $path;
    }
}
