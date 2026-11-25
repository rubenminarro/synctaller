<?php

namespace App\Multitenancy\Tasks;

use Illuminate\Support\Facades\DB;
use Spatie\Multitenancy\Contracts\IsTenant;
use Spatie\Multitenancy\Tasks\SwitchTenantTask;

/**
 * Con un taller activo, la conexión por defecto pasa a ser `tenant` (§3.3 de la especificación): los modelos, las
 * reglas `exists:`/`unique:` y el SQL crudo van a la base del taller sin nombrar la conexión. Al olvidar el taller
 * vuelve la anterior.
 *
 * En T2 solo los requests HTTP activan un taller (historial 2.93): la consola, el worker y el scheduler siguen con
 * `DB_CONNECTION` hasta T3. Va después de `SwitchTenantDatabaseTask`, que ya le fijó la base a `tenant`.
 */
class SwitchDefaultConnectionTask implements SwitchTenantTask
{
    private ?string $previous = null;

    public function makeCurrent(IsTenant $tenant): void
    {
        // El paquete olvida el taller anterior antes de activar otro: acá nunca hay uno activo.
        $this->previous = DB::getDefaultConnection();

        DB::setDefaultConnection(config('multitenancy.tenant_database_connection_name'));
    }

    public function forgetCurrent(): void
    {
        if ($this->previous !== null) {
            DB::setDefaultConnection($this->previous);
            $this->previous = null;
        }
    }
}
