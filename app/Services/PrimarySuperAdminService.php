<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

/**
 * Resolves and persists the immutable Primary Super Admin identity.
 * Initial association uses the canonical email once; thereafter only the user ID.
 */
class PrimarySuperAdminService
{
    public const SETTING_KEY = 'primary_super_admin_user_id';

    public const SETTING_GROUP = 'auth';

    public const CANONICAL_EMAIL = 'azwar.syifa.mufiqi@undira.ac.id';

    /**
     * Bind Setting to the canonical account if missing or stale.
     * Does not rebind when an existing ID still points to a live user.
     */
    public function ensureBound(): ?int
    {
        $currentId = $this->id();
        if ($currentId !== null) {
            $existing = User::query()->find($currentId);
            if ($existing) {
                return $currentId;
            }
        }

        $matches = User::query()
            ->where('email', self::CANONICAL_EMAIL)
            ->get(['id', 'email']);

        if ($matches->count() !== 1) {
            return null;
        }

        $id = (int) $matches->first()->id;
        Setting::set(self::SETTING_KEY, (string) $id, self::SETTING_GROUP);

        return $id;
    }

    public function id(): ?int
    {
        $raw = Setting::get(self::SETTING_KEY);
        if ($raw === null || $raw === '') {
            return null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    public function user(): ?User
    {
        $id = $this->id() ?? $this->ensureBound();

        return $id ? User::query()->find($id) : null;
    }

    public function isPrimary(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $id = $this->id();
        if ($id === null) {
            $id = $this->ensureBound();
        }

        return $id !== null && (int) $user->id === $id;
    }
}
