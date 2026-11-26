<?php

namespace App\Console\Commands;

use App\Enums\TenantMigrationRunStatus;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMigrationRun;
use App\Multitenancy\TenantMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Migra la base de cada taller (§3.8 de la especificación; T5, paso 1, historial 2.101). Recorre los talleres activos y
 * suspendidos, con o sin mantenimiento, o los de `--tenant`; con `--failed`, solo los que tienen fallida su última
 * corrida. Cada corrida queda en `tenant_migration_runs` de la central.
 *
 * Por taller: toma un lock en la cache de la central, activa el taller, comprueba que su base responde y aplica las
 * migraciones pendientes de `database/migrations`. No prepara el taller (`TenantRuntime::ensureReady()`): con el esquema
 * viejo, leer su configuración podría fallar. Mientras migra, el taller sigue atendiendo: las migraciones son
 * compatibles hacia atrás (se migra y después se publica el código).
 *
 * Migra con `TenantMigrator` (el `Migrator`, no el comando `migrate`, que con `--force` crea la base si no existe): un
 * taller que apunta a una base caída o mal escrita recibiría una vacía con todas las migraciones. Acá una base que falta
 * es una falla. La lógica es común con el alta (`tenants:create`; T5, paso 3, historial 2.103).
 *
 * Un taller que falla queda en mantenimiento (`503 TENANT_MAINTENANCE`), los demás siguen y el comando termina con
 * código 1. Cuando una corrida posterior sale bien, le quita el mantenimiento que puso la fallida; uno puesto a mano no
 * se quita. Al terminar deja el taller que estaba activo, o ninguno.
 */
class MigrateTenants extends Command
{
    protected $signature = 'tenants:migrate
        {--tenant=* : Id del taller (se puede repetir). Sin la opción, todos los activos y suspendidos}
        {--failed : Solo los talleres cuya última corrida falló}';

    protected $description = 'Aplica las migraciones pendientes en la base de cada taller';

    /**
     * Estados que se migran: un suspendido conserva el esquema al día para reactivarlo y para la exportación asistida.
     */
    private const MIGRATABLE = [TenantStatus::ACTIVE, TenantStatus::SUSPENDED];

    /**
     * Vida del lock por taller: más que cualquier migración razonable, para que un proceso cortado no lo deje tomado.
     */
    private const LOCK_SECONDS = 3600;

    public function handle(TenantMigrator $migrator): int
    {
        $ids = array_map('intval', $this->option('tenant'));
        $tenants = Tenant::query()
            ->whereIn('status', self::MIGRATABLE)
            ->when($ids !== [], fn ($query) => $query->whereKey($ids))
            ->when($this->option('failed'), fn ($query) => $query->whereKey(self::lastFailedTenantIds()))
            ->orderBy('id')
            ->get();

        $failed = false;

        foreach (array_diff($ids, $tenants->modelKeys()) as $id) {
            $this->error($this->option('failed')
                ? "Taller #{$id}: no existe, no se migra en su estado o su última corrida no falló."
                : "Taller #{$id}: no existe o no se migra en su estado.");
            $failed = true;
        }

        if ($tenants->isEmpty() && $ids === []) {
            $this->info($this->option('failed') ? 'No hay talleres con la última corrida fallida.' : 'No hay talleres para migrar.');
        }

        $previous = Tenant::current();

        // El lock se toma sin taller activo: con uno, la cache tendría su prefijo (`tenant:{id}:`) y otra corrida no lo vería.
        Tenant::forgetCurrent();

        try {
            foreach ($tenants as $tenant) {
                $failed = ! $this->runFor($tenant, $migrator) || $failed;
            }
        } finally {
            $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Talleres cuya última corrida terminó fallida. Una que quedó `running` (proceso cortado) no cuenta.
     *
     * @return list<int>
     */
    public static function lastFailedTenantIds(): array
    {
        return TenantMigrationRun::query()
            ->whereIn('id', TenantMigrationRun::query()->selectRaw('max(id)')->groupBy('tenant_id'))
            ->where('status', TenantMigrationRunStatus::FAILED)
            ->pluck('tenant_id')
            ->all();
    }

    public static function lockKey(Tenant $tenant): string
    {
        return "tenants:migrate:{$tenant->id}";
    }

    private function runFor(Tenant $tenant, TenantMigrator $migrator): bool
    {
        $label = "Taller #{$tenant->id} ({$tenant->name})";
        $lock = Cache::lock(self::lockKey($tenant), self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->error("{$label}: ya se está migrando en otra corrida.");

            return false;
        }

        try {
            $last = TenantMigrationRun::query()->where('tenant_id', $tenant->id)->latest('id')->first();
            $run = TenantMigrationRun::create([
                'tenant_id' => $tenant->id,
                'status' => TenantMigrationRunStatus::RUNNING,
                'started_at' => now(),
            ]);

            $tenant->makeCurrent();
            $before = null;

            try {
                // Conecta con la base: si falta o no responde, falla acá, antes de migrar (el Migrator no la crea).
                $before = $migrator->ran();
                $migrator->run();

                $applied = array_values(array_diff($migrator->ran(), $before));
            } catch (Throwable $e) {
                $applied = $before === null ? [] : $this->appliedSince($migrator, $before);
                $this->recordFailure($tenant, $run, $applied, $e);
                $this->error("{$label}: falló la migración y queda en mantenimiento. El detalle está en el log.");

                return false;
            } finally {
                Tenant::forgetCurrent();
            }

            $run->update([
                'status' => TenantMigrationRunStatus::SUCCEEDED,
                'migrations' => $applied,
                'finished_at' => now(),
            ]);

            // Solo el mantenimiento que puso una corrida fallida: uno puesto a mano no es de este comando.
            if ($last?->status === TenantMigrationRunStatus::FAILED && $last->maintenance_set) {
                $tenant->update(['maintenance' => false]);
            }

            $count = count($applied);
            $this->info($count === 0
                ? "{$label}: sin migraciones pendientes."
                : "{$label}: {$count} ".($count === 1 ? 'migración aplicada' : 'migraciones aplicadas').'.');

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<string>  $applied
     */
    private function recordFailure(Tenant $tenant, TenantMigrationRun $run, array $applied, Throwable $e): void
    {
        Log::error('Falló la migración del taller.', [
            'tenant_id' => $tenant->id,
            'exception' => $e->getMessage(),
        ]);

        $maintenanceSet = ! $tenant->maintenance;

        if ($maintenanceSet) {
            $tenant->update(['maintenance' => true]);
        }

        $run->update([
            'status' => TenantMigrationRunStatus::FAILED,
            'migrations' => $applied,
            'error' => $e->getMessage(),
            'maintenance_set' => $maintenanceSet,
            'finished_at' => now(),
        ]);
    }

    /**
     * Las que llegaron a aplicarse antes de la falla, si la base todavía responde.
     *
     * @param  list<string>  $before
     * @return list<string>
     */
    private function appliedSince(TenantMigrator $migrator, array $before): array
    {
        try {
            return array_values(array_diff($migrator->ran(), $before));
        } catch (Throwable) {
            return [];
        }
    }
}
