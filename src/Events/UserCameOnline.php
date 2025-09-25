<?php

namespace AesirCloud\Presence\Events;

class UserCameOnline
{
    public function __construct(public int|string $userId, public array $payload = [])
    {
    }
}
