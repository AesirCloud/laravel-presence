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
    $user = new User(['id' => 1, 'name' => 'Test User']);

    Carbon::setTestNow('2025-01-01 00:00:00');
    Presence::heartbeat($user);

    $status = Presence::status($user->getAuthIdentifier());
    expect($status['status'])->toBe('online')
        ->and($status['seconds_ago'])->toBe(0);

    Carbon::setTestNow('2025-01-01 00:01:30'); // 90 seconds later
    $status = Presence::status($user->getAuthIdentifier());
    expect($status['status'])->toBe('away');

    Carbon::setTestNow('2025-01-01 00:02:00'); // 120 seconds later
    $status = Presence::status($user->getAuthIdentifier());
    expect($status['status'])->toBe('offline');
});

it('returns a map for many users', function () {
    Carbon::setTestNow('2025-01-01 00:00:00');

    $u1 = new User(['id' => 1, 'name' => 'User 1']);
    $u2 = new User(['id' => 2, 'name' => 'User 2']);

    Presence::heartbeat($u1);
    $map = Presence::many([$u1->getAuthIdentifier(), $u2->getAuthIdentifier()]);

    expect($map[$u1->getAuthIdentifier()]['status'])->toBe('online')
        ->and($map[$u2->getAuthIdentifier()]['status'])->toBe('offline');
});