<?php

$apcuReady = function_exists('apcu_enabled') && apcu_enabled();

return [
    /*
    | cPanel / shared hosting: no Redis. Prefer APCu (shared memory) when the
    | PHP extension is enabled; otherwise file + StampedeCache file locks.
    | Set CACHE_DRIVER=apc or file explicitly if you do not want auto-detect.
    */
    'default' => env('CACHE_DRIVER') ?: ($apcuReady ? 'apc' : 'file'),
    'stores' => [
        'apc' => [
            'driver' => 'apc',
        ],
        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
    'prefix' => env('CACHE_PREFIX', 'gold_cache'),
];
