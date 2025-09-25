<?php

namespace AesirCloud\Presence;

use AesirCloud\Presence\Contracts\PresenceRepository;
use AesirCloud\Presence\Drivers\CachePresenceRepository;
use AesirCloud\Presence\Drivers\WebhookPresenceRepository;
use AesirCloud\Presence\Listeners\LoginMarkedOnline;
use AesirCloud\Presence\Listeners\LogoutMarkedOffline;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class PresenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/presence.php', 'presence');

        $this->app->bind(PresenceRepository::class, function ($app) {
            $cfg = $app['config']->get('presence');
            $store = $cfg['cache']['store']
                ? $app[CacheFactory::class]->store($cfg['cache']['store'])
                : $app['cache']->store();

            if ($cfg['driver'] === 'webhook') {
                return new WebhookPresenceRepository($store, (int)$cfg['cache']['ttl_seconds'], (int)$cfg['cache']['away_after_seconds']);
            }

            return new CachePresenceRepository($store, (int)$cfg['cache']['ttl_seconds'], (int)$cfg['cache']['away_after_seconds']);
        });

        $this->app->singleton(PresenceManager::class, fn($app) => new PresenceManager($app->make(PresenceRepository::class)));
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__ . '/../config/presence.php' => config_path('presence.php')], 'presence-config');
        }

        if (config('presence.routing.register_heartbeat_route')) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
        }

        Event::listen(Login::class, LoginMarkedOnline::class);
        Event::listen(Logout::class, LogoutMarkedOffline::class);
    }
}
