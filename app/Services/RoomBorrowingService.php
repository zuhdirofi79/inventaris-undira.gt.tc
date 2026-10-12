<?php

namespace App\Services;

use App\Enums\FacilityRequestStatus;
use App\Mail\RoomBorrowingDecisionMail;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class RoomBorrowingService
{
    public const DISK = 'local';

    public function __construct(
        private readonly RoomOccupancy $occupancy,
        private readonly DecisionMailer $mailer,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(
        User $user,
        array $data,
        ?UploadedFile $approvalDocument = null,
        ?UploadedFile $layoutDocument = null,
        ?UploadedFile $participantDocument = null,
    ): RoomBorrowing {
        if (! $approvalDocument instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'approval_document' => 'Dokumen persetujuan pimpinan wajib diunggah.',
            ]);
        }

        $start = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['starts_at']);
        $end = Carbon::createFromFormat('Y-m-d\TH:i', (string) $data['ends_at']);
        $room = Room::query()->with('floor.building.campus')->find((int) $data['room_id']);

        if (! $room || ! $room->is_active || ! $room->floor?->is_active || ! $room->floor?->building?->is_active) {
            throw ValidationException::withMessages([
                'room_id' => 'Ruangan tidak tersedia.',
            ]);
        }

        if ($room->floor?->building?->campus && ! $room->floor->building->campus->is_active) {
            throw ValidationException::withMessages([
                'room_id' => 'Kampus ruangan ini tidak aktif.',
            ]);
        }

        if ($this->occupancy->overlaps($room->id, $start, $end)) {
            throw ValidationException::withMessages([
                'starts_at' => 'Ruangan sudah disetujui pada jadwal tersebut.',
            ]);
        }

        $usesBudget = (bool) ($data['uses_budget'] ?? false);
        $stored = [];

        try {
            return DB::transaction(function () use ($user, $data, $room, $start, $end, $usesBudget, $approvalDocument, $layoutDocument, $participantDocument, &$stored) {
                $row = RoomBorrowing::query()->create([
                    'room_id' => $room->id,
                    'campus_id' => $room->floor?->building?->campus_id,
                    'borrower_name' => $data['borrower_name'],
                    'borrower_email' => $data['borrower_email'] ?? null,
                    'borrower_phone' => $data['borrower_phone'],
                    'activity_type' => $data['activity_type'],
                    'purpose' => trim(strip_tags((string) $data['purpose'])),
                    'documentation_url' => $data['documentation_url'] ?? null,
                    'uses_budget' => $usesBudget,
                    'budget_type' => $usesBudget ? trim(strip_tags((string) ($data['budget_type'] ?? ''))) : null,
                    'participant_internal' => (bool) ($data['participant_internal'] ?? false),
                    'participant_external' => (bool) ($data['participant_external'] ?? false),
                    'starts_at' => $start,
                    'ends_at' => $end,
                    'status' => FacilityRequestStatus::MenungguPersetujuan,
                    'requested_by' => $user->id,
                ]);

                $updates = [];
                foreach ([
                    'approval' => $approvalDocument,
                    'layout' => $layoutDocument,
                    'participants' => $participantDocument,
                ] as $kind => $file) {
                    if (! $file instanceof UploadedFile) {
                        continue;
                    }

                    $meta = $this->storeFile($row, $kind, $file);
                    $stored[] = $meta['path'];
                    $updates = array_merge($updates, $meta['columns']);
                }

                $row->update($updates);

                return $row->fresh();
            });
        } catch (Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk(self::DISK)->delete($path);
            }

            if (! $exception instanceof ValidationException) {
                throw ValidationException::withMessages([
                    'approval_document' => 'Dokumen tidak dapat disimpan saat ini. Silakan coba kembali.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function decide(User $admin, RoomBorrowing $borrowing, array $data): RoomBorrowing
    {
        $saved = DB::transaction(function () use ($admin, $borrowing, $data) {
            $fresh = RoomBorrowing::query()->whereKey($borrowing->id)->lockForUpdate()->first();
            if (! $fresh || $fresh->status !== FacilityRequestStatus::MenungguPersetujuan) {
                throw ValidationException::withMessages([
                    'decision' => 'Pengajuan sudah diproses.',
                ]);
            }

            if ($data['decision'] === 'reject') {
                $fresh->forceFill([
                    'status' => FacilityRequestStatus::Ditolak,
                    'approved_by' => $admin->id,
                    'rejected_at' => now(),
                    'rejection_reason' => trim((string) $data['rejection_reason']),
                    'approval_number' => $this->nextNumber(),
                ])->save();

                return $fresh->fresh(['room.floor.building.campus', 'approvedBy']);
            }

            if ($this->occupancy->overlaps($fresh->room_id, $fresh->starts_at, $fresh->ends_at, $fresh->id)) {
                throw ValidationException::withMessages([
                    'decision' => 'Ruangan sudah disetujui pada jadwal tersebut.',
                ]);
            }

            $fresh->forceFill([
                'status' => FacilityRequestStatus::Disetujui,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => null,
                'approval_number' => $this->nextNumber(),
            ])->save();

            return $fresh->fresh(['room.floor.building.campus', 'room.inventoryItems', 'approvedBy']);
        });

        $this->mailer->send(
            $saved->borrower_email,
            new RoomBorrowingDecisionMail($saved),
            'Email peminjaman ruangan gagal dikirim.',
            $saved->id,
        );

        return $saved;
    }

    /**
     * @return array{path: string, columns: array<string, mixed>}
     */
    private function storeFile(RoomBorrowing $borrowing, string $kind, UploadedFile $file): array
    {
        $prefix = [
            'approval' => 'approval_document',
            'layout' => 'layout_document',
            'participants' => 'participant_document',
        ][$kind] ?? null;

        if ($prefix === null) {
            throw new RuntimeException('Jenis dokumen ruangan tidak dikenal.');
        }

        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('room-borrowings/'.$borrowing->id, $filename, self::DISK);

        if ($path === false || $path === '') {
            throw new RuntimeException('Gagal menyimpan dokumen peminjaman ruangan.');
        }

        $original = trim(str_replace(['\\', '/'], '', $file->getClientOriginalName()));
        if ($original === '') {
            $original = $filename;
        }

        return [
            'path' => $path,
            'columns' => [
                $prefix.'_path' => $path,
                $prefix.'_original_name' => mb_substr($original, 0, 255),
                $prefix.'_size' => (int) $file->getSize(),
            ],
        ];
    }

    private function nextNumber(): string
    {
        do {
            $number = 'RNG-'.now()->format('Ymd-His').'-'.random_int(100, 999);
        } while (RoomBorrowing::query()->where('approval_number', $number)->exists());

        return $number;
    }
}
