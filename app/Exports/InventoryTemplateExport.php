<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class InventoryTemplateExport implements FromArray, WithHeadings
{
    public function headings(): array
    {
        return [
            'name',
            'category_code',
            'sub_category_code',
            'room_code',
            'brand',
            'type',
            'model',
            'serial_number',
            'specification',
            'purchase_year',
            'purchase_date',
            'funding_source',
            'price',
            'condition',
            'status',
            'responsible_person',
            'supplier_name',
            'vendor_name',
            'warranty_info',
            'warranty_expiry',
            'notes',
            'quantity',
            'unit',
        ];
    }

    public function array(): array
    {
        return [
            [
                'Contoh: Meja Kerja',
                'FUR',
                'FUR-001',
                'R101',
                'IKEA',
                'Meja',
                'BEKANT',
                'SN123456',
                'Meja kerja kayu',
                '2024',
                '2024-01-15',
                'APBN',
                '1500000',
                'bagus',
                'aktif',
                'John Doe',
                'PT Supplier ABC',
                'PT Vendor XYZ',
                'Garansi 1 tahun',
                '2025-01-15',
                'Catatan contoh',
                '1',
                'Unit',
            ],
        ];
    }
}
