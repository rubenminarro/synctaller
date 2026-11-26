<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Multitenancy\TenantStatusTransition;
use Illuminate\Console\Command;

/**
 * Reactiva un taller suspendido (P9; T5, paso 4, historial 2.104). Sus sesiones vuelven a funcionar y los avisos que
 * quedaron `failed` por la suspensión se pueden reenviar. Un taller dado de baja no se reactiva: la baja es final.
 */
class ReactivateTenant extends Command
{
    protected $signature = 'tenants:reactivate {tenant : Id del taller suspendido}';

    protected $description = 'Reactiva un taller suspendido';

    public function handle(TenantStatusTransition $transition): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Taller #{$this->argument('tenant')}: no existe.");

            return self::FAILURE;
        }

        $refused = $transition->apply($tenant, [TenantStatus::SUSPENDED], TenantStatus::ACTIVE);

        if ($refused !== null) {
            $this->error("Taller #{$tenant->id}: solo se reactiva un taller suspendido, y este está {$refused->label()}.");

            return self::FAILURE;
        }

        $this->info("Taller #{$tenant->id} ({$tenant->domain}) reactivado.");

        return self::SUCCESS;
    }
}
