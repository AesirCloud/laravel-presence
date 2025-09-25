<?php

use Tests\Fixtures\User;
use AesirCloud\Presence\Facades\Presence;
use Illuminate\Support\Carbon;

beforeEach(function () {
    config()->set('presence.driver', 'cache');
    config()->set('presence.cache.ttl_seconds', 120);
    config()->set('presence.cache.away_after_seconds', 90);
});

it('marks user online and transitions to away/offline based on time', function () {
    $user = new User(1);

    Carbon::setTestNow('2025-01-01 00:00:00');
    Presence::heartbeat($user);

    $status = Presence::status($user->id);
    expect($status['status'])->toBe('online')
        ->and($status['seconds_ago'])->toBe(0);

    Carbon::setTestNow('2025-01-01 00:01:40');
    $status = Presence::status($user->id);
    expect($status['status'])->toBe('away');

    Carbon::setTestNow('2025-01-01 00:02:10');
    $status = Presence::status($user->id);
    expect($status['status'])->toBe('offline');
});

it('returns a map for many users', function () {
    Carbon::setTestNow('2025-01-01 00:00:00');

    $u1 = new User(1);
    $u2 = new User(2);

    Presence::heartbeat($u1);
    $map = Presence::many([$u1->id, $u2->id]);

    expect($map[$u1->id]['status'])->toBe('online')
        ->and($map[$u2->id]['status'])->toBe('offline');
});
