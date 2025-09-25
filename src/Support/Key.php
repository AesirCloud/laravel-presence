<?php

namespace AesirCloud\Presence\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Key
{
    public static function for(Authenticatable $user): string
    {
        return self::forUserId($user->getAuthIdentifier(), self::scope());
    }

    public static function forUserId(int|string $userId, ?array $scope = null): string
    {
        $scope ??= self::scope();
        $guard = $scope['guard'] ?? (Auth::hasResolvedGuards() ? Auth::getDefaultDriver() : 'web');

        $parts = array_filter([
            'presence',
            $scope['tenant'] ?? null,
            ($scope['location'] ?? null) ? 'loc:' . $scope['location'] : null,
            ($scope['domain'] ?? null) ? 'dom:' . $scope['domain'] : null,
            'guard:' . $guard,
            'user:' . $userId,
        ]);

        return Str::of(implode('|', $parts))->snake()->toString();
    }

    protected static function scope(): array
    {
        $resolver = config('presence.scope.resolver');

        if (is_callable($resolver)) {
            return $resolver();
        }

        // Fallback scope when no resolver is configured
        $guard = 'web';
        if (Auth::hasResolvedGuards()) {
            $guard = Auth::getDefaultDriver();
        }

        return [
            'guard' => $guard,
        ];
    }
}