<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

class AuditLogService
{
    public function log(
        ?User $user,
        AuditAction|string $action,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
    ): AuditLog {
        $actionValue = $action instanceof AuditAction ? $action->value : $action;

        return AuditLog::query()->create([
            'user_id' => $user?->id,
            'action' => $actionValue,
            'auditable_type' => $auditable ? $auditable->getMorphClass() : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'description' => $description,
        ]);
    }
}
