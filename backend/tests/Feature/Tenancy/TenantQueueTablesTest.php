<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

/**
 * Las tablas de cola y cache viven solo en la central (T5, paso 2; historial 2.102; deuda #20): la migración de taller
 * `000074` las quita de la base del taller, las vuelve a crear al revertirla y se niega a correr sobre la central.
 * Cada test falla si se quita la protección que cubre.
 */
class TenantQueueTablesTest extends TestCase
{
    use RefreshDatabase;

    private const TABLES = ['jobs', 'job_batches', 'failed_jobs', 'cache', 'cache_locks'];

    private const MIGRATION = '0001_01_01_000074_drop_queue_and_cache_tables';

    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach (['testing_queue_tables', 'testing_misconfigured_central'] as $connection) {
            DB::purge($connection);
        }

        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_the_tenant_database_has_no_queue_or_cache_tables_and_the_central_does(): void
    {
        $tenant = Schema::connection(config('multitenancy.tenant_database_connection_name'));
        $central = Schema::connection('central');

        foreach (self::TABLES as $table) {
            $this->assertFalse($tenant->hasTable($table), "La tabla {$table} no debe existir en la base del taller.");
            $this->assertTrue($central->hasTable($table), "La tabla {$table} debe existir en la base central.");
        }
    }

    public function test_rolling_back_the_migration_recreates_the_tables(): void
    {
        $path = database_path('testing-queue-tables.sqlite');
        $this->files[] = $path;
        touch($path);
        config(['database.connections.testing_queue_tables' => array_merge(
            config('database.connections.sqlite'),
            ['database' => realpath($path)],
        )]);

        Artisan::call('migrate', ['--database' => 'testing_queue_tables', '--force' => true]);
        $this->assertTablesExist(false);

        Artisan::call('migrate:rollback', ['--database' => 'testing_queue_tables', '--step' => 1, '--force' => true]);
        $this->assertTablesExist(true);

        // Vuelta adelante: después de revertir, la migración se aplica de nuevo sin errores.
        Artisan::call('migrate', ['--database' => 'testing_queue_tables', '--force' => true]);
        $this->assertTablesExist(false);
    }

    public function test_it_refuses_to_run_on_a_connection_that_points_to_the_central_database(): void
    {
        // Otra conexión con la base central, como un `migrate` con `DB_DATABASE` mal puesto. Es otro PDO, fuera de la
        // transacción del test: si la migración borrara, borraría de verdad (y el test lo detectaría abajo).
        config(['database.connections.testing_misconfigured_central' => config('database.connections.central')]);

        $default = DB::getDefaultConnection();
        DB::setDefaultConnection('testing_misconfigured_central');

        try {
            $this->migration()->up();
            $this->fail('La migración no debe correr sobre la base central.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('no corre sobre la base central', $exception->getMessage());
        } finally {
            DB::setDefaultConnection($default);
        }

        foreach (self::TABLES as $table) {
            $this->assertTrue(Schema::connection('testing_misconfigured_central')->hasTable($table), "La tabla {$table} de la central no debe borrarse.");
        }
    }

    private function assertTablesExist(bool $expected): void
    {
        $schema = Schema::connection('testing_queue_tables');

        foreach (self::TABLES as $table) {
            $this->assertSame($expected, $schema->hasTable($table), "La tabla {$table} ".($expected ? 'debe' : 'no debe').' existir.');
        }
    }

    private function migration(): Migration
    {
        return require database_path('migrations/'.self::MIGRATION.'.php');
    }
}
