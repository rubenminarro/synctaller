<?php

namespace App\Console\Commands;

use App\Models\DiagnosticItemPhoto;
use App\Models\ReceptionPhoto;
use App\Models\Tenant;
use App\Models\VehicleDelivery;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Pasa al disco privado las firmas y las fotos que quedaron en el público antes de P8 (paso 3, historial 2.106). Lo
 * usará T6. Recorre las rutas guardadas en la base del taller (`vehicle_deliveries.signature_path`,
 * `reception_photos.path` y `diagnostic_item_photos.path`) y mueve cada archivo de `public` a `tenant_private` con la
 * misma ruta relativa: la base no cambia. Lo que no está en la base (el branding) queda en `public`.
 *
 * Es idempotente: un archivo que ya está en privado se saltea, y si además sigue en público esa copia se deja
 * (decisión del usuario) y se informa. Un archivo que no está en ningún disco solo se informa. El comando termina con
 * código 1 si el taller no existe o si un archivo no se pudo mover; los demás siguen. Al terminar deja el taller que
 * estaba activo, o ninguno.
 */
class PrivatizeTenantFiles extends Command
{
    protected $signature = 'tenants:privatize-files {--tenant= : Id del taller}';

    protected $description = 'Mueve las firmas y las fotos del taller del disco público al privado';

    private const FROM = 'public';

    private const TO = 'tenant_private';

    /** @var array{moved: int, already: int, kept: int, missing: int, failed: int} */
    private array $counts;

    public function handle(): int
    {
        // Se reinician en cada corrida: el mismo proceso puede llamar al comando más de una vez.
        $this->counts = ['moved' => 0, 'already' => 0, 'kept' => 0, 'missing' => 0, 'failed' => 0];
        $id = $this->option('tenant');

        if ($id === null || $id === '') {
            $this->error('Falta el taller: --tenant=<id>.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find((int) $id);

        if ($tenant === null) {
            $this->error("Taller #{$id}: no existe.");

            return self::FAILURE;
        }

        $label = "Taller #{$tenant->id} ({$tenant->name})";
        $previous = Tenant::current();

        try {
            $tenant->makeCurrent();

            $from = Storage::disk(self::FROM);
            $to = Storage::disk(self::TO);

            $this->privatize($label, 'Firma de la entrega', VehicleDelivery::query(), 'signature_path', $from, $to);
            $this->privatize($label, 'Foto de la recepción', ReceptionPhoto::query(), 'path', $from, $to);
            $this->privatize($label, 'Foto del diagnóstico', DiagnosticItemPhoto::query(), 'path', $from, $to);
        } finally {
            $previous === null ? Tenant::forgetCurrent() : $previous->makeCurrent();
        }

        $c = $this->counts;
        $this->info("{$label}: {$c['moved']} movidos, {$c['already']} ya estaban en privado ({$c['kept']} con copia en público), "
            ."{$c['missing']} faltantes y {$c['failed']} con error.");

        return $c['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function privatize(string $label, string $kind, Builder $query, string $column, FilesystemAdapter $from, FilesystemAdapter $to): void
    {
        $rows = $query->whereNotNull($column)->select(['id', $column])->lazyById(200);

        foreach ($rows as $row) {
            $path = $row->{$column};
            $item = "{$label}: {$kind} #{$row->id} ({$path})";

            try {
                $this->move($item, $path, $from, $to);
            } catch (Throwable $e) {
                $this->counts['failed']++;
                Log::error('No se pudo pasar el archivo al disco privado.', ['path' => $path, 'exception' => $e->getMessage()]);
                $this->error("{$item}: no se pudo mover. El detalle está en el log.");
            }
        }
    }

    private function move(string $item, string $path, FilesystemAdapter $from, FilesystemAdapter $to): void
    {
        // `fileExists` y no `exists`, que también es verdadero para una carpeta con ese nombre.
        $inPublic = $from->fileExists($path);

        if ($to->fileExists($path)) {
            $this->counts['already']++;

            if ($inPublic) {
                $this->counts['kept']++;
                $this->warn("{$item}: ya está en privado; la copia en público se deja.");
            }

            return;
        }

        if (! $inPublic) {
            $this->counts['missing']++;
            $this->warn("{$item}: falta el archivo en los dos discos.");

            return;
        }

        $stream = $from->readStream($path);

        if (! is_resource($stream)) {
            throw new RuntimeException('No se pudo leer el archivo del disco público.');
        }

        try {
            $to->writeStream($path, $stream);
        } finally {
            fclose($stream);
        }

        // Se borra del público solo después de escribirlo en privado: un corte deja las dos copias, no ninguna, y la
        // corrida siguiente lo cuenta como ya movido.
        if (! $from->delete($path)) {
            $this->counts['kept']++;
            $this->warn("{$item}: movido, pero la copia en público no se pudo borrar.");
        }

        $this->counts['moved']++;
    }
}
