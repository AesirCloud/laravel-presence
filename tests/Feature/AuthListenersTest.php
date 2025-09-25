<?php

use Tests\Fixtures\User;
use AesirCloud\Presence\Facades\Presence;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

it('login and logout listeners update presence', function () {
    $user = new User(7);

    Event::dispatch(new Login('web', $user, false));
    expect(Presence::status(7)['status'])->toBe('online');

    Event::dispatch(new Logout('web', $user));
    expect(Presence::status(7)['status'])->toBe('offline');
});
