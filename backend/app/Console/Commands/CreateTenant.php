<?php

namespace App\Console\Commands;

use App\Enums\TenantStatus;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Multitenancy\TenantDatabase;
use App\Multitenancy\TenantMigrator;
use App\Multitenancy\TenantRuntime;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Alta completa de un taller (§3.9 de la especificación; T5, paso 3, historial 2.103):
 * `provisioning` → base → migraciones → roles y permisos → administrador inicial → `system_settings` → `active`.
 *
 * Mientras está en `provisioning` el taller no atiende (`404 TENANT_NOT_FOUND`) y ni `tenants:migrate` ni el scheduler
 * lo recorren. Cada paso se puede repetir: si el alta falla, el taller queda en `provisioning` con el error en
 * `tenants.provisioning_error` y en el log, y se retoma corriendo el comando otra vez con el mismo dominio y la misma
 * base, o se descarta con `tenants:discard`.
 *
 * Nunca se hace sobre una base que ya tiene tablas, salvo que sea la del mismo taller en alta: así no puede caer sobre
 * la base de otro taller ni sobre una con datos. El administrador inicial recibe una contraseña aleatoria que se muestra
 * una sola vez; si ya existe (un alta retomada), conserva la suya. No depende del correo.
 *
 * `tenants:register` sigue siendo el camino de una instalación existente (§3.10), que ya tiene su base.
 */
class CreateTenant extends Command
{
    protected $signature = 'tenants:create
        {name : Etiqueta operativa del taller}
        {domain : Host por el que se identifica, sin esquema ni puerto (por ejemplo taller1.example.com)}
        {database : Base del taller. Se crea si no existe; si existe, debe estar vacía}
        {--admin-email= : Email del administrador inicial}
        {--admin-username= : Usuario del administrador inicial}
        {--admin-first-name= : Nombre del administrador inicial}
        {--admin-last-name= : Apellido del administrador inicial}
        {--company-name= : Nombre comercial del taller}
        {--company-legal-name= : Razón social del taller}
        {--company-tax-id= : RUC del taller}';

    protected $description = 'Da de alta un taller: crea y migra su base, sus roles, su administrador y su configuración';

    /**
     * Vida del lock del alta: más que cualquier alta razonable, para que un proceso cortado no lo deje tomado.
     */
    private const LOCK_SECONDS = 3600;

    /**
     * Datos de la empresa que el alta deja vacíos: sin guardarlos, los PDF y los correos tomarían los de la plataforma
     * (`.env`). El administrador los completa en Configuración.
     */
    private const BLANK_COMPANY_KEYS = ['company.activity', 'company.address', 'company.city', 'company.phone', 'company.email'];

    public function handle(TenantDatabase $databases, TenantMigrator $migrator, TenantRuntime $runtime): int
    {
        $data = $this->collectInput();
        $previous = Tenant::current();

        // El lock se toma sin taller activo: con uno, la cache tendría su prefijo (`tenant:{id}:`) y otra corrida no lo vería.
        Tenant::forgetCurrent();
        $lock = Cache::lock(self::lockKey($data['domain']), self::LOCK_SECONDS);

        if (! $lock->get()) {
            $this->error("El taller {$data['domain']} ya se está dando de alta en otra corrida.");
            $this->restore($previous);

            return self::FAILURE;
        }

        try {
            $tenant = $this->resumable($data);

            if (! $this->validate($data, $tenant, $databases)) {
                return self::FAILURE;
            }

            if ($tenant === null) {
                $tenant = Tenant::create([
                    'name' => $data['name'],
                    'domain' => $data['domain'],
                    'database' => $data['database'],
                    'status' => TenantStatus::PROVISIONING,
                ]);
                $this->info("Taller #{$tenant->id} registrado en alta: {$tenant->domain} → {$tenant->database}.");
            } else {
                $tenant->update(['name' => $data['name']]);
                $this->info("Taller #{$tenant->id}: se retoma el alta.");
            }

            return $this->provision($tenant, $data, $databases, $migrator, $runtime) ? self::SUCCESS : self::FAILURE;
        } finally {
            $lock->release();
            $this->restore($previous);
        }
    }

