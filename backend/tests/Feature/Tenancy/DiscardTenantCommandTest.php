<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `tenants:discard` (T5, paso 3; historial 2.103): borra la base y la fila de un taller en alta, y de ningún otro. Cada
 * test falla si se quita la protección que cubre. La cache es la `database` de la central, como en CreateTenantCommandTest.
 */
class DiscardTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite'];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        DB::purge(config('multitenancy.tenant_database_connection_name'));

        foreach ($this->files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }

        parent::tearDown();
    }

    public function test_it_drops_the_database_and_the_row_of_a_tenant_in_provisioning(): void
    {
        $tenant = $this->pending('pendiente');

        $this->artisan('tenants:discard', ['tenant' => $tenant->id])
            ->expectsConfirmation("Se borra la base {$tenant->database} y el registro del taller #{$tenant->id} (pendiente.localhost). ¿Seguir?", 'yes')
            ->expectsOutput("Taller #{$tenant->id} descartado: se borraron su base y su registro.")
            ->assertSuccessful();

        $this->assertNull(Tenant::find($tenant->id));
        $this->assertFileDoesNotExist($tenant->database);
        $this->assertSame(self::TEST_TENANT_ID, Tenant::current()?->id, 'Deja el taller que estaba activo.');
    }

    public function test_nothing_is_discarded_without_confirmation(): void
    {
        $tenant = $this->pending('pendiente');

        $this->artisan('tenants:discard', ['tenant' => $tenant->id])
            ->expectsConfirmation("Se borra la base {$tenant->database} y el registro del taller #{$tenant->id} (pendiente.localhost). ¿Seguir?", 'no')
            ->expectsOutput('No se descartó nada.')
            ->assertFailed();

        $this->assertNotNull(Tenant::find($tenant->id));
        $this->assertFileExists($tenant->database);
    }

    public function test_an_active_or_suspended_tenant_is_never_discarded(): void
    {
        $active = Tenant::find(self::TEST_TENANT_ID);
        $suspended = $this->pending('suspendido', ['status' => TenantStatus::SUSPENDED]);

        $this->artisan('tenants:discard', ['tenant' => $active->id, '--force' => true])
            ->expectsOutput("Taller #{$active->id}: solo se descarta un taller en alta, y este está Activo.")
            ->assertFailed();

        $this->artisan('tenants:discard', ['tenant' => $suspended->id, '--force' => true])
            ->expectsOutput("Taller #{$suspended->id}: solo se descarta un taller en alta, y este está Suspendido.")
            ->assertFailed();

        $this->assertFileExists($active->database);
        $this->assertFileExists($suspended->database);
        $this->assertNotNull(Tenant::find($suspended->id));
    }

    public function test_the_central_database_is_never_dropped(): void
    {
        // Otra forma de escribir la misma ruta no esquiva el control.
        $central = config('database.connections.central.database');
        $tenant = Tenant::create([
            'name' => 'Mal escrito',
            'domain' => 'mal.localhost',
            'database' => dirname($central).DIRECTORY_SEPARATOR.'.'.DIRECTORY_SEPARATOR.basename($central),
            'status' => TenantStatus::PROVISIONING,
        ]);

        $this->artisan('tenants:discard', ['tenant' => $tenant->id, '--force' => true])
            ->expectsOutput("Taller #{$tenant->id}: su base es la central o la de la conexión por defecto y no se borra.")
            ->assertFailed();

        $this->assertFileExists($central);
        $this->assertNotNull(Tenant::find($tenant->id));
    }

    public function test_it_respects_the_lock_of_a_provisioning_in_course(): void
    {
        $tenant = $this->pending('pendiente');

        Tenant::forgetCurrent();
        $lock = Cache::lock(CreateTenant::lockKey($tenant->domain), 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('tenants:discard', ['tenant' => $tenant->id, '--force' => true])
                ->expectsOutput("Taller #{$tenant->id}: se está dando de alta en otra corrida.")
                ->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertFileExists($tenant->database);
        $this->assertNotNull(Tenant::find($tenant->id));
    }

    public function test_an_unknown_tenant_is_reported(): void
    {
        $this->artisan('tenants:discard', ['tenant' => 99999, '--force' => true])
            ->expectsOutput('Taller #99999: no existe.')
            ->assertFailed();
    }

    private function pending(string $name, array $attributes = []): Tenant
    {
        $path = database_path("testing-discard-{$name}.sqlite");
        touch($path);
        $this->files[] = $path;

        return Tenant::create(array_merge([
            'name' => ucfirst($name),
            'domain' => "{$name}.localhost",
            'database' => $path,
            'status' => TenantStatus::PROVISIONING,
        ], $attributes));
    }
}
