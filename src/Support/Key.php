<?php

namespace AesirCloud\Presence\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;

class Key
{
    public static function for(Authenticatable $user): string
    {
        return self::forUserId($user->getAuthIdentifier(), self::scope());
    }

    public static function forUserId(int|string $userId, ?array $scope = null): string
    {
        $guard = auth()->getDefaultDriver();
        $scope ??= self::scope();
        $parts = array_filter([
            'presence',
            $scope['tenant'] ?? null,
            ($scope['location'] ?? null) ? 'loc:' . $scope['location'] : null,
            ($scope['domain'] ?? null) ? 'dom:' . $scope['domain'] : null,
            'guard:' . ($scope['guard'] ?? $guard),
            'user:' . $userId,
        ]);

        return Str::of(implode('|', $parts))->snake()->toString();
    }

    protected static function scope(): array
    {
        $resolver = config('presence.scope.resolver');
        return is_callable($resolver) ? $resolver() : [
            'guard' => auth()->getDefaultDriver(),
        ];
    }
}
