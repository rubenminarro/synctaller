<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Registra un taller en la base central (T2, paso 2; historial 2.93). Solo crea la fila: no crea la base ni la migra,
 * que es el alta completa de T5. Sirve para el taller #1 de una instalación existente (§3.10 de la especificación).
 */
class RegisterTenant extends Command
{
    protected $signature = 'tenants:register
        {name : Etiqueta operativa del taller}
        {domain : Host por el que se identifica, sin esquema ni puerto (por ejemplo 127.0.0.1)}
        {database : Base del taller, que ya debe existir}';

    protected $description = 'Registra un taller en la base central (no crea ni migra su base)';

    public function handle(): int
    {
        $data = [
            'name' => trim($this->argument('name')),
            'domain' => strtolower(trim($this->argument('domain'))),
            'database' => trim($this->argument('database')),
        ];

        $central = config('multitenancy.landlord_database_connection_name');

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            // Solo el host: el middleware compara contra `Request::getHost()`, que no trae esquema, puerto ni ruta.
            'domain' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9.-]+$/', Rule::unique("{$central}.tenants", 'domain')],
            'database' => ['required', 'string', 'max:255', Rule::unique("{$central}.tenants", 'database')],
        ], [
            'domain.regex' => 'El dominio debe ser solo el host, sin esquema, puerto ni ruta.',
            'domain.unique' => 'Ya hay un taller con ese dominio.',
            'database.unique' => 'Ya hay un taller con esa base.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $tenant = Tenant::create($data);

        $this->info("Taller #{$tenant->id} registrado: {$tenant->domain} → {$tenant->database}.");

        return self::SUCCESS;
    }
}
