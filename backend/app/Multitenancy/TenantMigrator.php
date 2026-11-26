<?php

namespace App\Multitenancy;

use Illuminate\Database\Migrations\Migrator;

/**
 * Migra la base del taller activo con las migraciones de `database/migrations` (T5, pasos 1 y 3; historial 2.101 y
 * 2.103). La usan `tenants:migrate`, `tenants:create` y `tenants:restore` (paso 5, historial 2.105).
 *
 * Usa el `Migrator` y no el comando `migrate`, que con `--force` crea la base si no existe: un taller que apunta a una
 * base caída o mal escrita recibiría una vacía con todas las migraciones. Acá una base que falta es una falla. Crearla
 * es un paso aparte y explícito del alta (`TenantDatabase::create()`).
 */
class TenantMigrator
{
    public function __construct(
        private readonly Migrator $migrator
    ) {}

    /**
     * Migraciones ya aplicadas en la base del taller activo. Conecta con la base: si falta o no responde, falla acá.
     *
     * @return list<string>
     */
    public function ran(): array
    {
        return $this->migrator->usingConnection($this->connection(),
            fn () => $this->migrator->repositoryExists() ? $this->migrator->getRepository()->getRan() : []);
    }

    /**
     * Migraciones del código que la base del taller activo todavía no tiene (`tenants:restore`, paso 5; 2.105).
     *
     * @return list<string>
     */
    public function pending(): array
    {
        $ran = $this->ran();

        return array_values(array_diff(array_keys($this->migrator->getMigrationFiles($this->paths())), $ran));
    }

    /**
     * Aplica las migraciones pendientes en la base del taller activo.
     */
    public function run(): void
    {
        $this->migrator->usingConnection($this->connection(), function () {
            if (! $this->migrator->repositoryExists()) {
                $this->migrator->getRepository()->createRepository();
            }

            $this->migrator->run($this->paths());
        });
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return array_merge($this->migrator->paths(), [database_path('migrations')]);
    }

    private function connection(): string
    {
        return config('multitenancy.tenant_database_connection_name');
    }
}
