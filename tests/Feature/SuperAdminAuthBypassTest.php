<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Models\User;
use App\Support\MailDelivery;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SuperAdminAuthBypassTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_super_admin_can_login_without_email_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'super.admin@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->syncRoles(['super-admin']);

        $this->post(route('login'), [
            'email' => 'super.admin@undira.ac.id',
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_must_verify_email_before_login(): void
    {
        $user = User::factory()->create([
            'email' => 'admin.pending@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->syncRoles(['admin']);

        $this->post(route('login'), [
            'email' => 'admin.pending@undira.ac.id',
            'password' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();
    }

    public function test_viewer_must_verify_email_before_login(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer.pending2@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => false,
            'email_verified_at' => null,
        ]);
        $user->syncRoles(['viewer']);

        $this->post(route('login'), [
            'email' => 'viewer.pending2@undira.ac.id',
            'password' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();
    }

    public function test_verified_middleware_allows_unverified_super_admin(): void
    {
        $user = User::factory()->create([
            'email' => 'super.mw@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->syncRoles(['super-admin']);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new EnsureEmailIsVerified)->handle($request, fn () => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    public function test_verified_middleware_blocks_unverified_viewer(): void
    {
        $user = User::factory()->create([
            'email' => 'viewer.mw@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => null,
        ]);
        $user->syncRoles(['viewer']);

        $request = Request::create('/dashboard', 'GET');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        $response = (new EnsureEmailIsVerified)->handle($request, fn () => response('ok'));

        $this->assertTrue($response->isRedirect());
        $this->assertSame(route('verification.notice'), $response->headers->get('Location'));
    }

    public function test_forgot_password_shows_smtp_message_when_mail_not_ready(): void
    {
        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.username' => null,
            'mail.mailers.smtp.password' => null,
        ]);

        $this->assertFalse(MailDelivery::isReady());

        $user = User::factory()->create([
            'email' => 'super.reset@undira.ac.id',
            'password' => Hash::make('Password123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles(['super-admin']);

        $this->from(route('password.request'))
            ->post(route('password.email'), [
                'email' => 'super.reset@undira.ac.id',
            ])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors([
                'email' => MailDelivery::notReadyMessage(),
            ]);
    }
}
