<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Exceptions\TenantException;
use App\Jobs\SendTenantReminders;
use App\Models\Tenant;
use App\Multitenancy\TenantRuntime;
use App\Services\WorkshopSettingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Recordatorios por taller (§3.5 de la especificación; T3, pasos 4 y 5, historial 2.96 y 2.97): recorre los talleres
 * activos y sin mantenimiento, o los de `--tenant`, y despacha a la cola un job de recordatorios por cada uno
 * (`SendTenantReminders`). La cola los procesa en su taller.
 *
 * Con `--due` (el scheduler, cada minuto) despacha solo si ya llegó la hora del taller (`reminders_at`, T1) y hoy
 * todavía no se despachó. No exige el minuto exacto: un taller que a su hora estaba en mantenimiento, suspendido, con la
 * base caída o con el servidor apagado recibe sus recordatorios en cuanto se puede. La fecha del último despacho queda
 * en la cache de la central.
 *
 * Un taller que falla queda en el log y no frena a los demás; el comando termina con código 1. Al terminar deja el
 * taller que estaba activo, o ninguno.
 */
class SendNotificationReminders extends Command
{
    protected $signature = 'notifications:send-reminders
        {--tenant=* : Id del taller (se puede repetir). Sin la opción, todos los activos}
        {--due : Solo los talleres a los que ya les llegó la hora y hoy no se les despachó (scheduler)}';

    protected $description = 'Despacha a la cola los recordatorios diarios de cada taller: facturas por vencer o vencidas, mantenimientos y cuentas por pagar vencidas';

    /**
     * Cuánto se recuerda el día del último despacho: alcanza con que pase la medianoche.
     */
    private const DISPATCHED_TTL_HOURS = 48;

    public function handle(TenantRuntime $runtime, WorkshopSettingService $settings): int
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
                $failed = ! $this->runFor($tenant, $runtime, $settings) || $failed;
            }
        } finally {
            $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function runFor(Tenant $tenant, TenantRuntime $runtime, WorkshopSettingService $settings): bool
    {
        try {
            $tenant->makeCurrent();

            if ($this->option('due')) {
                $key = self::dispatchedKey($tenant);

                if (Cache::get($key) === today()->toDateString()) {
                    return true;
                }

                // La hora es del taller: se lee de su base.
                $runtime->ensureReady();

                if (! $settings->remindersTimeReached()) {
                    return true;
                }
            }

            SendTenantReminders::dispatch();

            if ($this->option('due')) {
                Cache::put($key, today()->toDateString(), now()->addHours(self::DISPATCHED_TTL_HOURS));
            }
        } catch (Throwable $e) {
            // Si no se pudo preparar el taller, `TenantRuntime` ya dejó el detalle en el log. Sin marcar el día: se
            // vuelve a intentar en la próxima corrida.
            if (! $e instanceof TenantException) {
                Log::error('No se pudieron despachar los recordatorios del taller.', [
                    'tenant_id' => $tenant->id,
                    'exception' => $e->getMessage(),
                ]);
            }

            $this->error("Taller #{$tenant->id} ({$tenant->name}): no se pudieron despachar los recordatorios. El detalle está en el log.");

            return false;
        }

        $this->info("Taller #{$tenant->id} ({$tenant->name}): recordatorios despachados.");

        return true;
    }

    /**
     * Clave del último despacho del scheduler. Lleva el id del taller además del prefijo de cache del taller activo,
     * para no depender de que el prefijo llegue a la store ya resuelta.
     */
    public static function dispatchedKey(Tenant $tenant): string
    {
        return "notifications:reminders-dispatched:{$tenant->id}";
    }
}
