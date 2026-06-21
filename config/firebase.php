<?php

return [
    'default' => env('FIREBASE_PROJECT', 'app'),

    'projects' => [
        'app' => [
            'credentials' => env('FIREBASE_CREDENTIALS'),

            'database' => [
                'url' => env('FIREBASE_DATABASE_URL'),
            ],

            'storage' => [
                'default_bucket' => env('FIREBASE_STORAGE_BUCKET'),
            ],

            'cache_store' => env('FIREBASE_CACHE_STORE', 'file'),

            'logging' => [
                'http_log_channel'   => env('FIREBASE_HTTP_LOG_CHANNEL'),
                'http_debug_log_channel' => env('FIREBASE_HTTP_DEBUG_LOG_CHANNEL'),
            ],
        ],
    ],
];