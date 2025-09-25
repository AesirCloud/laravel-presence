<?php

namespace AesirCloud\Presence\Contracts;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;

interface PresenceRepository
{
    public function heartbeat(Authenticatable $user, array $meta = []): void;

    public function setOnline(Authenticatable $user, array $meta = []): void;

    public function setOffline(Authenticatable $user, array $meta = []): void;

    /** @return array{status:string,last_seen_at:DateTimeInterface|null,seconds_ago:int|null,meta:array} */
    public function status(int|string $userId, ?array $scope = null): array;

    /** @return array<int|string, array{status:string,last_seen_at:DateTimeInterface|null,seconds_ago:int|null,meta:array}> */
    public function many(array $userIds, ?array $scope = null): array;
}
