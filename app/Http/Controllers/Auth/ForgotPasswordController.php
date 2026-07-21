<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordWithOtpRequest;
use App\Http\Requests\VerifyRegistrationOtpRequest;
use App\Models\User;
use App\Services\PasswordResetOtpService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public const SESSION_USER = 'password_reset_user_id';

    public const SESSION_VERIFIED = 'password_reset_otp_verified';

    public function __construct(
        protected PasswordResetOtpService $otpService,
    ) {}

    public function showLinkRequestForm(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLinkEmail(ForgotPasswordRequest $request): RedirectResponse
    {
        $email = $request->input('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            return back()
                ->withErrors(['email' => 'Email tidak ditemukan.'])
                ->onlyInput('email');
        }

        $this->otpService->issue($user);

        $request->session()->put(self::SESSION_USER, $user->id);
        $request->session()->forget(self::SESSION_VERIFIED);

        return redirect()
            ->route('password.otp')
            ->with('success', 'Kode OTP telah dikirim ke email Anda.');
    }

    public function showOtpForm(Request $request): View|RedirectResponse
    {
        $user = $this->sessionUser($request);
        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Sesi reset password tidak ditemukan. Masukkan email Anda kembali.']);
        }

        return view('auth.password-otp', [
            'email' => $user->email,
        ]);
    }

    public function verifyOtp(VerifyRegistrationOtpRequest $request): RedirectResponse
    {
        $user = $this->sessionUser($request);
        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Sesi reset password tidak ditemukan. Masukkan email Anda kembali.']);
        }

        $this->otpService->verify($user, $request->input('otp'));

        $request->session()->put(self::SESSION_VERIFIED, true);

        return redirect()
            ->route('password.reset')
            ->with('success', 'OTP berhasil diverifikasi. Silakan buat password baru.');
    }

    public function resendOtp(Request $request): RedirectResponse
    {
        $user = $this->sessionUser($request);
        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Sesi reset password tidak ditemukan. Masukkan email Anda kembali.']);
        }

        $this->otpService->issue($user);
        $request->session()->forget(self::SESSION_VERIFIED);

        return back()->with('success', 'Kode OTP baru telah dikirim ke email Anda.');
    }

    public function showResetForm(Request $request): View|RedirectResponse
    {
        $user = $this->verifiedSessionUser($request);
        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Verifikasi OTP diperlukan sebelum mengatur password baru.']);
        }

        return view('auth.reset-password', [
            'email' => $user->email,
        ]);
    }

    public function reset(ResetPasswordWithOtpRequest $request): RedirectResponse
    {
        $user = $this->verifiedSessionUser($request);
        if (! $user) {
            return redirect()
                ->route('password.request')
                ->withErrors(['email' => 'Verifikasi OTP diperlukan sebelum mengatur password baru.']);
        }

        $user->forceFill([
            'password' => $request->input('password'),
            'remember_token' => Str::random(60),
        ])->save();

        event(new PasswordReset($user));

        $request->session()->forget([self::SESSION_USER, self::SESSION_VERIFIED]);

        return redirect()
            ->route('login')
            ->with('success', 'Password berhasil diubah. Silakan masuk dengan password baru.');
    }

    protected function sessionUser(Request $request): ?User
    {
        $userId = $request->session()->get(self::SESSION_USER);

        return $userId ? User::query()->find($userId) : null;
    }

    protected function verifiedSessionUser(Request $request): ?User
    {
        if (! $request->session()->get(self::SESSION_VERIFIED)) {
            return null;
        }

        return $this->sessionUser($request);
    }
}
