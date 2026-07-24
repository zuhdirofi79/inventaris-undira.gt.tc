<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogService;
use App\Support\UserAgentParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuditLogService $auditLogService,
    ) {}

    public function showLoginForm(): View
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $credentials = $request->only('email', 'password');
        $remember = $request->boolean('remember');

        if (! Auth::attempt($credentials, $remember)) {
            return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
        }

        $user = Auth::user();

        // Super Admin: bypass OTP / email_verified_at entirely.
        if ($user->requiresEmailVerification() && ! $user->hasVerifiedEmailAddress()) {
            Auth::logout();
            $request->session()->put('pending_verification_user_id', $user->id);

            return redirect()
                ->route('verification.notice')
                ->with('warning', 'Email belum diverifikasi. Masukkan kode OTP yang dikirim ke email Anda.');
        }

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun Anda tidak aktif.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $ua = $request->userAgent();
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'last_login_user_agent' => $ua,
            'last_login_device' => UserAgentParser::device($ua),
        ])->save();

        $this->auditLogService->log($user, AuditAction::Login, $user, null, [
            'ip' => $request->ip(),
            'browser' => UserAgentParser::browser($ua),
            'device' => UserAgentParser::device($ua),
        ], 'User login');

        if ($user->must_change_password) {
            return redirect()
                ->route('password.force.edit')
                ->with('warning', 'Anda wajib mengganti password sebelum melanjutkan.');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $this->auditLogService->log($user, AuditAction::Logout, $user, null, null, 'User logout');
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
