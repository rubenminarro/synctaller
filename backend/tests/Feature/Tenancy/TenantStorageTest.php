<?php

namespace Tests\Feature\Tenancy;

use App\Http\Resources\ReceptionPhotoResource;
use App\Models\ReceptionPhoto;
use App\Models\Tenant;
use App\Multitenancy\TenantStorage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Discos por taller (T4; historial 2.98). Cada test falla si se quita la protección que cubre: la raíz por taller y la
 * tarea que olvida los discos al cambiar de taller, el fallo cerrado sin taller, la URL con el host del taller y la URL
 * de las fotos de recepción armada por el disco.
 *
 * Usan los discos reales sobre una carpeta temporal, no `Storage::fake`, que reemplaza el disco y no conoce el taller.
 * No guardan nada en la base del taller: cambiar de taller no abre conexión.
 */
class TenantStorageTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/tenant-storage');
        (new Filesystem)->deleteDirectory($this->root);

        config([
            'filesystems.disks.public.root' => $this->root.'/public/tenants/{tenant}',
            'filesystems.disks.tenant_private.root' => $this->root.'/tenants/{tenant}/private',
        ]);
        TenantStorage::forgetDisks();
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function disks(): array
    {
        return [
            'público' => ['public', '/public/tenants/%d/'],
            'privado' => ['tenant_private', '/tenants/%d/private/'],
        ];
    }

    #[DataProvider('disks')]
    public function test_each_tenant_stores_its_files_under_its_own_root(string $disk, string $root): void
    {
        $tenantA = $this->tenant(1);
        $tenantB = $this->tenant(10);
        $path = 'claims/5/documento.pdf';

        $tenantA->makeCurrent();
        Storage::disk($disk)->put($path, 'archivo del taller 1');

        // La misma ruta guardada en la base de B no llega al archivo de A.
        $tenantB->makeCurrent();
        $this->assertFalse(Storage::disk($disk)->exists($path));
        Storage::disk($disk)->put($path, 'archivo del taller 10');

        $tenantA->makeCurrent();
        $this->assertSame('archivo del taller 1', Storage::disk($disk)->get($path));

        $this->assertFileExists($this->root.sprintf($root, 1).$path);
        $this->assertFileExists($this->root.sprintf($root, 10).$path);
    }

    #[DataProvider('disks')]
    public function test_without_a_current_tenant_the_disk_fails_closed(string $disk, string $root): void
    {
        // Ya resuelto con el taller de prueba: olvidar el taller también tiene que olvidar el disco.
        Storage::disk($disk)->put('a.txt', 'dato');
        $this->assertFileExists($this->root.sprintf($root, Tenant::current()->id).'a.txt');

        Tenant::forgetCurrent();

        $this->expectException(LogicException::class);

        Storage::disk($disk);
    }

    public function test_the_public_url_uses_the_tenant_host_with_the_scheme_and_port_of_app_url(): void
    {
        config(['app.url' => 'https://synctaller.test:8443']);

        $this->tenant(10)->makeCurrent();

        $this->assertSame(
            'https://t10.localhost:8443/storage/tenants/10/receptions/foto.jpg',
            Storage::disk('public')->url('receptions/foto.jpg')
        );
    }

    public function test_the_reception_photo_url_comes_from_the_tenant_disk(): void
    {
        $this->tenant(10)->makeCurrent();

        $photo = new ReceptionPhoto(['path' => 'receptions/foto.jpg']);
        $url = (new ReceptionPhotoResource($photo))->toArray(request())['url'];

        $this->assertStringStartsWith('http://t10.localhost', $url);
        $this->assertStringEndsWith('/storage/tenants/10/receptions/foto.jpg', $url);
    }

    private function tenant(int $id): Tenant
    {
        return tap((new Tenant)->forceFill([
            'id' => $id,
            'name' => "Taller {$id}",
            'domain' => "t{$id}.localhost",
            'database' => "synctaller_{$id}",
        ]))->save();
    }
}
