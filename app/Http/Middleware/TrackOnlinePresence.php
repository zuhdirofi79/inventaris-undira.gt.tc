<?php

namespace App\Http\Middleware;

use App\Services\OnlinePresenceService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackOnlinePresence
{
    public function __construct(
        protected OnlinePresenceService $presence,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null) {
            try {
                $this->presence->touch($user);
            } catch (\Throwable) {
                // Presence must never block navigation.
            }
        }

        return $next($request);
    }
}
