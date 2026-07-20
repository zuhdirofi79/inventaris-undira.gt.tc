<?php

namespace Database\Seeders;

use App\Models\InventoryConditionMaster;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\RoomFunction;
use Illuminate\Database\Seeder;

class MasterLookupSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PPPA', 'name' => 'Pengadaan, Pengelolaan, dan Perbaikan Aset'],
            ['code' => 'BAK', 'name' => 'Biro Administrasi Keuangan'],
            ['code' => 'IT', 'name' => 'Unit Teknologi Informasi'],
            ['code' => 'BOP', 'name' => 'Bagian Operasional'],
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
            ['code' => 'UNDIRA', 'name' => 'UNDIRA'],
            ['code' => 'HIBAH', 'name' => 'Hibah'],
            ['code' => 'KS', 'name' => 'Kerja Sama'],
            ['code' => 'YAY', 'name' => 'Yayasan'],
            ['code' => 'BOS', 'name' => 'Bantuan Operasional Sekolah / Kampus'],
            ['code' => 'APBN', 'name' => 'APBN'],
            ['code' => 'APBD', 'name' => 'APBD'],
            ['code' => 'SWADAYA', 'name' => 'Swadaya / Mandiri'],
        ];

        foreach ($types as $type) {
            ProcurementType::query()->firstOrCreate(
                ['code' => $type['code']],
                ['name' => $type['name'], 'is_active' => true],
            );
        }

        $functions = [
            ['code' => 'KELAS', 'name' => 'Ruang Kelas', 'sort_order' => 1],
            ['code' => 'LAB', 'name' => 'Laboratorium', 'sort_order' => 2],
            ['code' => 'KERJA', 'name' => 'Ruang Kerja', 'sort_order' => 3],
            ['code' => 'RAPAT', 'name' => 'Ruang Rapat', 'sort_order' => 4],
            ['code' => 'GUDANG', 'name' => 'Gudang', 'sort_order' => 5],
            ['code' => 'AULA', 'name' => 'Aula', 'sort_order' => 6],
        ];

        foreach ($functions as $fn) {
            RoomFunction::query()->firstOrCreate(
                ['code' => $fn['code']],
                ['name' => $fn['name'], 'is_active' => true, 'sort_order' => $fn['sort_order']],
            );
        }

        $conditions = [
            ['code' => 'BAIK', 'name' => 'Baik', 'sort_order' => 1],
            ['code' => 'LAYAK', 'name' => 'Layak', 'sort_order' => 2],
            ['code' => 'RUSAK', 'name' => 'Rusak', 'sort_order' => 3],
        ];

        foreach ($conditions as $condition) {
            InventoryConditionMaster::query()->firstOrCreate(
                ['code' => $condition['code']],
                ['name' => $condition['name'], 'is_active' => true, 'sort_order' => $condition['sort_order']],
            );
        }

        foreach (range(2020, 2026) as $year) {
            PurchaseYear::query()->firstOrCreate(
                ['year' => $year],
                ['is_active' => true],
            );
        }
    }
}
