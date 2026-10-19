<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\OperationalDriverDirectory;
use Illuminate\Database\Seeder;

/**
 * Idempotent local/testing Driver accounts for vehicle assignment QA.
 * Skips production. Does not overwrite passwords of existing accounts.
 */
class DriverTestUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DriverTestUsersSeeder skipped: production environment.');

            return;
        }

        $catalog = [
            [
                'name' => 'Driver Uji 1',
                'email' => 'driver1@example.test',
                'phone' => '081111100001',
                'password' => 'Driver@123',
            ],
            [
                'name' => 'Driver Uji 2',
                'email' => 'driver2@example.test',
                'phone' => '081111100002',
                'password' => 'Driver@123',
            ],
            [
                'name' => 'Driver Uji 3',
                'email' => 'driver3@example.test',
                'phone' => '081111100003',
                'password' => 'Driver@123',
            ],
        ];

        $directory = app(OperationalDriverDirectory::class);

        foreach ($catalog as $row) {
            $user = User::query()->withTrashed()->firstOrNew(['email' => $row['email']]);

            if ($user->trashed()) {
                $user->restore();
            }

            if (! $user->exists) {
                $user->password = $row['password'];
                if (method_exists($user, 'setDefaultPasswordValue')) {
                    $user->setDefaultPasswordValue($row['password']);
                }
                $user->must_change_password = false;
            }

            $user->fill([
                'name' => $row['name'],
                'phone' => $row['phone'],
                'is_active' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ]);
            $user->save();
            $user->syncRoles(['driver']);

            $directory->ensureForUser($user);
        }
    }
}
