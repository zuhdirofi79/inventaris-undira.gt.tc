<?php

namespace App\Support;

use App\Models\User;

/**
 * Sidebar persona helpers — UX grouping only (does not change permissions/routes).
 */
final class SidebarMenu
{
    public const PERSONA_ADMIN = 'admin';

    public const PERSONA_STAFF = 'staff';

    public const PERSONA_TEKNISI = 'teknisi';

    public const PERSONA_VIEWER = 'viewer';

    public static function user(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Highest-priority sidebar persona for the authenticated user.
     */
    public static function persona(?User $user = null): string
    {
        $user ??= self::user();
        if (! $user) {
            return self::PERSONA_VIEWER;
        }

        if ($user->hasAnyRole(['super-admin', 'admin'])) {
            return self::PERSONA_ADMIN;
        }

        if ($user->hasRole('staff')) {
            return self::PERSONA_STAFF;
        }

        if ($user->hasRole('teknisi')) {
            return self::PERSONA_TEKNISI;
        }

        return self::PERSONA_VIEWER;
    }

    public static function isAdminPersona(?User $user = null): bool
    {
        return self::persona($user) === self::PERSONA_ADMIN;
    }

    public static function isStaffPersona(?User $user = null): bool
    {
        return self::persona($user) === self::PERSONA_STAFF;
    }

    public static function isTeknisiPersona(?User $user = null): bool
    {
        return self::persona($user) === self::PERSONA_TEKNISI;
    }

    public static function isViewerPersona(?User $user = null): bool
    {
        return self::persona($user) === self::PERSONA_VIEWER;
    }

    /** Operator personas that manage full operational menus. */
    public static function isOperatorPersona(?User $user = null): bool
    {
        return in_array(self::persona($user), [self::PERSONA_ADMIN, self::PERSONA_STAFF], true);
    }

    public static function isSuperAdmin(?User $user = null): bool
    {
        $user ??= self::user();

        return (bool) $user?->hasRole('super-admin');
    }
}
