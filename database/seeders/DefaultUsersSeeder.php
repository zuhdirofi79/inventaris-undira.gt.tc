<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds default Admin, Teknisi, and Viewer accounts for Inventaris UNDIRA.
 * Passwords are only set when the user is first created (reseed-safe).
 */
class DefaultUsersSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['name' => 'Admin 1', 'email' => 'admin1@undira.ac.id', 'role' => 'admin', 'password' => 'Admin@123'],
            ['name' => 'Admin 2', 'email' => 'admin2@undira.ac.id', 'role' => 'admin', 'password' => 'Admin@123'],
            ['name' => 'Admin 3', 'email' => 'admin3@undira.ac.id', 'role' => 'admin', 'password' => 'Admin@123'],
            ['name' => 'Teknisi 1', 'email' => 'teknisi1@undira.ac.id', 'role' => 'teknisi', 'password' => 'Teknisi@123'],
            ['name' => 'Teknisi 2', 'email' => 'teknisi2@undira.ac.id', 'role' => 'teknisi', 'password' => 'Teknisi@123'],
            ['name' => 'Teknisi 3', 'email' => 'teknisi3@undira.ac.id', 'role' => 'teknisi', 'password' => 'Teknisi@123'],
            ['name' => 'Teknisi 4', 'email' => 'teknisi4@undira.ac.id', 'role' => 'teknisi', 'password' => 'Teknisi@123'],
            ['name' => 'Teknisi 5', 'email' => 'teknisi5@undira.ac.id', 'role' => 'teknisi', 'password' => 'Teknisi@123'],
            ['name' => 'Viewer 1', 'email' => 'viewer1@undira.ac.id', 'role' => 'viewer', 'password' => 'Viewer@123'],
            ['name' => 'Viewer 2', 'email' => 'viewer2@undira.ac.id', 'role' => 'viewer', 'password' => 'Viewer@123'],
            ['name' => 'Viewer 3', 'email' => 'viewer3@undira.ac.id', 'role' => 'viewer', 'password' => 'Viewer@123'],
        ];

        foreach ($catalog as $row) {
            $user = User::query()->withTrashed()->firstOrNew(['email' => $row['email']]);

            if ($user->trashed()) {
                $user->restore();
            }

            if (! $user->exists) {
                $user->password = $row['password'];
                $user->setDefaultPasswordValue($row['password']);
                $user->must_change_password = false;
            }

            $user->fill([
                'name' => $row['name'],
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            $user->save();
            $user->syncRoles([$row['role']]);
        }
    }
}
