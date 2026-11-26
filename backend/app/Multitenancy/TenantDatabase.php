<?php

namespace App\Multitenancy;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use PDO;

/**
 * La base de un taller como objeto del servidor: comprobar si existe y si tiene tablas, crearla y borrarla (T5, paso 3;
 * historial 2.103). Usa el motor de la conexión `tenant`: MySQL en desarrollo y producción, SQLite (un archivo) en los
 * tests. En MySQL las sentencias van por la conexión central, que es del mismo servidor y no depende del taller activo.
 *
 * La usan `tenants:create`, `tenants:discard` y `tenants:restore` (paso 5, historial 2.105). El nombre se valida acá también, porque va dentro de un
 * `CREATE DATABASE` o un `DROP DATABASE`: en MySQL solo `[a-z0-9_]` y en SQLite una ruta `.sqlite` en una carpeta que
 * ya existe.
 */
class TenantDatabase
{
    public function isSqlite(): bool
    {
        return config('database.connections.'.config('multitenancy.tenant_database_connection_name').'.driver') === 'sqlite';
    }

    /**
     * Mensaje de error si el nombre no sirve para la base de un taller; `null` si sirve.
     */
    public function invalidName(string $name): ?string
    {
        if ($this->isSqlite()) {
            return str_ends_with($name, '.sqlite') && is_dir(dirname($name))
                ? null
                : 'La base debe ser la ruta de un archivo .sqlite en una carpeta que exista.';
        }

        return preg_match('/^[a-z0-9_]{1,64}$/', $name) === 1
            ? null
            : 'La base solo admite minúsculas, números y guion bajo (hasta 64 caracteres).';
    }

    /**
     * Cantidad de tablas, o `null` si la base no existe.
     */
    public function tableCount(string $name): ?int
    {
        $this->assertValid($name);

        if ($this->isSqlite()) {
            if (! file_exists($name)) {
                return null;
            }

            $pdo = new PDO("sqlite:{$name}");
            $count = (int) $pdo->query("select count(*) from sqlite_master where type = 'table'")->fetchColumn();
            $pdo = null;

            return $count;
        }

        $central = DB::connection(config('multitenancy.landlord_database_connection_name'));

        if ($central->selectOne('select schema_name from information_schema.schemata where schema_name = ?', [$name]) === null) {
            return null;
        }

        return (int) $central->scalar('select count(*) from information_schema.tables where table_schema = ?', [$name]);
    }

    /**
     * Crea la base si no existe. No toca una que ya existe.
     */
    public function create(string $name): void
    {
        $this->assertValid($name);

        if ($this->isSqlite()) {
            if (! file_exists($name)) {
                touch($name);
            }

            return;
        }

        $template = config('database.connections.'.config('multitenancy.tenant_database_connection_name'));

        DB::connection(config('multitenancy.landlord_database_connection_name'))->statement(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET %s COLLATE %s',
            $name,
            $template['charset'] ?? 'utf8mb4',
            $template['collation'] ?? 'utf8mb4_unicode_ci',
        ));
    }

    /**
     * Borra la base si existe. Antes cierra la conexión `tenant`, que podría tenerla abierta.
     */
    public function drop(string $name): void
    {
        $this->assertValid($name);

        DB::purge(config('multitenancy.tenant_database_connection_name'));

        if ($this->isSqlite()) {
            if (file_exists($name)) {
                unlink($name);
            }

            return;
        }

        DB::connection(config('multitenancy.landlord_database_connection_name'))
            ->statement(sprintf('DROP DATABASE IF EXISTS `%s`', $name));
    }

    /**
     * Si la base es la central o la de la conexión por defecto: ningún taller la borra (`tenants:discard`) ni pasa a
     * usarla (`tenants:restore`).
     */
    public function isProtected(string $name): bool
    {
        $connections = [config('multitenancy.landlord_database_connection_name'), config('database.default')];

        foreach ($connections as $connection) {
            $protected = (string) config("database.connections.{$connection}.database");

            if ($protected !== '' && $this->same($protected, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * En SQLite la base es una ruta: se comparan las reales, para que otra forma de escribirla no esquive un control.
     */
    public function same(string $a, string $b): bool
    {
        $real = fn (string $path) => realpath($path) ?: $path;

        return strcasecmp($real($a), $real($b)) === 0;
    }

    private function assertValid(string $name): void
    {
        if (($error = $this->invalidName($name)) !== null) {
            throw new InvalidArgumentException($error);
        }
    }
}
