<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Multitenancy\TenantDatabase;
use App\Multitenancy\TenantMigrator;
use App\Multitenancy\TenantRuntime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;
use Throwable;

/**
 * Restauración de un taller (§3.9 de la especificación; T5, paso 5, historial 2.105): cambia `tenants.database` a una
 * base restaurada con otro nombre, después de verificarla. No importa el backup, que depende del hosting (runbook en
 * `docs/development/deployment.md`), ni toca la base anterior, que se borra a mano después.
 *
 * Verifica que la base nueva exista, tenga tablas, responda y sea del mismo taller (mismo RUC que la actual, si la
 * actual responde). Pide confirmación salvo con `--force` y después le aplica las migraciones que le falten, comprueba
 * que su configuración se lee (`TenantRuntime::ensureReady()`, lo mismo que cada request) y que tiene un administrador.
 * Si algo falla, el puntero no cambia y el taller sigue con su base de antes.
 *
 * Acepta talleres activos, suspendidos y dados de baja (la base conservada sirve para la exportación asistida). Uno
 * activo sigue atendiendo hasta el cambio: lo escrito en la base anterior después del backup se pierde, y la
 * confirmación lo advierte. Toma el lock de `tenants:migrate`, para no cruzarse con una migración, y cambia el puntero
 * releyendo la fila bloqueada. Al terminar borra la cache de permisos del taller, que tenía los de la base anterior.
 */
class RestoreTenant extends Command
{
    protected $signature = 'tenants:restore
        {tenant : Id del taller}
        {database : Base restaurada, con otro nombre que la actual}
        {--force : Sin pedir confirmación}';

    protected $description = 'Pasa un taller a una base restaurada, después de verificarla y migrarla';

    private const RESTORABLE = [TenantStatus::ACTIVE, TenantStatus::SUSPENDED, TenantStatus::CANCELLED];

    private const LOCK_SECONDS = 3600;

    public function handle(TenantDatabase $databases, TenantMigrator $migrator, TenantRuntime $runtime): int
    {
        $tenant = Tenant::query()->find((int) $this->argument('tenant'));
        $database = trim((string) $this->argument('database'));

        if ($tenant === null) {
            $this->error("Taller #{$this->argument('tenant')}: no existe.");

            return self::FAILURE;
        }

        if (($error = $this->refusal($tenant, $database, $databases)) !== null) {
            $this->error("Taller #{$tenant->id}: {$error}");

            return self::FAILURE;
        }

        $previous = Tenant::current();

        // El lock se toma sin taller activo: con uno, la cache tendría su prefijo (`tenant:{id}:`) y otra corrida no lo vería.
        Tenant::forgetCurrent();
        $lock = Cache::lock(MigrateTenants::lockKey($tenant), self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->error("Taller #{$tenant->id}: se está migrando o restaurando en otra corrida.");
            $this->restore($previous);

            return self::FAILURE;
        }

        try {
            return $this->restoreInto($tenant, $database, $migrator, $runtime) ? self::SUCCESS : self::FAILURE;
        } finally {
            Tenant::forgetCurrent();
            $lock->release();
            $this->restore($previous);
        }
    }

    /**
     * Motivo por el que no se restaura, o `null`. Solo mira la central y el servidor: no conecta con el taller.
     */
    private function refusal(Tenant $tenant, string $database, TenantDatabase $databases): ?string
    {
        if (! in_array($tenant->status, self::RESTORABLE, true)) {
            return "solo se restaura un taller activo, suspendido o dado de baja, y este está {$tenant->status->label()}.";
        }

        if ($tenant->database_deleted_at !== null) {
            return 'su base se borró al vencer la conservación y no se restaura.';
        }

        if (($error = $databases->invalidName($database)) !== null) {
            return $error;
        }

        if ($databases->same($database, $tenant->database)) {
            return 'la base restaurada debe tener otro nombre que la actual.';
        }

        if ($databases->isProtected($database)) {
            return 'la base es la central o la de la conexión por defecto.';
        }

        $other = Tenant::query()->whereKeyNot($tenant->id)->get()
            ->first(fn (Tenant $candidate) => $databases->same($candidate->database, $database));

        if ($other !== null) {
            return "la base es la del taller #{$other->id}.";
        }

        $tables = $databases->tableCount($database);

        if ($tables === null) {
            return "la base {$database} no existe.";
        }

        if ($tables === 0) {
            return "la base {$database} está vacía.";
        }

        return null;
    }

