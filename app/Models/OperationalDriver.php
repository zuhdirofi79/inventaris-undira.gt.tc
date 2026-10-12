<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalDriver extends Model
{
    protected $fillable = [
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

    public function borrowings(): HasMany
    {
        return $this->hasMany(VehicleBorrowing::class, 'assigned_driver_id');
    }

    public function label(): string
    {
        $phone = trim((string) $this->phone);

        return $phone !== '' ? $this->name.' · '.$phone : $this->name;
    }
}
