<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => true,
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'identity_service'),
            'username' => env('DB_USERNAME', 'unero'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8',
            'prefix' => '',
            'schema' => env('DB_SCHEMA', 'identity'),
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    'redis' => [
        'client' => env('REDIS_CLIENT', 'predis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', 'identity_service_database_'),
        ],
        'default' => [
            'scheme' => env('REDIS_SCHEME', 'tcp'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', '6379'),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'scheme' => env('REDIS_SCHEME', 'tcp'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'port' => env('REDIS_PORT', '6379'),
            'password' => env('REDIS_PASSWORD'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],
];
