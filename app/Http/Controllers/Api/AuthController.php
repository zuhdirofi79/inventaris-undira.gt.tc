<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        if (! Auth::attempt($request->only('email', 'password'))) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $token = Str::random(64);
        Cache::put('api_token:'.$token, $user->id, now()->addDays(30));

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => 60 * 60 * 24 * 30,
            'user' => $user->load('roles'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->bearerToken();

        if ($token) {
            Cache::forget('api_token:'.$token);
        }

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('roles', 'permissions'));
    }
}
