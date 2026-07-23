<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceStatusHistory extends Model
{
    protected $fillable = [
        'maintenance_id',
        'from_status',
        'to_status',
        'note',
        'changed_by',
    ];

    protected function casts(): array
    {
        return [
            'from_status' => MaintenanceStatus::class,
            'to_status' => MaintenanceStatus::class,
        ];
    }

    public function maintenance(): BelongsTo
    {
        return $this->belongsTo(Maintenance::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
