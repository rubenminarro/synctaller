<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Taller en el que corre la siembra (deuda #23; historial 2.100). Desde T4 (2.98) los seeders escriben archivos en los
 * discos del taller, que sin taller activo fallan. Con un taller activo (los tests, `tenants:artisan`) se usa ese. Sin
 * taller activo (`php artisan migrate --seed` o `db:seed`) se activa el registrado en la central con la base que se
 * siembra, la de la conexión por defecto, y se olvida al terminar. Si no hay ninguno, no se siembra nada.
 */
final class SeedingTenant
{
    public static function run(callable $seed): void
    {
        if (Tenant::current()) {
            $seed();

            return;
        }

        self::forDatabase(config('database.connections.'.config('database.default').'.database'))->makeCurrent();

        try {
            $seed();
        } finally {
            Tenant::forgetCurrent();
        }
    }

    /**
     * @throws RuntimeException si ningún taller usa esa base o no se puede leer la central
     */
    public static function forDatabase(?string $database): Tenant
    {
        try {
            $tenants = Tenant::all();
        } catch (QueryException $e) {
            throw new RuntimeException('No se pudo leer la base central. Migrarla y registrar el taller antes de sembrar: '
                .'php artisan migrate --database=central --path=database/migrations/central', previous: $e);
        }

        $target = self::normalize((string) $database);
        $tenant = $tenants->first(fn (Tenant $tenant) => self::normalize($tenant->database) === $target);

        return $tenant ?? throw new RuntimeException("Ningún taller registrado usa la base «{$database}». Registrarlo antes de "
            ."sembrar: php artisan tenants:register \"Taller principal\" 127.0.0.1 {$database}");
    }

    /**
     * En SQLite la base es un archivo: la misma puede escribirse con ruta relativa o absoluta, o con otras barras.
     */
    private static function normalize(string $database): string
    {
        $path = is_file($database) ? realpath($database) : false;

        return $path === false ? $database : str_replace('\\', '/', $path);
    }
}
