<?php

namespace Tests\Feature\Tenancy;

use App\Enums\ClientNotificationStatus;
use App\Enums\ClientNotificationType;
use App\Enums\NotificationChannel;
use App\Enums\TenantStatus;
use App\Jobs\SendClientNotification;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Multitenancy\Jobs\PrepareTenant;
use App\Multitenancy\Jobs\RunsInTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Tests\Concerns\UsesTenantDatabases;
use Tests\TestCase;

/**
 * Colas por taller (T3, paso 4; historial 2.96): todo job lleva su taller, el worker lo corre ahí y deja el contexto
 * limpio, se libera si el taller no está disponible y se descarta si está suspendido. Cada test falla si se quita la
 * protección que cubre.
 *
 * Los jobs van a la cola `database` de la central, que comparte la transacción del test, y los procesa un worker en el
 * mismo proceso (`queue:work --once`), sin taller activo, como en producción. Como en los demás tests de varios
 * talleres, la transacción va en la conexión fija `sqlite` y los datos de A y B quedan en sus archivos
 * (UsesTenantDatabases).
 */
class TenantQueueTest extends TestCase
{
    use RefreshDatabase;
    use UsesTenantDatabases;

    protected $connectionsToTransact = ['sqlite'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['queue.default' => 'database']);
        TenantProbeJob::$runs = [];
        FailingOnceJob::$failed = false;
    }

    protected function tearDown(): void
    {
        Tenant::forgetCurrent();

        parent::tearDown();
    }

    public function test_the_worker_runs_a_job_in_its_tenant_and_leaves_no_tenant_behind(): void
    {
        config(['company.legal_name' => 'Razón social del .env']);
        $a = $this->registerTenant('a');
        $this->storeLegalName($a, 'Taller A S.A.');
        Tenant::forgetCurrent();
        $connection = DB::getDefaultConnection();
        $prefix = config('cache.prefix');

        $a->makeCurrent();
        TenantProbeJob::dispatch();
        Tenant::forgetCurrent();
        $this->work();

        $this->assertSame([[
            'tenant' => $a->id,
            'database' => $a->database,
            'legal_name' => 'Taller A S.A.',
        ]], TenantProbeJob::$runs);

        $this->assertNull(Tenant::current());
        $this->assertSame($connection, DB::getDefaultConnection());
        $this->assertSame($prefix, config('cache.prefix'));
        $this->assertSame('Razón social del .env', config('company.legal_name'), 'La configuración del taller no queda en el worker.');
    }

    public function test_a_tenant_job_cannot_be_dispatched_without_a_tenant(): void
    {
        Tenant::forgetCurrent();

        try {
            TenantProbeJob::dispatch();
            $this->fail('Se despachó un job del taller sin taller activo.');
        } catch (LogicException $e) {
            $this->assertStringContainsString(TenantProbeJob::class, $e->getMessage());
        }

        $this->assertSame(0, DB::connection('central')->table('jobs')->count());

        // Un job que se declara fuera de los talleres sí se despacha.
        NotTenantAwareJob::dispatch();
        $this->assertSame(1, DB::connection('central')->table('jobs')->count());
    }

    public function test_a_job_is_released_with_backoff_while_the_tenant_database_does_not_respond(): void
    {
        $this->freezeTime();
        Log::spy();
        $tenant = Tenant::create([
            'name' => 'Sin base',
            'domain' => 'caido.localhost',
            'database' => database_path('testing-tenant-inexistente.sqlite'),
        ]);

        $tenant->makeCurrent();
        TenantProbeJob::dispatch();
        Tenant::forgetCurrent();

        $this->work();
        $this->assertReleasedFor(30);

        $this->travel(31)->seconds();
        $this->work();
        $this->assertReleasedFor(120);

        $this->assertSame([], TenantProbeJob::$runs);
        $this->assertSame(0, DB::connection('central')->table('failed_jobs')->count());
        Log::shouldHaveReceived('error')->withArgs(fn (string $message) => $message === 'La base del taller no responde.');
    }

    public function test_a_job_is_released_while_the_tenant_is_in_maintenance(): void
    {
        $this->freezeTime();
        $a = $this->registerTenant('a');

        $a->makeCurrent();
        TenantProbeJob::dispatch();
        Tenant::forgetCurrent();
        $a->update(['maintenance' => true]);

        $this->work();

        $this->assertReleasedFor(30);
        $this->assertSame([], TenantProbeJob::$runs);
    }

    public function test_a_pending_notification_of_a_suspended_tenant_fails_with_the_reason(): void
    {
        Mail::fake();
        $a = $this->registerTenant('a');
        $a->makeCurrent();
        $notification = $this->pendingNotification();
        SendClientNotification::dispatch($notification->id);
        Tenant::forgetCurrent();
        $a->update(['status' => TenantStatus::SUSPENDED]);

        $this->work();

        Mail::assertNothingSent();
        $this->assertSame(0, DB::connection('central')->table('jobs')->count());
        $this->assertSame(0, DB::connection('central')->table('failed_jobs')->count());

        $a->makeCurrent();
        $notification->refresh();
        $this->assertSame(ClientNotificationStatus::FAILED, $notification->status);
        $this->assertSame('Taller suspendido.', $notification->error);
        $this->assertSame(0, $notification->attempts);
    }

    public function test_queue_retry_runs_a_failed_job_again_in_its_tenant(): void
    {
        $a = $this->registerTenant('a');

        $a->makeCurrent();
        FailingOnceJob::dispatch();
        Tenant::forgetCurrent();

        // Una excepción dentro de `handle` no se reintenta (maxExceptions = 1).
        $this->work();
        $this->assertSame(1, DB::connection('central')->table('failed_jobs')->count());
        $this->assertSame([], TenantProbeJob::$runs);

        // `queue:retry` corre sin taller, como en la consola: el paquete toma el del payload para volver a encolarlo.
        Artisan::call('queue:retry', ['id' => ['all']]);
        Tenant::forgetCurrent();
        $this->work();

        $this->assertSame($a->id, TenantProbeJob::$runs[0]['tenant']);
        $this->assertNull(Tenant::current());
    }

    public function test_the_notification_job_retries_for_six_hours_only_when_the_tenant_is_not_available(): void
    {
        $this->freezeTime();
        $job = new SendClientNotification(1);

        $this->assertEquals(now()->addHours(6), $job->retryUntil());
        $this->assertSame(1, $job->maxExceptions);
        $this->assertFalse(property_exists($job, 'tries'), 'Con $tries = 1 una liberación lo dejaría fallido.');
        $this->assertEquals([new PrepareTenant], $job->middleware());
    }

    private function work(): void
    {
        Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
    }

    private function assertReleasedFor(int $seconds): void
    {
        $job = DB::connection('central')->table('jobs')->sole();

        $this->assertNull($job->reserved_at);
        $this->assertSame(now()->getTimestamp() + $seconds, (int) $job->available_at);
    }

    private function registerTenant(string $name): Tenant
    {
        return Tenant::create([
            'name' => "Taller {$name}",
            'domain' => "{$name}.localhost",
            'database' => $this->tenantDatabase($name),
        ]);
    }

    private function storeLegalName(Tenant $tenant, string $value): void
    {
        $tenant->makeCurrent();
        SystemSetting::updateOrCreate(['key' => 'company.legal_name'], ['value' => $value]);
    }

    private function pendingNotification(): ClientNotification
    {
        // La base de A queda entre ejecuciones: documento y email únicos.
        $client = Client::create([
            'document_number' => '4'.random_int(100000, 999999),
            'first_name' => 'Ana',
            'last_name' => 'Gómez',
            'phone' => '0981'.random_int(100000, 999999),
            'email' => Str::lower(Str::random(10)).'@example.com',
        ]);

        return ClientNotification::create([
            'client_id' => $client->id,
            'type' => ClientNotificationType::BUDGET_SENT,
            'channel' => NotificationChannel::MAIL,
            'subject_type' => 'budget',
            'subject_id' => 1,
            'recipient' => $client->email,
            'status' => ClientNotificationStatus::PENDING,
        ]);
    }
}

class TenantProbeJob implements ShouldQueue
{
    use Queueable;
    use RunsInTenant;

    /** @var array<int, array{tenant: ?int, database: string, legal_name: ?string}> */
    public static array $runs = [];

    public function handle(): void
    {
        self::$runs[] = [
            'tenant' => Tenant::current()?->id,
            'database' => DB::connection()->getDatabaseName(),
            'legal_name' => config('company.legal_name'),
        ];
    }
}

class FailingOnceJob extends TenantProbeJob
{
    public static bool $failed = false;

    public function handle(): void
    {
        if (! self::$failed) {
            self::$failed = true;

            throw new RuntimeException('Falla del job.');
        }

        parent::handle();
    }
}

class NotTenantAwareJob implements NotTenantAware, ShouldQueue
{
    use Queueable;

    public function handle(): void {}
}
