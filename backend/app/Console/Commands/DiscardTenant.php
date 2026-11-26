<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Multitenancy\TenantDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Descarta un alta que no terminó (T5, paso 3; historial 2.103): borra la base del taller y su fila de la central. Solo
 * actúa sobre un taller en `provisioning`, que nunca atendió ni tuvo jobs; uno activo o suspendido no se descarta
 * nunca (su baja es otro camino, paso 4).
 *
 * Toma el mismo lock que `tenants:create`, para no borrar un alta que se está retomando, y no borra nunca la base
 * central ni la de la conexión por defecto. Pide confirmación salvo con `--force`.
 */
class DiscardTenant extends Command
{
    protected $signature = 'tenants:discard
        {tenant : Id del taller en alta}
        {--force : Sin pedir confirmación}';

    protected $description = 'Descarta un taller en alta (provisioning): borra su base y su registro';

    public function handle(TenantDatabase $databases): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));

        if ($tenant === null) {
            $this->error("Taller #{$this->argument('tenant')}: no existe.");

            return self::FAILURE;
        }

        if ($tenant->status !== TenantStatus::PROVISIONING) {
            $this->error("Taller #{$tenant->id}: solo se descarta un taller en alta, y este está {$tenant->status->label()}.");

            return self::FAILURE;
        }

        if ($databases->isProtected($tenant->database)) {
            $this->error("Taller #{$tenant->id}: su base es la central o la de la conexión por defecto y no se borra.");

            return self::FAILURE;
        }

        if (! $this->option('force')
            && ! $this->confirm("Se borra la base {$tenant->database} y el registro del taller #{$tenant->id} ({$tenant->domain}). ¿Seguir?")) {
            $this->line('No se descartó nada.');

            return self::FAILURE;
        }

        $previous = Tenant::current();
        Tenant::forgetCurrent();
        $lock = Cache::lock(CreateTenant::lockKey($tenant->domain), 3600);

        if (! $lock->get()) {
            $this->error("Taller #{$tenant->id}: se está dando de alta en otra corrida.");
            $this->restore($previous, $tenant);

            return self::FAILURE;
        }

        try {
            // Releído con el lock tomado: un alta retomada mientras tanto pudo dejarlo activo.
            if ($tenant->fresh()?->status !== TenantStatus::PROVISIONING) {
                $this->error("Taller #{$tenant->id}: ya no está en alta y no se descarta.");

                return self::FAILURE;
            }

            $databases->drop($tenant->database);
            $tenant->delete();
        } catch (Throwable $e) {
            Log::error('Falló el descarte del taller.', [
                'tenant_id' => $tenant->id,
                'exception' => $e->getMessage(),
            ]);
            $this->error("Taller #{$tenant->id}: no se pudo descartar. El detalle está en el log.");

            return self::FAILURE;
        } finally {
            $lock->release();
            $this->restore($previous, $tenant);
        }

        $this->info("Taller #{$tenant->id} descartado: se borraron su base y su registro.");

        return self::SUCCESS;
    }

    private function restore(?Tenant $previous, Tenant $discarded): void
    {
        $previous === null || $previous->is($discarded) ? Tenant::forgetCurrent() : $previous->makeCurrent();
    }
}
