<?php

return [
    'driver' => env('PRESENCE_DRIVER', 'cache'), // cache | webhook

    'cache' => [
        'store' => env('PRESENCE_CACHE_STORE', null),
        'ttl_seconds' => env('PRESENCE_TTL', 120),
        'away_after_seconds' => env('PRESENCE_AWAY_AFTER', 90),
    ],

    'webhook' => [
        'url' => env('PRESENCE_WEBHOOK_URL'),
        'secret' => env('PRESENCE_WEBHOOK_SECRET', 'test-secret'),
        'timeout' => 3,
        'retries' => 1,
        'signature_header' => 'X-Presence-Signature',
        'algo' => 'sha256',
        'send_on' => [
            'online' => true,
            'offline' => true,
            'heartbeat' => false,
            'away' => true,
        ],
        'headers' => [],
    ],

    'routing' => [
        'register_heartbeat_route' => true,
        'prefix' => 'presence',
        'middleware' => ['web', 'auth'],
        'throttle' => '60,1',
    ],

    'scope' => [
        'include_guard' => true,
        'resolver' => null,
    ],
];
