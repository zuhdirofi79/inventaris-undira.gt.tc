<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public const EMAIL = 'azwar.syifa.mufiqi@undira.ac.id';

    public const DEFAULT_PASSWORD = 'SuperAdmin@123';

    public function run(): void
    {
        $user = User::query()->withTrashed()->firstOrNew(['email' => self::EMAIL]);

        if ($user->trashed()) {
            $user->restore();
        }

        // Password + default_password only on first create — never overwrite on reseed.
        if (! $user->exists) {
            $user->password = self::DEFAULT_PASSWORD;
            $user->setDefaultPasswordValue(self::DEFAULT_PASSWORD);
            $user->must_change_password = false;
        }

        $user->fill([
            'name' => 'Azwar Syifa Mufiqi',
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);
        $user->save();

        $user->syncRoles(['super-admin']);

        app(\App\Services\PrimarySuperAdminService::class)->ensureBound();
    }
}
