<?php

namespace App\Http\Middleware;

use App\Enums\TenantStatus;
use App\Exceptions\TenantException;
use App\Models\Tenant;
use App\Multitenancy\TenantRuntime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identifica el taller por el host antes de autenticar (§3.2 de la especificación; reglas de negocio 6.34). Corre en
 * todas las rutas `api/*`, también las públicas (`POST /login`, `GET /branding`).
 *
 * Orden: buscar el taller por `tenants.domain`, revisar su estado y su mantenimiento sin tocar su base, activarlo y
 * prepararlo (`TenantRuntime::ensureReady()`: su base responde y su configuración queda aplicada). Sin cabecera
 * `X-Tenant` (TD8): el host es el único camino.
 *
 * No olvida el taller al terminar: cada request corre en su propio proceso (§3.3). Si el taller ya es el activo, el
 * paquete no lo vuelve a activar.
 */
class IdentifyTenant
{
    public function __construct(
        private readonly TenantRuntime $runtime
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // `getHost()` ya lo da en minúsculas y sin puerto, como se guarda (`tenants:register`).
        $tenant = Tenant::query()->where('domain', $request->getHost())->first();

        // Un taller en alta todavía no existe para nadie (T5, paso 3; historial 2.103) y uno dado de baja ya no existe
        // (TD11; paso 4, historial 2.104).
        if ($tenant === null || in_array($tenant->status, [TenantStatus::PROVISIONING, TenantStatus::CANCELLED], true)) {
            throw TenantException::notFound();
        }

        if ($tenant->status === TenantStatus::SUSPENDED) {
            throw TenantException::suspended();
        }

        if ($tenant->maintenance) {
            throw TenantException::maintenance();
        }

        $tenant->makeCurrent();

        // Si su base no responde o no se puede leer su configuración, el taller no queda activo (503 TENANT_UNAVAILABLE).
        $this->runtime->ensureReady();

        return $next($request);
    }
}
