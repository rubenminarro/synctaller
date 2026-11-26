<?php

namespace App\Multitenancy\Jobs;

use App\Enums\TenantStatus;

/**
 * Un job que deja registro en la base del taller cuando se descarta porque el taller está suspendido (§3.5 de la
 * especificación; historial 2.96) o dado de baja (historial 2.104). Corre con el taller activo y su base preparada.
 * `$status` es el estado que causó el descarte: `suspended` o `cancelled`.
 */
interface DiscardsForSuspendedTenant
{
    public function discardForSuspendedTenant(TenantStatus $status): void;
}
