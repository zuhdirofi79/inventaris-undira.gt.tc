<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\ResetUserPasswordRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\PrimarySuperAdminService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('q', ''));
        $roleFilter = (string) $request->query('role', '');
        $statusFilter = (string) $request->query('status', '');
        $sort = (string) $request->query('sort', '');
        if (! in_array($sort, ['name_asc', 'name_desc', 'newest', 'oldest', 'inactive_90', 'never_login'], true)) {
            $sort = '';
        }

        $users = User::query()
            ->with('roles')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%');
                });
            })
            ->when($roleFilter !== '', function ($query) use ($roleFilter) {
                $query->role($roleFilter);
            })
            ->when($statusFilter === 'active', fn ($query) => $query->where('is_active', true))
            ->when($statusFilter === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($sort === 'inactive_90', fn ($query) => $query
                ->whereNotNull('last_login_at')
                ->where('last_login_at', '<', now()->subDays(90)))
            ->when($sort === 'never_login', fn ($query) => $query->whereNull('last_login_at'));

        match ($sort) {
            'name_desc' => $users->orderByDesc('name')->orderByDesc('id'),
            'newest' => $users->orderByDesc('created_at')->orderByDesc('id'),
            'oldest' => $users->orderBy('created_at')->orderBy('id'),
            'inactive_90' => $users->orderBy('last_login_at')->orderBy('id'),
            default => $users->orderBy('name')->orderBy('id'),
        };

        $users = $users->get();

        $roles = Role::query()->orderBy('name')->get();

        return view('admin.users.index', compact('users', 'roles', 'search', 'roleFilter', 'statusFilter', 'sort'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $roles = $data['roles'];

        if (in_array('super-admin', $roles, true) && ! $actor->isPrimarySuperAdmin()) {
            throw new AuthorizationException('Hanya Primary Super Admin yang dapat membuat akun Super Admin.');
        }

        $user = DB::transaction(function () use ($data, $roles) {
            $user = new User([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => false,
                'email_verified_at' => now(),
            ]);
            $user->setDefaultPasswordValue($data['password']);
            $user->save();
            $user->syncRoles($roles);

            return $user;
        });

        $this->audit->log(
            $actor,
            AuditAction::Create,
            $user,
            null,
            [
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $roles,
                'is_active' => (bool) $user->is_active,
            ],
            in_array('super-admin', $roles, true) ? 'SUPER_ADMIN_CREATED' : 'USER_CREATED',
            'User dibuat oleh '.$actor->email
        );

        return back()->with('success', 'User berhasil ditambahkan.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();
        $oldRoles = $user->getRoleNames()->values()->all();
        $oldActive = (bool) $user->is_active;
        $requestedActive = (bool) ($data['is_active'] ?? false);
        $wantsRoleChange = array_key_exists('roles', $data);
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

            if ($wantsRoleChange && isset($data['roles']) && ! $user->isPrimarySuperAdmin()) {
                $user->syncRoles($data['roles']);
            }
        });

        $user->refresh();
        $newRoles = $user->getRoleNames()->values()->all();

        $description = 'USER_UPDATED';
        if ($oldRoles !== $newRoles) {
            $description = 'USER_ROLE_CHANGED';
            if (in_array('super-admin', $oldRoles, true) && ! in_array('super-admin', $newRoles, true)) {
                $description = 'SUPER_ADMIN_DEMOTED';
            } elseif ($user->isSuperAdmin()) {
                $description = 'SUPER_ADMIN_UPDATED';
            }
        } elseif ($oldActive && ! $user->is_active) {
            $description = in_array('super-admin', $oldRoles, true)
                ? 'SUPER_ADMIN_DEACTIVATED'
                : 'USER_DEACTIVATED';
        } elseif (! $oldActive && $user->is_active) {
            $description = 'USER_ACTIVATED';
        }

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $user,
            ['roles' => $oldRoles, 'is_active' => $oldActive, 'name' => $user->name],
            ['roles' => $newRoles, 'is_active' => (bool) $user->is_active],
            $description,
            'User diperbarui oleh '.$actor->email
        );

        return back()->with('success', 'User berhasil diperbarui.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        $this->authorize('changeStatus', $user);

        if ($user->isPrimarySuperAdmin()) {
            $this->audit->log(
                $actor,
                AuditAction::Update,
                $user,
                ['is_active' => true],
                null,
                'PRIMARY_ADMIN_DEACTIVATION_BLOCKED',
                'Akun Primary Super Admin tidak dapat dinonaktifkan.'
            );
            abort(403, 'Akun Primary Super Admin tidak dapat dinonaktifkan.');
        }

        if ($actor->id === $user->id) {
            abort(403, 'Tidak dapat menonaktifkan akun sendiri.');
        }

        if ($user->isSuperAdmin() && ! $actor->isPrimarySuperAdmin()) {
            abort(403, 'Hanya Primary Super Admin yang dapat mengubah status Super Admin.');
        }

        $old = (bool) $user->is_active;
        $user->update(['is_active' => ! $old]);

        $this->audit->log(
            $actor,
            AuditAction::Update,
            $user,
            ['is_active' => $old],
            ['is_active' => (bool) $user->is_active],
            $user->is_active ? 'USER_ACTIVATED' : 'USER_DEACTIVATED',
            ($user->is_active ? 'User diaktifkan' : 'User dinonaktifkan').' oleh '.$actor->email
        );

        return back()->with('success', $user->is_active ? 'User berhasil diaktifkan.' : 'User berhasil dinonaktifkan.');
    }

    public function resetPassword(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $actor = $request->user();

        if ($user->isPrimarySuperAdmin() && ! $actor->isPrimarySuperAdmin()) {
            abort(403, 'Hanya Primary Super Admin yang dapat mereset password akun utama.');
        }

        if ($user->isSuperAdmin() && ! $actor->isPrimarySuperAdmin() && $actor->id !== $user->id) {
            abort(403, 'Hanya Primary Super Admin yang dapat mereset password Super Admin lain.');
        }

        $mustChange = (bool) ($data['must_change_password'] ?? false);

        $user->password = $data['password'];
        $user->must_change_password = $mustChange;
        $user->password_changed_at = null;
        $user->setDefaultPasswordValue($data['password']);
        $user->save();

        $this->audit->log(
            $actor,
            AuditAction::ResetPassword,
            $user,
            null,
            [
                'must_change_password' => $mustChange,
                'target_email' => $user->email,
            ],
            'USER_PASSWORD_RESET',
            'Password direset oleh '.$actor->email.($mustChange ? ' (wajib ganti saat login)' : '')
        );

        return back()->with('success', 'Password user berhasil direset.');
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
            $wasSuper ? 'SUPER_ADMIN_DELETED' : 'USER_DELETED',
            'User soft-deleted oleh '.$actor->email
        );

        return back()->with('success', 'User berhasil dihapus.');
    }
}
