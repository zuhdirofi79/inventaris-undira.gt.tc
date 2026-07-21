<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = 'azwar.syifa.mufiqi@undira.ac.id';

        $user = User::query()->firstOrNew(['email' => $email]);

        // Password only on first create — never overwrite an existing hash on reseed.
        if (! $user->exists) {
            $user->password = 'Undira@2026';
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
