<?php

namespace App\Multitenancy;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cambio de estado de un taller en la central (T5, paso 4; historial 2.104), para `tenants:suspend`,
 * `tenants:reactivate` y `tenants:cancel`. Relee la fila bloqueada dentro de una transacción de la central: dos
 * comandos a la vez no pasan los dos desde el mismo estado. No activa el taller ni toca su base, su mantenimiento ni
 * sus tokens. Cada cambio queda en el log con su `tenant_id`.
 */
class TenantStatusTransition
{
    /**
     * Pasa el taller a `$to` si su estado actual está en `$from`. Devuelve el estado releído cuando no corresponde, o
     * `null` si el cambio se hizo. `$attributes` se guarda junto con el estado (la fecha de la baja).
     *
     * @param  list<TenantStatus>  $from
     * @param  array<string, mixed>  $attributes
     */
    public function apply(Tenant $tenant, array $from, TenantStatus $to, array $attributes = []): ?TenantStatus
    {
        $central = config('multitenancy.landlord_database_connection_name');

        $refused = DB::connection($central)->transaction(function () use ($tenant, $from, $to, $attributes) {
            $locked = Tenant::query()->lockForUpdate()->findOrFail($tenant->id);

            if (! in_array($locked->status, $from, true)) {
                return $locked->status;
            }

            $locked->update(['status' => $to, ...$attributes]);

            return null;
        });

        if ($refused !== null) {
            return $refused;
        }

        $tenant->refresh();

        Log::info('Cambió el estado del taller.', ['tenant_id' => $tenant->id, 'status' => $to->value]);

        return null;
    }
}
