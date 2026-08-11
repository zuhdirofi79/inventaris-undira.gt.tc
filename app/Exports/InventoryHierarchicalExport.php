<?php

namespace App\Exports;

use App\Models\InventoryItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet "Detail Inventaris" — raw filtered inventory rows only.
 * Filter / statistik / ringkasan belong on the "Ringkasan" sheet, not here.
 */
class InventoryHierarchicalExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle, WithEvents
{
    /**
     * @param  list<array<string, mixed>>  $tree  Hierarchy from InventoryReportService (items only are exported)
     * @param  array<string, mixed>  $summary  Unused — kept for call-site BC; never written to this sheet
     * @param  array<string, string|null>  $filters  Unused — kept for call-site BC; never written to this sheet
     * @param  array<string, mixed>  $meta  Unused — kept for call-site BC; never written to this sheet
     */
    public function __construct(
        protected array $tree,
        protected array $summary = [],
        protected array $filters = [],
        protected array $meta = [],
    ) {}

    public function title(): string
    {
        return 'Detail Inventaris';
    }

    public function headings(): array
    {
        return [
            'No',
            'Nomor Inventaris',
            'Nama Barang',
            'Kategori',
            'Sub Kategori',
            'Unit Kerja',
            'Nama Ruangan',
            'Merk',
            'Serial Number',
            'Lokasi',
            'Status',
            'Kondisi',
            'PIC',
            'Tanggal Pengadaan',
            'Tahun Pengadaan',
            'Vendor',
            'Harga',
            'Kampus',
            'Gedung',
            'Lantai',
            'Ruangan',
            'Catatan',
        ];
    }

    public function array(): array
    {
        $rows = [];
        $no = 0;

        foreach ($this->tree as $campusNode) {
            $campusName = (string) ($campusNode['label'] ?? '');

            foreach ($campusNode['buildings'] ?? [] as $buildingNode) {
                $buildingName = (string) ($buildingNode['label'] ?? '');

                foreach ($buildingNode['floors'] ?? [] as $floorNode) {
                    $floorName = (string) ($floorNode['label'] ?? '');

                    foreach ($floorNode['rooms'] ?? [] as $roomNode) {
                        $roomName = (string) ($roomNode['label'] ?? '');

                        foreach ($roomNode['categories'] ?? [] as $categoryNode) {
                            $categoryName = (string) ($categoryNode['label'] ?? '');

                            foreach ($categoryNode['sub_categories'] ?? [] as $subNode) {
                                $subName = (string) ($subNode['label'] ?? '');

                                /** @var InventoryItem $item */
                                foreach ($subNode['items'] ?? [] as $item) {
                                    $no++;
                                    $rows[] = $this->mapItemRow(
                                        $item,
                                        $no,
                                        $campusName,
                                        $buildingName,
                                        $floorName,
                                        $roomName,
                                        $categoryName,
                                        $subName,
                                    );
                                }
                            }
                        }
                    }
                }
            }
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

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestColumn = $sheet->getHighestColumn();
                $highestRow = max(1, $sheet->getHighestRow());

                $sheet->setAutoFilter("A1:{$highestColumn}{$highestRow}");
                $sheet->freezePane('A2');

                $sheet->getStyle("A1:{$highestColumn}{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                ]);
            },
        ];
    }

    /**
     * @return list<mixed>
     */
    protected function mapItemRow(
        InventoryItem $item,
        int $no,
        string $campusName,
        string $buildingName,
        string $floorName,
        string $roomName,
        string $categoryName,
        string $subName,
    ): array {
        $location = method_exists($item, 'locationDisplayName')
            ? (string) ($item->locationDisplayName() ?: '—')
            : (trim(implode(' / ', array_filter([$campusName, $buildingName, $floorName, $roomName]))) ?: '—');

        $price = $item->price;
        if ($price === null || $price === '') {
            $priceFormatted = '—';
        } elseif (is_numeric($price)) {
            $priceFormatted = number_format((float) $price, 0, ',', '.');
        } else {
            $priceFormatted = (string) $price;
        }

        $pic = method_exists($item, 'picDisplayName')
            ? (string) ($item->picDisplayName() ?: '—')
            : '—';

        $vendorName = $item->vendor?->name ?: ($item->supplier?->name ?: '—');

        return [
            $no,
            $item->inventory_number,
            $item->name,
            $categoryName !== '' ? $categoryName : ($item->category?->name ?? '—'),
            $subName !== '' ? $subName : ($item->subCategory?->name ?? '—'),
            $item->orgUnit?->name ?: '—',
            $item->room?->name ?: '—',
            $item->brand ?: '—',
            $item->serial_number ?: '—',
            $location,
            $item->status?->label() ?? '—',
            $item->condition?->label() ?? '—',
            $pic,
            $item->purchase_date?->format('d/m/Y') ?? '—',
            $item->purchase_year ? (string) $item->purchase_year : '—',
            $vendorName,
            $priceFormatted,
            $campusName !== '' ? $campusName : '—',
            $buildingName !== '' ? $buildingName : '—',
            $floorName !== '' ? $floorName : '—',
            $roomName !== '' ? $roomName : '—',
            $item->notes ?: '',
        ];
    }
}
