<?php

namespace App\Multitenancy\Tasks;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hace que el registro de permisos de Spatie vuelva a tomar la cache, ya con el prefijo del taller.
 *
 * El registro se instancia al arrancar la aplicación (`Gate::before`), antes de identificar el taller, y se queda con
 * la cache global: sin esta tarea, los permisos de un taller se leerían desde otro (spike T0, historial 2.80).
 * `initializeCache()` no borra nada; `forgetCachedPermissions()` sí, y sumaba unos 140 ms por request.
 */
class SwitchTenantPermissionsCacheTask implements SwitchTenantTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        app(PermissionRegistrar::class)->initializeCache();
    }

    public function forgetCurrent(): void
    {
        app(PermissionRegistrar::class)->initializeCache();
    }
}
