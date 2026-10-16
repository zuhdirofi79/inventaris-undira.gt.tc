<?php

namespace Tests\Feature;

use App\Http\Requests\RegisterRequest;
use App\Mail\RegistrationOtpMail;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegisterDuplicateEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_register_succeeds_with_new_email(): void
    {
        Mail::fake();

        $response = $this->post(route('register'), [
            'name' => 'Viewer Baru',
            'email' => 'baru.unik@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = User::query()->where('email', 'baru.unik@undira.ac.id')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('viewer'));
        $this->assertFalse($user->is_active);
        Mail::assertSent(RegistrationOtpMail::class);
    }

    public function test_register_rejects_existing_email_without_http_500(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'existing@undira.ac.id',
        ]);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'User Baru',
            'identity_number' => 'NIM123',
            'email' => 'existing@undira.ac.id',
            'phone' => '08123456789',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors([
            'email' => RegisterRequest::DUPLICATE_EMAIL_MESSAGE,
        ]);
        $response->assertSessionHasInput([
            'name' => 'User Baru',
            'email' => 'existing@undira.ac.id',
            'identity_number' => 'NIM123',
            'phone' => '08123456789',
        ]);
        $this->assertFalse(session()->hasOldInput('password'));

        $this->assertSame(1, User::withTrashed()->where('email', 'existing@undira.ac.id')->count());
    }

    public function test_register_view_renders_email_error_and_login_link(): void
    {
        $this->withViewErrors([
            'email' => [RegisterRequest::DUPLICATE_EMAIL_MESSAGE],
        ])->view('auth.register')
            ->assertSee(RegisterRequest::DUPLICATE_EMAIL_MESSAGE, false)
            ->assertSee('is-invalid', false)
            ->assertSee('Masuk menggunakan akun Anda', false);
    }

    public function test_register_rejects_soft_deleted_email_without_http_500(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'trashed@undira.ac.id',
        ]);
        $user->delete();

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Coba Lagi',
            'email' => 'trashed@undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::withTrashed()->where('email', 'trashed@undira.ac.id')->count());
        $this->assertSame(0, User::query()->where('email', 'trashed@undira.ac.id')->count());
    }

    public function test_register_rejects_case_variant_of_existing_email(): void
    {
        Mail::fake();

        User::factory()->create([
            'email' => 'cased@undira.ac.id',
        ]);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Case Test',
            'email' => 'Cased@Undira.ac.id',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(302);
        $response->assertSessionHasErrors('email');
        $this->assertSame(1, User::withTrashed()->whereRaw('lower(email) = ?', ['cased@undira.ac.id'])->count());
    }

    public function test_register_rejects_empty_and_invalid_email_without_http_500(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'No Email',
            'email' => '',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('email');

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Bad Format',
            'email' => 'bukan-email',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('email');
    }

    public function test_register_password_validation_still_works(): void
    {
        $this->from(route('register'))->post(route('register'), [
            'name' => 'Weak Pass',
            'email' => 'weak.pass@undira.ac.id',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])->assertRedirect(route('register'))->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'weak.pass@undira.ac.id']);
    }

    public function test_race_condition_duplicate_email_returns_validation_not_500(): void
    {
        Mail::fake();

        $email = 'race.condition@undira.ac.id';
        User::factory()->create(['email' => $email]);

        // Simulate TOCTOU: validation already passed, then a concurrent insert won.
        // Call the controller with a request that skips the unique rule.
        $request = RegisterRequest::create('/register', 'POST', [
            'name' => 'Race User',
            'email' => $email,
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);
        $request->setContainer($this->app);
        $request->setRedirector($this->app->make('redirect'));
        $request->headers->set('Accept', 'text/html');

        $controller = $this->app->make(\App\Http\Controllers\Auth\RegisterController::class);

        try {
            $controller->register($request);
            $this->fail('Expected ValidationException for duplicate email race.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(
                RegisterRequest::DUPLICATE_EMAIL_MESSAGE,
                $exception->errors()['email'][0] ?? null
            );
        }

        $this->assertSame(1, User::withTrashed()->where('email', $email)->count());
    }

    public function test_existing_account_can_still_login_after_failed_duplicate_register(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'email' => 'login.keep@undira.ac.id',
            'password' => 'Password123!',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->assignRole('viewer');

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Hijack Attempt',
            'email' => 'login.keep@undira.ac.id',
            'password' => 'OtherPass123!',
            'password_confirmation' => 'OtherPass123!',
        ])->assertSessionHasErrors('email');

        $this->post(route('login'), [
            'email' => 'login.keep@undira.ac.id',
            'password' => 'Password123!',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user->fresh());
        $this->assertTrue($user->fresh()->hasRole('viewer'));
    }
}
