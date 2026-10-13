<?php

namespace App\Models;

use App\Enums\FacilityRequestStatus;
use App\Enums\RoomActivityType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomBorrowing extends Model
{
    protected $fillable = [
        'room_id',
        'campus_id',
        'borrower_name',
        'borrower_email',
        'borrower_phone',
        'activity_type',
        'purpose',
        'documentation_url',
        'uses_budget',
        'budget_type',
        'participant_internal',
        'participant_external',
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
        'approval_number',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'status' => FacilityRequestStatus::class,
            'activity_type' => RoomActivityType::class,
            'uses_budget' => 'boolean',
            'participant_internal' => 'boolean',
            'participant_external' => 'boolean',
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

    public function participantText(): string
    {
        $kinds = [];
        if ($this->participant_internal) {
            $kinds[] = 'Internal';
        }
        if ($this->participant_external) {
            $kinds[] = 'External';
        }

        return $kinds === [] ? '—' : implode(' dan ', $kinds);
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
