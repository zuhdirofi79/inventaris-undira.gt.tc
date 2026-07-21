<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PrimarySuperAdminService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected PrimarySuperAdminService $primaryAdmin,
    ) {
        $this->primaryAdmin->ensureBound();
    }

    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('roles')->orderBy('name')->paginate(20);
        $roles = Role::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $actor = $request->user();
        $roles = $data['roles'];

        if (in_array('super-admin', $roles, true) && ! $actor->isPrimarySuperAdmin()) {
            throw new AuthorizationException('Hanya Primary Super Admin yang dapat membuat akun Super Admin.');
        }

        $user = DB::transaction(function () use ($data, $roles, $request) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            $user->syncRoles($roles);

            return $user;
        });

        $this->audit->log(
            $actor,
            AuditAction::Create,
            $user,
            null,
            ['roles' => $roles, 'is_active' => (bool) $user->is_active],
            in_array('super-admin', $roles, true) ? 'SUPER_ADMIN_CREATED' : 'USER_CREATED'
        );

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,name'],
        ]);

        $actor = $request->user();
        $oldRoles = $user->getRoleNames()->values()->all();
        $oldActive = (bool) $user->is_active;
        $requestedActive = $request->boolean('is_active');
        $wantsRoleChange = $request->has('roles');
        $wantsDeactivate = $oldActive && ! $requestedActive;
        $wantsStatusChange = $requestedActive !== $oldActive;

        if ($user->isPrimarySuperAdmin()) {
            if ($wantsRoleChange) {
                $incoming = collect($data['roles'])->sort()->values()->all();
                $current = collect($oldRoles)->sort()->values()->all();
                if ($incoming !== $current) {
                    $this->audit->log(
                        $actor,
                        AuditAction::Update,
                        $user,
                        ['roles' => $oldRoles, 'is_active' => $oldActive],
                        null,
                        'PRIMARY_ADMIN_DEMOTION_BLOCKED',
                        'Akun Primary Super Admin tidak dapat dihapus, dinonaktifkan, atau diturunkan hak aksesnya.'
                    );
                    abort(403, 'Akun Primary Super Admin tidak dapat dihapus, dinonaktifkan, atau diturunkan hak aksesnya.');
                }
                unset($data['roles']);
                $wantsRoleChange = false;
            }

            if ($wantsDeactivate) {
                $this->audit->log(
                    $actor,
                    AuditAction::Update,
                    $user,
                    ['roles' => $oldRoles, 'is_active' => $oldActive],
                    null,
                    'PRIMARY_ADMIN_DEACTIVATION_BLOCKED',
                    'Akun Primary Super Admin tidak dapat dihapus, dinonaktifkan, atau diturunkan hak aksesnya.'
                );
                abort(403, 'Akun Primary Super Admin tidak dapat dihapus, dinonaktifkan, atau diturunkan hak aksesnya.');
            }

            $data['is_active'] = true;
        } else {
            if ($wantsRoleChange) {
                $this->authorize('changeRole', $user);

                $newRoles = $data['roles'];
                $touchesSuper = $user->isSuperAdmin()
                    || in_array('super-admin', $newRoles, true);

                if ($touchesSuper && ! $actor->isPrimarySuperAdmin()) {
                    $this->audit->log(
                        $actor,
                        AuditAction::Update,
                        $user,
                        ['roles' => $oldRoles],
                        ['roles' => $newRoles],
                        'SUPER_ADMIN_MANAGEMENT_BLOCKED',
                        'Hanya Primary Super Admin yang dapat mengelola atau menghapus akun Super Admin lainnya.'
                    );
                    abort(403, 'Hanya Primary Super Admin yang dapat mengelola atau menghapus akun Super Admin lainnya.');
                }
            }

            if ($wantsStatusChange) {
                $this->authorize('changeStatus', $user);
            }

            $data['is_active'] = $requestedActive;
        }

        DB::transaction(function () use ($user, $data, $wantsRoleChange) {
            $payload = collect($data)->except(['password', 'roles'])->all();
            $user->update($payload);

            if (! empty($data['password'])) {
                $user->update(['password' => $data['password']]);
            }

            if ($wantsRoleChange && isset($data['roles']) && ! $user->isPrimarySuperAdmin()) {
                $user->syncRoles($data['roles']);
            }
        });

        $user->refresh();
        $newRoles = $user->getRoleNames()->values()->all();

        $description = 'USER_UPDATED';
        if (in_array('super-admin', $oldRoles, true) && ! in_array('super-admin', $newRoles, true)) {
            $description = 'SUPER_ADMIN_DEMOTED';
        } elseif ($user->isSuperAdmin() && $oldRoles !== $newRoles) {
            $description = 'SUPER_ADMIN_UPDATED';
        } elseif ($oldActive && ! $user->is_active && in_array('super-admin', $oldRoles, true)) {
            $description = 'SUPER_ADMIN_DEACTIVATED';
        }

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $user,
            ['roles' => $oldRoles, 'is_active' => $oldActive],
            ['roles' => $newRoles, 'is_active' => (bool) $user->is_active],
            $description
        );

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();

        if ($user->isPrimarySuperAdmin()) {
            $reason = $actor->id === $user->id
                ? 'Primary Super Admin tidak dapat menghapus atau menurunkan akses akunnya sendiri.'
                : 'Akun Primary Super Admin tidak dapat dihapus.';

            $this->audit->log(
                $actor,
                AuditAction::Delete,
                $user,
                ['email' => $user->email],
                null,
                'PRIMARY_ADMIN_DELETE_BLOCKED',
                $reason
            );
            abort(403, $reason);
        }

        if ($actor->id === $user->id) {
            abort(403, 'Tidak dapat menghapus akun sendiri.');
        }

        if ($user->isSuperAdmin() && ! $actor->isPrimarySuperAdmin()) {
            $this->audit->log(
                $actor,
                AuditAction::Delete,
                $user,
                ['email' => $user->email, 'roles' => $user->getRoleNames()->all()],
                null,
                'SUPER_ADMIN_MANAGEMENT_BLOCKED',
                'Hanya Primary Super Admin yang dapat menghapus akun Super Admin.'
            );
            abort(403, 'Hanya Primary Super Admin yang dapat menghapus akun Super Admin.');
        }

        $this->authorize('delete', $user);

        $snapshot = [
            'email' => $user->email,
            'roles' => $user->getRoleNames()->all(),
            'is_active' => (bool) $user->is_active,
        ];
        $wasSuper = $user->isSuperAdmin();

        $user->delete();

        $this->audit->log(
            $actor,
            AuditAction::Delete,
            null,
            $snapshot,
            null,
            $wasSuper ? 'SUPER_ADMIN_DELETED' : 'USER_DELETED'
        );

        return back()->with('success', 'User berhasil dihapus.');
    }
}
