<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * Tareas al activar un taller (T2, pasos 1 y 2; historial 2.90 y 2.93). Cada test falla si se quita la protección que
 * cubre: la tarea de base, la conexión por defecto, el prefijo con separador, la tarea de permisos o las colas por taller
 * apagadas.
 *
 * Estos tests cambian de taller, y cada cambio vuelve a conectar `tenant`. La transacción de RefreshDatabase y la cache
 * van entonces en la conexión fija `sqlite`, la misma base de los tests, que ningún cambio de taller toca.
 */
class SwitchTenantTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    protected function setUp(): void
    {
        parent::setUp();

        // La cache de los tests es `array`, que no usa prefijo. La de base de datos sí, como en producción.
        config(['cache.default' => 'database', 'cache.stores.database.connection' => 'sqlite']);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_making_a_tenant_current_points_the_tenant_connection_to_its_database(): void
    {
        $tenant = $this->tenant(1, 'synctaller_a');

        $tenant->makeCurrent();

        $this->assertTrue(Tenant::current()->is($tenant));
        $this->assertSame('synctaller_a', DB::connection('tenant')->getDatabaseName());

        Tenant::forgetCurrent();

        $this->assertNull(Tenant::current());
        $this->assertNull(config('database.connections.tenant.database'), 'Sin taller activo, la plantilla queda sin base.');
    }

    public function test_the_default_connection_is_the_tenant_one_while_a_tenant_is_current(): void
    {
        // El taller de prueba ya está activo (TestCase): se vuelve a la conexión de arranque para medir desde cero.
        Tenant::forgetCurrent();
        $original = DB::getDefaultConnection();

        $this->tenant(1, 'synctaller_1')->makeCurrent();
        $this->assertSame('tenant', DB::getDefaultConnection());

        // Al cambiar de taller sigue siendo `tenant`, y al olvidarlo vuelve la de arranque, no la del taller anterior.
        $this->tenant(10, 'synctaller_10')->makeCurrent();
        $this->assertSame('tenant', DB::getDefaultConnection());

        Tenant::forgetCurrent();
        $this->assertSame($original, DB::getDefaultConnection());
        $this->assertNotSame('tenant', $original);
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
        Tenant::forgetCurrent();
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

        // Los permisos se leen de la base de cada taller, que tiene que existir y tener los roles sembrados. Se preparan
        // antes de activar: el seeder vacía la cache de permisos del taller activo.
        $tenant1 = $this->tenant(1, $this->tenantDatabase('a'));
        $tenant10 = $this->tenant(10, $this->tenantDatabase('b'));

        $tenant1->makeCurrent();
        $registrar->getPermissions();

        $tenant10->makeCurrent();
        $registrar->getPermissions();

        // Sin la tarea de permisos, el registro seguiría con la cache con la que arrancó y no aparecería ninguna de
        // estas claves: los dos talleres compartirían una sola.
        $keys = DB::connection('sqlite')->table('cache')->where('key', 'like', '%spatie.permission.cache')->pluck('key')->sort()->values()->all();

        $this->assertSame(['tenant:10:spatie.permission.cache', 'tenant:1:spatie.permission.cache'], $keys);
    }

    public function test_a_job_dispatched_without_a_tenant_still_runs(): void
    {
        // Con las colas por taller encendidas, el paquete borraría este job por no traer un taller (hasta T3).
        JobWithoutTenant::$handled = false;

        JobWithoutTenant::dispatch();

        $this->assertTrue(JobWithoutTenant::$handled);
    }

    public function test_a_sync_job_runs_in_the_tenant_of_the_request_and_leaves_it_current(): void
    {
        // Con los listeners de colas del paquete, el job olvidaba el taller y al terminar lo volvía a activar, lo que
        // reconectaba `tenant`: el resto del request quedaba fuera de su conexión (historial 2.93).
        $tenant = Tenant::current();
        $connection = DB::connection();

        JobWithoutTenant::dispatch();

        $this->assertSame($tenant->id, JobWithoutTenant::$tenantId);
        $this->assertSame('tenant', JobWithoutTenant::$defaultConnection);
        $this->assertTrue(Tenant::current()->is($tenant));
        $this->assertSame($connection, DB::connection(), 'La conexión del request no se reconecta.');
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

    public static ?int $tenantId = null;

    public static ?string $defaultConnection = null;

    public function handle(): void
    {
        self::$handled = true;
        self::$tenantId = Tenant::current()?->id;
        self::$defaultConnection = DB::getDefaultConnection();
    }
}
