<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use App\Services\RegistrationOtpService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        protected RegistrationOtpService $otpService,
    ) {}

    public function showRegistrationForm(): View
    {
        return view('auth.register');
    }

    public function register(RegisterRequest $request): RedirectResponse
    {
        try {
            $user = DB::transaction(function () use ($request) {
                $user = User::query()->create([
                    'name' => $request->input('name'),
                    'email' => $request->input('email'),
                    'password' => $request->input('password'),
                    'phone' => $request->input('phone'),
                    'identity_number' => $request->input('identity_number'),
                    'is_active' => false,
                    'email_verified_at' => null,
                ]);

                $user->assignRole('viewer');

                return $user;
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->isUsersEmailUniqueViolation($exception)) {
                throw ValidationException::withMessages([
                    'email' => RegisterRequest::DUPLICATE_EMAIL_MESSAGE,
                ]);
            }

            throw $exception;
        }

        $this->otpService->issue($user);

        $request->session()->put('pending_verification_user_id', $user->id);

        return redirect()
            ->route('verification.notice')
            ->with('success', 'Registrasi berhasil. Kode OTP telah dikirim ke email Anda.');
    }

    /**
     * Detect only the users.email unique index violation (race after validation).
     * Other DB uniqueness errors must not be disguised as an email conflict.
     */
    private function isUsersEmailUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        $message = $exception->getMessage();

        return str_contains($message, 'users.email')
            || str_contains($message, 'users_email_unique');
    }
}
