<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class InventorySettingSeeder extends Seeder
{
    public function run(): void
    {
        // EAMS default: Campus + Org Unit + Sub Classification + padded sequence
        // Example: CBB-PPPA-CHR-000001
        Setting::set(
            'inventory.number_format',
            '{campus}-{org_unit}-{sub_category}-{sequence}',
            'inventory'
        );

        Setting::set('inventory.sequence_padding', '6', 'inventory');

        // QR scan base URL (empty = use APP_URL). Future production domain override.
        Setting::set('inventory.scan_base_url', '', 'inventory');
    }
}
