<?php

namespace AesirCloud\Presence\Http\Controllers;

use AesirCloud\Presence\Facades\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HeartbeatController
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        Presence::heartbeat($user, [
            'ip' => $request->ip(),
            'ua' => substr((string)$request->userAgent(), 0, 255),
        ]);

        return response()->json(['ok' => true]);
    }
}