    /**
     * La clave del lock usa el dominio: antes del alta el taller no tiene id. `tenants:discard` toma el mismo.
     */
    public static function lockKey(string $domain): string
    {
        return "tenants:provision:{$domain}";
    }

    /**
     * @return array<string, string>
     */
    private function collectInput(): array
    {
        $text = fn (?string $value) => trim((string) $value);

        return [
            'name' => $text($this->argument('name')),
            'domain' => strtolower($text($this->argument('domain'))),
            'database' => $text($this->argument('database')),
            'admin_email' => strtolower($text($this->option('admin-email'))),
            'admin_username' => strtolower($text($this->option('admin-username'))),
            'admin_first_name' => $text($this->option('admin-first-name')),
            'admin_last_name' => $text($this->option('admin-last-name')),
            'company_name' => $text($this->option('company-name')),
            'company_legal_name' => $text($this->option('company-legal-name')),
            'company_tax_id' => $text($this->option('company-tax-id')),
        ];
    }

    /**
     * El taller en alta con el mismo dominio y la misma base, si lo hay: es el que se retoma.
     */
    private function resumable(array $data): ?Tenant
    {
        return Tenant::query()
            ->where('domain', $data['domain'])
            ->where('database', $data['database'])
            ->where('status', TenantStatus::PROVISIONING)
            ->first();
    }

    private function validate(array $data, ?Tenant $resuming, TenantDatabase $databases): bool
    {
        $central = config('multitenancy.landlord_database_connection_name');
        $unique = fn (string $column) => Rule::unique("{$central}.tenants", $column)->ignore($resuming?->id);
        $personName = ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+$/u'];

        // Las reglas del usuario son las del alta de usuarios (StoreUserRequest) y las de la empresa, las de Configuración.
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            // Solo el host: el middleware compara contra `Request::getHost()`, que no trae esquema, puerto ni ruta.
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', $unique('domain')],
            'database' => ['required', 'string', 'max:255', $unique('database'),
                function (string $attribute, string $value, \Closure $fail) use ($databases) {
                    if (($error = $databases->invalidName($value)) !== null) {
                        $fail($error);
                    }
                }],
            'admin_email' => ['required', config('validation.email_dns') ? 'email:rfc,dns' : 'email:rfc', 'max:50'],
            'admin_username' => ['required', 'string', 'alpha_dash', 'min:4', 'max:20'],
            'admin_first_name' => $personName,
            'admin_last_name' => $personName,
            'company_name' => ['required', 'string', 'max:150'],
            'company_legal_name' => ['required', 'string', 'max:150'],
            'company_tax_id' => ['required', 'string', 'max:30'],
        ], [
            // La aplicación no tiene traducciones: los mensajes en español van acá, como en los Form Requests.
            'required' => 'El campo :attribute es obligatorio.',
            'string' => 'El campo :attribute debe ser texto.',
            'min' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max' => 'El campo :attribute no debe tener más de :max caracteres.',
            'email' => 'El campo :attribute debe ser un correo válido.',
            'alpha_dash' => 'El campo :attribute solo puede contener letras, números y guiones (sin espacios).',
            'domain.regex' => 'El dominio debe ser solo el host, sin esquema, puerto ni ruta.',
            'domain.unique' => 'Ya hay un taller con ese dominio.',
            'database.unique' => 'Ya hay un taller con esa base.',
            'admin_first_name.regex' => 'El nombre del administrador solo puede contener letras y espacios.',
            'admin_last_name.regex' => 'El apellido del administrador solo puede contener letras y espacios.',
        ], [
            'name' => 'nombre',
            'domain' => 'dominio',
            'database' => 'base',
            'admin_email' => 'email del administrador',
            'admin_username' => 'usuario del administrador',
            'admin_first_name' => 'nombre del administrador',
            'admin_last_name' => 'apellido del administrador',
            'company_name' => 'nombre comercial',
            'company_legal_name' => 'razón social',
            'company_tax_id' => 'RUC',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            $pending = Tenant::query()->where('status', TenantStatus::PROVISIONING)
                ->where(fn ($query) => $query->where('domain', $data['domain'])->orWhere('database', $data['database']))
                ->first();

            if ($pending !== null && $resuming === null) {
                $this->line("El taller #{$pending->id} está en alta con {$pending->domain} → {$pending->database}: se retoma "
                    ."con ese dominio y esa base, o se descarta con tenants:discard {$pending->id}.");
            }

            return false;
        }

