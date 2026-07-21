<?php

namespace Tests\Feature;

use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_forgot_password_rejects_non_undira_email(): void
    {
        $this->post(route('password.email'), [
            'email' => 'user@gmail.com',
        ])->assertSessionHasErrors('email');
    }

    public function test_forgot_password_shows_not_found_for_unknown_email(): void
    {
        $this->post(route('password.email'), [
            'email' => 'tidak.ada@undira.ac.id',
        ])
            ->assertSessionHasErrors(['email' => 'Email tidak ditemukan.'])
            ->assertRedirect();
    }

    public function test_forgot_password_sends_otp_for_registered_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset.user@undira.ac.id',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post(route('password.email'), [
            'email' => $user->email,
        ])->assertRedirect(route('password.otp'));

        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use ($user) {
            return $mail->hasTo($user->email) && strlen($mail->otp) === 4;
        });
    }

    public function test_full_otp_reset_flow_allows_login_with_new_password(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset.flow@undira.ac.id',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.otp'));

        $otp = null;
        Mail::assertSent(PasswordResetOtpMail::class, function (PasswordResetOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->post(route('password.otp.verify'), ['otp' => $otp])
            ->assertRedirect(route('password.reset'));

        $this->post(route('password.update'), [
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect(route('login'));

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'NewPassword123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_invalid_otp_blocks_reset_form(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'reset.bad@undira.ac.id',
            'password' => Hash::make('OldPassword123!'),
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->post(route('password.email'), ['email' => $user->email]);

        $this->post(route('password.otp.verify'), ['otp' => '0000'])
            ->assertSessionHasErrors('otp');

        $this->get(route('password.reset'))
            ->assertRedirect(route('password.request'));
    }
}
