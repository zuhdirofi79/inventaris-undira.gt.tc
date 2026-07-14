# Sistem Inventaris Universitas Dian Nusantara (UNDIRA)

Aplikasi web **production-ready** untuk manajemen inventaris sarana prasarana Universitas Dian Nusantara. Dibangun dengan clean architecture, role-based access control, dan alur navigasi hierarkis **Kampus → Gedung → Lantai → Ruangan → Inventaris**.

## Tech Stack

| Layer | Teknologi |
|-------|-----------|
| Backend | Laravel 13, PHP 8.3 |
| Database | MySQL (production) / SQLite (development) |
| Frontend | Bootstrap 5, AdminLTE 3, jQuery |
| Plugins | DataTables, SweetAlert2, Select2, Chart.js, Font Awesome 6 |
| Packages | Spatie Permission, Laravel Excel, DomPDF, Simple QR Code, Picqer Barcode |

> **Catatan:** Scaffold menggunakan Laravel 13 (versi terbaru saat instalasi). API dan arsitektur kompatibel dengan spesifikasi Laravel 12+.

## Fitur Utama

- **Autentikasi** — Login, Register (Viewer), Forgot Password, Remember Me
- **Role & Permission** — Super Admin (full access) dan Viewer (read-only + laporan)
- **Navigasi Lokasi** — 3 kampus UNDIRA dengan hierarki gedung/lantai/ruangan
- **Master Inventaris** — 11 kategori, sub-kategori, supplier, vendor
- **Nomor Inventaris Otomatis** — Format `UND-CBB-A-L3-A302-MJ-0001`
- **QR Code & Barcode** — Generate otomatis, scan langsung ke detail barang
- **Import/Export Excel** — Template, preview, rollback on failure
- **Filter Realtime AJAX** — Kombinasi 15+ filter
- **Soft Delete & Trash** — Restore barang yang dihapus
- **Audit Log** — Semua aktivitas tercatat
- **Operasi** — Peminjaman, Maintenance, Stock Opname, Mutasi, Persetujuan Hapus
- **Laporan** — Excel, PDF, CSV
- **REST API** — Siap integrasi mobile app
- **Dark Mode** — Toggle tema gelap/terang

## Instalasi

### Prasyarat

- PHP 8.3+
- Composer 2.x
- MySQL 8.0+ (production) atau SQLite (development)
- Extension PHP: `pdo`, `mbstring`, `xml`, `gd`, `zip`

### Langkah Instalasi

```bash
# 1. Clone / masuk ke folder project
cd inventaris-undira.gt.tc

# 2. Install dependencies
composer install

# 3. Copy environment
cp .env.example .env
php artisan key:generate

# 4. Konfigurasi database di .env
# MySQL:
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=inventaris_undira
DB_USERNAME=root
DB_PASSWORD=

# 5. Jalankan migration & seeder
php artisan migrate
php artisan db:seed

# 6. Buat symbolic link storage
php artisan storage:link

# 7. Jalankan server
php artisan serve
```

Buka `http://localhost:8000`

### Kredensial Default

| Role | Email | Password |
|------|-------|----------|
| Super Admin | azwar.syifa.mufiqi@undira.ac.id | `Undira@2026` |

> Super Admin dibuat otomatis via seeder. Tidak bisa didaftarkan melalui halaman Register.

## Struktur Folder

```
app/
├── Enums/              # InventoryStatus, InventoryCondition, AuditAction, dll.
├── Exports/            # Excel export & template
├── Http/
│   ├── Controllers/    # Web + API controllers
│   ├── Middleware/     # EnsureSuperAdmin, AuthenticateApiToken
│   └── Requests/       # Form validation
├── Imports/            # Excel import dengan preview
├── Models/             # Eloquent models (19 models)
├── Notifications/      # Warranty, Maintenance, Borrowing alerts
└── Services/           # AuditLog, InventoryCode, Filter, Notification

database/
├── migrations/         # 21+ migration files
└── seeders/            # Role, SuperAdmin, Campus, Category, Inventory

resources/views/
├── layouts/            # app.blade.php (AdminLTE), auth.blade.php
├── auth/               # login, register, forgot-password
├── dashboard/          # Statistik & grafik
├── locations/          # Kampus → Gedung → Lantai → Ruangan
├── inventory/          # CRUD, import, trash, scan QR
├── master/             # Kategori, Supplier, Vendor
├── operations/         # Peminjaman, Maintenance, Stock Opname, Mutasi
├── reports/            # Laporan
└── admin/              # Users, Audit Log, Settings, Kelola Lokasi

routes/
├── web.php             # Web routes dengan permission middleware
└── api.php             # REST API v1
```

