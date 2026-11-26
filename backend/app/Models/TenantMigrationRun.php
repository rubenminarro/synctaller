<?php

namespace App\Models;

use App\Enums\TenantMigrationRunStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Multitenancy\Models\Concerns\UsesLandlordConnection;

/**
 * Una corrida de `tenants:migrate` en un taller, en la base central (§3.8 de la especificación; T5, paso 1, historial
 * 2.101). El detalle técnico de una falla queda acá y en el log, nunca en una respuesta de la API.
 *
 * @property int $id
 * @property int $tenant_id
 * @property TenantMigrationRunStatus $status
 * @property list<string>|null $migrations Migraciones aplicadas en esta corrida
 * @property string|null $error
 * @property bool $maintenance_set Si esta corrida puso al taller en mantenimiento al fallar
 * @property Carbon $started_at
 * @property Carbon|null $finished_at
 */
class TenantMigrationRun extends Model
{
    use UsesLandlordConnection;

    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'status',
        'migrations',
        'error',
        'maintenance_set',
        'started_at',
        'finished_at',
    ];

    protected $attributes = [
        'maintenance_set' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => TenantMigrationRunStatus::class,
            'migrations' => 'array',
            'maintenance_set' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
