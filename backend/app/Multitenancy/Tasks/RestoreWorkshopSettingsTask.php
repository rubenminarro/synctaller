<?php

namespace App\Multitenancy\Tasks;

use App\Services\WorkshopSettingService;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Al olvidar el taller, su configuración (`system_settings`, T1) vuelve a la del .env (T3, paso 4; historial 2.96).
 *
 * Aplicarla es parte de preparar el taller (`TenantRuntime::ensureReady()`), porque consulta su base. Restaurarla no:
 * usa los valores originales que recuerda `WorkshopSettingService`. Sin esto, un worker que termina un job del taller A
 * se quedaba con su remitente y sus datos de empresa.
 */
class RestoreWorkshopSettingsTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void {}

    public function forgetCurrent(): void
    {
        app(WorkshopSettingService::class)->restore();
    }
}
