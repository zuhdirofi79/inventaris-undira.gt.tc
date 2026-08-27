<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginRememberMeTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_loadout_branding(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        $this->assertStringContainsString('>LOADOUT</h1>', $html);
        $this->assertStringContainsString('(List of Operational Assets, Data, Organization,', $html);
        $this->assertStringContainsString('Utilization &amp; Tracking)', $html);
        $this->assertStringContainsString('auth-tagline', $html);
        $this->assertStringContainsString('&copy; '.date('Y').' Inventaris UNDIRA', $html);
        $this->assertStringNotContainsString('Sistem Manajemen Inventaris', $html);
    }

    public function test_login_succeeds_without_remember_me(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer-remember-off@undira.ac.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_login_succeeds_with_remember_checkbox_on(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer-remember-on@undira.ac.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        // Browser default checkbox value when no value= attribute is set.
        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionDoesntHaveErrors('remember');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_login_succeeds_with_remember_value_one(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer-remember-one@undira.ac.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => '1',
        ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionDoesntHaveErrors('remember');

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_succeeds_after_remember_login(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer-remember-logout@undira.ac.id',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
            'remember' => 'on',
        ])->assertRedirect(route('dashboard'));

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
