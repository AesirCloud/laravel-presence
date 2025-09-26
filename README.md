# AesirCloud Laravel Presence

A Laravel package for tracking user presence (online/away/offline) with multiple drivers and real-time capabilities.

## Features

- **Multiple Drivers**: Cache-based storage or webhook notifications
- **Automatic Status Transitions**: Online → Away → Offline based on configurable time thresholds
- **Event System**: Dispatches events when users come online, go offline, or update presence
- **Authentication Integration**: Automatically tracks presence on login/logout
- **Scoped Keys**: Support for multi-tenant applications with custom scope resolvers
- **Real-time Ready**: Built-in support for broadcasting presence updates via Laravel Reverb
- **Middleware Support**: Optional middleware for automatic presence updates
- **API Routes**: RESTful heartbeat endpoint for client-side updates

## Installation

Install via Composer:

```bash
  composer require aesircloud/laravel-presence
```

Publish the configuration file:

```bash
  php artisan vendor:publish --tag=presence-config
```

## Configuration

The package configuration is located in `config/presence.php`:

```php
return [
    'driver' => env('PRESENCE_DRIVER', 'cache'), // cache | webhook

    'cache' => [
        'store' => env('PRESENCE_CACHE_STORE', null),
        'ttl_seconds' => env('PRESENCE_TTL', 120),
        'away_after_seconds' => env('PRESENCE_AWAY_AFTER', 90),
    ],

    'webhook' => [
        'url' => env('PRESENCE_WEBHOOK_URL'),
        'secret' => env('PRESENCE_WEBHOOK_SECRET'),
        // ... additional webhook settings
    ],

    'routing' => [
        'register_heartbeat_route' => true,
        'prefix' => 'presence',
        'middleware' => ['web', 'auth'],
        'throttle' => '60,1',
    ],

    'scope' => [
        'include_guard' => true,
        'resolver' => null, // Custom scope resolver callback
    ],
];
```

## Basic Usage

### Tracking Presence

```php
use AesirCloud\Presence\Facades\Presence;

// Mark user as online
Presence::online($user, ['ip' => request()->ip()]);

// Send heartbeat (updates last seen time)
Presence::heartbeat($user, ['page' => 'dashboard']);

// Mark user as offline
Presence::offline($user);

// Get user's current status
$status = Presence::status($user->id);
// Returns: ['status' => 'online', 'last_seen_at' => Carbon, 'seconds_ago' => 30, 'meta' => [...]]

// Get status for multiple users
$statuses = Presence::many([1, 2, 3, 4]);
```

### Status Values

- **online**: User is actively using the application
- **away**: User hasn't been seen for more than `away_after_seconds` (default: 90s)
- **offline**: User hasn't been seen for more than `ttl_seconds` (default: 120s)

### Events

The package dispatches several events you can listen to:

```php
use AesirCloud\Presence\Events\UserCameOnline;
use AesirCloud\Presence\Events\UserWentOffline;
use AesirCloud\Presence\Events\PresenceUpdated;

// In EventServiceProvider
protected $listen = [
    UserCameOnline::class => [
        SendWelcomeNotification::class,
    ],
    UserWentOffline::class => [
        LogUserActivity::class,
    ],
    PresenceUpdated::class => [
        BroadcastPresenceUpdate::class,
    ],
];
```

## Real-time Updates with Laravel Reverb

Laravel Reverb is perfect for broadcasting presence updates to connected clients. Here's how to integrate:

### 1. Install and Configure Reverb

```bash
  composer require laravel/reverb
  php artisan reverb:install
  php artisan migrate
```

### 2. Create a Presence Event Listener

