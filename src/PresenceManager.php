<?php

namespace AesirCloud\Presence;

use AesirCloud\Presence\Contracts\PresenceRepository;
use Illuminate\Contracts\Auth\Authenticatable;

class PresenceManager
{
    public function __construct(protected PresenceRepository $repo)
    {
    }

    public function heartbeat(Authenticatable $user, array $meta = []): void
    {
        $this->repo->heartbeat($user, $meta);
    }

    public function status(int|string $userId, ?array $scope = null): array
    {
        return $this->repo->status($userId, $scope);
    }

    public function many(array $userIds, ?array $scope = null): array
    {
        return $this->repo->many($userIds, $scope);
    }

    public function online(Authenticatable $user, array $meta = []): void
    {
        $this->repo->setOnline($user, $meta);
    }

    public function offline(Authenticatable $user, array $meta = []): void
    {
        $this->repo->setOffline($user, $meta);
    }
}
