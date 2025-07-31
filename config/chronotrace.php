<?php

return [
    // Mode de capture
    'enabled' => env('CHRONOTRACE_ENABLED', true),
    'mode' => env('CHRONOTRACE_MODE', 'record_on_error'), // always | sample | record_on_error | targeted
    'sample_rate' => env('CHRONOTRACE_SAMPLE_RATE', 0.001), // 0.1% des requêtes réussies

    // Stockage
    'storage' => env('CHRONOTRACE_STORAGE', 'local'), // local | s3 | minio
    'path' => env('CHRONOTRACE_PATH', storage_path('chronotrace')),

    // Configuration S3/Minio
    's3' => [
        'bucket' => env('CHRONOTRACE_S3_BUCKET', 'chronotrace'),
        'region' => env('CHRONOTRACE_S3_REGION', 'us-east-1'),
        'endpoint' => env('CHRONOTRACE_S3_ENDPOINT'), // Pour Minio
        'path_prefix' => env('CHRONOTRACE_S3_PREFIX', 'traces'),
    ],

    // Rétention et purge
    'retention_days' => env('CHRONOTRACE_RETENTION_DAYS', 15),
    'auto_purge' => env('CHRONOTRACE_AUTO_PURGE', true),

    // Compression et optimisation
    'compression' => [
        'enabled' => true,
        'level' => 6, // Niveau de compression gzip (1-9)
        'max_payload_size' => 1024 * 1024, // 1MB - au-delà, on stocke en blob séparé
    ],

    // Sécurité et PII
    /*
    |--------------------------------------------------------------------------
    | PII Scrubbing Configuration
    |--------------------------------------------------------------------------
    */
    'scrub' => [
        'password',
        'token',
        'secret',
        'key',
        'authorization',
        'cookie',
        'session',
        'credit_card',
        'ssn',
        'email',
        'phone',
    ],

    // Routes et jobs ciblés (pour mode 'targeted')
    'targets' => [
        'routes' => [
            'checkout/*',
            'payment/*',
            'orders/*',
        ],
        'jobs' => [
            'ProcessPayment',
            'SendOrderConfirmation',
        ],
    ],

    // Capture - ce qu'on enregistre
    'capture' => [
        'request' => true,
        'response' => true,
        'database' => true,
        'cache' => true,
        'http' => true, // Requêtes HTTP externes
        'mail' => true,
        'notifications' => true,
        'events' => true,
        'jobs' => true,
        'filesystem' => false, // Peut être lourd
    ],

    // Performance
    'async_storage' => true, // Stockage asynchrone via queue
    'queue_connection' => env('CHRONOTRACE_QUEUE_CONNECTION', 'default'),
    'queue_name' => env('CHRONOTRACE_QUEUE', 'chronotrace'),

    // Debug et développement
    'debug' => env('CHRONOTRACE_DEBUG', false),
    'local_replay_db' => env('CHRONOTRACE_REPLAY_DB', 'sqlite'), // sqlite | memory
];
