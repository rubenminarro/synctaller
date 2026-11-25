<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Client;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * Identificación del taller por el host (T2, paso 2; reglas de negocio 6.34, historial 2.93) y aislamiento entre dos
 * talleres (§3.12 de la especificación). Cada test falla si se quita la revisión que cubre.
 *
 * Cambiar de taller vuelve a conectar `tenant`: la transacción de RefreshDatabase va en la conexión fija `sqlite`, como
 * en SwitchTenantTest. Los datos de los talleres A y B quedan en sus archivos (UsesTenantDatabases).
 */
class IdentifyTenantTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    public function test_an_unknown_host_responds_tenant_not_found_on_every_route(): void
    {
        foreach ($this->routes('desconocido.localhost') as [$method, $uri]) {
            $this->json($method, $uri, $this->loginPayload())
                ->assertNotFound()
                ->assertJsonPath('error_code', 'TENANT_NOT_FOUND')
                ->assertJsonPath('message', 'No se encontró el taller.');
        }
    }

    public function test_a_suspended_tenant_responds_forbidden_also_on_login_and_branding(): void
    {
        $this->registerTenant('a', ['status' => TenantStatus::SUSPENDED]);

        foreach ($this->routes('a.localhost') as [$method, $uri]) {
            $this->json($method, $uri, $this->loginPayload())
                ->assertForbidden()
                ->assertJsonPath('error_code', 'TENANT_SUSPENDED');
        }
    }

    public function test_a_tenant_in_maintenance_responds_service_unavailable(): void
    {
        $this->registerTenant('a', ['maintenance' => true]);

        foreach ($this->routes('a.localhost') as [$method, $uri]) {
            $this->json($method, $uri, $this->loginPayload())
                ->assertServiceUnavailable()
                ->assertJsonPath('error_code', 'TENANT_MAINTENANCE');
        }
    }

    public function test_suspension_is_checked_before_maintenance(): void
    {
        $this->registerTenant('a', ['status' => TenantStatus::SUSPENDED, 'maintenance' => true]);

        $this->getJson('http://a.localhost/api/branding')
            ->assertForbidden()
            ->assertJsonPath('error_code', 'TENANT_SUSPENDED');
    }

    public function test_a_tenant_whose_database_does_not_respond_answers_without_technical_details(): void
    {
        $missing = database_path('testing-tenant-inexistente.sqlite');
        Tenant::create(['name' => 'Sin base', 'domain' => 'caido.localhost', 'database' => $missing]);
        Log::spy();

        $response = $this->getJson('http://caido.localhost/api/branding')
            ->assertServiceUnavailable()
            ->assertJsonPath('error_code', 'TENANT_UNAVAILABLE')
            ->assertJsonPath('message', 'El servicio del taller no está disponible.');

        $this->assertStringNotContainsString('sqlite', $response->getContent());
        $this->assertNull(Tenant::current(), 'Un taller sin base no queda activo.');
        // El detalle va al log, con el taller.
        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message, array $context = []) => $message === 'La base del taller no responde.'
                && ($context['tenant_id'] ?? null) === Tenant::where('domain', 'caido.localhost')->value('id')
                && str_contains($context['exception'], 'testing-tenant-inexistente'))
            ->once();
    }

    public function test_the_host_is_compared_without_port_and_case(): void
    {
        $this->registerTenant('a');

        $this->getJson('http://A.LocalHost:8000/api/branding')->assertOk();
    }

    public function test_the_request_runs_on_the_tenant_database_as_default_connection(): void
    {
        $a = $this->registerTenant('a');
        $token = $this->tokenIn($a);

        $this->withToken($token)->getJson('http://a.localhost/api/me')->assertOk();

        $this->assertTrue(Tenant::current()->is($a));
        $this->assertSame('tenant', DB::getDefaultConnection());
        $this->assertSame($a->database, DB::connection()->getDatabaseName());
    }

    public function test_a_token_of_tenant_a_is_rejected_on_the_host_of_tenant_b(): void
    {
        $a = $this->registerTenant('a');
        $this->registerTenant('b');
        $token = $this->tokenIn($a);

        $this->withToken($token)->getJson('http://b.localhost/api/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_a_record_of_tenant_a_does_not_exist_on_tenant_b(): void
    {
        $a = $this->registerTenant('a');
        $b = $this->registerTenant('b');

        $a->makeCurrent();
        $client = $this->client();
        $token = $this->tokenIn($b);

        $this->withToken($token)->getJson("http://b.localhost/api/clients/{$client->id}")
            ->assertNotFound()
            ->assertJsonPath('error_code', 'RESOURCE_NOT_FOUND');
    }

    public function test_exists_rules_do_not_see_records_of_another_tenant(): void
    {
        $a = $this->registerTenant('a');
        $b = $this->registerTenant('b');

        $a->makeCurrent();
        $client = $this->client();
        $token = $this->tokenIn($b);

        $this->withToken($token)->postJson('http://b.localhost/api/receptions', ['client_id' => $client->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('client_id');
    }

    /**
     * Rutas públicas y privadas: la identificación va antes de autenticar.
     *
     * @return list<array{string, string}>
     */
    private function routes(string $host): array
    {
        return [
            ['POST', "http://{$host}/api/login"],
            ['GET', "http://{$host}/api/branding"],
            ['GET', "http://{$host}/api/me"],
        ];
    }

    private function loginPayload(): array
    {
        return ['email' => 'nadie@test.local', 'password' => 'password'];
    }

    private function registerTenant(string $name, array $attributes = []): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => $this->tenantDatabase($name),
            ...$attributes,
        ]);
    }

    /**
     * Un administrador con su token, guardado en la base del taller.
     */
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

    /**
     * Un cliente en el taller activo. Solo se crean en A: un id de A no puede existir en B por casualidad.
     */
    private function client(): Client
    {
        return Client::create([
            'document_number' => '4'.random_int(100000, 999999),
            'first_name' => 'Ana',
            'last_name' => 'Gomez',
            'phone' => '0981'.random_int(100000, 999999),
            'email' => Str::lower(Str::random(12)).'@example.com',
        ]);
    }
}
