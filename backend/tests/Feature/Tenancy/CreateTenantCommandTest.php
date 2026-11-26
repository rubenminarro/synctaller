<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Multitenancy\TenantDatabase;
use App\Multitenancy\TenantRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use PDO;
use RuntimeException;
use Tests\TestCase;

/**
 * `tenants:create` (T5, paso 3; historial 2.103): alta completa de un taller, que se puede retomar si falla y nunca
 * cae sobre una base con tablas. Cada test falla si se quita la protección que cubre.
 *
 * Cada taller usa un archivo SQLite propio que el test borra al terminar. Como en TenantMigrateCommandTest, la
 * transacción de los tests va en la conexión fija `sqlite`, porque activar un taller vuelve a conectar `tenant`, y la
 * cache es la `database` de la central: la `array` se vacía al cambiar de taller y perdería el lock.
 */
class CreateTenantCommandTest extends TestCase
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

    public function test_it_provisions_a_tenant_that_its_admin_can_log_into(): void
    {
        $database = $this->databasePath('nuevo');
        $previous = Tenant::current();

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, Artisan::call('tenants:create', $this->arguments($database)));
        $output = Artisan::output();

        $this->assertTrue($previous->is(Tenant::current()), 'Deja el taller que estaba activo.');

        $tenant = Tenant::where('domain', 'nuevo.localhost')->sole();
        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertNull($tenant->provisioning_error);
        $this->assertSame($database, $tenant->database);
        $this->assertFileExists($database);

        $this->assertSame(1, preg_match('/que no se vuelve a mostrar: (\S+)/', $output, $match));
        $password = $match[1];

        // Por el host del taller nuevo, como cualquier request: la base, el administrador y su configuración.
        $token = $this->postJson('http://nuevo.localhost/api/login', ['email' => 'admin@nuevo.com.py', 'password' => $password])
            ->assertOk()
            ->json('data.token');

        $this->withToken($token)->getJson('http://nuevo.localhost/api/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'admin@nuevo.com.py');

        $tenant->makeCurrent();
        $admin = User::where('email', 'admin@nuevo.com.py')->sole();
        $this->assertSame(['nuevo', 'Ana', 'Benítez'], [$admin->name, $admin->first_name, $admin->last_name]);
        $this->assertTrue($admin->hasRole('administrador'));
        $this->assertSame(1, User::count());

        $settings = SystemSetting::pluck('value', 'key')->all();
        $this->assertSame('Taller Nuevo', $settings['company.name']);
        $this->assertSame('Taller Nuevo S.A.', $settings['company.legal_name']);
        $this->assertSame('80012345-6', $settings['company.tax_id']);

        // Vacíos y no ausentes: sin guardarlos, los PDF y los correos tomarían los de la plataforma.
        foreach (['company.activity', 'company.address', 'company.city', 'company.phone', 'company.email'] as $key) {
            $this->assertSame('', $settings[$key] ?? null, $key);
        }
    }

    public function test_a_failed_provisioning_stays_in_provisioning_and_resumes_keeping_the_admin_password(): void
    {
        Log::spy();
        $database = $this->databasePath('retomado');
        $runtime = Mockery::mock(TenantRuntime::class);
        $runtime->shouldReceive('ensureReady')->once()->andThrow(new RuntimeException('La base no respondió.'));
        $this->app->instance(TenantRuntime::class, $runtime);

        $this->artisan('tenants:create', $this->arguments($database, 'retomado'))
            ->expectsOutputToContain('que no se vuelve a mostrar')
            ->expectsOutputToContain('falló el alta y queda en provisioning')
            ->assertFailed();

        $tenant = Tenant::where('domain', 'retomado.localhost')->sole();
        $this->assertSame(TenantStatus::PROVISIONING, $tenant->status);
        $this->assertSame('La base no respondió.', $tenant->provisioning_error);
        $this->assertFalse((bool) Tenant::current()?->is($tenant), 'No deja activo el taller en alta.');

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'Falló el alta del taller.'
                && ($context['tenant_id'] ?? null) === $tenant->id)
            ->once();

        $hash = $this->adminPasswordHash($tenant, 'admin@retomado.com.py');

        // Mientras está en alta no atiende ni se migra.
        $this->getJson('http://retomado.localhost/api/branding')->assertNotFound()->assertJsonPath('error_code', 'TENANT_NOT_FOUND');
        $this->artisan('tenants:migrate', ['--tenant' => [$tenant->id]])
            ->expectsOutput("Taller #{$tenant->id}: no existe o no se migra en su estado.")
            ->assertFailed();

        $this->app->forgetInstance(TenantRuntime::class);

        $this->artisan('tenants:create', $this->arguments($database, 'retomado'))
            ->expectsOutput("Taller #{$tenant->id}: se retoma el alta.")
            ->expectsOutputToContain('ya existía y conserva su contraseña')
            ->doesntExpectOutputToContain('que no se vuelve a mostrar')
            ->assertSuccessful();

        $tenant->refresh();
        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertNull($tenant->provisioning_error);
        $this->assertSame(1, Tenant::where('domain', 'retomado.localhost')->count());
        $this->assertSame($hash, $this->adminPasswordHash($tenant, 'admin@retomado.com.py'));
    }

    public function test_it_refuses_a_database_that_already_has_tables(): void
    {
        $database = $this->databasePath('en-uso');
        (new PDO("sqlite:{$database}"))->exec('CREATE TABLE clients (id INTEGER PRIMARY KEY)');

        $this->artisan('tenants:create', $this->arguments($database))
            ->expectsOutput('La base ya existe y tiene tablas: el alta no se hace sobre una base en uso.')
            ->assertFailed();

        $this->assertFalse(Tenant::where('domain', 'nuevo.localhost')->exists());
        $this->assertSame(1, app(TenantDatabase::class)->tableCount($database), 'La base no se toca.');
    }

    public function test_an_empty_existing_database_is_accepted(): void
    {
        $database = $this->databasePath('vacia');
        touch($database);

        $this->artisan('tenants:create', $this->arguments($database))->assertSuccessful();

        $this->assertSame(TenantStatus::ACTIVE, Tenant::where('domain', 'nuevo.localhost')->sole()->status);
    }

    public function test_it_refuses_a_domain_or_database_of_another_tenant(): void
    {
        $taken = Tenant::find(self::TEST_TENANT_ID);

        $this->artisan('tenants:create', array_merge($this->arguments($this->databasePath('otro')), ['domain' => strtoupper($taken->domain)]))
            ->expectsOutput('Ya hay un taller con ese dominio.')
            ->assertFailed();

        // La del taller de prueba, que tiene datos: la protege la unicidad y, antes de ella, su contenido.
        $this->artisan('tenants:create', $this->arguments($taken->database))
            ->expectsOutput('Ya hay un taller con esa base.')
            ->assertFailed();

        // Con su dominio y su base a la vez: un taller activo no se "retoma", solo uno en alta.
        $this->artisan('tenants:create', array_merge($this->arguments($taken->database), ['domain' => $taken->domain]))
            ->expectsOutput('Ya hay un taller con ese dominio.')
            ->expectsOutput('Ya hay un taller con esa base.')
            ->assertFailed();

        $this->assertSame(TenantStatus::ACTIVE, $taken->fresh()->status);
        $this->assertSame('Taller de prueba', $taken->fresh()->name);

        $this->assertFalse(Tenant::where('domain', 'nuevo.localhost')->exists());
    }

    public function test_a_pending_provisioning_with_other_data_is_pointed_out_and_not_resumed(): void
    {
        $pending = Tenant::create([
            'name' => 'Pendiente',
            'domain' => 'nuevo.localhost',
            'database' => $this->databasePath('pendiente'),
            'status' => TenantStatus::PROVISIONING,
        ]);

        $this->artisan('tenants:create', $this->arguments($this->databasePath('otra')))
            ->expectsOutput('Ya hay un taller con ese dominio.')
            ->expectsOutputToContain("o se descarta con tenants:discard {$pending->id}")
            ->assertFailed();

        $this->assertSame(TenantStatus::PROVISIONING, $pending->fresh()->status);
        $this->assertFileDoesNotExist($this->files[1]);
    }

    public function test_it_validates_the_admin_and_company_data_before_registering(): void
    {
        $arguments = array_merge($this->arguments($this->databasePath('nuevo')), [
            '--admin-email' => 'no-es-un-email',
            '--admin-username' => 'ab',
            '--admin-last-name' => 'B3nítez',
            '--company-tax-id' => '',
        ]);

        $this->artisan('tenants:create', $arguments)
            ->expectsOutput('El campo email del administrador debe ser un correo válido.')
            ->expectsOutput('El campo usuario del administrador debe tener al menos 4 caracteres.')
            ->expectsOutput('El apellido del administrador solo puede contener letras y espacios.')
            ->expectsOutput('El campo RUC es obligatorio.')
            ->assertFailed();

        $this->assertFalse(Tenant::where('domain', 'nuevo.localhost')->exists());
        $this->assertFileDoesNotExist($this->files[0]);
    }

    public function test_it_rejects_a_domain_with_scheme_or_port_and_an_invalid_database_name(): void
    {
        $this->artisan('tenants:create', array_merge($this->arguments($this->databasePath('nuevo')), ['domain' => 'nuevo.localhost:8000']))
            ->expectsOutput('El dominio debe ser solo el host, sin esquema, puerto ni ruta.')
            ->assertFailed();

        $this->artisan('tenants:create', $this->arguments(database_path('testing-create-nuevo.db')))
            ->expectsOutput('La base debe ser la ruta de un archivo .sqlite en una carpeta que exista.')
            ->assertFailed();

        $this->assertSame(0, Tenant::where('name', 'Taller Nuevo')->count());
    }

    public function test_mysql_database_names_only_allow_lowercase_digits_and_underscore(): void
    {
        config(['database.connections.'.config('multitenancy.tenant_database_connection_name').'.driver' => 'mysql']);
        $databases = app(TenantDatabase::class);

        $this->assertNull($databases->invalidName('synctaller_taller_2'));

        foreach (['synctaller`; DROP DATABASE synctaller; --', 'Synctaller', 'synctaller-2', '', str_repeat('a', 65)] as $name) {
            $this->assertNotNull($databases->invalidName($name), $name);
        }
    }

    public function test_it_respects_the_lock_of_another_provisioning(): void
    {
        // Sin taller activo, como lo toma el comando: con uno, la clave llevaría su prefijo.
        Tenant::forgetCurrent();
        $lock = Cache::lock(CreateTenant::lockKey('nuevo.localhost'), 60);
        $this->assertTrue($lock->get());

        try {
            $this->artisan('tenants:create', $this->arguments($this->databasePath('nuevo')))
                ->expectsOutput('El taller nuevo.localhost ya se está dando de alta en otra corrida.')
                ->assertFailed();
        } finally {
            $lock->release();
        }

        $this->assertFalse(Tenant::where('domain', 'nuevo.localhost')->exists());
    }

    /**
     * @return array<string, string>
     */
    private function arguments(string $database, string $slug = 'nuevo'): array
    {
        return [
            'name' => 'Taller '.ucfirst($slug),
            'domain' => "{$slug}.localhost",
            'database' => $database,
            '--admin-email' => "admin@{$slug}.com.py",
            '--admin-username' => $slug,
            '--admin-first-name' => 'Ana',
            '--admin-last-name' => 'Benítez',
            '--company-name' => 'Taller '.ucfirst($slug),
            '--company-legal-name' => 'Taller '.ucfirst($slug).' S.A.',
            '--company-tax-id' => '80012345-6',
        ];
    }

    private function adminPasswordHash(Tenant $tenant, string $email): string
    {
        $previous = Tenant::current();
        $tenant->makeCurrent();

        try {
            $hash = User::where('email', $email)->sole()->password;

            return $hash;
        } finally {
            $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
        }
    }

    /**
     * Una ruta sin archivo. Si el comando lo crea, el test lo borra al terminar.
     */
    private function databasePath(string $name): string
    {
        $path = database_path("testing-create-{$name}.sqlite");

        if (file_exists($path)) {
            unlink($path);
        }

        return $this->files[] = $path;
    }
}
