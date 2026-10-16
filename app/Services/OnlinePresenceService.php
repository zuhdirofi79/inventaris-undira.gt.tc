<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OnlinePresenceService
{
    public const ONLINE_WINDOW_SECONDS = 300;

    public const TOUCH_THROTTLE_SECONDS = 60;

    public const DASHBOARD_LIMIT = 10;

    /**
     * Mark the authenticated user as recently active (throttled).
     */
    public function touch(User $user): void
    {
        if (! $user->is_active) {
            return;
        }

        $now = now();
        $lastSeen = $user->last_seen_at;
        if ($lastSeen instanceof Carbon && $lastSeen->gt($now->copy()->subSeconds(self::TOUCH_THROTTLE_SECONDS))) {
            return;
        }

        // Avoid model events / dirtying the in-memory auth user unexpectedly.
        DB::table('users')
            ->where('id', $user->id)
            ->update(['last_seen_at' => $now]);

        $user->last_seen_at = $now;
    }

    /**
     * @return array{total: int, users: list<array{id: int, name: string, role: string}>}
     */
    public function dashboardSnapshot(?User $viewer = null): array
    {
        $threshold = now()->subSeconds(self::ONLINE_WINDOW_SECONDS);

        $query = User::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $threshold)
            ->with('roles:id,name')
            ->orderByDesc('last_seen_at')
            ->orderBy('name');

        $total = (clone $query)->count();

        /** @var Collection<int, User> $users */
        $users = $query->limit(self::DASHBOARD_LIMIT)->get();

        return [
            'total' => $total,
            'users' => $users->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $this->roleLabel($user),
            ])->all(),
        ];
    }

    protected function roleLabel(User $user): string
    {
        $role = $user->roles->first()?->name;

        return match ($role) {
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
            'driver' => 'Driver',
            'ob' => 'OB',
            'teknisi' => 'Teknisi',
            default => $role ? str_replace('-', ' ', ucwords($role, '-')) : 'Pengguna',
        };
    }
}
