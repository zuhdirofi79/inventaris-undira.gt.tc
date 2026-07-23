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
            'staff' => 'Staff',
            'viewer' => 'Viewer',
        ];
    }

    public static function label(string $roleName): string
    {
        return self::map()[$roleName] ?? $roleName;
    }
}