```php
// app/Listeners/BroadcastPresenceUpdate.php
<?php

namespace App\Listeners;

use AesirCloud\Presence\Events\PresenceUpdated;
use AesirCloud\Presence\Events\UserCameOnline;
use AesirCloud\Presence\Events\UserWentOffline;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Broadcast;

class BroadcastPresenceUpdate implements ShouldQueue
{
    public function handle(PresenceUpdated|UserCameOnline|UserWentOffline $event): void
    {
        // Broadcast to a general presence channel
        Broadcast::channel('presence.global', [
            'type' => match(get_class($event)) {
                UserCameOnline::class => 'user.online',
                UserWentOffline::class => 'user.offline', 
                PresenceUpdated::class => 'user.heartbeat',
            },
            'user_id' => $event->userId,
            'timestamp' => now()->toISOString(),
            'payload' => $event->payload ?? [],
        ]);
    }
}
```

### 3. Register the Listener

```php
// app/Providers/EventServiceProvider.php
use AesirCloud\Presence\Events\{PresenceUpdated, UserCameOnline, UserWentOffline};

protected $listen = [
    UserCameOnline::class => [BroadcastPresenceUpdate::class],
    UserWentOffline::class => [BroadcastPresenceUpdate::class],
    PresenceUpdated::class => [BroadcastPresenceUpdate::class],
];
```

### 4. Frontend Integration

```javascript
// resources/js/presence.js
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// Listen for presence updates
window.Echo.channel('presence.global')
    .listen('.user.online', (e) => {
        console.log(`User ${e.user_id} came online`);
        updateUserStatus(e.user_id, 'online');
    })
    .listen('.user.offline', (e) => {
        console.log(`User ${e.user_id} went offline`);
        updateUserStatus(e.user_id, 'offline');
    })
    .listen('.user.heartbeat', (e) => {
        console.log(`User ${e.user_id} sent heartbeat`);
        updateUserStatus(e.user_id, 'online');
    });

// Send heartbeats from the client
setInterval(() => {
    fetch('/presence/heartbeat', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            page: window.location.pathname
        })
    });
}, 30000); // Every 30 seconds

function updateUserStatus(userId, status) {
    // Update your UI to show user status
    const userElement = document.querySelector(`[data-user-id="${userId}"]`);
    if (userElement) {
        userElement.className = `user-status user-${status}`;
    }
}
```

### 5. Start Reverb Server

```bash
  php artisan reverb:start
```

## Advanced Usage

### Custom Scope Resolver

For multi-tenant applications, you can define custom scopes:

```php
// config/presence.php
'scope' => [
    'resolver' => function () {
        return [
            'tenant' => auth()->user()?->tenant_id,
            'location' => session('current_location'),
            'guard' => auth()->getDefaultDriver(),
        ];
    },
],
```

### Middleware Integration

Add the presence middleware to automatically track user activity:

```php
// app/Http/Kernel.php
protected $middlewareGroups = [
    'web' => [
        // ... other middleware
        \AesirCloud\Presence\Http\Middleware\UpdatePresence::class,
    ],
];
```

### Webhook Driver

For distributed systems, use the webhook driver:

```php
// .env
PRESENCE_DRIVER=webhook
PRESENCE_WEBHOOK_URL=https://your-api.com/webhooks/presence
PRESENCE_WEBHOOK_SECRET=your-webhook-secret
```

## API Reference

### Facade Methods

```php
// Set user online with optional metadata
Presence::online($user, array $meta = [])

// Send heartbeat (preferred for regular updates)
Presence::heartbeat($user, array $meta = [])

// Set user offline
Presence::offline($user, array $meta = [])

// Get single user status
Presence::status(int|string $userId, ?array $scope = null)

// Get multiple user statuses
Presence::many(array $userIds, ?array $scope = null)
```

### HTTP Endpoints

- `POST /presence/heartbeat` - Send user heartbeat (requires authentication)

### Events

- `UserCameOnline($userId, $payload)` - User transitions from offline to online
- `UserWentOffline($userId, $payload)` - User goes offline
- `PresenceUpdated($userId, $type, $payload)` - Any presence update (heartbeat, online, etc.)

## Testing

Run the package tests:

```bash
  vendor/bin/pest
```

## Requirements

- PHP 8.2+
- Laravel 12.0+
- For real-time features: Laravel Reverb or compatible WebSocket server

## License

MIT License. See LICENSE file for details.