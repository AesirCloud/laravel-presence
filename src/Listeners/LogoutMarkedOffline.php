<?php

namespace AesirCloud\Presence\Listeners;

use AesirCloud\Presence\Facades\Presence;
use Illuminate\Auth\Events\Logout;

class LogoutMarkedOffline
{
    public function handle(Logout $event): void
    {
        Presence::offline($event->user, ['source' => 'logout']);
    }
}
