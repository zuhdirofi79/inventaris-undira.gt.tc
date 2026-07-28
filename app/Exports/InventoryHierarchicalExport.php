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
 * Hierarchical Excel/CSV export matching the official print/PDF report structure.
 */
class InventoryHierarchicalExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle
{
    /**
     * @param  list<array<string, mixed>>  $tree
     * @param  array<string, mixed>  $summary
     * @param  array<string, string|null>  $filters
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        protected array $tree,
        protected array $summary,
        protected array $filters,
        protected array $meta,
    ) {}

    public function title(): string
    {
        return 'Detail Inventaris';
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
            'No',
            'No. Inventaris',
            'Nama Barang',
            'Merk',
            'Serial Number',
            'Kondisi',
            'Tahun Pengadaan',
            'Status',
            'PIC',
            'Jumlah',
            'Catatan',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $cols = 18;

        foreach ($this->filters as $label => $value) {
            $rows[] = $this->pad(['FILTER', $label, (string) $value], $cols);
        }

        if ($this->filters !== []) {
            $rows[] = array_fill(0, $cols, '');
        }

        $rows[] = $this->pad([
            'RINGKASAN',
            'Total Inventaris',
            (string) ($this->summary['total_items'] ?? 0),
            'Kampus',
            (string) ($this->summary['campus_count'] ?? 0),
            'Gedung',
            (string) ($this->summary['building_count'] ?? 0),
            'Ruangan',
            (string) ($this->summary['room_count'] ?? 0),
        ], $cols);
        $rows[] = $this->pad([
            'RINGKASAN',
            'Aktif',
            (string) ($this->summary['aktif'] ?? 0),
            'Rusak',
            (string) ($this->summary['rusak'] ?? 0),
            'Dipinjam',
            (string) ($this->summary['dipinjam'] ?? 0),
            'Maintenance',
            (string) ($this->summary['maintenance'] ?? 0),
        ], $cols);

        $recap = $this->summary['sub_category_recap'] ?? ['rows' => []];
        foreach ($recap['rows'] ?? [] as $row) {
            $rows[] = $this->pad([
                'REKAP SUB KATEGORI',
                $row['label'] ?? '',
                (string) ($row['count'] ?? 0).' Unit',
            ], $cols);
        }
        if (! empty($recap['rows'])) {
            $rows[] = $this->pad([
                'REKAP SUB KATEGORI',
                'TOTAL SUB KATEGORI',
                ((int) ($recap['kind_count'] ?? 0)).' Jenis',
                'TOTAL INVENTARIS',
                ((int) ($recap['total_units'] ?? 0)).' Unit',
            ], $cols);
        }

        $rows[] = array_fill(0, $cols, '');

        foreach ($this->tree as $campusNode) {
            $campusName = $campusNode['label'];
            $rows[] = $this->pad([
                'KAMPUS', $campusName, '', '', '', '', '', '', '', '', '', '', '', '', '', '',
                $campusNode['totals']['count'],
                'Aktif: '.($campusNode['totals']['aktif'] ?? 0).' · Rusak: '.($campusNode['totals']['rusak'] ?? 0),
            ], $cols);

            foreach ($campusNode['buildings'] as $buildingNode) {
                $buildingName = $buildingNode['label'];
                $rows[] = $this->pad([
                    'GEDUNG', $campusName, $buildingName, '', '', '', '', '', '', '', '', '', '', '', '', '',
                    $buildingNode['totals']['count'],
                    'Ruangan: '.($buildingNode['room_count'] ?? 0),
                ], $cols);

                foreach (($buildingNode['sub_category_recap']['rows'] ?? []) as $recapRow) {
                    $rows[] = $this->pad([
                        'REKAP GEDUNG', $campusName, $buildingName, '', '', '', $recapRow['label'] ?? '', '', '', '', '', '', '', '', '', '',
                        $recapRow['count'] ?? 0,
                        '',
                    ], $cols);
                }

                foreach ($buildingNode['floors'] as $floorNode) {
                    $floorName = $floorNode['label'];
                    $rows[] = $this->pad([
                        'LANTAI', $campusName, $buildingName, $floorName, '', '', '', '', '', '', '', '', '', '', '', '',
                        $floorNode['totals']['count'],
                        '',
                    ], $cols);

                    foreach (($floorNode['sub_category_recap']['rows'] ?? []) as $recapRow) {
                        $rows[] = $this->pad([
                            'REKAP LANTAI', $campusName, $buildingName, $floorName, '', '', $recapRow['label'] ?? '', '', '', '', '', '', '', '', '', '',
                            $recapRow['count'] ?? 0,
                            '',
                        ], $cols);
                    }

                    foreach ($floorNode['rooms'] as $roomNode) {
                        $roomName = $roomNode['label'];
                        $rows[] = $this->pad([
                            'RUANGAN', $campusName, $buildingName, $floorName, $roomName, '', '', '', '', '', '', '', '', '', '', '',
                            $roomNode['totals']['count'],
                            'Aktif: '.($roomNode['totals']['aktif'] ?? 0).' · Rusak: '.($roomNode['totals']['rusak'] ?? 0),
                        ], $cols);

                        foreach (($roomNode['sub_category_recap']['rows'] ?? []) as $recapRow) {
                            $rows[] = $this->pad([
                                'REKAP RUANGAN', $campusName, $buildingName, $floorName, $roomName, '', $recapRow['label'] ?? '', '', '', '', '', '', '', '', '', '',
                                $recapRow['count'] ?? 0,
                                '',
                            ], $cols);
                        }

                        foreach ($roomNode['categories'] as $categoryNode) {
                            $categoryName = $categoryNode['label'];
                            $rows[] = $this->pad([
                                'KATEGORI', $campusName, $buildingName, $floorName, $roomName, $categoryName, '', '', '', '', '', '', '', '', '', '',
                                $categoryNode['totals']['count'],
                                '',
                            ], $cols);

                            foreach ($categoryNode['sub_categories'] as $subNode) {
                                $subName = $subNode['label'];
                                $rows[] = $this->pad([
                                    'SUB KATEGORI', $campusName, $buildingName, $floorName, $roomName, $categoryName, $subName, '', '', '', '', '', '', '', '', '',
                                    $subNode['totals']['count'],
                                    '',
                                ], $cols);

                                /** @var \App\Models\InventoryItem $item */
                                foreach ($subNode['items'] as $index => $item) {
                                    $rows[] = [
                                        'INVENTARIS',
                                        $campusName,
                                        $buildingName,
                                        $floorName,
                                        $roomName,
                                        $categoryName,
                                        $subName,
                                        str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                                        $item->inventory_number,
                                        $item->name,
                                        $item->brand,
                                        $item->serial_number,
                                        $item->condition?->label(),
                                        $item->purchase_year ? (string) $item->purchase_year : '-',
                                        $item->status?->label(),
                                        $item->picDisplayName() ?? '',
                                        1,
                                        $item->notes,
                                    ];
                                }
                            }
                        }
                    }
                }
            }

            $rows[] = array_fill(0, $cols, '');
        }

        $rows[] = $this->pad(['GRAND TOTAL', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', $this->summary['total_items'], ''], $cols);
        $rows[] = $this->pad(['META', 'Dicetak', $this->meta['printed_at'] ?? '', $this->meta['user_name'] ?? ''], $cols);

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

    /**
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    protected function pad(array $row, int $cols): array
    {
        return array_pad($row, $cols, '');
    }
}
