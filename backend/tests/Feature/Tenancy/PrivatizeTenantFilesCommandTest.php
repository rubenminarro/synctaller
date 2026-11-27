<?php

namespace Tests\Feature\Tenancy;

use App\Models\DiagnosticItemPhoto;
use App\Models\DiagnosticItemTemplate;
use App\Models\ReceptionPhoto;
use App\Models\Tenant;
use App\Multitenancy\TenantStorage;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Receptions\ReceptionTestCase;

/**
 * `tenants:privatize-files` (P8, paso 3, historial 2.106): pasa las firmas y las fotos del disco público al privado con
 * la misma ruta. Es idempotente, no toca lo que no está en la base (el branding), deja la copia pública de un archivo
 * que ya estaba en privado e informa los faltantes sin fallar. Cada test falla si se quita la protección que cubre.
 *
 * Usan los discos reales sobre una carpeta temporal, como TenantStorageTest: `Storage::fake` no conoce el taller.
 */
class PrivatizeTenantFilesCommandTest extends ReceptionTestCase
{
    private string $root;

    private string $signature;

    private string $receptionPhoto;

    private string $diagnosticPhoto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = storage_path('framework/testing/privatize-files');
        (new Filesystem)->deleteDirectory($this->root);

        config([
            'filesystems.disks.public.root' => $this->root.'/public/tenants/{tenant}',
            'filesystems.disks.tenant_private.root' => $this->root.'/tenants/{tenant}/private',
        ]);
        TenantStorage::forgetDisks();

