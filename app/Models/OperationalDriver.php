<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalDriver extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(VehicleBorrowing::class, 'assigned_driver_id');
    }

    /**
     * Prefer linked user name for display; fall back to stored historical name.
     */
    public function displayName(): string
    {
        if ($this->relationLoaded('user') && $this->user) {
            return (string) $this->user->name;
        }

        if ($this->user_id) {
            $this->loadMissing('user');
            if ($this->user) {
                return (string) $this->user->name;
            }
        }

        return (string) $this->name;
    }

    /**
     * Prefer linked user phone; fall back to stored historical phone.
     */
    public function displayPhone(): ?string
    {
        if ($this->relationLoaded('user') && $this->user) {
            $phone = trim((string) ($this->user->phone ?? ''));

            return $phone !== '' ? $phone : null;
        }

        if ($this->user_id) {
            $this->loadMissing('user');
            if ($this->user) {
                $phone = trim((string) ($this->user->phone ?? ''));

                return $phone !== '' ? $phone : null;
            }
        }

        $phone = trim((string) ($this->phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    public function label(): string
    {
        $name = $this->displayName();
        $phone = $this->displayPhone();

        return $phone !== null ? $name.' · '.$phone : $name;
    }

    public function isLinkedToUser(): bool
    {
        return $this->user_id !== null;
    }

    public function accountIsActive(): bool
    {
        $this->loadMissing('user');

        return (bool) ($this->user?->is_active ?? $this->is_active);
    }
}
