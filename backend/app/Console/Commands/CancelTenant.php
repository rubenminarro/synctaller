<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Multitenancy\TenantStatusTransition;
use Illuminate\Console\Command;

/**
 * Da de baja un taller activo o suspendido (T5, paso 4; historial 2.104). La baja es final: el taller responde
 * `404 TENANT_NOT_FOUND` (TD11), no se migra, el scheduler lo salta y sus jobs se descartan con registro. Su base y su
 * fila se conservan: la plataforma guarda la base 5 años desde `cancelled_at` (historial 2.94), y el borrado y la
 * exportación asistida son procedimientos operativos (paso 5). Pide confirmación salvo con `--force`.
 */
class CancelTenant extends Command
{
    protected $signature = 'tenants:cancel
        {tenant : Id del taller activo o suspendido}
        {--force : Sin pedir confirmación}';

    protected $description = 'Da de baja un taller (estado final; su base se conserva)';

    public function handle(TenantStatusTransition $transition): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Taller #{$this->argument('tenant')}: no existe.");

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Se da de baja el taller #{$tenant->id} ({$tenant->domain}). La baja es final: deja de responder y su base se conserva. ¿Seguir?")) {
            $this->line('No se dio de baja nada.');

            return self::FAILURE;
        }

        $refused = $transition->apply(
            $tenant,
            [TenantStatus::ACTIVE, TenantStatus::SUSPENDED],
            TenantStatus::CANCELLED,
            ['cancelled_at' => now()],
        );

        if ($refused !== null) {
            $this->error("Taller #{$tenant->id}: solo se da de baja un taller activo o suspendido, y este está {$refused->label()}.");

            return self::FAILURE;
        }

        $this->info("Taller #{$tenant->id} ({$tenant->domain}) dado de baja el {$tenant->cancelled_at->format('d/m/Y H:i')}. Su base {$tenant->database} se conserva.");

        return self::SUCCESS;
    }
}
