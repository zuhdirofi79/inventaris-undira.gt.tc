<?php

namespace App\Support;

final class RoleLabels
{
    /**
     * @return array<string, string>
     */
    public static function map(): array
    {
        return [
            'super-admin' => 'Super Admin',
            'admin' => 'Admin',
            'teknisi' => 'Teknisi',
            'staff' => 'Staff',
            'viewer' => 'Viewer',
            'driver' => 'Driver',
            'ob' => 'OB',
        ];
    }

    public static function label(string $roleName): string
    {
        return self::map()[$roleName] ?? $roleName;
    }

    public static function badgeClass(string $roleName): string
    {
        return match ($roleName) {
            'super-admin' => 'bg-danger',
            'admin' => 'bg-primary',
            'teknisi' => 'bg-info text-dark',
            'viewer' => 'bg-secondary',
            'staff' => 'bg-undira-blue',
            'driver' => 'bg-success',
            'ob' => 'bg-warning text-dark',
            default => 'bg-undira-blue',
        };
    }
}
