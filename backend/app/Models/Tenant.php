<?php

namespace App\Models;

use App\Enums\TenantStatus;
use Spatie\Multitenancy\Models\Tenant as BaseTenant;

/**
 * Taller registrado en la base central (§3.1 de la especificación). Solo datos técnicos: el nombre comercial, el
 * branding y el IVA van en `system_settings` de cada taller, y las credenciales de base, en el `.env`. La fila no se
 * borra: un job de un taller inexistente se perdería en silencio.
 *
 * La conexión es la central, por `UsesLandlordConnection` del modelo del paquete.
 *
 * @property int $id
 * @property string $name Etiqueta operativa
 * @property string $domain Host por el que se identifica el taller (TD3)
 * @property string $database Base del taller
 * @property TenantStatus $status
 * @property bool $maintenance
 */
class Tenant extends BaseTenant
{
    protected $fillable = [
        'name',
        'domain',
        'database',
        'status',
        'maintenance',
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
        ];
    }
}
