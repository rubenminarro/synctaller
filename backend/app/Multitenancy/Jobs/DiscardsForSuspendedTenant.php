<?php

namespace App\Multitenancy\Jobs;

/**
 * Un job que deja registro en la base del taller cuando se descarta porque el taller está suspendido (§3.5 de la
 * especificación; historial 2.96). Corre con el taller activo y su base preparada.
 */
interface DiscardsForSuspendedTenant
{
    public function discardForSuspendedTenant(): void;
}
