<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\MigrateTenants;
use App\Enums\TenantMigrationRunStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMigrationRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Tests\TestCase;

/**
 * `tenants:migrate` (T5, paso 1; historial 2.101): migra cada taller activo o suspendido con un lock por taller,
 * registra cada corrida en la central, deja en mantenimiento al que falla sin frenar a los demás y `--failed` repite
 * solo los fallidos. Cada test falla si se quita la protección que cubre.
 *
 * Cada taller usa un archivo SQLite propio que el test crea vacío y borra al terminar. El taller de prueba (TestCase)
 * también se recorre sin `--tenant`: su base ya está migrada, así que no tiene pendientes. Como en ReminderCommandTest,
 * la transacción de los tests va en la conexión fija `sqlite`, porque activar un taller vuelve a conectar `tenant`, y
 * la cache es la `database` de la central, la de producción: la `array` se vacía al cambiar de taller y perdería el lock.
 */
class TenantMigrateCommandTest extends TestCase
{
    use RefreshDatabase;

    protected $connectionsToTransact = ['sqlite'];

    /** @var list<string> */
    private array $files = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'database']);
        Tenant::forgetCurrent();
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

    public function test_it_migrates_active_and_suspended_tenants_and_records_each_run(): void
    {
        $a = $this->tenant('a', $this->emptyDatabase('a'));
        $suspended = $this->tenant('suspendido', $this->emptyDatabase('suspendido'), ['status' => TenantStatus::SUSPENDED]);
        $total = count($this->migrationNames());

        $this->artisan('tenants:migrate')
            ->expectsOutput("Taller #{$a->id} (Taller a): {$total} migraciones aplicadas.")
            ->expectsOutput("Taller #{$suspended->id} (Taller suspendido): {$total} migraciones aplicadas.")
            ->assertSuccessful();

        $this->assertNull(Tenant::current(), 'Deja el taller que estaba activo: ninguno.');

        foreach ([$a, $suspended] as $tenant) {
            $this->assertSame($this->migrationNames(), $this->ranIn($tenant));

            $run = TenantMigrationRun::where('tenant_id', $tenant->id)->sole();
            $this->assertSame(TenantMigrationRunStatus::SUCCEEDED, $run->status);
            $this->assertSame($this->migrationNames(), $run->migrations);
            $this->assertNull($run->error);
            $this->assertNotNull($run->finished_at);
        }

        // Sin pendientes, otra corrida no aplica nada y también queda registrada.
        $this->artisan('tenants:migrate', ['--tenant' => [$a->id]])
            ->expectsOutput("Taller #{$a->id} (Taller a): sin migraciones pendientes.")
            ->assertSuccessful();

        $this->assertSame([], TenantMigrationRun::where('tenant_id', $a->id)->latest('id')->first()->migrations);
    }

    public function test_a_failing_tenant_stays_in_maintenance_and_the_rest_are_migrated(): void
    {
        Log::spy();
        $broken = $this->tenant('caido', $this->missingDatabase('caido'));
        $a = $this->tenant('a', $this->emptyDatabase('a'));

        $this->artisan('tenants:migrate', ['--tenant' => [$broken->id, $a->id]])
            ->expectsOutput("Taller #{$broken->id} (Taller caido): falló la migración y queda en mantenimiento. El detalle está en el log.")
            ->expectsOutput("Taller #{$a->id} (Taller a): ".count($this->migrationNames()).' migraciones aplicadas.')
            ->assertFailed();

        $this->assertTrue($broken->fresh()->maintenance);
        $this->assertFalse($a->fresh()->maintenance);
        $this->assertFileDoesNotExist($broken->database, 'Una base que falta es una falla: no se crea.');

        $run = TenantMigrationRun::where('tenant_id', $broken->id)->sole();
        $this->assertSame(TenantMigrationRunStatus::FAILED, $run->status);
        $this->assertTrue($run->maintenance_set);
        $this->assertNotEmpty($run->error);
        $this->assertSame([], $run->migrations);

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'Falló la migración del taller.'
                && ($context['tenant_id'] ?? null) === $broken->id)
            ->once();
    }

    public function test_a_migration_failing_halfway_records_what_was_applied(): void
    {
        $database = $this->emptyDatabase('mitad');
        // Una migración intermedia crea `vehicles`: si ya existe, falla después de aplicar las anteriores.
        (new PDO("sqlite:{$database}"))->exec('CREATE TABLE vehicles (id INTEGER PRIMARY KEY)');
        $tenant = $this->tenant('mitad', $database);

        $this->artisan('tenants:migrate', ['--tenant' => [$tenant->id]])->assertFailed();

        $position = array_search('0001_01_01_000008_create_vehicles_table', $this->migrationNames(), true);
        $this->assertGreaterThan(0, $position);
        $applied = array_slice($this->migrationNames(), 0, $position);

        $run = TenantMigrationRun::where('tenant_id', $tenant->id)->sole();
        $this->assertSame(TenantMigrationRunStatus::FAILED, $run->status);
        $this->assertSame($applied, $run->migrations);
        $this->assertStringContainsString('vehicles', $run->error);
        $this->assertSame($applied, $this->ranIn($tenant));
        $this->assertTrue($tenant->fresh()->maintenance);
    }

    public function test_the_failed_option_retries_only_the_failed_tenants_and_lifts_their_maintenance(): void
    {
        $broken = $this->tenant('caido', $this->missingDatabase('caido'));
        $a = $this->tenant('a', $this->emptyDatabase('a'));

        $this->artisan('tenants:migrate', ['--tenant' => [$broken->id, $a->id]])->assertFailed();

        // Se arregla la base (acá, se crea vacía) y se repiten solo los fallidos.
        touch($broken->database);

        $this->artisan('tenants:migrate', ['--failed' => true])
            ->expectsOutput("Taller #{$broken->id} (Taller caido): ".count($this->migrationNames()).' migraciones aplicadas.')
            ->doesntExpectOutputToContain("Taller #{$a->id}")
            ->doesntExpectOutputToContain('#'.self::TEST_TENANT_ID)
            ->assertSuccessful();

        $this->assertFalse($broken->fresh()->maintenance);
        $this->assertSame(1, TenantMigrationRun::where('tenant_id', $a->id)->count());

        $this->artisan('tenants:migrate', ['--failed' => true])
            ->expectsOutput('No hay talleres con la última corrida fallida.')
            ->assertSuccessful();

        $this->artisan('tenants:migrate', ['--failed' => true, '--tenant' => [$a->id]])
            ->expectsOutput("Taller #{$a->id}: no existe, no se migra en su estado o su última corrida no falló.")
            ->assertFailed();
    }

    public function test_it_does_not_lift_a_maintenance_set_by_hand(): void
    {
        $tenant = $this->tenant('manual', $this->missingDatabase('manual'), ['maintenance' => true]);

        $this->artisan('tenants:migrate', ['--tenant' => [$tenant->id]])->assertFailed();
        $this->assertFalse(TenantMigrationRun::where('tenant_id', $tenant->id)->sole()->maintenance_set);

        touch($tenant->database);
        $this->artisan('tenants:migrate', ['--failed' => true])->assertSuccessful();

        $this->assertTrue($tenant->fresh()->maintenance, 'El mantenimiento puesto a mano no es de este comando.');
    }

    public function test_a_tenant_already_being_migrated_is_reported_and_skipped(): void
    {
        $a = $this->tenant('a', $this->emptyDatabase('a'));
        $b = $this->tenant('b', $this->emptyDatabase('b'));
        $c = $this->tenant('c', $this->emptyDatabase('c'));
        // Otra corrida, sin taller activo, tiene tomados los locks de A y C.
        $locks = [Cache::lock(MigrateTenants::lockKey($a), 60), Cache::lock(MigrateTenants::lockKey($c), 60)];
        $this->assertTrue($locks[0]->get());
        $this->assertTrue($locks[1]->get());

        // El lock tiene que verse igual con un taller activo al empezar (A) y después de migrar otro (C): sin prefijo de cache.
        $b->makeCurrent();

        try {
            $this->artisan('tenants:migrate', ['--tenant' => [$a->id, $b->id, $c->id]])
                ->expectsOutput("Taller #{$a->id} (Taller a): ya se está migrando en otra corrida.")
                ->expectsOutput("Taller #{$b->id} (Taller b): ".count($this->migrationNames()).' migraciones aplicadas.')
                ->expectsOutput("Taller #{$c->id} (Taller c): ya se está migrando en otra corrida.")
                ->assertFailed();
        } finally {
            array_map(fn ($lock) => $lock->release(), $locks);
        }

        foreach ([$a, $c] as $tenant) {
            $this->assertSame(0, TenantMigrationRun::where('tenant_id', $tenant->id)->count());
            $this->assertSame([], $this->ranIn($tenant), 'Su base no se tocó.');
        }

        // Liberado el lock, se migra.
        Tenant::forgetCurrent();
        $this->artisan('tenants:migrate', ['--tenant' => [$a->id]])->assertSuccessful();
        $this->assertSame($this->migrationNames(), $this->ranIn($a));
    }

    public function test_the_tenant_option_reports_unknown_tenants(): void
    {
        $this->artisan('tenants:migrate', ['--tenant' => [999999]])
            ->expectsOutput('Taller #999999: no existe o no se migra en su estado.')
            ->assertFailed();
    }

    public function test_it_leaves_the_tenant_that_was_current(): void
    {
        $a = $this->tenant('a', $this->emptyDatabase('a'));
        $b = $this->tenant('b', $this->emptyDatabase('b'));

        $a->makeCurrent();
        $this->artisan('tenants:migrate', ['--tenant' => [$a->id, $b->id]])->assertSuccessful();

        $this->assertSame($a->id, Tenant::current()?->id);
    }

    /**
     * @return list<string>
     */
    private function migrationNames(): array
    {
        return collect(glob(database_path('migrations/*.php')))
            ->map(fn (string $path) => basename($path, '.php'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Migraciones registradas en la base del taller, leídas sin activarlo.
     *
     * @return list<string>
     */
    private function ranIn(Tenant $tenant): array
    {
        $pdo = new PDO("sqlite:{$tenant->database}");
        $exists = $pdo->query("SELECT count(*) FROM sqlite_master WHERE type = 'table' AND name = 'migrations'")->fetchColumn();

        if ((int) $exists === 0) {
            return [];
        }

        return $pdo->query('SELECT migration FROM migrations ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tenant(string $name, string $database, array $attributes = []): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => $database,
            ...$attributes,
        ]);
    }

    private function emptyDatabase(string $name): string
    {
        $path = $this->databasePath($name);
        touch($path);

        return $path;
    }

    /**
     * Una base que no existe. Si el comando la creara, el test la borra igual al terminar.
     */
    private function missingDatabase(string $name): string
    {
        return $this->databasePath($name);
    }

    private function databasePath(string $name): string
    {
        $path = database_path("testing-migrate-{$name}.sqlite");

        if (file_exists($path)) {
            unlink($path);
        }

        return $this->files[] = $path;
    }
}
