<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'azwar.syifa.mufiqi@undira.ac.id'],
            [
                'name' => 'Azwar Syifa Mufiqi',
                'password' => 'Undira@2026',
                'is_active' => true,
            ]
        );

        $user->syncRoles(['super-admin']);
    }
}
