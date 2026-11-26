<?php

namespace App\Multitenancy\Jobs;

use DateTimeInterface;

/**
 * Lo común a los jobs del taller (§3.5 de la especificación; T3, paso 4, historial 2.96):
 *
 * - `PrepareTenant` antes de `handle`: libera el job si el taller no está disponible y lo descarta si está suspendido.
 * - Reintentos durante 6 horas desde el despacho (TD7), solo por esas liberaciones.
 * - Una excepción dentro de `handle` lo deja fallido sin reintentar (`maxExceptions = 1`): reintentarlo podría
 *   duplicar un email.
 *
 * Si vence `retryUntil` con la base del taller todavía caída, el job queda en `failed_jobs` de la central y se recupera
 * con `queue:retry`. Lo que el job registra en la base del taller (un aviso `pending`) queda como estaba.
 */
trait RunsInTenant
{
    public int $maxExceptions = 1;

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [new PrepareTenant];
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(6);
    }
}
