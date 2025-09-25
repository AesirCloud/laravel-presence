<?php

namespace Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use AesirCloud\Presence\PresenceServiceProvider;
use Illuminate\Events\EventServiceProvider;
use Illuminate\Routing\RoutingServiceProvider;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            EventServiceProvider::class,
            RoutingServiceProvider::class,
            PresenceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // App key + array drivers
        $app['config']->set('app.key', 'base64:Wm1xa2N0dnd5enkxMjM0NTY3ODkwYWJjZGVmZ2hpamtsbW5vcA==');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', ['driver' => 'array']);
        $app['config']->set('presence.driver', 'cache');
        $app['config']->set('presence.cache.ttl_seconds', 120);
        $app['config']->set('presence.cache.away_after_seconds', 90);
        $app['config']->set('presence.routing.register_heartbeat_route', true);
    }
}
