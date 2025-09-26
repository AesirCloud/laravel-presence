# AesirCloud Laravel Presence

User presence for **Laravel** (online / away / offline) with a cache-backed heartbeat and optional **webhook** fan-out.

- **Cache driver** (Redis recommended) with TTL-based presence
- **Webhook driver** for real-time delivery (HMAC-signed)


<p align="center">
<a href="https://github.com/aesircloud/laravel-presence/actions" target="_blank"><img src="https://img.shields.io/github/actions/workflow/status/aesircloud/laravel-presence/test.yml?branch=main&style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-presence" target="_blank"><img src="https://img.shields.io/packagist/v/aesircloud/laravel-presence.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-presence" target="_blank"><img src="https://img.shields.io/packagist/dt/aesircloud/laravel-presence.svg?style=flat-square"/></a>
<a href="https://packagist.org/packages/aesircloud/laravel-presence" target="_blank"><img src="https://img.shields.io/packagist/l/aesircloud/laravel-presence.svg?style=flat-square"/></a>
</p>

---

## Requirements

- PHP **8.4+**
- Laravel **12.x**
- Cache store (Redis recommended in production)

---

## Install

```bash
  composer require aesircloud/laravel-presence
  php artisan vendor:publish --tag=presence-config
```

This publishes `config/presence.php`.

### Minimal `.env`

```env
# cache | webhook
PRESENCE_DRIVER=cache

# Cache
PRESENCE_CACHE_STORE=redis
PRESENCE_TTL=120
PRESENCE_AWAY_AFTER=90
```

Key options (see `config/presence.php`):

- `driver`: `cache` (default) or `webhook`
- `cache.ttl_seconds` / `cache.away_after_seconds`
- `webhook.url`, `webhook.secret`, `webhook.signature_header` (default `X-Presence-Signature`), `webhook.algo` (default `sha256`)
- `routing.register_heartbeat_route`: expose `/presence/heartbeat` if `true`
- `scope.resolver`: optional closure that returns `{ tenant, location, domain, guard }` to scope cache keys

---

## Laravel 12 middleware (auto-heartbeats)

In Laravel 12, register middleware in **`bootstrap/app.php`**:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Heartbeat roughly every 30s per session for authenticated users
        $middleware->append(\AesirCloud\Presence\Http\Middleware\UpdatePresence::class);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

This quietly updates presence on user navigation. You can also run heartbeats from JS (below).

---

## Optional frontend heartbeat

If you prefer a JS heartbeat (SPA/Livewire/Alpine), enable the built-in route and poll every 30–60s:

```php
// config/presence.php
'routing' => [
    'register_heartbeat_route' => true,
    'prefix' => 'presence',
    'middleware' => ['web', 'auth'],
    'throttle' => '60,1',
],
```

```html
<script>
  setInterval(() => {
    fetch('/presence/heartbeat', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
    });
  }, 30000);
</script>
```

---

## Reverb setup (real-time updates)

Use **Reverb** as your WebSocket server and have this package push presence changes to clients using the **webhook** driver. Your app will receive signed webhooks, verify them, and **broadcast** over Reverb.

### 1) Switch to the webhook driver

```env
PRESENCE_DRIVER=webhook

# Where your app will receive presence webhooks
PRESENCE_WEBHOOK_URL=https://your-app.test/hooks/presence
PRESENCE_WEBHOOK_SECRET=base64:your-long-random-secret
```

### 2) Configure broadcasting to Reverb

**.env** (example values; see Laravel docs for details):

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=reverb-app-id
REVERB_APP_KEY=reverb-app-key
REVERB_APP_SECRET=reverb-app-secret

REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_ENCRYPTED=false
```

**config/broadcasting.php** should already include the `reverb` driver in Laravel 12. Ensure your default connection is set via `BROADCAST_CONNECTION=reverb`.

Start the Reverb server in another terminal (or supervisor):

```bash
  php artisan reverb:start
```

### 3) Webhook receiver that broadcasts to Reverb

Create a route that **verifies the signature** and **broadcasts** a simple event. Example in `routes/api.php` (or a dedicated route file):

```php
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Events\PresenceUpdatedBroadcast;

