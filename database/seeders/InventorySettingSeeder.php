<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class InventorySettingSeeder extends Seeder
{
    public function run(): void
    {
        // Official UNDIRA format is enforced in InventoryCodeService (setting kept for reference only).
        // Sequence is scoped by campus + sub_category. Example: CBB-HIBAH-26-EL-LPT-000001
        Setting::set(
            'inventory.number_format',
            '{campus}-{procurement}-{year}-{category}-{sub_category}-{sequence}',
            'inventory'
        );

        Setting::set('inventory.sequence_padding', '6', 'inventory');

        // QR scan base URL (empty = use APP_URL). Future production domain override.
        Setting::set('inventory.scan_base_url', '', 'inventory');
    }
}
