<?php

namespace AesirCloud\Presence\Events;

class PresenceUpdated
{
    public function __construct(
        public int|string $userId,
        public string     $type,
        public array      $payload = []
    )
    {
    }
}
