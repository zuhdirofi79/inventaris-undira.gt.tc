<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Builds a unified chronological timeline for an inventory item
 * (create → mutations → maintenance → borrowings → condition/status audits).
 */
class InventoryHistoryService
{
    /**
     * @return list<array{at: Carbon, date_label: string, time_label: string, title: string, detail: ?string, actor: ?string, icon: string, tone: string}>
     */
    public function timeline(InventoryItem $item): array
    {
        $tz = config('app.timezone', 'Asia/Jakarta');
        $events = collect();

        if ($item->created_at) {
            $events->push([
                'at' => $item->created_at->copy(),
                'title' => 'Ditambahkan',
                'detail' => 'Inventaris dicatat ke sistem.',
                'actor' => $item->creator?->name,
                'icon' => 'bi-plus-circle',
                'tone' => 'success',
            ]);
        }

        foreach ($item->mutations as $mutation) {
            $events->push([
                'at' => $mutation->created_at->copy(),
                'title' => 'Dipindah (Mutasi)',
                'detail' => trim(
                    ($mutation->fromRoom?->full_location ?? '—')
                    .' → '
                    .($mutation->toRoom?->full_location ?? '—')
                ).($mutation->reason ? ' · '.$mutation->reason : ''),
                'actor' => $mutation->movedBy?->name,
                'icon' => 'bi-arrow-left-right',
                'tone' => 'info',
            ]);
        }

        foreach ($item->maintenances as $maint) {
            $when = $maint->service_date
                ? Carbon::parse($maint->service_date)->timezone($tz)->startOfDay()
                : ($maint->created_at?->copy() ?? now());
            $events->push([
                'at' => $when,
                'title' => 'Pemeliharaan'.($maint->maintenance_number ? ' '.$maint->maintenance_number : ''),
                'detail' => trim(
                    ($maint->maintenance_type?->label() ?? '')
                    .' · '.($maint->status?->label() ?? '')
                    .($maint->technician ? ' · '.$maint->technician : '')
                    .($maint->vendor?->name ? ' · '.$maint->vendor->name : '')
                    .($maint->result ? ' · '.$maint->result->label() : '')
                    .($maint->notes ? ' · '.$maint->notes : '')
                ) ?: null,
                'actor' => $maint->creator?->name,
                'icon' => 'bi-wrench-adjustable',
                'tone' => 'warning',
            ]);
        }

        foreach ($item->borrowings as $borrowing) {
            $when = $borrowing->borrow_date
                ? Carbon::parse($borrowing->borrow_date)->timezone($tz)->startOfDay()
                : ($borrowing->created_at?->copy() ?? now());
            $events->push([
                'at' => $when,
                'title' => 'Peminjaman',
                'detail' => trim(
                    ($borrowing->borrower_name ?? '')
                    .' · '
                    .($borrowing->status?->label() ?? '')
                ),
                'actor' => $borrowing->approvedBy?->name,
                'icon' => 'bi-hand-index',
                'tone' => 'secondary',
            ]);
        }

        $audits = AuditLog::query()
            ->where('auditable_type', $item->getMorphClass())
            ->where('auditable_id', $item->getKey())
            ->with('user')
            ->latest()
            ->limit(40)
            ->get();

        foreach ($audits as $log) {
            $action = $log->action instanceof AuditAction
                ? $log->action
                : AuditAction::tryFrom((string) $log->action);

            if ($action === AuditAction::Create) {
                continue; // already covered by created_at
            }

            $detail = $this->auditDetail($log);
            if ($detail === null && $action === AuditAction::Update) {
                continue;
            }

            $events->push([
                'at' => $log->created_at->copy(),
                'title' => $action?->label() ?? (string) $log->action,
                'detail' => $detail ?? $log->description,
                'actor' => $log->user?->name,
                'icon' => match ($action) {
                    AuditAction::Delete, AuditAction::ForceDelete => 'bi-trash',
                    AuditAction::Restore => 'bi-arrow-counterclockwise',
                    AuditAction::GenerateQr, AuditAction::ScanQr => 'bi-qr-code',
                    default => 'bi-pencil-square',
                },
                'tone' => match ($action) {
                    AuditAction::Delete, AuditAction::ForceDelete => 'danger',
                    AuditAction::Restore => 'success',
                    default => 'primary',
                },
            ]);
        }

        return $events
            ->sortByDesc(fn (array $e) => $e['at']->timestamp)
            ->values()
            ->map(function (array $e) use ($tz) {
                /** @var Carbon $at */
                $at = $e['at']->timezone($tz);

                return [
                    'at' => $at,
                    'date_label' => $at->translatedFormat('d F Y'),
                    'time_label' => $at->format('H:i').' WIB',
                    'title' => $e['title'],
                    'detail' => $e['detail'],
                    'actor' => $e['actor'],
                    'icon' => $e['icon'],
                    'tone' => $e['tone'],
                ];
            })
            ->all();
    }

    protected function auditDetail(AuditLog $log): ?string
    {
        $old = is_array($log->old_values) ? $log->old_values : [];
        $new = is_array($log->new_values) ? $log->new_values : [];
        $parts = [];

        foreach (['condition' => 'Kondisi', 'status' => 'Status', 'name' => 'Nama'] as $field => $label) {
            if (! array_key_exists($field, $new)) {
                continue;
            }
            $from = $old[$field] ?? null;
            $to = $new[$field] ?? null;
            if ((string) $from === (string) $to) {
                continue;
            }
            $parts[] = "{$label}: ".($from ?: '—').' → '.($to ?: '—');
        }

        $oldPicId = $old['pic_id'] ?? null;
        $newPicId = $new['pic_id'] ?? null;
        if (array_key_exists('pic_id', $new) && (string) $oldPicId !== (string) $newPicId) {
            $fromPic = $old['pic_name']
                ?? (\App\Models\User::query()->whereKey($oldPicId)->value('name'))
                ?? 'Belum ditentukan';
            $toPic = $new['pic_name']
                ?? (\App\Models\User::query()->whereKey($newPicId)->value('name'))
                ?? 'Belum ditentukan';
            if (! $oldPicId) {
                $fromPic = 'Belum ditentukan';
            }
            if (! $newPicId) {
                $toPic = 'Belum ditentukan';
            }
            $parts[] = "PIC diubah: Dari {$fromPic} Menjadi {$toPic}";
        }

        if ($parts === [] && filled($log->description)) {
            return $log->description;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
