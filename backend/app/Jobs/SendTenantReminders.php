<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Multitenancy\Jobs\RunsInTenant;
use App\Services\NotificationReminderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Recordatorios diarios de un taller (§3.5 de la especificación; T3, paso 5, historial 2.97): lo despacha
 * `notifications:send-reminders`, uno por taller, así la falla de uno no frena a los demás. Corre en el taller que lo
 * despachó (`RunsInTenant`): si su base no responde o está en mantenimiento, se reintenta hasta 6 horas, y con el
 * taller suspendido se descarta.
 *
 * Las claves de deduplicación de `NotificationReminderService` hacen seguro correrlo más de una vez por día.
 */
class SendTenantReminders implements ShouldQueue
{
    use Queueable;
    use RunsInTenant;

    public function handle(NotificationReminderService $reminders): void
    {
        $result = $reminders->run();

        Log::info('Recordatorios registrados.', [
            'tenant_id' => Tenant::current()?->id,
            ...$result,
        ]);
    }
}
