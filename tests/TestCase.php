<?php

namespace Tests;

use AesirCloud\Presence\PresenceServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Hashing\HashManager;

class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            PresenceServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Set up basic Laravel configuration
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
        $app['config']->set('app.env', 'testing');

        // Cache and session configuration
        $app['config']->set('cache.default', 'array');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        // Auth configuration
        $app['config']->set('auth.defaults', [
            'guard' => 'web',
            'passwords' => 'users',
        ]);

        $app['config']->set('auth.guards', [
            'web' => [
                'driver' => 'session',
                'provider' => 'users',
            ],
        ]);

        $app['config']->set('auth.providers', [
            'users' => [
                'driver' => 'eloquent',
                'model' => Tests\Fixtures\User::class,
            ],
        ]);

        // Presence package configuration
        $app['config']->set('presence.driver', 'cache');
        $app['config']->set('presence.cache.ttl_seconds', 120);
        $app['config']->set('presence.cache.away_after_seconds', 90);
        $app['config']->set('presence.routing.register_heartbeat_route', true);
        $app['config']->set('presence.routing.prefix', 'presence');
        $app['config']->set('presence.routing.middleware', ['web']);
        $app['config']->set('presence.routing.throttle', '60,1');
    }

    protected function defineDatabaseMigrations(): void
    {
        // We'll use in-memory testing, no actual migrations needed
    }
}