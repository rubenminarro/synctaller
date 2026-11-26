<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // Discos por taller (T4, historial 2.98; App\Multitenancy\TenantStorage): `{tenant}` es el id del taller
        // activo y sin taller no se resuelven. `tenant_url` es la ruta pública: la URL lleva el host del taller.
        // `url` queda para Storage::fake en los tests.
        'public' => [
            'driver' => 'tenant_local',
            'root' => storage_path('app/public/tenants/{tenant}'),
            'url' => env('APP_URL').'/storage',
            'tenant_url' => '/storage/tenants/{tenant}',
            'visibility' => 'public',
            'lazy_root_creation' => true,
            'throw' => false,
            'report' => false,
        ],

        // Privado del taller (decisión E): documentos de siniestros y, con P8, fotos y firmas. Sin serve ni URL: solo
        // se descargan por la API, con permiso y auditoría.
        'tenant_private' => [
            'driver' => 'tenant_local',
            'root' => storage_path('app/tenants/{tenant}/private'),
            'visibility' => 'private',
            'lazy_root_creation' => true,
            'serve' => false,
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
