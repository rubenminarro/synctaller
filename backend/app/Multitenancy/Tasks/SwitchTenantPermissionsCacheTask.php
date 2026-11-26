<?php

namespace App\Multitenancy\Tasks;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hace que el registro de permisos de Spatie vuelva a tomar la cache, ya con el prefijo del taller, y olvide los
 * permisos que tenía cargados en memoria.
 *
 * El registro se instancia al arrancar la aplicación (`Gate::before`), antes de identificar el taller, y se queda con
 * la cache global: sin esta tarea, los permisos de un taller se leerían desde otro (spike T0, historial 2.80).
 * `initializeCache()` no borra nada; `forgetCachedPermissions()` sí, y sumaba unos 140 ms por request.
 *
 * `initializeCache()` tampoco vacía la colección ya cargada: en un worker, que corre jobs de varios talleres en el mismo
 * proceso, el taller B usaría los permisos del A (T3, paso 4; historial 2.96). `clearPermissionsCollection()` solo
 * vacía la memoria: no consulta la base ni la cache.
 */
class SwitchTenantPermissionsCacheTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        $this->switch();
    }

    public function forgetCurrent(): void
    {
        $this->switch();
    }

    private function switch(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->clearPermissionsCollection();
        $registrar->initializeCache();
    }
}
