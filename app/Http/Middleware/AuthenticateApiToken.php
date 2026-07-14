<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $userId = Cache::get('api_token:'.$token);

        if (! $userId) {
            return response()->json(['message' => 'Invalid or expired token.'], 401);
        }

        $user = User::query()->where('id', $userId)->where('is_active', true)->first();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
