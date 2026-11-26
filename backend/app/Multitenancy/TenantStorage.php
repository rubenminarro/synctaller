<?php

namespace App\Multitenancy;

use App\Models\Tenant;
use Illuminate\Support\Facades\Storage;
use LogicException;

/**
 * Discos por taller (T4, §3.7 de la especificación; historial 2.98). Un disco con el driver `tenant_local` es un disco
 * `local` cuya raíz y URL se completan con el taller activo al resolverlo: `{tenant}` se reemplaza por su id. Las rutas
 * guardadas en la base son relativas al disco y no cambian.
 *
 * Fallo cerrado (como `TenantRuntime::ensureReady()`, TD10): sin taller activo, resolver uno de estos discos lanza una
 * excepción. No hay raíz por defecto donde un archivo quede mezclado con los de un taller.
 *
 * La instancia resuelta queda en el `FilesystemManager`: `SwitchTenantStorageTask` la olvida en cada cambio de taller.
 */
final class TenantStorage
{
    public const DRIVER = 'tenant_local';

    public const PLACEHOLDER = '{tenant}';

    public static function register(): void
    {
        // El manager vuelve a enlazar la función a sí mismo: adentro `self` sería él, no esta clase.
        Storage::extend(self::DRIVER, fn ($app, array $config) => Storage::createLocalDriver(TenantStorage::configFor($config)));
    }

    /**
     * Configuración del disco para el taller activo. `tenant_url` es la ruta pública: la URL se arma con el host del
     * taller y el esquema y el puerto de `APP_URL`, para que un taller nunca reciba enlaces con el host de otro.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     *
     * @throws LogicException sin taller activo
     */
    public static function configFor(array $config): array
    {
        $tenant = Tenant::current() ?? throw new LogicException('Los archivos del taller requieren un taller activo.');
        $id = (string) $tenant->getKey();

        $config['root'] = str_replace(self::PLACEHOLDER, $id, $config['root']);

        if (isset($config['tenant_url'])) {
            $config['url'] = self::origin($tenant).str_replace(self::PLACEHOLDER, $id, $config['tenant_url']);
        }

        return $config;
    }

    /**
     * Olvida las instancias resueltas de los discos por taller.
     */
    public static function forgetDisks(): void
    {
        $disks = array_keys(array_filter(
            config('filesystems.disks', []),
            fn (array $disk) => ($disk['driver'] ?? null) === self::DRIVER
        ));

        if ($disks !== []) {
            Storage::forgetDisk($disks);
        }
    }

    private static function origin(Tenant $tenant): string
    {
        $url = parse_url((string) config('app.url'));
        $scheme = $url['scheme'] ?? 'http';
        $port = isset($url['port']) ? ':'.$url['port'] : '';

        return "{$scheme}://{$tenant->domain}{$port}";
    }
}