## Flow Sistem

```
Login
  ↓
Pilih Kampus (Cibubur / Tanjung Duren / Greenville)
  ↓
Pilih Gedung (A, B, C, D)
  ↓
Pilih Lantai (L1–L4)
  ↓
Pilih Ruangan (Kelas, Lab, Gudang, dll.)
  ↓
Daftar Inventaris Ruangan (grouped by nama barang)
  ↓
Klik jumlah unit → Detail semua unit (Meja 001–020)
  ↓
Klik unit → Halaman detail lengkap + QR Code
```

## Hak Akses

### Super Admin
Semua permission: CRUD inventaris, import/export, kelola lokasi, users, settings, backup, approve deletion.

### Viewer
- Login, lihat data, search, filter, download laporan
- **Tidak bisa:** tambah, edit, hapus, import, export master, ubah setting

Permission dikelola via **Spatie Laravel Permission**.

## Import Excel

1. Login sebagai Super Admin
2. Menu **Data Inventaris** → **Import**
3. Download template Excel
4. Isi data sesuai kolom template
5. Upload → Preview → Proses
6. Sistem menampilkan jumlah berhasil/gagal + pesan error per baris
7. Rollback otomatis jika semua baris gagal

## Export

- **Excel** — `/inventory/export/excel`
- **CSV** — `/inventory/export/csv`
- **PDF** — `/inventory/export/pdf`
- **Label QR/Barcode** — Cetak massal dari halaman inventaris

## API Documentation

Base URL: `/api/v1`

### Autentikasi

```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "azwar.syifa.mufiqi@undira.ac.id",
  "password": "Undira@2026"
}
```

Response:
```json
{
  "token": "Bearer ...",
  "user": { "id": 1, "name": "...", "email": "..." }
}
```

Gunakan header `Authorization: Bearer {token}` untuk request berikutnya.

### Endpoints

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | `/api/v1/dashboard/stats` | Statistik dashboard |
| GET | `/api/v1/locations/campuses` | Daftar kampus |
| GET | `/api/v1/locations/campuses/{id}/buildings` | Gedung per kampus |
| GET | `/api/v1/locations/buildings/{id}/floors` | Lantai per gedung |
| GET | `/api/v1/locations/floors/{id}/rooms` | Ruangan per lantai |
| GET | `/api/v1/inventory` | Daftar inventaris (paginated) |
| GET | `/api/v1/inventory/{id}` | Detail inventaris |
| GET | `/api/v1/inventory/scan/{number}` | Scan QR by nomor inventaris |

## ERD (Entity Relationship Diagram)

Lihat [docs/ERD.md](docs/ERD.md) untuk diagram relasi lengkap.

### Relasi Utama

```
campuses 1──N buildings 1──N floors 1──N rooms 1──N inventory_items
categories 1──N sub_categories
categories 1──N inventory_items
inventory_items 1──N inventory_photos
inventory_items 1──N inventory_mutations
inventory_items 1──N borrowings
inventory_items 1──N maintenances
users N──N roles (Spatie)
audit_logs ── users
```

## Seeder Data

| Seeder | Data |
|--------|------|
| RolePermissionSeeder | 2 roles, 40+ permissions |
| SuperAdminSeeder | 1 super admin |
| CampusSeeder | 3 kampus, 12 gedung, 48 lantai, 312 ruangan |
| CategorySeeder | 11 kategori, 29 sub-kategori |
| SupplierVendorSeeder | 5 supplier, 4 vendor |
| InventorySeeder | 69+ item inventaris sample |

Reset database:
```bash
php artisan migrate:fresh --seed
```

## QR Code Scan

Setiap barang memiliki QR Code. URL publik:
```
/scan/{nomor_inventaris}
```
Contoh: `/scan/UND-CBB-A-L3-A302-MJ-0001`

## Backup & Restore

Super Admin → **Settings** → Backup/Restore database langsung dari panel admin.

## Lisensi

© 2026 Universitas Dian Nusantara — Bagian Sarana Prasarana
