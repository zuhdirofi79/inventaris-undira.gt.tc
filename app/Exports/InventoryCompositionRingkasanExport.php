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
 * Sheet "Ringkasan" — lokasi, kondisi, status, top 10 (filter-aware).
 */
class InventoryCompositionRingkasanExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  array<string, mixed>  $summary
     * @param  array<string, string|null>  $filters
     */
    public function __construct(
        protected array $summary = [],
        protected array $filters = [],
    ) {}

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function headings(): array
    {
        return ['Bagian', 'Uraian', 'Jumlah'];
    }

    public function array(): array
    {
        $rows = [];
        $composition = $this->summary['composition'] ?? [];

        foreach ($this->filters as $label => $value) {
            $rows[] = ['Filter', (string) $label, (string) $value];
        }
        if ($this->filters !== []) {
            $rows[] = ['', '', ''];
        }

        $loc = $composition['location'] ?? [];
        $rows[] = ['Info Lokasi', 'Kampus', (int) ($loc['kampus'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Gedung', (int) ($loc['gedung'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Lantai', (int) ($loc['lantai'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Ruangan', (int) ($loc['ruangan'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Kategori', (int) ($loc['kategori'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Sub Kategori', (int) ($loc['sub_kategori'] ?? 0)];
        $rows[] = ['Info Lokasi', 'Total Inventaris', (int) ($loc['total_inventaris'] ?? $this->summary['total_items'] ?? 0)];
        $rows[] = ['', '', ''];

        foreach (($composition['by_condition']['rows'] ?? []) as $row) {
            $rows[] = ['Kondisi', $row['label'] ?? '', (int) ($row['count'] ?? 0)];
        }
        $rows[] = ['Kondisi', 'Total', (int) ($composition['by_condition']['total_units'] ?? 0)];
        $rows[] = ['', '', ''];

        foreach (($composition['by_status']['rows'] ?? []) as $row) {
            $rows[] = ['Status', $row['label'] ?? '', (int) ($row['count'] ?? 0)];
        }
        $rows[] = ['Status', 'Total', (int) ($composition['by_status']['total_units'] ?? 0)];
        $rows[] = ['', '', ''];

        foreach (($composition['top_sub_categories'] ?? []) as $i => $row) {
            $rows[] = ['Top 10 Sub Kategori', ($i + 1).'. '.($row['label'] ?? ''), (int) ($row['count'] ?? 0)];
        }

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
