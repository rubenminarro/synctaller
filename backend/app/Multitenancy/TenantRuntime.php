<?php

namespace App\Multitenancy;

use App\Exceptions\TenantException;
use App\Models\Tenant;
use App\Services\WorkshopSettingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prepara el taller activo (§3.3 de la especificación; historial 2.80 y 2.95): comprueba que su base responde y aplica
 * su configuración (`system_settings`, T1). Es la segunda de las dos fases: cambiar de taller (las tareas del paquete)
 * no abre conexión; preparar sí, y es lo único que puede fallar.
 *
 * Es el único punto de entrada en los cuatro contextos: el request (`IdentifyTenant`), cada job (su middleware
 * `PrepareTenant`, historial 2.96), el scheduler y el comando de recordatorios (por cada taller). Sin taller activo
 * (el filtro del scheduler hasta T3, paso 5) aplica la configuración sobre la conexión por defecto, como antes de la
 * línea T, y un error sigue su curso.
 */
class TenantRuntime
{
    public function __construct(
        private readonly WorkshopSettingService $settings
    ) {}

    /**
     * @throws TenantException si el taller activo no responde o no se puede leer su configuración
     */
    public function ensureReady(): void
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            $this->settings->apply();

            return;
        }

        $this->attempt($tenant, 'La base del taller no responde.',
            fn () => DB::connection(config('multitenancy.tenant_database_connection_name'))->getPdo());

        $this->attempt($tenant, 'No se pudo aplicar la configuración del taller.',
            fn () => $this->settings->apply());
    }

    /**
     * Si falla, el taller no queda activo y el detalle va al log: la respuesta no lleva datos técnicos.
     */
    private function attempt(Tenant $tenant, string $message, callable $step): void
    {
        try {
            $step();
        } catch (Throwable $e) {
            Log::error($message, [
                'tenant_id' => $tenant->id,
                'exception' => $e->getMessage(),
            ]);

            Tenant::forgetCurrent();

            throw TenantException::unavailable();
        }
    }
}
