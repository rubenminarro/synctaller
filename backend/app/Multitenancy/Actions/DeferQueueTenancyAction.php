<?php

namespace App\Multitenancy\Actions;

use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;

/**
 * Colas sin taller hasta T3 (historial 2.93): no registra los listeners de colas del paquete.
 *
 * Con ellos, al procesar un job que no es "del taller" el paquete olvida el taller activo y, al terminar, lo vuelve a
 * activar, lo que reconecta `tenant`. Un job `sync` corre dentro del request que lo despachó: el resto del request
 * quedaba en otra conexión. En T2 los jobs corren donde se procesan, como antes de la línea T: el worker, con
 * `DB_CONNECTION`; un job `sync`, en el taller del request. T3 los hace llevar su taller (§3.5 de la especificación).
 */
class DeferQueueTenancyAction extends MakeQueueTenantAwareAction
{
    public function execute(): void {}
}