        $this->actingAsAdmin();
        [$this->signature, $this->receptionPhoto, $this->diagnosticPhoto] = $this->recordsWithPaths();
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_it_moves_signatures_and_photos_to_the_private_disk_and_leaves_the_branding(): void
    {
        foreach ($this->paths() as $path) {
            Storage::disk('public')->put($path, "contenido de {$path}");
        }
        Storage::disk('public')->put('branding/logo.png', 'logo');

        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])
            ->expectsOutput('Taller #'.self::TEST_TENANT_ID.' (Taller de prueba): 3 movidos, 0 ya estaban en privado (0 con copia en público), 0 faltantes y 0 con error.')
            ->assertExitCode(0);

        foreach ($this->paths() as $path) {
            $this->assertSame("contenido de {$path}", Storage::disk('tenant_private')->get($path));
        }
        $this->assertSame(['branding/logo.png'], Storage::disk('public')->allFiles());

        // Deja activo el taller que estaba.
        $this->assertSame(self::TEST_TENANT_ID, Tenant::current()?->id);
    }

    public function test_a_second_run_changes_nothing(): void
    {
        foreach ($this->paths() as $path) {
            Storage::disk('public')->put($path, "contenido de {$path}");
        }

        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])->assertExitCode(0);
        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])
            ->expectsOutputToContain('0 movidos, 3 ya estaban en privado (0 con copia en público), 0 faltantes y 0 con error.')
            ->assertExitCode(0);

        foreach ($this->paths() as $path) {
            $this->assertSame("contenido de {$path}", Storage::disk('tenant_private')->get($path));
        }
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_file_already_private_is_not_overwritten_and_its_public_copy_is_kept(): void
    {
        Storage::disk('public')->put($this->receptionPhoto, 'copia vieja');
        Storage::disk('tenant_private')->put($this->receptionPhoto, 'privada');

        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])
            ->expectsOutputToContain("Foto de la recepción #{$this->photoId()} ({$this->receptionPhoto}): ya está en privado; la copia en público se deja.")
            ->expectsOutputToContain('0 movidos, 1 ya estaban en privado (1 con copia en público), 2 faltantes y 0 con error.')
            ->assertExitCode(0);

        $this->assertSame('privada', Storage::disk('tenant_private')->get($this->receptionPhoto));
        $this->assertSame('copia vieja', Storage::disk('public')->get($this->receptionPhoto));
    }

    public function test_missing_files_are_reported_without_failing(): void
    {
        Storage::disk('public')->put($this->signature, 'firma');

        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])
            ->expectsOutputToContain("({$this->receptionPhoto}): falta el archivo en los dos discos.")
            ->expectsOutputToContain("({$this->diagnosticPhoto}): falta el archivo en los dos discos.")
            ->expectsOutputToContain('1 movidos, 0 ya estaban en privado (0 con copia en público), 2 faltantes y 0 con error.')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk('tenant_private')->exists($this->signature));
    }

    public function test_a_file_that_cannot_be_moved_keeps_its_public_copy_and_the_others_continue(): void
    {
        foreach ($this->paths() as $path) {
            Storage::disk('public')->put($path, "contenido de {$path}");
        }
        // Una carpeta con el nombre del archivo en el destino hace fallar la escritura.
        (new Filesystem)->makeDirectory($this->root.'/tenants/'.self::TEST_TENANT_ID.'/private/'.$this->receptionPhoto, 0755, true);

        $this->artisan('tenants:privatize-files', ['--tenant' => self::TEST_TENANT_ID])
            ->expectsOutputToContain("({$this->receptionPhoto}): no se pudo mover. El detalle está en el log.")
            ->expectsOutputToContain('2 movidos, 0 ya estaban en privado (0 con copia en público), 0 faltantes y 1 con error.')
            ->assertExitCode(1);

        $this->assertSame("contenido de {$this->receptionPhoto}", Storage::disk('public')->get($this->receptionPhoto));
        $this->assertTrue(Storage::disk('tenant_private')->exists($this->signature));
        $this->assertTrue(Storage::disk('tenant_private')->exists($this->diagnosticPhoto));
    }

    public function test_it_requires_an_existing_tenant(): void
    {
        $this->artisan('tenants:privatize-files')
            ->expectsOutput('Falta el taller: --tenant=<id>.')
            ->assertExitCode(1);

        $this->artisan('tenants:privatize-files', ['--tenant' => 999])
            ->expectsOutput('Taller #999: no existe.')
            ->assertExitCode(1);
    }

    /**
     * Una entrega con firma, una foto de recepción y una de diagnóstico, con las rutas que guardaba el disco público.
     *
     * @return array{string, string, string}
     */
    private function recordsWithPaths(): array
    {
        $reception = $this->createReception();

        // Fila directa: el comando solo lee la ruta, y registrar una entrega exige facturar la recepción.
        DB::table('vehicle_deliveries')->insert([
            'reception_id' => $reception['id'],
            'vehicle_id' => $this->vehicle->id,
            'client_id' => $this->client->id,
            'delivery_sequence' => 1,
            'delivery_number' => 'ENT-000001',
            'delivered_at' => now(),
            'delivered_by' => auth()->id(),
            'final_mileage' => 45100,
            'received_by_name' => 'Ana Gomez',
            'received_by_document' => '1234567',
            'customer_accepted' => true,
            'signature_path' => 'deliveries/firma.png',
            'previous_reception_status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        ReceptionPhoto::create(['reception_id' => $reception['id'], 'path' => 'receptions/frente.jpg']);

        $diagnostic = $this->postJson('/api/diagnostics', [
            'reception_id' => $reception['id'],
            'customer_complaint' => 'Ruido al frenar',
            'priority' => 'high',
            'requires_parts' => true,
            'requires_repair' => true,
        ])->assertCreated()->json('data');
        $template = DiagnosticItemTemplate::create(['code' => 'DIA-PRIV01', 'title' => 'Pastillas', 'severity' => 'medium']);
        $item = $this->postJson('/api/diagnostic-items', [
            'diagnostic_id' => $diagnostic['id'],
            'diagnostic_item_template_id' => $template->id,
            'severity' => 'high',
            'status' => 'replace_required',
            'requires_repair' => false,
            'requires_replacement' => true,
            'estimated_cost' => 250000,
            'estimated_time' => 60,
        ])->assertCreated()->json('data');

        DiagnosticItemPhoto::create(['diagnostic_item_id' => $item['id'], 'path' => "diagnostic-items/{$item['id']}/pastilla.jpg"]);

        return ['deliveries/firma.png', 'receptions/frente.jpg', "diagnostic-items/{$item['id']}/pastilla.jpg"];
    }

    /**
     * @return list<string>
     */
    private function paths(): array
    {
        return [$this->signature, $this->receptionPhoto, $this->diagnosticPhoto];
    }

    private function photoId(): int
    {
        return ReceptionPhoto::query()->where('path', $this->receptionPhoto)->value('id');
    }
}
