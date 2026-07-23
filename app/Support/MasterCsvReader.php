<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Read master-data CSV/Excel uploads into associative row arrays.
 */
final class MasterCsvReader
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function associativeRows(UploadedFile|string $file): array
    {
        $sheets = Excel::toArray(new class {}, $file);
        $sheet = $sheets[0] ?? [];
        if ($sheet === []) {
            return [];
        }

        $header = array_map(
            static fn ($h) => strtolower(trim((string) $h)),
            array_shift($sheet) ?? [],
        );

        $rows = [];
        foreach ($sheet as $row) {
            $data = [];
            foreach ($header as $i => $key) {
                if ($key === '') {
                    continue;
                }
                $data[$key] = $row[$i] ?? null;
            }
            if ($data !== []) {
                $rows[] = $data;
            }
        }

        return $rows;
    }

    public static function parseActive(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
