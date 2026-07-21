<?php

namespace Tests\Feature;

use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationOtpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_register_rejects_non_undira_email(): void
    {
        $this->post(route('register'), [
            'name' => 'User Luar',
            'email' => 'user@gmail.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'user@gmail.com']);
    }

    public function test_register_sends_otp_and_keeps_account_inactive(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Viewer Undira',
            'identity_number' => '1234567890',
            'email' => 'viewer.otp@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $user = User::query()->where('email', 'viewer.otp@undira.ac.id')->first();
        $this->assertNotNull($user);
        $this->assertFalse($user->is_active);
        $this->assertNull($user->email_verified_at);
        $this->assertSame('1234567890', $user->identity_number);
        $this->assertTrue($user->hasRole('viewer'));

        Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use ($user) {
            return $mail->hasTo($user->email)
                && strlen($mail->otp) === 4
                && ctype_digit($mail->otp);
        });
    }

    public function test_verify_otp_activates_account_and_logs_in(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Viewer Verify',
            'email' => 'viewer.verify@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $otp = null;
        Mail::assertSent(RegistrationOtpMail::class, function (RegistrationOtpMail $mail) use (&$otp) {
            $otp = $mail->otp;

            return true;
        });

        $this->post(route('verification.verify'), ['otp' => $otp])
            ->assertRedirect(route('dashboard'));

        $user = User::query()->where('email', 'viewer.verify@undira.ac.id')->first();
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_redirects_unverified_user_to_otp_page(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Viewer Pending',
            'email' => 'viewer.pending@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();

        $this->post(route('login'), [
            'email' => 'viewer.pending@undira.ac.id',
            'password' => 'Password123!',
        ])->assertRedirect(route('verification.notice'));

        $this->assertGuest();
    }

    public function test_invalid_otp_is_rejected(): void
    {
        Mail::fake();

        $this->post(route('register'), [
            'name' => 'Viewer Bad Otp',
            'email' => 'viewer.badotp@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $this->post(route('verification.verify'), ['otp' => '0000'])
            ->assertSessionHasErrors('otp');

        $user = User::query()->where('email', 'viewer.badotp@undira.ac.id')->first();
        $this->assertFalse($user->is_active);
        $this->assertNull($user->email_verified_at);
    }
}
