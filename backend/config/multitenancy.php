<?php

use App\Models\Tenant;
use App\Multitenancy\Tasks\PrefixTenantCacheTask;
use App\Multitenancy\Tasks\RestoreWorkshopSettingsTask;
use App\Multitenancy\Tasks\SwitchDefaultConnectionTask;
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
     * Tareas al activar un taller, en este orden: la base, la conexión por defecto (historial 2.93), después la cache
     * y por último los permisos, que releen la cache ya con el prefijo del taller y vacían los que tenían en memoria.
     * Al olvidarlo, la configuración del taller vuelve a la del .env (historial 2.96). Ninguna abre una conexión:
     * preparar el taller (comprobar su base y aplicar su configuración) es otra fase (historial 2.80).
     */
    'switch_tenant_tasks' => [
        SwitchTenantDatabaseTask::class,
        SwitchDefaultConnectionTask::class,
        PrefixTenantCacheTask::class,
        SwitchTenantPermissionsCacheTask::class,
        RestoreWorkshopSettingsTask::class,
    ],

    'tenant_model' => Tenant::class,

    /*
     * Todo job es del taller (T3, paso 4; historial 2.96): al despacharlo lleva el id del taller activo (en el Context
     * de Laravel) y el worker lo activa antes de correrlo. Despachar uno sin taller activo falla en ese momento
     * (`RequireTenantForQueuedJobs`): si no, el paquete lo borraría al procesarlo, sin dejarlo en `failed_jobs`.
     */
    'queues_are_tenant_aware_by_default' => true,

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
