<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\PrimarySuperAdminService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrimarySuperAdminProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected PrimarySuperAdminService $primary;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->primary = app(PrimarySuperAdminService::class);
    }

    protected function makePrimary(): User
    {
        $user = User::factory()->create([
            'email' => PrimarySuperAdminService::CANONICAL_EMAIL,
            'name' => 'Azwar Syifa Mufiqi',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles(['super-admin']);
        $this->primary->ensureBound();

        return $user->fresh();
    }

    protected function makeOtherSuperAdmin(string $email = 'war1@undira.ac.id'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Other Super',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles(['super-admin']);

        return $user;
    }

    protected function makeAdminUser(string $email = 'war2@undira.ac.id'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Admin User',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    public function test_canonical_email_binds_immutable_primary_id(): void
    {
        $primary = $this->makePrimary();

        $this->assertSame($primary->id, $this->primary->id());
        $this->assertTrue($primary->isPrimarySuperAdmin());

        // Email change must not drop protection (identity is user id).
        $primary->update(['email' => 'renamed-primary@undira.ac.id']);
        $this->assertTrue($primary->fresh()->isPrimarySuperAdmin());
        $this->assertSame($primary->id, (int) Setting::get(PrimarySuperAdminService::SETTING_KEY));
    }

    public function test_other_super_admin_cannot_delete_primary(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();

        $this->actingAs($other)
            ->delete(route('admin.users.destroy', $primary))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $primary->id]);
    }

    public function test_primary_cannot_delete_itself(): void
    {
        $primary = $this->makePrimary();

        $this->actingAs($primary)
            ->delete(route('admin.users.destroy', $primary))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $primary->id]);
    }

    public function test_primary_can_delete_other_super_admin(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();

        $this->actingAs($primary)
            ->delete(route('admin.users.destroy', $other))
            ->assertRedirect();

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
    }

    public function test_other_super_admin_cannot_delete_another_super_admin(): void
    {
        $this->makePrimary();
        $a = $this->makeOtherSuperAdmin('sa-a@undira.ac.id');
        $b = $this->makeOtherSuperAdmin('sa-b@undira.ac.id');

        $this->actingAs($a)
            ->delete(route('admin.users.destroy', $b))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $b->id]);
    }

    public function test_other_super_admin_cannot_demote_or_deactivate_primary(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();

        $this->actingAs($other)
            ->put(route('admin.users.update', $primary), [
                'name' => $primary->name,
                'email' => $primary->email,
                'roles' => ['admin'],
                'is_active' => '0',
            ])
            ->assertForbidden();

        $primary->refresh();
        $this->assertTrue($primary->is_active);
        $this->assertTrue($primary->hasRole('super-admin'));
    }

    public function test_primary_cannot_demote_or_deactivate_itself(): void
    {
        $primary = $this->makePrimary();

        $this->actingAs($primary)
            ->put(route('admin.users.update', $primary), [
                'name' => $primary->name,
                'email' => $primary->email,
                'roles' => ['admin'],
                'is_active' => '0',
            ])
            ->assertForbidden();

        $primary->refresh();
        $this->assertTrue($primary->is_active);
        $this->assertTrue($primary->hasRole('super-admin'));
    }

    public function test_primary_can_demote_other_super_admin(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();

        $this->actingAs($primary)
            ->put(route('admin.users.update', $other), [
                'name' => $other->name,
                'email' => $other->email,
                'roles' => ['admin'],
                'is_active' => '1',
            ])
            ->assertRedirect();

        $other->refresh();
        $this->assertTrue($other->hasRole('admin'));
        $this->assertFalse($other->hasRole('super-admin'));
    }

    public function test_users_index_shows_primary_badge_and_hides_delete(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();
        $admin = $this->makeAdminUser();

        $html = $this->actingAs($primary)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Primary Super Admin')
            ->getContent();

        $this->assertStringContainsString('fa-shield-alt', $html);
        $this->assertStringContainsString('fa-lock', $html);

        // DELETE form must not target the primary account (update shares the same URL).
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$primary->id.'"[^>]*method="POST"[^>]*>\s*<input[^>]+name="_method"[^>]+value="DELETE"/is',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$other->id.'"[^>]*>[\s\S]*?name="_method"[^>]+value="DELETE"/i',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$admin->id.'"[^>]*>[\s\S]*?name="_method"[^>]+value="DELETE"/i',
            $html
        );
    }

    public function test_other_super_admin_ui_hides_destructive_actions_for_super_admins(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();
        $admin = $this->makeAdminUser();

        $html = $this->actingAs($other)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$primary->id.'"[^>]*>[\s\S]*?name="_method"[^>]+value="DELETE"/i',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$other->id.'"[^>]*>[\s\S]*?name="_method"[^>]+value="DELETE"/i',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<form[^>]+action="[^"]*admin\/users\/'.$admin->id.'"[^>]*>[\s\S]*?name="_method"[^>]+value="DELETE"/i',
            $html
        );
    }

    public function test_second_user_is_not_primary(): void
    {
        $primary = $this->makePrimary();
        $other = $this->makeOtherSuperAdmin();

        $this->assertTrue($primary->isPrimarySuperAdmin());
        $this->assertFalse($other->isPrimarySuperAdmin());
    }
}
