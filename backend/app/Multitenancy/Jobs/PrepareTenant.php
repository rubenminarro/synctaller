<?php

namespace App\Multitenancy\Jobs;

use App\Enums\TenantStatus;
use App\Exceptions\TenantException;
use App\Models\Tenant;
use App\Multitenancy\TenantRuntime;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Middleware de los jobs del taller (§3.5 de la especificación; T3, paso 4, historial 2.96). Corre después de que el
 * paquete activa el taller del job (en `JobProcessing`, sin consultas) y antes de `handle`:
 *
 * 1. Taller en mantenimiento: libera el job con espera, sin tocar su base.
 * 2. Prepara el taller (`TenantRuntime::ensureReady()`): si su base no responde o no se puede leer su configuración,
 *    libera el job con espera. Un problema de infraestructura no lo deja fallido.
 * 3. Taller suspendido (P9): descarta el job. Si es un aviso, antes lo deja `failed` con el motivo (`DiscardsForSuspendedTenant`).
 *
 * Liberar el job no cuenta como excepción (`maxExceptions`): los reintentos terminan con `retryUntil` (`RunsInTenant`).
 */
class PrepareTenant
{
    /**
     * Espera antes de volver a intentar, en segundos, según el intento (§3.11): 30 s, 2, 5 y 15 minutos, y después 15.
     */
    public const DELAYS = [30, 120, 300, 900];

    public function handle(object $job, Closure $next): void
    {
        $tenant = Tenant::current();

        if ($tenant?->maintenance) {
            $this->release($job);

            return;
        }

        try {
            app(TenantRuntime::class)->ensureReady();
        } catch (TenantException) {
            // El detalle ya quedó en el log (`TenantRuntime`).
            $this->release($job);

            return;
        }

        if ($tenant?->status === TenantStatus::SUSPENDED) {
            $this->discard($job, $tenant);

            return;
        }

        $next($job);
    }

    private function discard(object $job, Tenant $tenant): void
    {
        try {
            if ($job instanceof DiscardsForSuspendedTenant) {
                $job->discardForSuspendedTenant();
            }
        } catch (Throwable $e) {
            Log::error('No se pudo descartar el job del taller suspendido.', [
                'tenant_id' => $tenant->id,
                'job' => $job::class,
                'exception' => $e->getMessage(),
            ]);
            $this->release($job);

            return;
        }

        Log::info('Job descartado: el taller está suspendido.', ['tenant_id' => $tenant->id, 'job' => $job::class]);

        $job->delete();
    }

    private function release(object $job): void
    {
        $attempt = max($job->attempts(), 1);

        $job->release(self::DELAYS[min($attempt, count(self::DELAYS)) - 1]);
    }
}
