<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesCentralDatabase;
use Tests\TestCase;

/**
 * Tareas al activar un taller (T2, paso 1; historial 2.90). Cada test falla si se quita la protección que cubre:
 * la tarea de base, el prefijo con separador, la tarea de permisos o las colas por taller apagadas.
 */
class SwitchTenantTest extends TestCase
{
    use RefreshDatabase;
    use UsesCentralDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // La cache de los tests es `array`, que no usa prefijo. La de base de datos sí, como en producción.
        config(['cache.default' => 'database', 'cache.stores.database.connection' => null]);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_making_a_tenant_current_points_the_tenant_connection_to_its_database(): void
    {
        $tenant = $this->tenant(1, 'synctaller_a');
        $default = config('database.default');

        $tenant->makeCurrent();

        $this->assertTrue(Tenant::current()->is($tenant));
        $this->assertSame('synctaller_a', DB::connection('tenant')->getDatabaseName());
        $this->assertSame($default, config('database.default'), 'En el paso 1 la conexión por defecto no cambia.');

        Tenant::forgetCurrent();

        $this->assertNull(Tenant::current());
        $this->assertNull(config('database.connections.tenant.database'), 'Sin taller activo, la plantilla queda sin base.');
    }

    public function test_cache_prefixes_of_tenants_1_and_10_do_not_collide(): void
    {
        $tenant1 = $this->tenant(1, 'synctaller_1');
        $tenant10 = $this->tenant(10, 'synctaller_10');

        $tenant1->makeCurrent();
        Cache::put('0abc', 'dato del taller 1');

        $tenant10->makeCurrent();

        // Con el prefijo del paquete (`tenant_id_1` + `0abc` = `tenant_id_10` + `abc`) el taller 10 lo leería.
        $this->assertNull(Cache::get('abc'));
        $this->assertSame('tenant:10:', config('cache.prefix'));

        $tenant1->makeCurrent();
        $this->assertSame('dato del taller 1', Cache::get('0abc'));
    }

    public function test_forgetting_the_tenant_restores_the_original_cache_prefix(): void
    {
        $original = config('cache.prefix');

        $this->tenant(1, 'synctaller_a')->makeCurrent();
        Tenant::forgetCurrent();

        $this->assertSame($original, config('cache.prefix'));
    }

    public function test_each_tenant_caches_its_permissions_under_its_own_key(): void
    {
        // El registro se instancia antes de activar el taller, como al arrancar la aplicación (`Gate::before`).
        $registrar = app(PermissionRegistrar::class);
        $registrar->initializeCache();

        $this->tenant(1, 'synctaller_1')->makeCurrent();
        $registrar->getPermissions();

        $this->tenant(10, 'synctaller_10')->makeCurrent();
        $registrar->getPermissions();

        // Sin la tarea de permisos, el registro seguiría con la cache con la que arrancó y no aparecería ninguna de
        // estas claves: los dos talleres compartirían una sola.
        $keys = DB::table('cache')->where('key', 'like', '%spatie.permission.cache')->pluck('key')->sort()->values()->all();

        $this->assertSame(['tenant:10:spatie.permission.cache', 'tenant:1:spatie.permission.cache'], $keys);
    }

    public function test_a_job_dispatched_without_a_tenant_still_runs(): void
    {
        // Con las colas por taller encendidas, el paquete borraría este job por no traer un taller (hasta T3).
        JobWithoutTenant::$handled = false;

        JobWithoutTenant::dispatch();

        $this->assertTrue(JobWithoutTenant::$handled);
    }

    private function tenant(int $id, string $database): Tenant
    {
        return tap((new Tenant)->forceFill([
            'id' => $id,
            'name' => "Taller {$id}",
            'domain' => "t{$id}.localhost",
            'database' => $database,
        ]))->save();
    }
}

class JobWithoutTenant implements ShouldQueue
{
    use Queueable;

    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }
}
