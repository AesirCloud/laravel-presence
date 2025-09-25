<?php

namespace AesirCloud\Presence\Events;

class UserWentOffline
{
    public function __construct(public int|string $userId, public array $payload = [])
    {
    }
}
