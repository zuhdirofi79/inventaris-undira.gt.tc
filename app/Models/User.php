<?php

namespace App\Models;

use App\Services\PrimarySuperAdminService;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'phone', 'identity_number', 'is_active', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
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
}
