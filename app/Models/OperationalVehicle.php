<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationalVehicle extends Model
{
    protected $fillable = [
        'name',
        'plate_number',
        'category',
        'passenger_capacity',
        'notes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'passenger_capacity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function borrowings(): HasMany
    {
        return $this->hasMany(VehicleBorrowing::class, 'assigned_vehicle_id');
    }

    public function label(): string
    {
        return trim($this->name.' · '.$this->plate_number.($this->category ? ' · '.$this->category : ''));
    }
}
