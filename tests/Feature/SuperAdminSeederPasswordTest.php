<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminSeederPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_reseed_does_not_overwrite_existing_super_admin_password(): void
    {
        $this->seed(SuperAdminSeeder::class);

        $user = User::query()->where('email', 'azwar.syifa.mufiqi@undira.ac.id')->firstOrFail();
        $customPassword = 'CustomSecurePass!99';
        $user->password = $customPassword;
        $user->save();

        $this->assertTrue(Hash::check($customPassword, $user->fresh()->getAttributes()['password']));

        $this->seed(SuperAdminSeeder::class);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check($customPassword, $fresh->getAttributes()['password']));
        $this->assertTrue($fresh->hasRole('super-admin'));
        $this->assertTrue($fresh->is_active);
    }

    public function test_super_admin_login_rejects_wrong_password(): void
    {
        $user = User::factory()->create([
            'email' => 'super.wrongpass@undira.ac.id',
            'password' => 'CorrectPassword!1',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles(['super-admin']);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'super.wrongpass@undira.ac.id',
                'password' => 'WrongPassword!1',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_password_cast_does_not_double_hash_on_update(): void
    {
        $user = User::factory()->create([
            'email' => 'hash.check@undira.ac.id',
            'password' => 'PlainOnce!1',
            'is_active' => true,
        ]);

        $this->assertTrue(Hash::check('PlainOnce!1', $user->fresh()->getAttributes()['password']));

        $user->password = 'PlainTwice!2';
        $user->save();

        $this->assertTrue(Hash::check('PlainTwice!2', $user->fresh()->getAttributes()['password']));
        $this->assertFalse(Hash::check('PlainOnce!1', $user->fresh()->getAttributes()['password']));
    }
}
