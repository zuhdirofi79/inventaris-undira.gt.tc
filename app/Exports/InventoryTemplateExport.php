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
            'procurement_type_code',
            'room_code',
            'brand',
            'type',
            'model',
            'serial_number',
            'specification',
            'purchase_year',
            'purchase_date',
            'funding_source',
            'condition',
            'status',
            'pic',
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
                'Contoh: Laptop Kerja',
                'EL',
                'LPT',
                'HIBAH',
                'R101',
                'Dell',
                'Laptop',
                'Latitude',
                'SN123456',
                'Laptop kerja',
                '2026',
                '2026-01-15',
                'Hibah',
                'bagus',
                'aktif',
                'azwar@example.com',
                'John Doe',
                'PT Supplier ABC',
                'PT Vendor XYZ',
                'Garansi 1 tahun',
                '2027-01-15',
                'Catatan contoh',
                '1',
                'Unit',
            ],
        ];
    }
}
