<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Exceptions\TenantException;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Identifica el taller por el host antes de autenticar (§3.2 de la especificación; reglas de negocio 6.34). Corre en
 * todas las rutas `api/*`, también las públicas (`POST /login`, `GET /branding`).
 *
 * Orden: buscar el taller por `tenants.domain`, revisar su estado y su mantenimiento sin tocar su base, activarlo y
 * comprobar que su base responde. Sin cabecera `X-Tenant` (TD8): el host es el único camino.
 *
 * No olvida el taller al terminar: cada request corre en su propio proceso (§3.3). Si el taller ya es el activo, el
 * paquete no lo vuelve a activar.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        // `getHost()` ya lo da en minúsculas y sin puerto, como se guarda (`tenants:register`).
        $tenant = Tenant::query()->where('domain', $request->getHost())->first();

        if ($tenant === null) {
            throw TenantException::notFound();
        }

        if ($tenant->status === TenantStatus::SUSPENDED) {
            throw TenantException::suspended();
        }

        if ($tenant->maintenance) {
            throw TenantException::maintenance();
        }

        $tenant->makeCurrent();

        $this->ensureDatabaseResponds($tenant);

        return $next($request);
    }

    private function ensureDatabaseResponds(Tenant $tenant): void
    {
        try {
            DB::connection(config('multitenancy.tenant_database_connection_name'))->getPdo();
        } catch (Throwable $e) {
            Log::error('La base del taller no responde.', [
                'tenant_id' => $tenant->id,
                'exception' => $e->getMessage(),
            ]);

            Tenant::forgetCurrent();

            throw TenantException::unavailable();
        }
    }
}
