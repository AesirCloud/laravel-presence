<?php

namespace AesirCloud\Presence\Facades;

use AesirCloud\Presence\PresenceManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Facade;

/**
 * @method static void heartbeat(Authenticatable $user, array $meta = [])
 * @method static array status(int|string $userId, ?array $scope = null)
 * @method static array many(array $userIds, ?array $scope = null)
 * @method static void online(Authenticatable $user, array $meta = [])
 * @method static void offline(Authenticatable $user, array $meta = [])
 */
class Presence extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PresenceManager::class;
    }
}
