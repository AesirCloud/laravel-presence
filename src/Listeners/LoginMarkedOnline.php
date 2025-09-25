<?php

namespace AesirCloud\Presence\Listeners;

use AesirCloud\Presence\Facades\Presence;
use Illuminate\Auth\Events\Login;

class LoginMarkedOnline
{
    public function handle(Login $event): void
    {
        Presence::online($event->user, ['source' => 'login']);
    }
}
