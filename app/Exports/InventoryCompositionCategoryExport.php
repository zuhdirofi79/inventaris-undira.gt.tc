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
 * Sheet "Komposisi Kategori".
 */
class InventoryCompositionCategoryExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array{rows?: list<array{label: string, count: int, percent?: int}>, total_units?: int}  $category
     */
    public function __construct(
        protected array $category = [],
    ) {}

    public function title(): string
    {
        return 'Komposisi Kategori';
    }

    public function headings(): array
    {
        return ['Kategori', 'Jumlah', 'Persentase'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->category['rows'] ?? [] as $row) {
            $rows[] = [
                $row['label'] ?? 'Tanpa Kategori',
                ((int) ($row['count'] ?? 0)).' Unit',
                ((int) ($row['percent'] ?? 0)).'%',
            ];
        }

        if ($rows === []) {
            $rows[] = ['Tidak ada data', '0 Unit', '0%'];
        }

        $total = (int) ($this->category['total_units'] ?? 0);
        $rows[] = ['TOTAL', $total.' Unit', $total > 0 ? '100%' : '0%'];

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
