<?php

namespace App\Http\Controllers;

use App\Services\OnlinePresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PresenceController extends Controller
{
    public function __construct(
        protected OnlinePresenceService $presence,
    ) {}

    public function heartbeat(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return response()->json(['ok' => false], 401);
        }

        try {
            $this->presence->touch($user);
        } catch (\Throwable) {
            return response()->json(['ok' => false], 500);
        }

        return response()->json(['ok' => true]);
    }
}
