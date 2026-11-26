<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * `notifications:send-reminders` por taller (T3, paso 4; historial 2.96): recorre los talleres activos y sin
 * mantenimiento, o los de `--tenant`; un taller que falla no frena a los demás. Cada test falla si se quita la
 * protección que cubre.
 *
 * El taller de prueba (TestCase) queda en mantenimiento para que el comando no lo recorra: su base es la de los tests,
 * fuera de la transacción de la conexión fija `sqlite`.
 */
class ReminderCommandTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    protected function setUp(): void
    {
        parent::setUp();

        Tenant::forgetCurrent();
        Tenant::whereKey(self::TEST_TENANT_ID)->update(['maintenance' => true]);
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_it_runs_in_every_active_tenant_and_a_failing_one_does_not_stop_the_rest(): void
    {
        Log::spy();
        $broken = $this->tenant('caido', $this->missingDatabase('caido'));
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));
        $suspended = $this->tenant('suspendido', $this->missingDatabase('suspendido'), ['status' => TenantStatus::SUSPENDED]);
        $maintenance = $this->tenant('mantenimiento', $this->missingDatabase('mantenimiento'), ['maintenance' => true]);

        $this->artisan('notifications:send-reminders')
            ->expectsOutput("Taller #{$broken->id} (Taller caido):")
            ->expectsOutput('No se pudieron registrar los recordatorios. El detalle está en el log.')
            ->expectsOutput("Taller #{$a->id} (Taller a):")
            ->expectsOutput("Taller #{$b->id} (Taller b):")
            ->doesntExpectOutput("Taller #{$suspended->id} (Taller suspendido):")
            ->doesntExpectOutput("Taller #{$maintenance->id} (Taller mantenimiento):")
            ->assertFailed();

        $this->assertNull(Tenant::current(), 'Deja el taller que estaba activo: ninguno.');
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'La base del taller no responde.'
                && ($context['tenant_id'] ?? null) === $broken->id)
            ->once();
    }

    public function test_the_tenant_option_limits_the_run_to_active_tenants(): void
    {
        $a = $this->tenant('a', $this->tenantDatabase('a'));
        $b = $this->tenant('b', $this->tenantDatabase('b'));
        $suspended = $this->tenant('suspendido', $this->missingDatabase('suspendido'), ['status' => TenantStatus::SUSPENDED]);

        $this->artisan('notifications:send-reminders', ['--tenant' => [$b->id]])
            ->expectsOutput("Taller #{$b->id} (Taller b):")
            ->doesntExpectOutput("Taller #{$a->id} (Taller a):")
            ->assertSuccessful();

        $this->artisan('notifications:send-reminders', ['--tenant' => [$suspended->id]])
            ->expectsOutput("Taller #{$suspended->id}: no existe, está suspendido o está en mantenimiento.")
            ->assertFailed();
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
     * Una base que no existe: si el comando recorriera ese taller, fallaría al prepararlo.
     */
    private function missingDatabase(string $name): string
    {
        return database_path("testing-tenant-inexistente-{$name}.sqlite");
    }
}
