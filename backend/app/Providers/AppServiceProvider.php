<?php

namespace App\Providers;

use App\Models\Budget;
use App\Models\Invoice;
use App\Models\PurchaseItem;
use App\Models\Reception;
use App\Models\VehicleMaintenance;
use App\Models\WorkOrder;
use App\Models\WorkOrderAllocation;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderPart;
use App\Multitenancy\RequireTenantForQueuedJobs;
use App\Multitenancy\TenantStorage;
use App\Services\WorkshopSettingService;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(WorkshopSettingService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Alias para referencias de inventario, orígenes de líneas de factura,
        // documentos que originan avisos a clientes y sujetos de las excepciones
        // internas. Sin enforce: otras relaciones polimórficas (Spatie,
        // notificaciones de usuarios) siguen guardando el nombre de clase.
        Relation::morphMap([
            'work_order_part' => WorkOrderPart::class,
            'work_order_item' => WorkOrderItem::class,
            'work_order' => WorkOrder::class,
            'work_order_allocation' => WorkOrderAllocation::class,
            'purchase_item' => PurchaseItem::class,
            'budget' => Budget::class,
            'reception' => Reception::class,
            'invoice' => Invoice::class,
            'vehicle_maintenance' => VehicleMaintenance::class,
        ]);

        // Todo job es del taller: no se despacha sin uno activo (historial 2.96). Cada job prepara su taller en su
        // middleware (`PrepareTenant`), donde puede liberarse si la base no responde: en `Queue::before` no podía.
        Queue::createPayloadUsing(new RequireTenantForQueuedJobs);

        // Discos con la raíz del taller activo (T4, historial 2.98).
        TenantStorage::register();

        // Bypass implícito para el rol 'administrador'
        Gate::before(function ($user, $ability) {
            return $user->hasRole('administrador') ? true : null;
        });
    }
}
