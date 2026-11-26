<?php

namespace Tests\Feature\Tenancy;

use App\Console\Commands\SendNotificationReminders;
use App\Enums\TenantStatus;
use App\Jobs\SendTenantReminders;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Services\NotificationReminderService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;
use Throwable;

/**
 * Recordatorios por taller (T3, pasos 4 y 5; historial 2.96 y 2.97): `notifications:send-reminders` despacha un job
 * por taller activo y sin mantenimiento, o por los de `--tenant`, y la cola lo corre en su taller. Con `--due` (el
 * scheduler) despacha cuando ya llegó la hora de cada taller y hoy no se despachó, aunque no sea el minuto exacto. Un
 * taller que falla no frena a los demás. Cada test falla si se quita la protección que cubre.
 *
 * Los jobs van a la cola `database` de la central, que comparte la transacción del test, y los procesa un worker en el
 * mismo proceso, como en TenantQueueTest. El taller de prueba (TestCase) queda en mantenimiento para que el comando no
 * lo recorra: su base es la de los tests, fuera de la transacción de la conexión fija `sqlite`.
 */
class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    /** @var array<int, int|null> taller activo en cada corrida del job */
    private array $runs = [];

    protected function setUp(): void
    {
        parent::setUp();

        // La cola y la cache de producción, en la central. La cache `array` de los tests se vacía al cambiar de taller.
        config(['queue.default' => 'database', 'cache.default' => 'database']);
        Tenant::forgetCurrent();
        Tenant::whereKey(self::TEST_TENANT_ID)->update(['maintenance' => true]);

        $this->mock(NotificationReminderService::class)
            ->shouldReceive('run')
            ->andReturnUsing(function () {
                $this->runs[] = Tenant::current()?->id;

                return ['invoices' => 0, 'maintenances' => 0, 'accounts_payable' => 0];
            });
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_it_dispatches_one_job_per_active_tenant_and_each_runs_in_its_tenant(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));
        $suspended = $this->tenant('suspendido', $this->missingDatabase('suspendido'), ['status' => TenantStatus::SUSPENDED]);
        $maintenance = $this->tenant('mantenimiento', $this->missingDatabase('mantenimiento'), ['maintenance' => true]);

        // Sin `--due`, sin mirar la hora: a las 05:00 todavía no es la de ninguno.
        $this->travelTo('2026-11-02 05:00:00');
        $this->artisan('notifications:send-reminders')
            ->expectsOutput("Taller #{$a->id} (Taller a): recordatorios despachados.")
            ->expectsOutput("Taller #{$b->id} (Taller b): recordatorios despachados.")
            ->doesntExpectOutputToContain("Taller #{$suspended->id}")
            ->doesntExpectOutputToContain("Taller #{$maintenance->id}")
            ->assertSuccessful();

        $this->assertNull(Tenant::current(), 'Deja el taller que estaba activo: ninguno.');
        $this->assertSame(2, $this->queuedJobs());

        $this->work();
        $this->work();

        $this->assertSame([$a->id, $b->id], $this->runs);
        $this->assertSame(0, $this->queuedJobs());
    }

    public function test_the_scheduler_dispatches_at_the_hour_of_each_tenant_once_a_day(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));
        $this->storeRemindersAt($a, null); // la del .env
        $this->storeRemindersAt($b, '10:00');
        config(['notifications.reminders_at' => '08:00']);

        $this->travelTo('2026-11-02 07:59:00');
        $this->due()->assertSuccessful();
        $this->assertSame(0, $this->queuedJobs());

        // No hace falta el minuto exacto: a las 09:00 ya pasó la de A.
        $this->travelTo('2026-11-02 09:00:00');
        $this->due()
            ->expectsOutput("Taller #{$a->id} (Taller a): recordatorios despachados.")
            ->doesntExpectOutputToContain("Taller #{$b->id}")
            ->assertSuccessful();
        $this->assertSame(1, $this->queuedJobs());

        $this->travelTo('2026-11-02 09:01:00');
        $this->due()->doesntExpectOutputToContain('despachados')->assertSuccessful();

        $this->travelTo('2026-11-02 10:00:00');
        $this->due()
            ->expectsOutput("Taller #{$b->id} (Taller b): recordatorios despachados.")
            ->doesntExpectOutputToContain("Taller #{$a->id}")
            ->assertSuccessful();

        // Al día siguiente, otra vez.
        $this->travelTo('2026-11-03 08:30:00');
        $this->due()
            ->expectsOutput("Taller #{$a->id} (Taller a): recordatorios despachados.")
            ->assertSuccessful();

        $this->assertSame(3, $this->queuedJobs());
        $this->assertNull(Tenant::current());
    }

    public function test_a_tenant_that_missed_its_hour_gets_its_reminders_when_it_is_available_again(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'), ['maintenance' => true]);
        $this->storeRemindersAt($a, '08:00');

        $this->travelTo('2026-11-02 08:00:00');
        $this->due()->assertSuccessful();
        $this->assertSame(0, $this->queuedJobs(), 'En mantenimiento no se recorre.');

        $a->update(['maintenance' => false]);
        $this->travelTo('2026-11-02 11:30:00');
        $this->due()
            ->expectsOutput("Taller #{$a->id} (Taller a): recordatorios despachados.")
            ->assertSuccessful();

        $this->work();
        $this->assertSame([$a->id], $this->runs);
    }

    public function test_a_failing_tenant_does_not_stop_the_rest_and_is_tried_again(): void
    {
        Log::spy();
        $broken = $this->tenant('caido', $this->missingDatabase('caido'));
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $this->storeRemindersAt($a, '08:00');

        $this->travelTo('2026-11-02 09:00:00');
        $this->due()
            ->expectsOutput("Taller #{$broken->id} (Taller caido): no se pudieron despachar los recordatorios. El detalle está en el log.")
            ->expectsOutput("Taller #{$a->id} (Taller a): recordatorios despachados.")
            ->assertFailed();

        $this->assertSame(1, $this->queuedJobs());
        $this->assertNull(Cache::get(SendNotificationReminders::dispatchedKey($broken)), 'Sin marcar el día: se reintenta.');
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'La base del taller no responde.'
                && ($context['tenant_id'] ?? null) === $broken->id)
            ->once();

        $this->travelTo('2026-11-02 09:01:00');
        $this->due()
            ->expectsOutput("Taller #{$broken->id} (Taller caido): no se pudieron despachar los recordatorios. El detalle está en el log.")
            ->assertFailed();
    }

    public function test_the_tenant_option_limits_the_run_to_active_tenants(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));
        $suspended = $this->tenant('suspendido', $this->missingDatabase('suspendido'), ['status' => TenantStatus::SUSPENDED]);

        $this->artisan('notifications:send-reminders', ['--tenant' => [$b->id]])
            ->expectsOutput("Taller #{$b->id} (Taller b): recordatorios despachados.")
            ->doesntExpectOutputToContain("Taller #{$a->id}")
            ->assertSuccessful();

        $this->artisan('notifications:send-reminders', ['--tenant' => [$suspended->id]])
            ->expectsOutput("Taller #{$suspended->id}: no existe, está suspendido o está en mantenimiento.")
            ->assertFailed();

        $this->assertSame(1, $this->queuedJobs());
    }

    public function test_it_leaves_the_tenant_that_was_current(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));

        $a->makeCurrent();
        $this->artisan('notifications:send-reminders')->assertSuccessful();

        $this->assertSame($a->id, Tenant::current()?->id);
        $this->assertNotSame($b->id, Tenant::current()?->id);
    }

    public function test_the_scheduler_runs_every_minute_with_the_due_option_and_without_a_global_filter(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'notifications:send-reminders'));

        $event = $events->sole();
        $this->assertStringEndsWith('notifications:send-reminders --due', $event->command);
        $this->assertSame('* * * * *', $event->expression);

        // La hora la decide cada taller: el scheduler no filtra ni consulta la conexión por defecto.
        $this->travelTo('2026-11-02 05:00:00');
        $this->assertTrue($event->filtersPass(app()));
    }

    public function test_it_fails_closed_without_a_database_in_the_default_connection(): void
    {
        // Producción (TD10): `DB_DATABASE` vacío. Sin taller activo, una consulta falla.
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $this->storeRemindersAt($a, '08:00');
        config(['database.connections.closed' => array_merge(config('database.connections.sqlite'), ['database' => ''])]);
        DB::setDefaultConnection('closed');

        try {
            DB::table('users')->count();
            $this->fail('La conexión por defecto respondió sin taller activo.');
        } catch (Throwable) {
            // Esperado.
        }

        // El scheduler y el worker no la necesitan.
        $this->travelTo('2026-11-02 09:00:00');
        $this->due()->assertSuccessful();
        $this->work();

        $this->assertSame([$a->id], $this->runs);
        $this->assertSame('closed', DB::getDefaultConnection());
    }

    private function due()
    {
        return $this->artisan('notifications:send-reminders', ['--due' => true]);
    }

    private function work(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
    }

    private function queuedJobs(): int
    {
        return DB::connection('central')->table('jobs')->pluck('payload')
            ->filter(fn (string $payload) => json_decode($payload, true)['displayName'] === SendTenantReminders::class)
            ->count();
    }

    /**
     * Guarda (o borra, con null) la hora de los recordatorios en la base del taller y lo deja sin activar.
     */
    private function storeRemindersAt(Tenant $tenant, ?string $value): void
    {
        $tenant->makeCurrent();

        if ($value === null) {
            SystemSetting::where('key', 'notifications.reminders_at')->delete();
        } else {
            SystemSetting::updateOrCreate(['key' => 'notifications.reminders_at'], ['value' => $value]);
        }

        Tenant::forgetCurrent();
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

    /**
     * Una base que no existe: si el comando tuviera que leerla, fallaría al preparar el taller.
     */
    private function missingDatabase(string $name): string
    {
        return database_path("testing-tenant-inexistente-{$name}.sqlite");
    }
}
