<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Hierarchical Excel/CSV export matching the browser/PDF report structure.
 */
class InventoryHierarchicalExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $summary
     * @param  array<string, string|null>  $filters
     * @param  array{printed_at: string, user_name: string, title: string}  $meta
     */
    public function __construct(
        protected array $tree,
        protected array $summary,
        protected array $filters,
        protected array $meta,
    ) {}

    public function title(): string
    {
        return 'Laporan Inventaris';
    }

    public function headings(): array
    {
        return [
            'Level',
            'Kampus',
            'Gedung',
            'Lantai',
            'Ruangan',
            'Kategori',
            'Sub Kategori',
            'No. Inventaris',
            'Nama Barang',
            'Status',
            'Kondisi',
            'Jumlah',
            'Catatan',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->filters as $label => $value) {
            $rows[] = ['FILTER', $label, (string) $value, '', '', '', '', '', '', '', '', '', ''];
        }

        if ($this->filters !== []) {
            $rows[] = array_fill(0, 13, '');
        }

        foreach ($this->tree as $campusNode) {
            $campusName = $campusNode['label'];
            $rows[] = [
                'KAMPUS', $campusName, '', '', '', '', '', '', '', '', '',
                $campusNode['totals']['count'],
                $this->categorySummaryText($campusNode['by_category'] ?? []),
            ];

            foreach ($campusNode['buildings'] as $buildingNode) {
                $buildingName = $buildingNode['label'];
                $rows[] = [
                    'GEDUNG', $campusName, $buildingName, '', '', '', '', '', '', '', '',
                    $buildingNode['totals']['count'],
                    'Ruangan: '.($buildingNode['room_count'] ?? 0),
                ];

                foreach ($buildingNode['floors'] as $floorNode) {
                    $floorName = $floorNode['label'];
                    $rows[] = [
                        'LANTAI', $campusName, $buildingName, $floorName, '', '', '', '', '', '', '',
                        $floorNode['totals']['count'],
                        '',
                    ];

                    foreach ($floorNode['rooms'] as $roomNode) {
                        $roomName = $roomNode['label'];
                        $rows[] = [
                            'RUANGAN', $campusName, $buildingName, $floorName, $roomName, '', '', '', '', '', '',
                            $roomNode['totals']['count'],
                            $this->categorySummaryText($roomNode['by_category'] ?? []),
                        ];

                        foreach ($roomNode['categories'] as $categoryNode) {
                            $categoryName = $categoryNode['label'];
                            $rows[] = [
                                'KATEGORI', $campusName, $buildingName, $floorName, $roomName, $categoryName, '', '', '', '', '',
                                $categoryNode['totals']['count'],
                                '',
                            ];

                            foreach ($categoryNode['sub_categories'] as $subNode) {
                                $subName = $subNode['label'];
                                $rows[] = [
                                    'SUB KATEGORI', $campusName, $buildingName, $floorName, $roomName, $categoryName, $subName, '', '', '', '',
                                    $subNode['totals']['count'],
                                    '',
                                ];

                                /** @var \App\Models\InventoryItem $item */
                                foreach ($subNode['items'] as $item) {
                                    $rows[] = [
                                        'INVENTARIS',
                                        $campusName,
                                        $buildingName,
                                        $floorName,
                                        $roomName,
                                        $categoryName,
                                        $subName,
                                        $item->inventory_number,
                                        $item->name,
                                        $item->status?->label(),
                                        $item->condition?->label(),
                                        1,
                                        $item->notes,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $rows[] = array_fill(0, 13, '');
        }

        $rows[] = ['GRAND TOTAL', '', '', '', '', '', '', '', '', '', '', $this->summary['total_items'], ''];
        $rows[] = ['RINGKASAN', 'Aktif', (string) ($this->summary['aktif'] ?? 0), '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['RINGKASAN', 'Dipinjam', (string) ($this->summary['dipinjam'] ?? 0), '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['RINGKASAN', 'Maintenance', (string) ($this->summary['maintenance'] ?? 0), '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['RINGKASAN', 'Rusak', (string) ($this->summary['rusak'] ?? 0), '', '', '', '', '', '', '', '', '', ''];

        foreach (($this->summary['by_category'] ?? []) as $cat => $count) {
            $rows[] = ['RINGKASAN', 'Kategori: '.$cat, (string) $count, '', '', '', '', '', '', '', '', '', ''];
        }

        $rows[] = ['META', 'Dicetak', $this->meta['printed_at'] ?? '', $this->meta['user_name'] ?? '', '', '', '', '', '', '', '', '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @param  array<string, int>  $byCategory
     */
    protected function categorySummaryText(array $byCategory): string
    {
        $parts = [];
        foreach ($byCategory as $name => $count) {
            $parts[] = $name.': '.$count;
        }

        return implode(', ', $parts);
    }
}
