<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `tenants:register` (T2, paso 2; historial 2.93): solo crea la fila del taller en la base central.
 */
class RegisterTenantCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_registers_an_active_tenant_with_the_host_in_lowercase(): void
    {
        $this->artisan('tenants:register', ['name' => 'Taller 1', 'domain' => 'Taller1.Example.COM', 'database' => 'synctaller_1'])
            ->assertSuccessful();

        $tenant = Tenant::where('database', 'synctaller_1')->sole();

        $this->assertSame('taller1.example.com', $tenant->domain);
        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertFalse($tenant->maintenance);
    }

    public function test_it_rejects_a_domain_with_scheme_or_port(): void
    {
        foreach (['http://taller1.example.com', 'taller1.example.com:8000', 'taller1.example.com/api'] as $domain) {
            $this->artisan('tenants:register', ['name' => 'Taller 1', 'domain' => $domain, 'database' => 'synctaller_1'])
                ->expectsOutput('El dominio debe ser solo el host, sin esquema, puerto ni ruta.')
                ->assertFailed();
        }

        $this->assertFalse(Tenant::where('database', 'synctaller_1')->exists());
    }

    public function test_it_rejects_a_domain_or_database_already_registered(): void
    {
        Tenant::create(['name' => 'Taller 1', 'domain' => 'taller1.example.com', 'database' => 'synctaller_1']);

        $this->artisan('tenants:register', ['name' => 'Otro', 'domain' => 'TALLER1.example.com', 'database' => 'synctaller_2'])
            ->expectsOutput('Ya hay un taller con ese dominio.')
            ->assertFailed();

        $this->artisan('tenants:register', ['name' => 'Otro', 'domain' => 'taller2.example.com', 'database' => 'synctaller_1'])
            ->expectsOutput('Ya hay un taller con esa base.')
            ->assertFailed();
    }
}