Route::post('/hooks/presence', function (Request $request) {
    $sig = $request->header('X-Presence-Signature'); // "t=...,v1=..."
    abort_unless($sig, 401, 'Missing signature');

    // Parse header "t=<unix>,v1=<hmac>"
    $parts = collect(explode(',', $sig))
        ->mapWithKeys(function ($p) {
            [$k, $v] = array_pad(explode('=', trim($p), 2), 2, null);
            return [$k => $v];
        });

    $t  = $parts->get('t');
    $v1 = $parts->get('v1');
    abort_unless($t && $v1, 401, 'Invalid signature format');

    $payload = $request->getContent();
    $secret  = config('presence.webhook.secret');
    $algo    = config('presence.webhook.algo', 'sha256');
    $calc    = hash_hmac($algo, "{$t}.{$payload}", $secret);

    abort_unless(hash_equals($calc, $v1), 401, 'Invalid signature');

    // Payload from the package looks like:
    // { "user_id": 123, "occurred_at": "...", "meta": {...} }
    $data = $request->json()->all();

    // Broadcast over Reverb
    event(new PresenceUpdatedBroadcast($data));

    return response()->noContent();
})->name('hooks.presence');
```

Create the broadcast event, e.g. **`app/Events/PresenceUpdatedBroadcast.php`**:

```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PresenceUpdatedBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public array $payload) {}

    public function broadcastOn(): array
    {
        // Choose channels appropriate for your app. Examples:
        return [
            new Channel('presence.global'),
            new PrivateChannel('presence.user.' . ($this->payload['user_id'] ?? 'unknown')),
        ];
    }

    public function broadcastAs(): string
    {
        return 'presence.updated';
    }

    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
```

> If you want auth-protected channels, implement channel authorization in `routes/channels.php` and subscribe from the client using `private-` / `presence-` channels.

### 4) Frontend: Echo + Reverb

Install Echo if you haven’t:
```bash
  npm i laravel-echo pusher-js
```

**`resources/js/echo.js`** (example Reverb Echo config):

```js
import Echo from 'laravel-echo';

window.Echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'http') === 'https',
  enabledTransports: ['ws', 'wss'],
});
```

Subscribe anywhere in your app JS:

```js
import './echo';

window.Echo.channel('presence.global')
  .listen('.presence.updated', (e) => {
    console.log('Presence updated:', e); // e = { user_id, occurred_at, meta }
  });

// Example: user-specific channel
// window.Echo.private(`presence.user.${userId}`)
//   .listen('.presence.updated', (e) => { ... });
```

> If you use `private` or `presence` channels, ensure `routes/channels.php` returns `true`/user model for your channel gates.

---

## Frontend examples (Blade)

Below are simple ways to use presence from Blade templates.

### 1) Add a heartbeat from Blade (no SPA required)

In your layout (e.g., `resources/views/layouts/app.blade.php`), add a CSRF meta tag and a small polling script:

```blade
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
</head>
<body>
    {{ $slot ?? '' }}

    @auth
    <script>
      // Send a heartbeat every 30 seconds while the page is open
      setInterval(() => {
        fetch('{{ url('/presence/heartbeat') }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
          }
        }).catch(() => {});
      }, 30000);
    </script>
    @endauth
</body>
</html>
```

> Ensure `config('presence.routing.register_heartbeat_route')` is `true` and the route is protected by `['web', 'auth']` (default).

### 2) Server-rendered presence badge in Blade

Render a simple badge using the package facade. Example partial: `resources/views/components/presence-badge.blade.php`

```blade
@props(['userId'])

@php
    $status = \AesirCloud\Presence\Facades\Presence::status($userId);
    $color  = match ($status['status']) {
        'online' => 'bg-green-500',
        'away'   => 'bg-yellow-500',
        default  => 'bg-gray-400',
    };
@endphp

<span class="inline-flex items-center gap-2 text-sm">
    <span class="h-2.5 w-2.5 rounded-full {{ $color }}"></span>
    <span class="capitalize">{{ $status['status'] }}</span>
