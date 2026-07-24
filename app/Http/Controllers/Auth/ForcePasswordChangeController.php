<?php

namespace App\Http\Controllers\Auth;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ForcePasswordChangeController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function edit(): View
    {
        return view('auth.force-password-change');
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'password.required' => 'Password baru wajib diisi.',
            'password.confirmed' => 'Konfirmasi password tidak cocok.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->getAuthPassword())) {
            return back()->withErrors(['current_password' => 'Password saat ini tidak sesuai.']);
        }

        $user->password = $data['password'];
        $user->save();
        $user->markPasswordChangedByUser();

        $this->audit->log(
            $user,
            AuditAction::Update,
            $user,
            null,
            ['must_change_password' => false],
            'USER_PASSWORD_CHANGED_BY_SELF',
            'User mengganti password wajib setelah reset/login.'
        );

        return redirect()
            ->route('dashboard')
            ->with('success', 'Password berhasil diganti.');
    }
}
