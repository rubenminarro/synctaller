<?php

namespace Tests\Feature\Tenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\UsesCentralDatabase;
use Tests\TestCase;

/**
 * Registro de talleres en la base central (T2, paso 1; historial 2.90).
 */
class TenantTest extends TestCase
{
    use RefreshDatabase;
    use UsesCentralDatabase;

    public function test_the_tenant_is_stored_in_the_central_database_and_not_in_the_workshop_one(): void
    {
        $tenant = Tenant::create(['name' => 'Taller A', 'domain' => 'a.localhost', 'database' => 'synctaller_a']);

        $this->assertSame('central', $tenant->getConnectionName());
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'domain' => 'a.localhost'], 'central');
        $this->assertFalse(Schema::hasTable('tenants'), 'La tabla tenants no debe existir en la base del taller.');
    }

    public function test_a_new_tenant_is_active_and_not_in_maintenance(): void
    {
        $tenant = Tenant::create(['name' => 'Taller A', 'domain' => 'a.localhost', 'database' => 'synctaller_a'])->fresh();

        $this->assertSame(TenantStatus::ACTIVE, $tenant->status);
        $this->assertFalse($tenant->maintenance);
    }

    public function test_the_status_is_stored_with_the_enum_value(): void
    {
        $tenant = Tenant::create([
            'name' => 'Taller A',
            'domain' => 'a.localhost',
            'database' => 'synctaller_a',
            'status' => TenantStatus::SUSPENDED,
        ]);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'status' => 'suspended'], 'central');
        $this->assertSame(TenantStatus::SUSPENDED, $tenant->fresh()->status);
    }

    public function test_two_tenants_cannot_share_a_domain(): void
    {
        Tenant::create(['name' => 'Taller A', 'domain' => 'a.localhost', 'database' => 'synctaller_a']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tenant::create(['name' => 'Taller B', 'domain' => 'a.localhost', 'database' => 'synctaller_b']);
    }

    public function test_two_tenants_cannot_share_a_database(): void
    {
        Tenant::create(['name' => 'Taller A', 'domain' => 'a.localhost', 'database' => 'synctaller_a']);

        $this->expectException(UniqueConstraintViolationException::class);

        Tenant::create(['name' => 'Taller B', 'domain' => 'b.localhost', 'database' => 'synctaller_a']);
    }
}
