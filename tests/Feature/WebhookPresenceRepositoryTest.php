<?php

use Tests\Fixtures\User;
use AesirCloud\Presence\Facades\Presence;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('presence.driver', 'webhook');
    config()->set('presence.webhook.url', 'https://example.test/presence');
    config()->set('presence.webhook.secret', 'secret');
    config()->set('presence.webhook.send_on.heartbeat', false);
});

it('sends signed webhook on online/offline', function () {
    Http::fake();

    $user = new User(9);
    Presence::online($user);
    Presence::offline($user);

    Http::assertSentCount(2);

    Http::assertSent(function ($request) {
        return $request->url() === 'https://example.test/presence'
            && $request->header('X-Presence-Signature') !== null
            && ($request->data()['user_id'] ?? null) === 9;
    });
});
