<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * Taller registrado en la base central (§3.1 de la especificación). Solo datos técnicos: el nombre comercial, el
 * branding y el IVA van en `system_settings` de cada taller, y las credenciales de base, en el `.env`. La fila no se
 * borra: un job de un taller inexistente se perdería en silencio. La única excepción es un taller que no terminó su
 * alta (`provisioning`), que nunca atendió ni tuvo jobs: `tenants:discard` lo borra con su base (historial 2.103).
 *
 * La conexión es la central, por `UsesLandlordConnection` del modelo del paquete.
 *
 * @property int $id
 * @property string $name Etiqueta operativa
 * @property string $domain Host por el que se identifica el taller (TD3)
 * @property string $database Base del taller
 * @property TenantStatus $status
 * @property bool $maintenance
 * @property string|null $provisioning_error Error del último intento de alta, mientras está en `provisioning`
 * @property Carbon|null $cancelled_at Fecha de la baja (`tenants:cancel`): desde ahí corren los 5 años de conservación
 */
class Tenant extends BaseTenant
{
    protected $fillable = [
        'name',
        'domain',
        'database',
        'status',
        'maintenance',
        'provisioning_error',
        'cancelled_at',
    ];

    protected $attributes = [
        'status' => 'active',
        'maintenance' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'maintenance' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }
}
