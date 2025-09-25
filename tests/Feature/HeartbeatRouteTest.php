<?php

use Tests\Fixtures\User;
use AesirCloud\Presence\Facades\Presence;
use Illuminate\Support\Facades\Route;

it('responds to heartbeat route and updates presence', function () {
    expect(Route::has('presence.heartbeat'))->toBeTrue();

    $user = new User(['id' => 42, 'name' => 'Test User']);

    $this->actingAs($user, 'web');
    $resp = $this->post('/presence/heartbeat');
    $resp->assertOk()->assertJson(['ok' => true]);

    $status = Presence::status(42);
    expect($status['status'])->toBe('online');
});