<?php

namespace Tests\Feature\Seeders;

use App\Models\Tenant;
use Database\Seeders\SeedingTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * Taller de la siembra (deuda #23; historial 2.100): sin taller activo, `php artisan migrate --seed` siembra en el taller
 * registrado con la base que se siembra, porque los seeders escriben en los discos del taller (T4, 2.98). Cada test
 * falla si se quita la protección que cubre.
 *
 * El taller de prueba (TestCase) usa la base de los tests: al olvidarlo y volver a activarlo, `tenant` vuelve a conectar
 * fuera de la transacción, así que la transacción va en la conexión fija `sqlite`, como en ReminderCommandTest. Los
 * tests no escriben en la base del taller.
 */
class SeedingTenantTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite'];

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::forgetCurrent();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_without_a_current_tenant_it_seeds_in_the_tenant_of_the_seeded_database_and_then_forgets_it(): void
    {
        $seen = null;

        SeedingTenant::run(function () use (&$seen) {
            $seen = [Tenant::current()?->id, Storage::disk('tenant_private')->path('claims')];
        });

        $this->assertSame(self::TEST_TENANT_ID, $seen[0]);
        $this->assertStringContainsString('tenants/'.self::TEST_TENANT_ID.'/private', str_replace('\\', '/', $seen[1]));
        $this->assertNull(Tenant::current());
    }

    public function test_the_tenant_is_found_when_its_database_path_is_written_another_way(): void
    {
        // La base de los tests es una ruta absoluta (tests/bootstrap.php); el taller la tiene escrita con un `..` que
        // solo coincide al resolverla, en Windows y en Linux.
        $database = config('database.connections.'.config('database.default').'.database');
        $sameFile = base_path('database/../database/'.basename($database));
        Tenant::whereKey(self::TEST_TENANT_ID)->update(['database' => $sameFile]);

        $this->assertNotSame($database, $sameFile);
        $this->assertSame(self::TEST_TENANT_ID, SeedingTenant::forDatabase($database)->id);
    }

    public function test_it_forgets_the_tenant_also_when_the_seeding_fails(): void
    {
        try {
            SeedingTenant::run(fn () => throw new RuntimeException('falla de un seeder'));
            $this->fail('La excepción del seeder debía llegar al llamador.');
        } catch (RuntimeException $e) {
            $this->assertSame('falla de un seeder', $e->getMessage());
        }

        $this->assertNull(Tenant::current());
    }

    public function test_without_a_tenant_for_the_seeded_database_nothing_is_seeded(): void
    {
        Tenant::whereKey(self::TEST_TENANT_ID)->update(['database' => 'otra-base.sqlite']);
        $seeded = false;

        try {
            SeedingTenant::run(function () use (&$seeded) {
                $seeded = true;
            });
            $this->fail('Sin taller registrado para la base no debía sembrar.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Ningún taller registrado usa la base', $e->getMessage());
            $this->assertStringContainsString('tenants:register', $e->getMessage());
        }

        $this->assertFalse($seeded);
        $this->assertNull(Tenant::current());
    }

    public function test_with_a_current_tenant_it_seeds_there_and_keeps_it_current(): void
    {
        $tenant = Tenant::findOrFail(self::TEST_TENANT_ID);
        $tenant->makeCurrent();
        $seen = null;

        SeedingTenant::run(function () use (&$seen) {
            $seen = Tenant::current()?->id;
        });

        $this->assertSame(self::TEST_TENANT_ID, $seen);
        $this->assertSame(self::TEST_TENANT_ID, Tenant::current()?->id);
    }
}
