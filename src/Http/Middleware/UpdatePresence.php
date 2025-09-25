<?php

namespace AesirCloud\Presence\Http\Middleware;

use AesirCloud\Presence\Facades\Presence;
use Closure;
use Illuminate\Http\Request;

class UpdatePresence
{
    public function handle(Request $request, Closure $next)
    {
        if ($user = $request->user()) {
            if (!$request->session()->get('presence:last', 0) || time() - (int)$request->session()->get('presence:last') >= 30) {
                Presence::heartbeat($user, ['ip' => $request->ip()]);
                $request->session()->put('presence:last', time());
            }
        }

        return $next($request);
    }
}
