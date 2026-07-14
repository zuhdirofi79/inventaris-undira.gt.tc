<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->hasRole('super-admin')) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Super admin access required.'], 403);
            }

            return redirect()->route('dashboard')->with('error', 'Akses ditolak. Hanya super admin.');
        }

        return $next($request);
    }
}
