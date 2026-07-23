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
        ?string $reason = null,
        ?int $affectedCount = null,
    ): AuditLog {
        $actionValue = $action instanceof AuditAction ? $action->value : $action;

        if ($affectedCount === null && is_array($newValues)) {
            $affectedCount = isset($newValues['affected_count'])
                ? (int) $newValues['affected_count']
                : (isset($newValues['imported']) ? (int) $newValues['imported'] : null);
        }

        if ($reason === null && is_array($newValues) && isset($newValues['reason'])) {
            $reason = (string) $newValues['reason'];
        }

        $meta = [];
        if ($user) {
            $roles = $user->relationLoaded('roles')
                ? $user->roles->pluck('name')->all()
                : $user->getRoleNames()->all();
            $meta['role'] = implode(', ', $roles) ?: null;
            $meta['user_name'] = $user->name;
        }

        if (is_array($newValues)) {
            $newValues = array_merge($meta, $newValues);
        } elseif ($meta !== []) {
            $newValues = $meta;
        }

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
            'reason' => $reason,
            'affected_count' => $affectedCount,
        ]);
    }
}
