<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Models\Vendor;
use Illuminate\Database\Seeder;

class SupplierVendorSeeder extends Seeder
{
    /**
     * @var list<array<string, mixed>>
     */
    private array $suppliers = [
        [
            'name' => 'PT Mitra Teknologi Indonesia',
            'contact_person' => 'Budi Santoso',
            'phone' => '021-87654321',
            'email' => 'sales@mitrateknologi.co.id',
            'address' => 'Jl. Raya Condet No. 88, Jakarta Timur',
        ],
        [
            'name' => 'CV Sumber Office Supply',
            'contact_person' => 'Siti Rahmawati',
            'phone' => '021-55667788',
            'email' => 'order@sumberoffice.co.id',
            'address' => 'Jl. Tanjung Duren Raya No. 12, Jakarta Barat',
        ],
        [
            'name' => 'PT Lab Scientific Nusantara',
            'contact_person' => 'Dr. Agus Wijaya',
            'phone' => '021-99887766',
            'email' => 'info@labscientific.id',
            'address' => 'Kawasan Industri Pulogadung, Jakarta Timur',
        ],
        [
            'name' => 'PT Furnitur Pendidikan Mandiri',
            'contact_person' => 'Rudi Hartono',
            'phone' => '021-44556677',
            'email' => 'penjualan@furniturpendidikan.co.id',
            'address' => 'Jl. Cibubur Indah Raya Blok C2, Depok',
        ],
        [
            'name' => 'PT Jaringan Solusi Digital',
            'contact_person' => 'Dewi Lestari',
            'phone' => '021-33445566',
            'email' => 'proyek@jsd.net.id',
            'address' => 'Greenville Business Park, Jakarta Barat',
        ],
    ];

    /**
     * @var list<array<string, mixed>>
     */
    private array $vendors = [
        [
            'name' => 'PT Servis Komputer Nusantara',
            'contact_person' => 'Andi Pratama',
            'phone' => '0812-3456-7890',
            'email' => 'service@serviskomputer.id',
            'address' => 'Jl. Meruya Ilir No. 45, Jakarta Barat',
        ],
        [
            'name' => 'CV Maintenance Facility UNDIRA Partner',
            'contact_person' => 'Hendra Gunawan',
            'phone' => '0813-9876-5432',
            'email' => 'maintenance@facilitypartner.co.id',
            'address' => 'Jl. Alternatif Cibubur KM 4, Bekasi',
        ],
        [
            'name' => 'PT AC & Elektrikal Prima',
            'contact_person' => 'Yusuf Maulana',
            'phone' => '021-77889900',
            'email' => 'teknisi@acprima.co.id',
            'address' => 'Jl. Tanjung Duren Selatan No. 8, Jakarta Barat',
        ],
        [
            'name' => 'PT Keamanan Integrasi Sistem',
            'contact_person' => 'Maria Christina',
            'phone' => '021-66778899',
            'email' => 'proyek@keamananintegrasi.id',
            'address' => 'Greenville Office Tower Lt. 5, Jakarta Barat',
        ],
    ];

    public function run(): void
    {
        foreach ($this->suppliers as $supplier) {
            Supplier::updateOrCreate(
                ['email' => $supplier['email']],
                array_merge($supplier, ['is_active' => true])
            );
        }

        foreach ($this->vendors as $vendor) {
            Vendor::updateOrCreate(
                ['email' => $vendor['email']],
                array_merge($vendor, ['is_active' => true])
            );
        }
    }
}
