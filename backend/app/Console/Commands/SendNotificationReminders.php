<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Exceptions\TenantException;
use App\Models\Tenant;
use App\Multitenancy\TenantRuntime;
use App\Services\NotificationReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recordatorios por taller (T3, paso 4; historial 2.96): recorre los talleres activos y sin mantenimiento, o los de
 * `--tenant`, y en cada uno lo activa, lo prepara y registra sus recordatorios. Los avisos salen a la cola con su taller.
 * Un taller que falla queda en el log y no frena a los demás; el comando termina con código 1.
 *
 * Al terminar deja el taller que estaba activo, o ninguno.
 */
class SendNotificationReminders extends Command
{
    protected $signature = 'notifications:send-reminders
        {--tenant=* : Id del taller (se puede repetir). Sin la opción, todos los activos}';

    protected $description = 'Registra los recordatorios diarios de cada taller: facturas por vencer o vencidas, mantenimientos y cuentas por pagar vencidas';

    public function handle(NotificationReminderService $reminders, TenantRuntime $runtime): int
    {
        $ids = array_map('intval', $this->option('tenant'));
        $tenants = Tenant::query()
            ->where('status', TenantStatus::ACTIVE)
            ->where('maintenance', false)
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->orderBy('id')
            ->get();

        $failed = false;

        foreach (array_diff($ids, $tenants->modelKeys()) as $id) {
            $this->error("Taller #{$id}: no existe, está suspendido o está en mantenimiento.");
            $failed = true;
        }

        $previous = Tenant::current();

        try {
            foreach ($tenants as $tenant) {
                $failed = ! $this->runFor($tenant, $reminders, $runtime) || $failed;
            }
        } finally {
            $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runFor(Tenant $tenant, NotificationReminderService $reminders, TenantRuntime $runtime): bool
    {
        $this->line("Taller #{$tenant->id} ({$tenant->name}):");

        try {
            $tenant->makeCurrent();
            $runtime->ensureReady();
            $result = $reminders->run();
        } catch (Throwable $e) {
            // Si no se pudo preparar el taller, `TenantRuntime` ya dejó el detalle en el log.
            if (! $e instanceof TenantException) {
                Log::error('No se pudieron registrar los recordatorios del taller.', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e->getMessage(),
                ]);
            }

            $this->error('No se pudieron registrar los recordatorios. El detalle está en el log.');

            return false;
        }

        $this->info("Facturas: {$result['invoices']} aviso(s).");
        $this->info("Mantenimientos: {$result['maintenances']} aviso(s).");
        $this->info("Cuentas por pagar: {$result['accounts_payable']} aviso(s) interno(s).");

        return true;
    }
}
