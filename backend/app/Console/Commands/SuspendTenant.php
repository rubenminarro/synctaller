<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Multitenancy\TenantStatusTransition;
use Illuminate\Console\Command;

/**
 * Suspende un taller activo (P9; T5, paso 4, historial 2.104): bloqueo total y reversible. Todas sus rutas, también el
 * login y el branding, responden `403 TENANT_SUSPENDED`, el scheduler lo salta y sus jobs se descartan con registro.
 * No borra datos ni revoca tokens: al reactivarlo (`tenants:reactivate`) las sesiones vuelven a funcionar.
 */
class SuspendTenant extends Command
{
    protected $signature = 'tenants:suspend {tenant : Id del taller activo}';

    protected $description = 'Suspende un taller activo (bloqueo total y reversible)';

    public function handle(TenantStatusTransition $transition): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Taller #{$this->argument('tenant')}: no existe.");

            return self::FAILURE;
        }

        $refused = $transition->apply($tenant, [TenantStatus::ACTIVE], TenantStatus::SUSPENDED);

        if ($refused !== null) {
            $this->error("Taller #{$tenant->id}: solo se suspende un taller activo, y este está {$refused->label()}.");

            return self::FAILURE;
        }

        $this->info("Taller #{$tenant->id} ({$tenant->domain}) suspendido: no atiende hasta reactivarlo.");

        return self::SUCCESS;
    }
}
