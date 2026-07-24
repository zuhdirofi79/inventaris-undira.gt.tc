<?php

namespace App\Models;

use App\Services\PrimarySuperAdminService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'name',
    'email',
    'password',
    'phone',
    'identity_number',
    'is_active',
    'email_verified_at',
    'last_login_at',
    'last_login_ip',
    'last_login_user_agent',
    'last_login_device',
    'must_change_password',
    'password_changed_at',
    'default_password',
])]
#[Hidden(['password', 'remember_token', 'default_password'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
            'password_changed_at' => 'datetime',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    public function isPrimarySuperAdmin(): bool
    {
        return app(PrimarySuperAdminService::class)->isPrimary($this);
    }

    /**
     * Super Admin bypasses email OTP verification.
     */
    public function requiresEmailVerification(): bool
    {
        return ! $this->isSuperAdmin();
    }

    public function hasVerifiedEmailAddress(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function inventoryAsPic(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'pic_id');
    }

    public function setDefaultPasswordValue(?string $plainPassword): void
    {
        $this->default_password = filled($plainPassword)
            ? Crypt::encryptString($plainPassword)
            : null;
    }

    public function defaultPasswordDisplay(): string
    {
        if ($this->password_changed_at !== null || blank($this->default_password)) {
            return 'Sudah Diubah';
        }

        try {
            return Crypt::decryptString($this->default_password);
        } catch (\Throwable) {
            return 'Sudah Diubah';
        }
    }

    public function markPasswordChangedByUser(): void
    {
        $this->forceFill([
            'must_change_password' => false,
            'password_changed_at' => now(),
            'default_password' => null,
        ])->save();
    }
}
