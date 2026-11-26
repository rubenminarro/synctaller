<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\MigrateTenants;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Multitenancy\TenantMigrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PDO;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * `tenants:restore` (T5, paso 5; historial 2.105): cambia la base de un taller a una restaurada, solo después de
 * verificarla, y la migra si le falta algo. Cada base es una copia de una base de taller migrada y con roles, que el
 * test borra. Cada test falla si se quita la protección que cubre. La cache es la `database` de la central, como en
 * CreateTenantCommandTest.
 */
class RestoreTenantCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

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

    public function test_it_switches_to_the_restored_database_after_verifying_it(): void
    {
        Log::spy();
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $token = $this->tokenIn($restored);

        $this->withToken($token)->getJson('http://restaurar.localhost/api/me')->assertUnauthorized();
        Tenant::find(self::TEST_TENANT_ID)->makeCurrent();

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored])
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost), Activo: {$tenant->database} → {$restored}.")
            ->expectsOutput('Base restaurada: Taller de prueba, RUC 80012345-6.')
            ->expectsOutput('El taller está activo: lo que se haya guardado en la base actual después del backup se pierde.')
            ->expectsConfirmation('¿Cambiar la base del taller?', 'yes')
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost): restaurado, ahora usa la base {$restored}.")
            ->expectsOutput("La base anterior, {$tenant->database}, no se tocó: se borra a mano cuando ya no haga falta.")
            ->assertSuccessful();

        $this->assertSame($restored, $tenant->fresh()->database);
        $this->assertFileExists($tenant->database, 'La base anterior no se borra.');
        $this->assertSame(self::TEST_TENANT_ID, Tenant::current()?->id, 'Deja el taller que estaba activo.');

        Tenant::forgetCurrent();
        $this->withToken($token)->getJson('http://restaurar.localhost/api/me')->assertOk();

        Log::shouldHaveReceived('info')->with('Cambió la base del taller.', [
            'tenant_id' => $tenant->id, 'from' => $tenant->database, 'to' => $restored,
        ])->once();
    }

    public function test_if_the_restored_tenant_was_active_it_stays_active_with_its_new_database(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);
        $tenant->makeCurrent();

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->assertSuccessful();

        $this->assertSame($tenant->id, Tenant::current()?->id);
        $this->assertSame($restored, Tenant::current()->database);
        $this->assertSame($restored, DB::connection(config('multitenancy.tenant_database_connection_name'))->getDatabaseName());
    }

    public function test_nothing_changes_without_confirmation(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored])
            ->expectsConfirmation('¿Cambiar la base del taller?', 'no')
            ->expectsOutput('No se cambió nada.')
            ->assertFailed();

        $this->assertSame($tenant->database, $tenant->fresh()->database);
    }

    public function test_a_database_of_another_workshop_is_refused(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80099999-1');
        $this->tokenIn($restored);

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost): la base restaurada no es de este taller (RUC 80099999-1 en lugar de 80012345-6). No se cambió nada.")
            ->assertFailed();

        $this->assertSame($tenant->database, $tenant->fresh()->database);
    }

    public function test_when_the_current_database_does_not_respond_it_warns_and_restores(): void
    {
        $tenant = $this->tenantWith(database_path('testing-restore-caida.sqlite'), ['status' => TenantStatus::SUSPENDED]);
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored])
            ->expectsOutput('La base actual no responde o no tiene RUC: no se pudo comprobar que la restaurada sea de este taller.')
            ->doesntExpectOutput('El taller está activo: lo que se haya guardado en la base actual después del backup se pierde.')
            ->expectsConfirmation('¿Cambiar la base del taller?', 'yes')
            ->assertSuccessful();

        $this->assertSame($restored, $tenant->fresh()->database);
        $this->assertFileDoesNotExist(database_path('testing-restore-caida.sqlite'), 'No crea la base que falta.');
    }

    public function test_a_cancelled_tenant_is_restored_and_keeps_its_status(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'), [
            'status' => TenantStatus::CANCELLED, 'cancelled_at' => now(),
        ]);
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->assertSuccessful();

        $this->assertSame($restored, $tenant->fresh()->database);
        $this->assertSame(TenantStatus::CANCELLED, $tenant->fresh()->status);
    }

    public function test_pending_migrations_are_applied_to_the_restored_database_before_switching(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);
        // Un backup anterior a la 000074, que es repetible (`dropIfExists`).
        $this->sql($restored, "delete from migrations where migration = '0001_01_01_000074_drop_queue_and_cache_tables'");

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored])
            ->expectsOutput('Migraciones pendientes, que se aplican antes del cambio: 1.')
            ->expectsConfirmation('¿Cambiar la base del taller?', 'yes')
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost): migraciones aplicadas en la base restaurada: 1.")
            ->assertSuccessful();

        $tenant->fresh()->makeCurrent();
        $this->assertSame([], app(TenantMigrator::class)->pending());
    }

    public function test_a_failed_migration_leaves_the_tenant_on_its_previous_database(): void
    {
        Log::spy();
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);
        // Pendiente pero con la columna ya creada: la migración falla al repetirse.
        $this->sql($restored, "delete from migrations where migration = '0001_01_01_000073_add_customer_contact_to_invoices_table'");

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost): no se pudo preparar la base restaurada. No se cambió nada; el detalle está en el log.")
            ->assertFailed();

        $this->assertSame($tenant->database, $tenant->fresh()->database);
        Log::shouldHaveReceived('error')->withArgs(fn ($message, $context) => $message === 'Falló la restauración del taller.'
            && $context['tenant_id'] === $tenant->id)->once();
    }

    public function test_a_restored_database_without_an_administrator_is_refused(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->expectsOutput("Taller #{$tenant->id} (restaurar.localhost): la base restaurada no tiene ningún administrador. No se cambió nada.")
            ->assertFailed();

        $this->assertSame($tenant->database, $tenant->fresh()->database);
    }

    public function test_it_refuses_what_cannot_be_restored_without_touching_anything(): void
    {
        $current = $this->workshopBase('actual', '80012345-6');
        $tenant = $this->tenantWith($current);
        $other = Tenant::find(self::TEST_TENANT_ID);
        $empty = database_path('testing-restore-vacia.sqlite');
        touch($empty);
        $this->files[] = $empty;
        $central = config('database.connections.central.database');

        $cases = [
            [$current, 'la base restaurada debe tener otro nombre que la actual.'],
            // Otra forma de escribir la misma ruta no esquiva el control.
            [dirname($current).DIRECTORY_SEPARATOR.'.'.DIRECTORY_SEPARATOR.basename($current), 'la base restaurada debe tener otro nombre que la actual.'],
            [$central, 'la base es la central o la de la conexión por defecto.'],
            [$other->database, 'la base es la central o la de la conexión por defecto.'],
            [database_path('testing-restore-no-existe.sqlite'), 'la base '.database_path('testing-restore-no-existe.sqlite').' no existe.'],
            [$empty, "la base {$empty} está vacía."],
            ['otra.txt', 'La base debe ser la ruta de un archivo .sqlite en una carpeta que exista.'],
        ];

        foreach ($cases as [$database, $message]) {
            $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $database, '--force' => true])
                ->expectsOutput("Taller #{$tenant->id}: {$message}")
                ->assertFailed();
        }

        $this->assertSame($current, $tenant->fresh()->database);
    }

    public function test_the_database_of_another_tenant_is_refused(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $otherDatabase = $this->workshopBase('otro', '80012345-6');
        $other = Tenant::create(['name' => 'Otro', 'domain' => 'otro.localhost', 'database' => $otherDatabase]);

        // Sin el taller de prueba activo, la base del otro no es la de la conexión por defecto: la frena su propio control.
        Tenant::forgetCurrent();

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $otherDatabase, '--force' => true])
            ->expectsOutput("Taller #{$tenant->id}: la base es la del taller #{$other->id}.")
            ->assertFailed();
    }

    public function test_tenants_in_provisioning_or_with_their_database_deleted_are_refused(): void
    {
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $provisioning = $this->tenantWith($this->workshopBase('alta', '80012345-6'), ['status' => TenantStatus::PROVISIONING]);
        $deleted = Tenant::create([
            'name' => 'Borrado',
            'domain' => 'borrado.localhost',
            'database' => database_path('testing-restore-borrada.sqlite'),
            'status' => TenantStatus::CANCELLED,
            'cancelled_at' => now()->subYears(5),
            'database_deleted_at' => now(),
        ]);

        $this->artisan('tenants:restore', ['tenant' => $provisioning->id, 'database' => $restored, '--force' => true])
            ->expectsOutput("Taller #{$provisioning->id}: solo se restaura un taller activo, suspendido o dado de baja, y este está En alta.")
            ->assertFailed();

        $this->artisan('tenants:restore', ['tenant' => $deleted->id, 'database' => $restored, '--force' => true])
            ->expectsOutput("Taller #{$deleted->id}: su base se borró al vencer la conservación y no se restaura.")
            ->assertFailed();

        $this->artisan('tenants:restore', ['tenant' => 99999, 'database' => $restored, '--force' => true])
            ->expectsOutput('Taller #99999: no existe.')
            ->assertFailed();
    }

    public function test_it_respects_the_lock_of_a_migration_in_course(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);

        Tenant::forgetCurrent();
        $lock = Cache::lock(MigrateTenants::lockKey($tenant), 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
                ->expectsOutput("Taller #{$tenant->id}: se está migrando o restaurando en otra corrida.")
                ->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertSame($tenant->database, $tenant->fresh()->database);
    }

    public function test_the_permissions_cache_of_the_previous_database_is_forgotten(): void
    {
        $tenant = $this->tenantWith($this->workshopBase('actual', '80012345-6'));
        $restored = $this->workshopBase('restaurada', '80012345-6');
        $this->tokenIn($restored);
        $key = config('permission.cache.key');

        $tenant->makeCurrent();
        app(PermissionRegistrar::class)->getCacheRepository()->put($key, 'roles de la base anterior');
        Tenant::forgetCurrent();

        $this->artisan('tenants:restore', ['tenant' => $tenant->id, 'database' => $restored, '--force' => true])
            ->assertSuccessful();

        $tenant->fresh()->makeCurrent();
        $this->assertFalse(app(PermissionRegistrar::class)->getCacheRepository()->has($key));
    }

    /**
     * Copia de una base de taller migrada y con roles, con el RUC dado en `system_settings`.
     */
    private function workshopBase(string $name, string $taxId): string
    {
        $path = database_path("testing-restore-{$name}.sqlite");
        copy($this->tenantDatabase('restore-source'), $path);
        $this->files[] = $path;

        $now = now()->toDateTimeString();
        foreach (['company.name' => 'Taller de prueba', 'company.tax_id' => $taxId] as $key => $value) {
            $this->sql($path, 'insert into system_settings (key, value, created_at, updated_at) values (?, ?, ?, ?)', [$key, $value, $now, $now]);
        }

        return realpath($path);
    }

    private function tenantWith(string $database, array $attributes = []): Tenant
    {
        return Tenant::create([
            'name' => 'Restaurar',
            'domain' => 'restaurar.localhost',
            'database' => $database,
            ...$attributes,
        ]);
    }

    /**
     * Administrador con token en la base dada, sin tocar la central.
     */
    private function tokenIn(string $database): string
    {
        $previous = Tenant::current();
        (new Tenant)->forceFill(['id' => 999999, 'database' => $database])->makeCurrent();

        $user = User::create([
            'name' => 'admin_'.Str::random(8),
            'first_name' => 'Admin',
            'last_name' => 'Taller',
            'email' => Str::lower(Str::random(12)).'@test.local',
            'password' => bcrypt('password'),
        ])->assignRole('administrador');
        $token = $user->createToken('test')->plainTextToken;

        $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();

        return $token;
    }

    private function sql(string $database, string $statement, array $bindings = []): void
    {
        $pdo = new PDO("sqlite:{$database}");
        $pdo->prepare($statement)->execute($bindings);
        $pdo = null;
    }
}
