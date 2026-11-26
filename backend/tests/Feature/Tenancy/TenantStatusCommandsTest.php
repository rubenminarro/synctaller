<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use App\Multitenancy\TenantStatusTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * `tenants:suspend`, `tenants:reactivate` y `tenants:cancel` (T5, paso 4; historial 2.104): cada comando acepta solo
 * sus estados de origen, relee la fila bloqueada y no toca tokens ni mantenimiento. Cada test falla si se quita la
 * protección que cubre.
 */
class TenantStatusCommandsTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_a_suspended_tenant_keeps_its_sessions_and_gets_them_back_when_reactivated(): void
    {
        Log::spy();
        $a = $this->registerTenant('a');
        $token = $this->tokenIn($a);

        $this->artisan('tenants:suspend', ['tenant' => $a->id])
            ->expectsOutput("Taller #{$a->id} (a.localhost) suspendido: no atiende hasta reactivarlo.")
            ->assertSuccessful();

        $this->assertSame(TenantStatus::SUSPENDED, $a->fresh()->status);
        $this->withToken($token)->getJson('http://a.localhost/api/me')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'TENANT_SUSPENDED');

        $this->artisan('tenants:reactivate', ['tenant' => $a->id])
            ->expectsOutput("Taller #{$a->id} (a.localhost) reactivado.")
            ->assertSuccessful();

        $this->assertSame(TenantStatus::ACTIVE, $a->fresh()->status);
        $this->withToken($token)->getJson('http://a.localhost/api/me')->assertOk();

        Log::shouldHaveReceived('info')->with('Cambió el estado del taller.', ['tenant_id' => $a->id, 'status' => 'suspended'])->once();
        Log::shouldHaveReceived('info')->with('Cambió el estado del taller.', ['tenant_id' => $a->id, 'status' => 'active'])->once();
    }

    public function test_suspending_and_reactivating_do_not_touch_the_maintenance(): void
    {
        $tenant = $this->row('mantenimiento', ['maintenance' => true]);

        $this->artisan('tenants:suspend', ['tenant' => $tenant->id])->assertSuccessful();
        $this->assertTrue($tenant->fresh()->maintenance);

        $this->artisan('tenants:reactivate', ['tenant' => $tenant->id])->assertSuccessful();
        $this->assertTrue($tenant->fresh()->maintenance);
    }

    public function test_only_an_active_tenant_is_suspended(): void
    {
        foreach ([TenantStatus::SUSPENDED, TenantStatus::PROVISIONING, TenantStatus::CANCELLED] as $status) {
            $tenant = $this->row($status->value, ['status' => $status]);

            $this->artisan('tenants:suspend', ['tenant' => $tenant->id])
                ->expectsOutput("Taller #{$tenant->id}: solo se suspende un taller activo, y este está {$status->label()}.")
                ->assertFailed();

            $this->assertSame($status, $tenant->fresh()->status);
        }
    }

    public function test_only_a_suspended_tenant_is_reactivated_and_a_cancellation_is_final(): void
    {
        foreach ([TenantStatus::ACTIVE, TenantStatus::PROVISIONING, TenantStatus::CANCELLED] as $status) {
            $tenant = $this->row($status->value, ['status' => $status]);

            $this->artisan('tenants:reactivate', ['tenant' => $tenant->id])
                ->expectsOutput("Taller #{$tenant->id}: solo se reactiva un taller suspendido, y este está {$status->label()}.")
                ->assertFailed();

            $this->assertSame($status, $tenant->fresh()->status);
        }
    }

    public function test_an_active_or_suspended_tenant_is_cancelled_with_its_date_and_stops_existing(): void
    {
        $this->travelTo(Carbon::parse('2026-11-26 15:30:00'));
        $a = $this->registerTenant('a');
        $token = $this->tokenIn($a);
        $suspended = $this->row('suspendido', ['status' => TenantStatus::SUSPENDED]);

        $this->artisan('tenants:cancel', ['tenant' => $a->id, '--force' => true])
            ->expectsOutput("Taller #{$a->id} (a.localhost) dado de baja el 26/11/2026 15:30. Su base {$a->database} se conserva.")
            ->assertSuccessful();
        $this->artisan('tenants:cancel', ['tenant' => $suspended->id, '--force' => true])->assertSuccessful();

        foreach ([$a, $suspended] as $tenant) {
            $tenant->refresh();
            $this->assertSame(TenantStatus::CANCELLED, $tenant->status);
            $this->assertTrue($tenant->cancelled_at->equalTo(now()));
        }

        $this->assertFileExists($a->database);
        $this->withToken($token)->getJson('http://a.localhost/api/me')
            ->assertNotFound()
            ->assertJsonPath('error_code', 'TENANT_NOT_FOUND');
    }

    public function test_nothing_is_cancelled_without_confirmation(): void
    {
        $tenant = $this->row('activo');

        $this->artisan('tenants:cancel', ['tenant' => $tenant->id])
            ->expectsConfirmation("Se da de baja el taller #{$tenant->id} (activo.localhost). La baja es final: deja de responder y su base se conserva. ¿Seguir?", 'no')
            ->expectsOutput('No se dio de baja nada.')
            ->assertFailed();

        $tenant->refresh();
        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertNull($tenant->cancelled_at);
    }

    public function test_a_tenant_in_provisioning_or_already_cancelled_is_not_cancelled(): void
    {
        $date = Carbon::parse('2026-01-10 09:00:00');

        foreach ([TenantStatus::PROVISIONING, TenantStatus::CANCELLED] as $status) {
            $tenant = $this->row($status->value, [
                'status' => $status,
                'cancelled_at' => $status === TenantStatus::CANCELLED ? $date : null,
            ]);

            $this->artisan('tenants:cancel', ['tenant' => $tenant->id, '--force' => true])
                ->expectsOutput("Taller #{$tenant->id}: solo se da de baja un taller activo o suspendido, y este está {$status->label()}.")
                ->assertFailed();

            $tenant->refresh();
            $this->assertSame($status, $tenant->status);
            $this->assertSame($status === TenantStatus::CANCELLED ? $date->toDateTimeString() : null, $tenant->cancelled_at?->toDateTimeString());
        }
    }

    public function test_the_transition_rereads_the_current_status(): void
    {
        // El comando cargó el taller activo y otra corrida lo dio de baja mientras tanto.
        $tenant = $this->row('carrera');
        Tenant::whereKey($tenant->id)->update(['status' => TenantStatus::CANCELLED->value]);

        $refused = app(TenantStatusTransition::class)->apply($tenant, [TenantStatus::ACTIVE], TenantStatus::SUSPENDED);

        $this->assertSame(TenantStatus::CANCELLED, $refused);
        $this->assertSame(TenantStatus::CANCELLED, $tenant->fresh()->status);
    }

    public function test_an_unknown_tenant_is_reported(): void
    {
        $commands = [
            'tenants:suspend' => [],
            'tenants:reactivate' => [],
            'tenants:cancel' => ['--force' => true],
        ];

        foreach ($commands as $command => $options) {
            $this->artisan($command, ['tenant' => 99999, ...$options])
                ->expectsOutput('Taller #99999: no existe.')
                ->assertFailed();
        }
    }

    /**
     * Un taller con su base migrada, para entrar por su host.
     */
    private function registerTenant(string $name): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => $this->tenantDatabase($name),
        ]);
    }

    /**
     * Solo la fila: los comandos no tocan la base del taller.
     */
    private function row(string $name, array $attributes = []): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => database_path("testing-no-usada-{$name}.sqlite"),
            ...$attributes,
        ]);
    }

    private function tokenIn(Tenant $tenant): string
    {
        $tenant->makeCurrent();

        $user = User::create([
            'name' => 'admin_'.Str::random(8),
            'first_name' => 'Admin',
            'last_name' => 'Taller',
            'email' => Str::lower(Str::random(12)).'@test.local',
            'password' => bcrypt('password'),
        ])->assignRole('administrador');

        return $user->createToken('test')->plainTextToken;
    }
}
