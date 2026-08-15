<?php

return [
    'images_drivers' => [
        'ftp' => 'Внешний FTP Сервер',
        'yandex' => 'Яндекс Облако (Object Storage)',
        's3' => 'Amazon Cloud (S3)',
        's3_all' => 'Любое S3 совместимое хранилище',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application. Just store away!
    |
    */

    'default' => env('FILESYSTEM_DRIVER', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Default Cloud Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Many applications store files both locally and in the cloud. For this
    | reason, you may specify a default "cloud" driver here. This driver
    | will be bound as the Cloud disk implementation in the container.
    |
    */

    'cloud' => env('FILESYSTEM_CLOUD', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Here you may configure as many filesystem "disks" as you wish, and you
    | may even configure multiple disks of the same driver. Defaults have
    | been setup for each driver as an example of the required options.
    |
    | Supported Drivers: "local", "ftp", "s3", "rackspace"
    |
    */

    'disks' => [

        'iexexchanger-disk' => [
            'driver' => 'local',
            'root' => storage_path('app/iexexchanger/images'),
        ],

        'exports' => [
            'driver' => 'local',
            'root'   => storage_path('app/exports'),
        ],

        'config' => [
            'driver' => 'local',
            'root' => storage_path('app/config'),
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app'),
        ],

        /*
         * Support chat visitor uploads — not web-accessible (no public URL).
         */
        'support_chat_private' => [
            'driver' => 'local',
            'root' => storage_path('app/support-chat/private'),
        ],

        'archive' => [
            'driver' => 'local',
            'root' => storage_path('app/archive'),
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_KEY'),
            'secret' => env('AWS_SECRET'),
            'region' => env('AWS_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
        ],

        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            // Optional FTP Settings...
            // 'port'     => 21,
            // 'root'     => '',
            // 'passive'  => true,
            // 'ssl'      => true,
            // 'timeout'  => 30,
        ],

        'sftp' => [
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),

            // Settings for SSH key based authentication...
            // 'privateKey' => '/path/to/privateKey',
            // 'password' => 'encryption-password',

            // Optional SFTP Settings...
            // 'port' => 22,
            // 'root' => '',
            // 'timeout' => 30,
        ],

        'azure' => [
            'driver' => 'azure',
            'name' => env('AZURE_STORAGE_NAME'),
            'key' => env('AZURE_STORAGE_KEY'),
            'container' => env('AZURE_STORAGE_CONTAINER'),
        ],

        'rackspace' => [
            'driver' => 'rackspace',
            'username' => env('RACKSPACE_USERNAME'),
            'key' => env('RACKSPACE_KEY'),
            'container' => env('RACKSPACE_CONTAINER'),
            'endpoint' => 'https://identity.api.rackspacecloud.com/v2.0/',
            'region' => env('RACKSPACE_REGION', 'IAD'),
            'url_type' => env('RACKSPACE_PUBLIC_URL', 'publicURL'),
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
