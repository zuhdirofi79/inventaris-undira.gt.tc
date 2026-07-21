<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyRegistrationOtpRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\RegistrationOtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class EmailVerificationController extends Controller
{
    public function __construct(
        protected RegistrationOtpService $otpService,
        protected AuditLogService $auditLogService,
    ) {}

    public function notice(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors(['email' => 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.']);
        }

        // Super Admin never needs OTP verification.
        if ($user->isSuperAdmin()) {
            $request->session()->forget('pending_verification_user_id');

            if (! Auth::check()) {
                Auth::login($user);
                $request->session()->regenerate();
            }

            return redirect()->route('dashboard');
        }

        if ($user->email_verified_at && $user->is_active) {
            $request->session()->forget('pending_verification_user_id');

            return redirect()->route('login')->with('success', 'Email sudah terverifikasi. Silakan masuk.');
        }

        return view('auth.verify-email', [
            'email' => $user->email,
        ]);
    }

    public function verify(VerifyRegistrationOtpRequest $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors(['email' => 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.']);
        }

        $this->otpService->verify($user, $request->input('otp'));

        $request->session()->forget('pending_verification_user_id');

        Auth::login($user);
        $request->session()->regenerate();

        $this->auditLogService->log($user, AuditAction::Login, $user, null, null, 'User verified email & login');

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email berhasil diverifikasi. Akun Anda aktif.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()
                ->route('register')
                ->withErrors(['email' => 'Sesi verifikasi tidak ditemukan. Silakan daftar ulang.']);
        }

        if ($user->email_verified_at) {
            return redirect()->route('login')->with('success', 'Email sudah terverifikasi. Silakan masuk.');
        }

        $this->otpService->issue($user);

        return back()->with('success', 'Kode OTP baru telah dikirim ke email Anda.');
    }

    protected function pendingUser(Request $request): ?User
    {
        $userId = $request->session()->get('pending_verification_user_id');
        if (! $userId) {
            return null;
        }

        return User::query()->find($userId);
    }
}
