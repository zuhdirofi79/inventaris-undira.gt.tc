<?php

namespace App\Services;

use App\Models\Campus;
use App\Models\OrgUnit;
use App\Models\Room;
use Illuminate\Validation\ValidationException;

/**
 * Generates location codes: {CAMPUS_CODE}-{UNIT_CODE}-{ROOM_CODE}
 * e.g. CBB-PPPA-A-101
 *
 * Room code (A-101) is NOT the same as generated location code.
 */
class LocationCodeGeneratorService
{
    public function generate(?Campus $campus, ?OrgUnit $orgUnit, ?Room $room): string
    {
        $campusCode = strtoupper(trim((string) ($campus?->code ?? '')));
        $unitCode = strtoupper(trim((string) ($orgUnit?->code ?? '')));
        $roomCode = strtoupper(trim((string) ($room?->code ?? '')));

        $missing = [];
        if ($campusCode === '') {
            $missing[] = 'kode kampus';
        }
        if ($unitCode === '') {
            $missing[] = 'kode unit/divisi';
        }
        if ($roomCode === '') {
            $missing[] = 'kode ruangan';
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'generated_location_code' => 'Kode lokasi tidak dapat dibuat karena master berikut belum memiliki kode: '.implode(', ', $missing).'.',
            ]);
        }

        return "{$campusCode}-{$unitCode}-{$roomCode}";
    }

    /**
     * Preview helper that returns null instead of throwing (for UI).
     */
    public function tryGenerate(?Campus $campus, ?OrgUnit $orgUnit, ?Room $room): ?string
    {
        try {
            return $this->generate($campus, $orgUnit, $room);
        } catch (ValidationException) {
            return null;
        }
    }
}
