<?php

namespace App\Multitenancy\Tasks;

use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\PrefixCacheTask;

/**
 * Prefijo de cache por taller con separador: `tenant:{id}:` (§3.6 de la especificación).
 *
 * El prefijo del paquete es `tenant_id_{id}` sin separador, así que la clave `0abc` del taller 1 y la clave `abc` del
 * taller 10 terminan siendo la misma (`tenant_id_10abc`). Pasa con cualquier clave que empiece con un dígito, como
 * los hashes hexadecimales del limitador de intentos.
 */
class PrefixTenantCacheTask extends PrefixCacheTask
{
    public function makeCurrent(IsTenant $tenant): void
    {
        $this->setCachePrefix("tenant:{$tenant->getKey()}:");
    }
}
