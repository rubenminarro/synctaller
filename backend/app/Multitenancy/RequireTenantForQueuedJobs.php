<?php

namespace App\Multitenancy;

use App\Models\Tenant;
use LogicException;
use ReflectionClass;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;

/**
 * Un job del taller no se despacha sin un taller activo (T3, paso 4; historial 2.96). Corre al armar el payload
 * (`Queue::createPayloadUsing`), también con la cola `sync`.
 *
 * Al procesarlo, el paquete busca el taller en el payload; si no lo encuentra, borra el job antes de lanzar la
 * excepción y no llega a `failed_jobs` (hallazgo del spike, historial 2.80): el aviso se perdería en silencio. Acá
 * falla quien lo despacha, en el momento.
 *
 * Decide si el job es del taller con las mismas reglas que el paquete (interfaces, listas y valor por defecto de
 * `config/multitenancy.php`). Los envoltorios de Laravel (mails, notificaciones y listeners en cola) siguen el valor
 * por defecto: el paquete solo los resuelve deserializando el payload.
 */
class RequireTenantForQueuedJobs
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function __invoke(?string $connection, ?string $queue, array $payload): array
    {
        if (Tenant::current() !== null) {
            return [];
        }

        // Al correr los hooks, `commandName` todavía es el job mismo: Laravel lo cambia por el nombre de la clase después.
        $job = $payload['data']['commandName'] ?? $payload['displayName'] ?? null;
        $class = is_object($job) ? $job::class : $job;

        if ($this->isTenantAware($class)) {
            throw new LogicException("El job {$class} es del taller y se despachó sin un taller activo.");
        }

        return [];
    }

    private function isTenantAware(?string $class): bool
    {
        $default = config('multitenancy.queues_are_tenant_aware_by_default') === true;

        if ($class === null || ! class_exists($class) || array_key_exists($class, config('multitenancy.queueable_to_job'))) {
            return $default;
        }

        $reflection = new ReflectionClass($class);

        return match (true) {
            $reflection->implementsInterface(config('multitenancy.tenant_aware_interface', TenantAware::class)) => true,
            $reflection->implementsInterface(config('multitenancy.not_tenant_aware_interface', NotTenantAware::class)) => false,
            in_array($class, config('multitenancy.tenant_aware_jobs'), true) => true,
            in_array($class, config('multitenancy.not_tenant_aware_jobs'), true) => false,
            default => $default,
        };
    }
}
