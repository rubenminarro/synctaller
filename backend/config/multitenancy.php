<?php

use App\Models\Tenant;
use App\Multitenancy\Tasks\PrefixTenantCacheTask;
use App\Multitenancy\Tasks\SwitchTenantPermissionsCacheTask;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Queue\CallQueuedClosure;
use Spatie\Multitenancy\Actions\ForgetCurrentTenantAction;
use Spatie\Multitenancy\Actions\MakeQueueTenantAwareAction;
use Spatie\Multitenancy\Actions\MakeTenantCurrentAction;
use Spatie\Multitenancy\Actions\MigrateTenantAction;
use Spatie\Multitenancy\Jobs\NotTenantAware;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\Tasks\SwitchTenantDatabaseTask;

/*
 * spatie/laravel-multitenancy 4.2.3 (TD2). Línea T, paso 1 de T2 (historial 2.90): el paquete queda instalado y
 * configurado, pero todavía nada activa un taller. La identificación por host es el paso 2, con un middleware propio.
 */
return [

    // Sin buscador del paquete: el taller lo identifica `IdentifyTenant` (paso 2), con los errores TENANT_* en JSON.
    'tenant_finder' => null,

    'tenant_artisan_search_fields' => [
        'id',
    ],

    /*
     * Tareas al activar un taller, en este orden: la base, después la cache y por último los permisos, que releen la
     * cache ya con el prefijo del taller. Ninguna abre una conexión: preparar el taller (comprobar su base y aplicar
     * su configuración) es otra fase (historial 2.80).
     */
    'switch_tenant_tasks' => [
        SwitchTenantDatabaseTask::class,
        PrefixTenantCacheTask::class,
        SwitchTenantPermissionsCacheTask::class,
    ],

    'tenant_model' => Tenant::class,

    /*
     * Apagado hasta T3. El paquete revisa todos los jobs de la cola: si un job se considera del taller y no trae uno,
     * lo borra y falla. Mientras nada active un taller, encenderlo perdería los avisos a clientes.
     */
    'queues_are_tenant_aware_by_default' => false,

    'tenant_database_connection_name' => 'tenant',

    'landlord_database_connection_name' => 'central',

    'current_tenant_context_key' => 'tenantId',

    'current_tenant_container_key' => 'currentTenant',

    'shared_routes_cache' => false,

    'actions' => [
        'make_tenant_current_action' => MakeTenantCurrentAction::class,
        'forget_current_tenant_action' => ForgetCurrentTenantAction::class,
        'make_queue_tenant_aware_action' => MakeQueueTenantAwareAction::class,
        'migrate_tenant' => MigrateTenantAction::class,
    ],

    'queueable_to_job' => [
        SendQueuedMailable::class => 'mailable',
        SendQueuedNotifications::class => 'notification',
        CallQueuedClosure::class => 'closure',
        CallQueuedListener::class => 'class',
        BroadcastEvent::class => 'event',
    ],

    'tenant_aware_interface' => TenantAware::class,

    'not_tenant_aware_interface' => NotTenantAware::class,

    'tenant_aware_jobs' => [],

    'not_tenant_aware_jobs' => [],
];
