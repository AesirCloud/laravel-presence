<?php

namespace AesirCloud\Presence\Drivers;

use AesirCloud\Presence\Contracts\PresenceRepository;
use AesirCloud\Presence\Events\PresenceUpdated;
use AesirCloud\Presence\Events\UserCameOnline;
use AesirCloud\Presence\Events\UserWentOffline;
use AesirCloud\Presence\Support\Key;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as CacheStore;
use Illuminate\Support\Carbon;

class CachePresenceRepository implements PresenceRepository
{
    public function __construct(
        protected CacheStore $cache,
        protected int        $ttlSeconds,
        protected int        $awayAfterSeconds
    )
    {
    }

    public function heartbeat(Authenticatable $user, array $meta = []): void
    {
        $this->setOnline($user, $meta);
        event(new PresenceUpdated($user->getAuthIdentifier(), 'heartbeat', $this->payload($user, $meta)));
    }

    public function setOnline(Authenticatable $user, array $meta = []): void
    {
        $key = Key::for($user);
        $now = Carbon::now();

        $was = $this->cache->get($key);
        $this->cache->put($key, [
            'last_seen_at' => $now->toIso8601String(),
            'meta' => $meta,
        ], $this->ttlSeconds);

        if (!$was) {
            event(new UserCameOnline($user->getAuthIdentifier(), $this->payload($user, $meta)));
        } else {
            event(new PresenceUpdated($user->getAuthIdentifier(), 'online', $this->payload($user, $meta)));
        }
    }

    protected function payload(Authenticatable $user, array $meta): array
    {
        return [
            'user_id' => $user->getAuthIdentifier(),
            'occurred_at' => now()->toIso8601String(),
            'meta' => $meta,
        ];
    }

    public function setOffline(Authenticatable $user, array $meta = []): void
    {
        $key = Key::for($user);
        $this->cache->forget($key);

        event(new UserWentOffline($user->getAuthIdentifier(), $this->payload($user, $meta)));
    }

    public function many(array $userIds, ?array $scope = null): array
    {
        $out = [];
        foreach ($userIds as $id) {
            $out[$id] = $this->status($id, $scope);
        }
        return $out;
    }

    public function status(int|string $userId, ?array $scope = null): array
    {
        $key = Key::forUserId($userId, $scope);
        $raw = $this->cache->get($key);

        if (!$raw) {
            return ['status' => 'offline', 'last_seen_at' => null, 'seconds_ago' => null, 'meta' => []];
        }

        $last = Carbon::parse($raw['last_seen_at']);
        $ago = now()->diffInSeconds($last);
        $status = $ago > $this->ttlSeconds ? 'offline' : ($ago > $this->awayAfterSeconds ? 'away' : 'online');

        return [
            'status' => $status,
            'last_seen_at' => $last,
            'seconds_ago' => $ago,
            'meta' => $raw['meta'] ?? [],
        ];
    }
}
