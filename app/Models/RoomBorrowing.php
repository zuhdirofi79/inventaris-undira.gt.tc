<?php

namespace App\Models;

use App\Enums\FacilityRequestStatus;
use App\Models\Concerns\TracksApprovalCancellation;
use App\Models\Concerns\TracksTrashMeta;
use App\Support\ApprovalCancellationWindow;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoomBorrowing extends Model
{
    use SoftDeletes;
    use TracksApprovalCancellation;
    use TracksTrashMeta;

    protected $fillable = [
        'room_id',
        'campus_id',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'borrower_affiliation',
        'activity_type',
        'purpose',
        'documentation_url',
        'uses_budget',
        'budget_type',
        'participant_internal',
        'participant_internal_count',
        'participant_external',
        'participant_external_count',
        'approval_document_path',
        'approval_document_original_name',
        'approval_document_size',
        'layout_document_path',
        'layout_document_original_name',
        'layout_document_size',
        'participant_document_path',
        'participant_document_original_name',
        'participant_document_size',
        'starts_at',
        'ends_at',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_at',
        'rejection_reason',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason',
        'approval_number',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'status' => FacilityRequestStatus::class,
            'uses_budget' => 'boolean',
            'participant_internal' => 'boolean',
            'participant_external' => 'boolean',
            'participant_internal_count' => 'integer',
            'participant_external_count' => 'integer',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function activityTypeOption(): BelongsTo
    {
        return $this->belongsTo(RoomActivityType::class, 'activity_type', 'code');
    }

    public function activityTypeLabel(): string
    {
        if ($this->relationLoaded('activityTypeOption') && $this->activityTypeOption) {
            return $this->activityTypeOption->name;
        }

        return RoomActivityType::labelFor(
            is_string($this->activity_type) ? $this->activity_type : null
        );
    }

    public function budgetTypeOption(): BelongsTo
    {
        return $this->belongsTo(RoomBudgetType::class, 'budget_type', 'code');
    }

    public function budgetDisplayLabel(): string
    {
        if (! $this->uses_budget) {
            return 'Tidak menggunakan anggaran';
        }

        if ($this->relationLoaded('budgetTypeOption') && $this->budgetTypeOption) {
            return $this->budgetTypeOption->name;
        }

        return RoomBudgetType::labelFor(
            is_string($this->budget_type) ? $this->budget_type : null
        );
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function displayLabel(): string
    {
        if ($this->status === FacilityRequestStatus::Disetujui && $this->hasEnded()) {
            return 'Selesai';
        }

        return $this->status?->label() ?? '—';
    }

    public function displayColor(): string
    {
        if ($this->status === FacilityRequestStatus::Disetujui && $this->hasEnded()) {
            return 'secondary';
        }

        return $this->status?->badgeColor() ?? 'secondary';
    }

    public function hasEnded(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->ends_at !== null && $this->ends_at->lt($moment);
    }

    public function hasStarted(?CarbonInterface $moment = null): bool
    {
        $moment ??= now();

        return $this->starts_at !== null && $this->starts_at->lte($moment);
    }

    public function canCancelApproval(?CarbonInterface $moment = null): bool
    {
        return $this->status === FacilityRequestStatus::Disetujui
            && $this->cancelled_at === null
            && $this->isWithinApprovalCancelWindow($moment)
            && ! $this->hasStarted($moment);
    }

    public function approvalCancelBlockReason(?CarbonInterface $moment = null): ?string
    {
        if ($this->status === FacilityRequestStatus::PersetujuanDibatalkan || $this->cancelled_at !== null) {
            return 'Persetujuan sudah dibatalkan.';
        }

        if ($this->status !== FacilityRequestStatus::Disetujui) {
            return 'Hanya peminjaman yang berstatus Disetujui yang dapat dibatalkan.';
        }

        if ($this->hasStarted($moment)) {
            return 'Jadwal ruangan sudah dimulai atau sedang berlangsung; pembatalan tidak diizinkan.';
        }

        if (! $this->isWithinApprovalCancelWindow($moment)) {
            return ApprovalCancellationWindow::remainingLabel($this->approved_at, $moment);
        }

        return null;
    }

    public function participantInternalCount(): int
    {
        return max(0, (int) ($this->participant_internal_count ?? 0));
    }

    public function participantExternalCount(): int
    {
        return max(0, (int) ($this->participant_external_count ?? 0));
    }

    public function totalParticipantCount(): int
    {
        $internal = $this->participant_internal ? $this->participantInternalCount() : 0;
        $external = $this->participant_external ? $this->participantExternalCount() : 0;

        return $internal + $external;
    }

    public function participantText(): string
    {
        $kinds = [];
        if ($this->participant_internal) {
            $kinds[] = 'Internal '.$this->participantInternalCount();
        }
        if ($this->participant_external) {
            $kinds[] = 'External '.$this->participantExternalCount();
        }

        if ($kinds === []) {
            return '—';
        }

        return implode(', ', $kinds).' (Total '.$this->totalParticipantCount().')';
    }

    /**
     * @return array{path: string, name: string, size: int|null}|null
     */
    public function documentMeta(string $kind): ?array
    {
        $columns = [
            'approval' => ['approval_document_path', 'approval_document_original_name', 'approval_document_size'],
            'layout' => ['layout_document_path', 'layout_document_original_name', 'layout_document_size'],
            'participants' => ['participant_document_path', 'participant_document_original_name', 'participant_document_size'],
        ][$kind] ?? null;

        if ($columns === null) {
            return null;
        }

        $path = $this->{$columns[0]};
        if (! is_string($path) || $path === '') {
            return null;
        }

        $name = $this->{$columns[1]};
        $size = $this->{$columns[2]};

        return [
            'path' => $path,
            'name' => is_string($name) && $name !== '' ? $name : basename($path),
            'size' => is_numeric($size) ? (int) $size : null,
        ];
    }

    public function scheduleText(): string
    {
        $start = $this->starts_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i');
        $end = $this->ends_at?->timezone((string) config('app.timezone'))->format('d/m/Y H:i');

        return trim(($start ?: '—').' – '.($end ?: '—'));
    }
}
