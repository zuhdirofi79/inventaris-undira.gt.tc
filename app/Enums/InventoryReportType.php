<?php

namespace App\Enums;

enum InventoryReportType: string
{
    case Dashboard = 'dashboard';
    case Complete = 'lengkap';
    case ByCampus = 'per-kampus';
    case ByBuilding = 'per-gedung';
    case ByFloor = 'per-lantai';
    case ByRoom = 'per-ruangan';
    case ByCategory = 'per-kategori';
    case BySubCategory = 'per-subkategori';
    case ByPic = 'per-pic';
    case Borrowed = 'dipinjam';
    case RoomBorrowed = 'peminjaman-ruangan';
    case VehicleBorrowed = 'peminjaman-kendaraan';
    case Maintenance = 'maintenance';
    case Damaged = 'rusak';
    case Inactive = 'non-aktif';
    case Mutated = 'dimutasi';
    case Deleted = 'dihapus';
    case Recap = 'rekapitulasi';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => 'Dashboard Laporan',
            self::Complete => 'Inventaris Lengkap',
            self::ByCampus => 'Inventaris per Kampus',
            self::ByBuilding => 'Inventaris per Gedung',
            self::ByFloor => 'Inventaris per Lantai',
            self::ByRoom => 'Inventaris per Ruangan',
            self::ByCategory => 'Inventaris per Kategori',
            self::BySubCategory => 'Inventaris per Subkategori',
            self::ByPic => 'Inventaris per PIC',
            self::Borrowed => 'Inventaris Dipinjam',
            self::RoomBorrowed => 'Laporan Peminjaman Ruangan',
            self::VehicleBorrowed => 'Laporan Peminjaman Kendaraan',
            self::Maintenance => 'Inventaris Maintenance',
            self::Damaged => 'Inventaris Rusak',
            self::Inactive => 'Inventaris Non Aktif',
            self::Mutated => 'Inventaris Dimutasi',
            self::Deleted => 'Inventaris Dihapus',
            self::Recap => 'Rekapitulasi Inventaris',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Dashboard => 'fas fa-tachometer-alt',
            self::Complete => 'fas fa-list',
            self::ByCampus => 'fas fa-university',
            self::ByBuilding => 'fas fa-building',
            self::ByFloor => 'fas fa-layer-group',
            self::ByRoom => 'fas fa-door-open',
            self::ByCategory => 'fas fa-tags',
            self::BySubCategory => 'fas fa-tag',
            self::ByPic => 'fas fa-user-tie',
            self::Borrowed => 'fas fa-hand-holding',
            self::RoomBorrowed => 'fas fa-door-closed',
            self::VehicleBorrowed => 'fas fa-car',
            self::Maintenance => 'fas fa-tools',
            self::Damaged => 'fas fa-exclamation-triangle',
            self::Inactive => 'fas fa-ban',
            self::Mutated => 'fas fa-exchange-alt',
            self::Deleted => 'fas fa-trash',
            self::Recap => 'fas fa-chart-pie',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Dashboard => 'Ringkasan eksekutif dan akses cepat ke seluruh jenis laporan.',
            self::Complete => 'Seluruh inventaris dengan kolom aset lengkap termasuk QR.',
            self::ByCampus => 'Laporan hierarkis Kampus → Gedung → Lantai → Ruangan → Kategori.',
            self::ByBuilding => 'Inventaris seluruh ruangan dalam gedung terpilih.',
            self::ByFloor => 'Inventaris seluruh ruangan dalam lantai terpilih.',
            self::ByRoom => 'Inventaris satu ruangan beserta subtotal status.',
            self::ByCategory => 'Pengelompokan inventaris berdasarkan kategori.',
            self::BySubCategory => 'Pengelompokan inventaris berdasarkan subkategori.',
            self::ByPic => 'Pengelompokan inventaris berdasarkan penanggung jawab (PIC).',
            self::Borrowed => 'Riwayat dan status peminjaman inventaris.',
            self::RoomBorrowed => 'Riwayat penggunaan dan persetujuan peminjaman ruangan.',
            self::VehicleBorrowed => 'Riwayat pengajuan, persetujuan, dan penggunaan kendaraan operasional.',
            self::Maintenance => 'Work order maintenance / perbaikan inventaris.',
            self::Damaged => 'Inventaris rusak beserta status perbaikan.',
            self::Inactive => 'Aset nonaktif: hilang, dilelang, dimusnahkan, rusak permanen.',
            self::Mutated => 'Riwayat mutasi lokasi inventaris.',
            self::Deleted => 'Inventaris yang telah dihapus (soft delete / trash).',
            self::Recap => 'Dashboard rekapitulasi, kartu KPI, dan grafik agregat.',
        };
    }

    /**
     * Types shown on the inventaris report dashboard (excludes facility borrowing reports).
     *
     * @return list<self>
     */
    public static function menuTypes(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $t) => $t !== self::Dashboard
                && $t !== self::RoomBorrowed
                && $t !== self::VehicleBorrowed
        ));
    }

    /**
     * @return list<string>
     */
    public static function facilityBorrowingSlugs(): array
    {
        return [
            self::Borrowed->value,
            self::RoomBorrowed->value,
            self::VehicleBorrowed->value,
        ];
    }

    public function isHierarchy(): bool
    {
        return in_array($this, [
            self::ByCampus,
            self::ByBuilding,
            self::ByFloor,
            self::ByRoom,
            self::ByCategory,
            self::BySubCategory,
            self::ByPic,
        ], true);
    }

    public function isTransactional(): bool
    {
        return in_array($this, [
            self::Borrowed,
            self::RoomBorrowed,
            self::VehicleBorrowed,
            self::Maintenance,
            self::Mutated,
        ], true);
    }

    public function family(): string
    {
        return match ($this) {
            self::Borrowed => 'borrowing',
            self::RoomBorrowed => 'room-borrowing',
            self::VehicleBorrowed => 'vehicle-borrowing',
            self::Maintenance => 'maintenance',
            self::Mutated => 'mutation',
            self::Dashboard, self::Recap => 'dashboard',
            default => 'inventory',
        };
    }
}
