<?php

use AesirCloud\Presence\Http\Controllers\HeartbeatController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => config('presence.routing.prefix'),
    'middleware' => array_merge(
        config('presence.routing.middleware', []),
        ['throttle:' . config('presence.routing.throttle', '60,1')]
    ),
], function () {
    Route::post('/heartbeat', HeartbeatController::class)->name('presence.heartbeat');
});