    private function restoreInto(Tenant $tenant, string $database, TenantMigrator $migrator, TenantRuntime $runtime): bool
    {
        $label = "Taller #{$tenant->id} ({$tenant->domain})";
        $current = $this->identity($tenant);

        // El mismo taller con la base restaurada, solo en memoria: la central no cambia hasta el final.
        $candidate = (clone $tenant)->forceFill(['database' => $database]);

        try {
            $candidate->makeCurrent();
            $pending = $migrator->pending();
        } catch (Throwable $e) {
            return $this->abort($label, 'la base restaurada no responde.', $tenant, $e);
        }

        $restored = $this->identity($candidate);

        if ($current['tax_id'] !== null && $current['tax_id'] !== $restored['tax_id']) {
            $this->error("{$label}: la base restaurada no es de este taller (RUC ".($restored['tax_id'] ?? 'sin dato')
                ." en lugar de {$current['tax_id']}). No se cambió nada.");

            return false;
        }

        if (! $this->confirmed($tenant, $database, $restored, $pending, $current['tax_id'] === null)) {
            $this->line('No se cambió nada.');

            return false;
        }

        try {
            $candidate->makeCurrent();

            if ($pending !== []) {
                $migrator->run();
                $this->line("{$label}: migraciones aplicadas en la base restaurada: ".count($pending).'.');
            }

            $runtime->ensureReady();
        } catch (Throwable $e) {
            return $this->abort($label, 'no se pudo preparar la base restaurada.', $tenant, $e);
        }

        if (! User::query()->whereHas('roles', fn ($query) => $query->where('name', 'administrador'))->exists()) {
            $this->error("{$label}: la base restaurada no tiene ningún administrador. No se cambió nada.");

            return false;
        }

        Tenant::forgetCurrent();

        $from = $tenant->database;

        if (! $this->switchDatabase($tenant, $from, $database)) {
            $this->error("{$label}: el taller cambió mientras se verificaba la base. No se cambió nada.");

            return false;
        }

        // La cache de permisos lleva el id del taller y tenía los roles de la base anterior.
        $tenant->makeCurrent();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Tenant::forgetCurrent();

        Log::info('Cambió la base del taller.', ['tenant_id' => $tenant->id, 'from' => $from, 'to' => $database]);

        $this->info("{$label}: restaurado, ahora usa la base {$database}.");
        $this->line("La base anterior, {$from}, no se tocó: se borra a mano cuando ya no haga falta.");

        return true;
    }

    /**
     * Cambio de puntero con la fila bloqueada: solo si sigue en un estado restaurable y con la base que se leyó.
     */
    private function switchDatabase(Tenant $tenant, string $from, string $to): bool
    {
        $central = config('multitenancy.landlord_database_connection_name');

        $switched = DB::connection($central)->transaction(function () use ($tenant, $from, $to) {
            $locked = Tenant::query()->lockForUpdate()->find($tenant->id);

            if ($locked === null || $locked->database !== $from || $locked->database_deleted_at !== null
                || ! in_array($locked->status, self::RESTORABLE, true)) {
                return false;
            }

            $locked->update(['database' => $to]);

            return true;
        });

        $tenant->refresh();

        return $switched;
    }

    /**
     * Nombre comercial y RUC de la base del taller, o `null` si no responde o no los tiene.
     *
     * @return array{name: ?string, tax_id: ?string}
     */
    private function identity(Tenant $tenant): array
    {
        $value = fn (string $key) => ($text = trim((string) SystemSetting::get($key))) === '' ? null : $text;

        try {
            $tenant->makeCurrent();

            return ['name' => $value('company.name'), 'tax_id' => $value('company.tax_id')];
        } catch (Throwable) {
            return ['name' => null, 'tax_id' => null];
        } finally {
            Tenant::forgetCurrent();
        }
    }

    /**
     * @param  array{name: ?string, tax_id: ?string}  $restored
     * @param  list<string>  $pending
     */
    private function confirmed(Tenant $tenant, string $database, array $restored, array $pending, bool $unverified): bool
    {
        if ($this->option('force')) {
            return true;
        }

        $this->line("Taller #{$tenant->id} ({$tenant->domain}), {$tenant->status->label()}: {$tenant->database} → {$database}.");
        $this->line('Base restaurada: '.($restored['name'] ?? 'sin nombre comercial').', RUC '.($restored['tax_id'] ?? 'sin dato').'.');

        if ($unverified) {
            $this->warn('La base actual no responde o no tiene RUC: no se pudo comprobar que la restaurada sea de este taller.');
        }

        if ($pending !== []) {
            $this->line('Migraciones pendientes, que se aplican antes del cambio: '.count($pending).'.');
        }

        if ($tenant->status === TenantStatus::ACTIVE) {
            $this->warn('El taller está activo: lo que se haya guardado en la base actual después del backup se pierde.');
        }

        return $this->confirm('¿Cambiar la base del taller?');
    }

    private function abort(string $label, string $message, Tenant $tenant, Throwable $e): bool
    {
        Tenant::forgetCurrent();

        Log::error('Falló la restauración del taller.', [
            'tenant_id' => $tenant->id,
            'exception' => $e->getMessage(),
        ]);

        $this->error("{$label}: {$message} No se cambió nada; el detalle está en el log.");

        return false;
    }

    /**
     * Deja el taller que estaba activo, releído: si era este, ahora con su base nueva.
     */
    private function restore(?Tenant $previous): void
    {
        $fresh = $previous === null ? null : Tenant::query()->find($previous->id);

        $fresh === null ? Tenant::forgetCurrent() : $fresh->makeCurrent();
    }
}
