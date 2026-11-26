<?php

namespace App\Multitenancy\Tasks;

use App\Multitenancy\TenantStorage;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Olvida los discos por taller (`public` y `tenant_private`) al activar y al olvidar un taller (T4; historial 2.98): el
 * próximo uso los vuelve a resolver con la raíz y la URL del taller activo, o falla si no hay ninguno.
 *
 * Sin esta tarea, un worker que corre jobs de varios talleres en el mismo proceso (historial 2.96) seguiría guardando
 * en la carpeta del primero.
 */
class SwitchTenantStorageTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        TenantStorage::forgetDisks();
    }

    public function forgetCurrent(): void
    {
        TenantStorage::forgetDisks();
    }
}
