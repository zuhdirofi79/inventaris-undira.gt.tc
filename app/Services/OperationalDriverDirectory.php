<?php

namespace App\Services;

use App\Models\OperationalDriver;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps operational_drivers rows in sync with User accounts that have the driver role.
 * Assignment FK stays on operational_drivers for history compatibility.
 */
class OperationalDriverDirectory
{
    public const ROLE = 'driver';

    /**
     * Ensure every active/inactive user with role driver has a linked OperationalDriver row.
     * Name/phone mirror the user account. Legacy rows without user_id are left untouched.
     *
     * @return Collection<int, OperationalDriver>
     */
    public function syncFromUsers(): Collection
    {
        $users = User::query()
            ->role(self::ROLE)
            ->orderBy('name')
            ->get();

        return DB::transaction(function () use ($users) {
            $synced = collect();

            foreach ($users as $user) {
                $synced->push($this->ensureForUser($user));
            }

            // Soft-disable roster rows whose user lost the driver role or was deleted.
            OperationalDriver::query()
                ->whereNotNull('user_id')
                ->whereNotIn('user_id', $users->modelKeys())
                ->update(['is_active' => false]);

            return $synced->sortBy(fn (OperationalDriver $d) => mb_strtolower($d->displayName()))->values();
        });
    }

    public function ensureForUser(User $user, ?string $phoneOverride = null): OperationalDriver
    {
        if ($phoneOverride !== null) {
            $phone = trim($phoneOverride);
            $user->forceFill(['phone' => $phone !== '' ? $phone : null])->save();
            $user->refresh();
        }

        $driver = OperationalDriver::query()->firstOrNew(['user_id' => $user->id]);
        $driver->forceFill([
            'name' => $user->name,
            'phone' => filled($user->phone) ? $user->phone : null,
            'is_active' => (bool) $user->is_active && $user->hasRole(self::ROLE),
        ])->save();

        $driver->setRelation('user', $user);

        return $driver;
    }

    /**
     * Drivers that may be selected for a new fleet assignment.
     *
     * @return Collection<int, OperationalDriver>
     */
    public function assignable(): Collection
    {
        $this->syncFromUsers();

        return OperationalDriver::query()
            ->with('user')
            ->where('is_active', true)
            ->whereNotNull('user_id')
            ->whereHas('user', function ($q) {
                $q->where('is_active', true)->role(self::ROLE);
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * All driver-linked roster rows for the master page (includes inactive accounts).
     *
     * @return Collection<int, OperationalDriver>
     */
    public function masterList(): Collection
    {
        return $this->syncFromUsers()->loadMissing('user');
    }

    public function isAssignable(OperationalDriver $driver): bool
    {
        $driver->loadMissing('user');
        $user = $driver->user;

        if ($user instanceof User) {
            return $driver->is_active
                && $user->is_active
                && $user->hasRole(self::ROLE);
        }

        // Legacy roster rows (pre user-link) remain assignable while still marked active.
        return $driver->user_id === null && $driver->is_active;
    }
}