        // Una base con tablas solo se acepta si es la del alta que se retoma: nunca la de otro taller ni una con datos.
        if ($resuming === null && ($databases->tableCount($data['database']) ?? 0) > 0) {
            $this->error('La base ya existe y tiene tablas: el alta no se hace sobre una base en uso.');

            return false;
        }

        return true;
    }

    private function provision(Tenant $tenant, array $data, TenantDatabase $databases, TenantMigrator $migrator, TenantRuntime $runtime): bool
    {
        $label = "Taller #{$tenant->id} ({$tenant->name})";

        try {
            $databases->create($tenant->database);
            $this->line("{$label}: base lista.");

            $tenant->makeCurrent();

            $migrator->run();
            $this->line("{$label}: migraciones aplicadas.");

            app(RolesAndPermissionsSeeder::class)->run();
            $this->line("{$label}: roles y permisos.");

            $this->createAdmin($label, $data);

            $this->writeSettings($data);
            $this->line("{$label}: configuración del taller.");

            // Lo que hará cada request: su base responde y su configuración se aplica.
            $runtime->ensureReady();
        } catch (Throwable $e) {
            Tenant::forgetCurrent();

            Log::error('Falló el alta del taller.', [
                'tenant_id' => $tenant->id,
                'exception' => $e->getMessage(),
            ]);

            $tenant->update(['provisioning_error' => $e->getMessage()]);

            $this->error("{$label}: falló el alta y queda en provisioning. Se retoma con el mismo comando o se descarta "
                ."con tenants:discard {$tenant->id}. El detalle está en el log.");

            return false;
        }

        Tenant::forgetCurrent();
        $tenant->update(['status' => TenantStatus::ACTIVE, 'provisioning_error' => null]);
        $this->info("{$label}: alta completa, el taller está activo.");

        return true;
    }

    /**
     * Crea el administrador inicial con una contraseña aleatoria, que se muestra una sola vez. Si ya existe (un alta
     * retomada), conserva su contraseña y sus datos.
     */
    private function createAdmin(string $label, array $data): void
    {
        $admin = User::query()->where('email', $data['admin_email'])->first();

        if ($admin !== null) {
            if (! $admin->hasRole('administrador')) {
                $admin->assignRole('administrador');
            }

            $this->line("{$label}: el administrador {$admin->email} ya existía y conserva su contraseña.");

            return;
        }

        $password = Str::password(20);

        $admin = User::create([
            'email' => $data['admin_email'],
            'name' => $data['admin_username'],
            'first_name' => $data['admin_first_name'],
            'last_name' => $data['admin_last_name'],
            'password' => Hash::make($password),
        ]);
        $admin->assignRole('administrador');

        $this->line("{$label}: administrador {$admin->email} (usuario {$admin->name}).");
        $this->warn("Contraseña del administrador, que no se vuelve a mostrar: {$password}");
    }

    private function writeSettings(array $data): void
    {
        $values = [
            'company.name' => $data['company_name'],
            'company.legal_name' => $data['company_legal_name'],
            'company.tax_id' => $data['company_tax_id'],
        ] + array_fill_keys(self::BLANK_COMPANY_KEYS, '');

        foreach ($values as $key => $value) {
            SystemSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function restore(?Tenant $previous): void
    {
        $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
    }
}
