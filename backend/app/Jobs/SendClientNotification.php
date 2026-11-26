<?php

namespace App\Jobs;

use App\Enums\TenantStatus;
use App\Multitenancy\Jobs\DiscardsForSuspendedTenant;
use App\Multitenancy\Jobs\RunsInTenant;
use App\Services\ClientNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía un aviso registrado, en el taller que lo despachó (`RunsInTenant`, historial 2.96). Un fallo del envío queda
 * registrado (failed) y se reenvía a mano, sin reintentos: solo se reintenta, hasta 6 horas, si la base del taller no
 * responde o está en mantenimiento. Con el taller suspendido o dado de baja, el aviso queda failed con el motivo.
 */
class SendClientNotification implements DiscardsForSuspendedTenant, ShouldQueue
{
    use Queueable;
    use RunsInTenant;

    public function __construct(
        public readonly int $clientNotificationId
    ) {}

    public function handle(ClientNotificationService $service): void
    {
        $service->deliver($this->clientNotificationId);
    }

    public function discardForSuspendedTenant(TenantStatus $status): void
    {
        app(ClientNotificationService::class)->failForSuspendedTenant($this->clientNotificationId, $status);
    }
}