</span>
```

Use it anywhere:

```blade
<x-presence-badge :user-id="$user->id" />
```

> This is **server-rendered**; the badge updates on the next page load. For live updates without a reload, see the Alpine/Echo approach below.

### 3) Live-updating badge with Alpine.js + Reverb (Echo)

If you’re using the webhook + Reverb flow, you can listen for `presence.updated` and update the UI without a page reload.

**Blade component:** `resources/views/components/presence-badge-live.blade.php`

```blade
@props(['userId'])

<span
  x-data="{
    status: '{{ \AesirCloud\Presence\Facades\Presence::status($userId)['status'] }}',
    color() {
      return this.status === 'online' ? 'bg-green-500'
           : this.status === 'away'   ? 'bg-yellow-500'
           : 'bg-gray-400';
    },
    init() {
      if (window.Echo) {
        // Global channel; switch to a private channel if you prefer
        window.Echo.channel('presence.global')
          .listen('.presence.updated', (e) => {
            if (Number(e.user_id) === Number({{ $userId }})) {
              this.status = e.meta?.status ?? this.status; // or compute from backend payload
            }
          });
      }
    }
  }"
  class="inline-flex items-center gap-2 text-sm"
>
  <span :class="['h-2.5 w-2.5 rounded-full', color()]"></span>
  <span class="capitalize" x-text="status"></span>
</span>
```

Use it:

```blade
<x-presence-badge-live :user-id="$user->id" />
```

**Notes:**
- The example expects your webhook receiver to broadcast a payload including `user_id` and (optionally) `meta.status`. If you don’t include `status` inside `meta`, you can look it up via an API endpoint you control, or compute it server-side before broadcasting.
- Make sure your `resources/js/echo.js` is loaded once per page (see Reverb/Echo setup above).

### 4) Tiny API endpoint (optional) for fetching status via JS

If you want the live badge to **pull** status (instead of relying on `meta.status`), expose a small endpoint:

```php
// routes/api.php
use Illuminate\Support\Facades\Route;
use AesirCloud\Presence\Facades\Presence;

Route::middleware(['auth:sanctum'])
    ->get('/presence/status/{user}', function (\App\Models\User $user) {
        return response()->json(Presence::status($user->id));
    })->name('api.presence.status');
```

Then in the Alpine component, fetch it on `init()`:

```js
fetch('/api/presence/status/{{ $userId }}', { headers: { 'Accept': 'application/json' } })
  .then(r => r.json())
  .then(data => { this.status = data.status; })
  .catch(() => {});
```

---

## Package API (Facade)

```php
use AesirCloud\Presence\Facades\Presence;

// Mark a user online / heartbeat (refresh TTL)
Presence::heartbeat($user, ['ip' => request()->ip()]);

// Force online/offline
Presence::online($user);
Presence::offline($user);

// Read status
$status = Presence::status($userId);
// ['status'=>'online|away|offline','last_seen_at'=>Carbon|null,'seconds_ago'=>int|null,'meta'=>[]]

$many = Presence::many([1, 2, 3]);
```

### Status semantics

- `online`: elapsed < `away_after_seconds`
- `away`: `away_after_seconds` ≤ elapsed < `ttl_seconds`
- `offline`: elapsed ≥ `ttl_seconds` or key expired

---

## Multi-tenant / key scoping

Inject additional scope (tenant, location, domain, guard) into cache keys:

```php
// config/presence.php
'scope' => [
    'include_guard' => true,
    'resolver' => function () {
        $user = auth()->user();
        return [
            'tenant'   => $user?->tenant_id,
            'location' => $user?->current_location_id,
            'domain'   => request()->getHost(), // or custom host mapping
            'guard'    => auth()->getDefaultDriver(),
        ];
    },
],
```

---

## Security & performance notes

- Use **Redis** in production for fast expirations and predictable TTLs.
- Avoid sending webhooks for every heartbeat (`webhook.send_on.heartbeat = false` by default).
- Presence is **ephemeral**; don’t persist presence to your database unless you have a clear need.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Security

If you've found a bug regarding security please mail [security@aesircloud.com](mailto:security@aesircloud.com) instead of using the issue tracker.

## LICENSE

The MIT License (MIT). Please see [License](LICENSE.md) file for more information.