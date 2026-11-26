<?php

namespace Tests\Feature\Tenancy;

use App\Jobs\SendClientNotification;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Multitenancy\TenantRuntime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use LogicException;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * Preparar el taller (T2, paso 3; historial 2.95): `TenantRuntime::ensureReady()` comprueba la base del taller activo y
 * aplica su configuración, en el request, en cada job y en el comando de recordatorios. Sin taller activo, falla. Cada test
 * falla si se quita la protección que cubre.
 *
 * Como en IdentifyTenantTest, la transacción va en la conexión fija `sqlite` y los datos de A y B quedan en sus
 * archivos (UsesTenantDatabases): los valores se guardan con `updateOrCreate` y se borran los que el test no quiere.
 */
class TenantRuntimeTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    public function test_each_request_applies_the_settings_of_its_tenant(): void
    {
        config(['company.legal_name' => 'Razón social del .env']);
        $a = $this->registerTenant('a');
        $b = $this->registerTenant('b');
        $this->storeLegalName($a, 'Taller A S.A.');
        $this->storeLegalName($b, 'Taller B S.A.');

        $this->getJson('http://a.localhost/api/branding')->assertOk();
        $this->assertSame('Taller A S.A.', config('company.legal_name'));

        $this->getJson('http://b.localhost/api/branding')->assertOk();
        $this->assertSame('Taller B S.A.', config('company.legal_name'));
    }

    public function test_a_value_saved_in_one_tenant_does_not_reach_another_without_it(): void
    {
        config(['company.legal_name' => 'Razón social del .env']);
        $a = $this->registerTenant('a');
        $b = $this->registerTenant('b');
        $this->storeLegalName($a, 'Taller A S.A.');
        $this->storeLegalName($b, null);

        $this->getJson('http://a.localhost/api/branding')->assertOk();
        $this->getJson('http://b.localhost/api/branding')->assertOk();

        $this->assertSame('Razón social del .env', config('company.legal_name'));
    }

    public function test_a_tenant_whose_settings_cannot_be_read_responds_without_technical_details(): void
    {
        // La base existe y conecta, pero no está migrada: falla la lectura de system_settings.
        $empty = database_path('testing-tenant-sin-migrar.sqlite');
        file_put_contents($empty, '');
        $tenant = Tenant::create(['name' => 'Sin migrar', 'domain' => 'vacio.localhost', 'database' => realpath($empty)]);
        Log::spy();

        $response = $this->getJson('http://vacio.localhost/api/branding')
            ->assertServiceUnavailable()
            ->assertJsonPath('error_code', 'TENANT_UNAVAILABLE')
            ->assertJsonPath('message', 'El servicio del taller no está disponible.');

        $this->assertStringNotContainsString('system_settings', $response->getContent());
        $this->assertNull(Tenant::current(), 'Un taller sin configuración legible no queda activo.');
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'No se pudo aplicar la configuración del taller.'
                && ($context['tenant_id'] ?? null) === $tenant->id
                && str_contains($context['exception'], 'system_settings'))
            ->once();
    }

    public function test_each_job_applies_the_settings_of_the_active_tenant(): void
    {
        config(['company.legal_name' => 'Razón social del .env']);
        $a = $this->registerTenant('a');
        $this->storeLegalName($a, 'Taller A S.A.');
        $a->makeCurrent();

        // En su middleware (`PrepareTenant`, historial 2.96). Un aviso inexistente no envía nada.
        SendClientNotification::dispatch(0);

        $this->assertSame('Taller A S.A.', config('company.legal_name'));
    }

    public function test_without_an_active_tenant_it_fails_closed(): void
    {
        // Fallo cerrado (TD10; T3, paso 5, historial 2.97): antes aplicaba la configuración de `DB_CONNECTION`.
        config(['company.legal_name' => 'Razón social del .env']);
        Tenant::forgetCurrent();
        SystemSetting::create(['key' => 'company.legal_name', 'value' => 'De la conexión por defecto S.A.']);

        try {
            app(TenantRuntime::class)->ensureReady();
            $this->fail('Se preparó la configuración sin taller activo.');
        } catch (LogicException $e) {
            $this->assertSame('Preparar el taller requiere un taller activo.', $e->getMessage());
        }

        $this->assertSame('Razón social del .env', config('company.legal_name'));
    }

    private function registerTenant(string $name): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => $this->tenantDatabase($name),
        ]);
    }

    /**
     * Guarda (o borra, con null) la razón social en la base del taller y lo deja sin activar.
     */
    private function storeLegalName(Tenant $tenant, ?string $value): void
    {
        $tenant->makeCurrent();

        if ($value === null) {
            SystemSetting::where('key', 'company.legal_name')->delete();
        } else {
            SystemSetting::updateOrCreate(['key' => 'company.legal_name'], ['value' => $value]);
        }

        Tenant::forgetCurrent();
    }
}
