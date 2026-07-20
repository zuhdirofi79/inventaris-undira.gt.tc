<?php

namespace Database\Seeders;

use App\Models\OrgUnit;
use App\Models\ProcurementType;
use Illuminate\Database\Seeder;

class MasterLookupSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PPPA', 'name' => 'Pengadaan Pengelolaan Perbaikan Aset'],
            ['code' => 'BAK', 'name' => 'Biro Administrasi Keuangan'],
            ['code' => 'IT', 'name' => 'Unit Teknologi Informasi'],
            ['code' => 'AKD', 'name' => 'Biro Akademik'],
            ['code' => 'UMUM', 'name' => 'Bagian Umum'],
        ];

        foreach ($units as $unit) {
            OrgUnit::query()->firstOrCreate(
                ['code' => $unit['code']],
                ['name' => $unit['name'], 'is_active' => true],
            );
        }

        $types = [
            ['code' => 'BOS', 'name' => 'Bantuan Operasional Sekolah / Kampus'],
            ['code' => 'APBN', 'name' => 'APBN'],
            ['code' => 'APBD', 'name' => 'APBD'],
            ['code' => 'HIBAH', 'name' => 'Hibah'],
            ['code' => 'SWADAYA', 'name' => 'Swadaya / Mandiri'],
        ];

        foreach ($types as $type) {
            ProcurementType::query()->firstOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true],
            );
        }
    }
}
