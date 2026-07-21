<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerified
{
    /**
     * Super Admin may access the app without email_verified_at / OTP.
     * Admin and Viewer must verify email first.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if ($user->hasVerifiedEmailAddress()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Email belum diverifikasi.'], 403);
        }

        $request->session()->put('pending_verification_user_id', $user->id);
        auth()->logout();

        return redirect()
            ->route('verification.notice')
            ->with('warning', 'Email belum diverifikasi. Masukkan kode OTP yang dikirim ke email Anda.');
    }
}
