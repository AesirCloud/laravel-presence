<?php

use AesirCloud\Presence\Support\Key;

it('builds scoped cache keys with resolver', function () {
    config()->set('presence.scope.resolver', function () {
        return [
            'tenant'   => 'ACME',
            'location' => 'HQ',
            'domain'   => 'example.test',
            'guard'    => 'web',
        ];
    });

    $key = Key::forUserId(5);
    expect($key)->toContain('presence')
        ->and($key)->toContain('_a_c_m_e') // The key is snake_cased
        ->and($key)->toContain('loc:_h_q') // The key is snake_cased
        ->and($key)->toContain('dom:example.test')
        ->and($key)->toContain('guard:web')
        ->and($key)->toContain('user:5');
});