<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "Komposisi Sub Kategori" — sub-category counts for the active report filter.
 */
class InventorySubCategoryRecapExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array{rows?: list<array{label: string, count: int}>, kind_count?: int, total_units?: int}  $recap
     */
    public function __construct(
        protected array $recap = [],
    ) {}

    public function title(): string
    {
        return 'Komposisi Sub Kategori';
    }

    public function headings(): array
    {
        return ['Sub Kategori', 'Jumlah'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->recap['rows'] ?? [] as $row) {
            $rows[] = [
                $row['label'] ?? 'Tanpa Sub Kategori',
                (int) ($row['count'] ?? 0).' Unit',
            ];
        }

        if ($rows === []) {
            $rows[] = ['Tidak ada data', '0 Unit'];
        }

        $rows[] = ['', ''];
        $rows[] = [
            'TOTAL SUB KATEGORI : '.((int) ($this->recap['kind_count'] ?? count($this->recap['rows'] ?? []))).' Jenis',
            'TOTAL INVENTARIS : '.((int) ($this->recap['total_units'] ?? 0)).' Unit',
        ];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4A4A4A'],
                ],
            ],
        ];
    }
}
