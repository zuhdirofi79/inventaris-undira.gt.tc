<?php

namespace App\Exports;

use App\Models\InventoryItem;
use App\Services\InventoryFilterService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class InventoryExport implements FromQuery, WithHeadings, WithMapping
{
    public function __construct(
        protected Request $request,
        protected InventoryFilterService $filterService,
    ) {}

    public function query()
    {
        return $this->filterService
            ->apply($this->request)
            ->with(['category', 'subCategory', 'room.floor.building.campus', 'supplier', 'vendor'])
            ->orderBy('inventory_number');
    }

    public function headings(): array
    {
        return [
            'No. Inventaris',
            'Nama',
            'Kategori',
            'Sub Kategori',
            'Merk',
            'Tipe',
            'Model',
            'Serial Number',
            'Kondisi',
            'Status',
            'Kampus',
            'Gedung',
            'Lantai',
            'Ruangan',
            'Tahun Pembelian',
            'Tanggal Pembelian',
            'Harga',
            'Supplier',
            'Vendor',
            'Penanggung Jawab',
            'Garansi Berakhir',
            'Catatan',
        ];
    }

    public function map($item): array
    {
        /** @var InventoryItem $item */
        $room = $item->room;
        $floor = $room?->floor;
        $building = $floor?->building;
        $campus = $building?->campus;

        return [
            $item->inventory_number,
            $item->name,
            $item->category?->name,
            $item->subCategory?->name,
            $item->brand,
            $item->type,
            $item->model,
            $item->serial_number,
            $item->condition?->label(),
            $item->status?->label(),
            $campus?->name,
            $building?->name,
            $floor?->name,
            $room?->name,
            $item->purchase_year,
            $item->purchase_date?->format('Y-m-d'),
            $item->price !== null ? $item->price : null,
            $item->supplier?->name,
            $item->vendor?->name,
            $item->responsible_person,
            $item->warranty_expiry?->format('Y-m-d'),
            $item->notes,
        ];
    }
}
