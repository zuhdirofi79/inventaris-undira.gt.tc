<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Services\AuditLogService;
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

        if (! $user->is_active) {
            Auth::logout();

            return back()->withErrors(['email' => 'Akun Anda tidak aktif.'])->onlyInput('email');
        }

        $request->session()->regenerate();

        $this->auditLogService->log($user, AuditAction::Login, $user, null, null, 'User login');

        return redirect()->intended(route('campuses.index'));
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
