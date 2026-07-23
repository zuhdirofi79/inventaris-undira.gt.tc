# Laporan Penyempurnaan Inventaris UNDIRA

**Tanggal:** 23 Juli 2026  
**Prinsip:** Arsitektur lama dipertahankan; fokus penyempurnaan fitur EAM/ERP.

---

## 1. File yang diubah (utama)

### Barcode dihapus / QR diperkuat
- `app/Services/InventoryCodeService.php`
- `app/Http/Controllers/InventoryQrController.php`
- `app/Observers/InventoryItemObserver.php`
- `app/Models/InventoryItem.php` (hapus `barcode_url` append)
- `routes/web.php` (hapus route barcode)
- `resources/views/inventory/show.blade.php`
- `resources/views/inventory/print-label.blade.php`
- `resources/views/inventory/scan.blade.php`
- `resources/views/inventory/group-units.blade.php`
- `resources/views/inventory/_form.blade.php`
- `resources/views/operations/scanner/index.blade.php`
- `resources/views/layouts/app.blade.php`
- `public/js/undira-loading.js`
- `composer.json` / `composer.lock` (remove Picqer)

### Dashboard & history
- `app/Services/DashboardStatsService.php`
- `app/Http/Controllers/DashboardController.php`
- `resources/views/dashboard/index.blade.php`
- `app/Services/InventoryHistoryService.php` (**baru**)

### Activity / audit
- `app/Enums/AuditAction.php`
- `app/Services/AuditLogService.php`
- `app/Services/TrashCascadeService.php`
- `app/Http/Controllers/MutationController.php`
- `app/Http/Controllers/Auth/ForgotPasswordController.php`
- `app/Http/Controllers/Auth/EmailVerificationController.php`
- `app/Http/Controllers/DeletionApprovalController.php`
- `database/migrations/2026_07_23_120000_expand_audit_and_attachment_types.php`

### Mutasi lokasi (tanpa edit manual room)
- `app/Http/Requests/UpdateInventoryRequest.php`
- `resources/views/inventory/edit.blade.php`

### Import / backup / dokumen / filter
- `app/Http/Controllers/InventoryImportController.php` (`index` dihidupkan)
- `app/Http/Controllers/SettingController.php` (unduh backup + harden restore)
- `resources/views/admin/settings/index.blade.php`
- `app/Http/Requests/StoreInventoryAttachmentRequest.php`
- `app/Services/InventoryFilterService.php`
- `app/Http/Controllers/InventoryController.php`
- `resources/views/inventory/items.blade.php`
- `resources/views/inventory/index.blade.php`
- `resources/views/components/inventory-global-filters.blade.php` (**baru**)

### Tes
- `tests/Feature/InventoryQrAndDashboardTest.php`
- `tests/Feature/InventoryMasterConfigurationTest.php`

---

## 2. Fitur yang ditambahkan / diperkuat

| # | Fitur | Status |
|---|--------|--------|
| 1 | Hapus Barcode (UI, generate, route, library) | Selesai |
| 2 | QR otomatis berisi **URL** `/scan/{kode}` ke halaman detail | Sudah + diperjelas |
| 3 | Halaman scan publik responsif (foto, lokasi lengkap, riwayat) | Selesai |
| 4 | Dashboard KPI: Unit Kerja, Kategori, Subkategori, User (+ yang sudah ada) | Selesai |
| 4b | Chart: Kondisi + Unit Kerja | Selesai |
| 5 | Activity log diperluas (Generate QR, Mutasi, Force Delete, Reset Password, Verifikasi Email) + role di meta | Selesai |
| 6 | Mutasi tetap channel resmi; edit inventaris **tidak** mengubah `room_id` | Selesai |
| 7 | Timeline inventaris di halaman show | Selesai |
| 8 | Import Excel UI dihidupkan kembali (`/inventory/import`) | Selesai |
| 9 | Export Excel/CSV/PDF (sudah ada) + tombol di index | Dipertahankan |
| 10 | Filter Unit Kerja di daftar item + komponen filter global reusable | Selesai |
| 11 | Foto utama + tambahan (sudah ada fase 7) | Dipertahankan |
| 12 | Tipe dokumen: Invoice, Manual Book, Garansi, Dokumen Pendukung | Selesai |
| 13 | Backup: buat + **unduh**; daftar file; restore allowlist aman | Selesai |
| 14 | Audit trail kondisi/status muncul di timeline | Selesai |
| 15 | Eager load timeline; gate aktivitas dashboard dengan `audit-log.view` | Selesai |

---

## 3. Fitur yang dihapus (Barcode)

- Generate barcode PNG (Picqer)
- Route `inventory.barcode` & `inventory.barcode.download`
- Tombol Unduh Barcode / kartu barcode di detail
- Barcode pada label cetak
- Kolom barcode di group-units
- Label menu “Scan Barcode” → **Scan QR**
- Library `picqer/php-barcode-generator`

**Tidak dihapus:** kolom DB `inventory_items.barcode` (data lama aman; lookup legacy masih bisa lewat scan).

---

## 4. Library

| Aksi | Package |
|------|---------|
| **Dihapus** | `picqer/php-barcode-generator` |
| **Tetap** | `simplesoftwareio/simple-qrcode`, Maatwebsite Excel, DomPDF, Spatie Permission |
| **Ditambah** | Tidak ada package baru |

---

## 5. Query yang dioptimalkan

- Dashboard summary menambah agregasi `COUNT` untuk org unit / kategori / subkategori / user (cached).
- Chart kondisi & unit kerja via `GROUP BY` (bukan loop PHP).
- Inventory show: eager load mutasi/maintenance/borrowing + audit untuk timeline.
- Scan publik: eager load lokasi + foto + riwayat terbatas.
- Filter inventaris: dukungan `org_unit_id` di `scopeFilter`.

---

## 6. Cara pengujian

### QR & tanpa barcode
1. Buat inventaris via **Konfigurasi Inventaris**.
2. Buka detail → pastikan ada QR, **tidak** ada Unduh Barcode.
3. Cetak label → hanya QR.
4. Buka URL di QR (atau `/scan/{nomor}`) tanpa login → halaman detail lengkap.
5. Login dengan izin `inventory.view` → scan redirect ke show.

### Dashboard
1. Buka Dashboard sebagai Super Admin.
2. Cek KPI: Total Inventaris, Kampus, Gedung, Lantai, Ruangan, Unit Kerja, Kategori, Subkategori, User.
3. Cek chart Kondisi & Unit Kerja.

### Mutasi & timeline
1. Edit inventaris → lokasi terkunci; link ke Mutasi.
2. Buat mutasi → lokasi berubah; timeline menampilkan “Dipindah”.
3. Ubah kondisi → timeline menampilkan perubahan audit.

### Import / export
1. `/inventory/import` → unduh template → preview → process.
2. Filter di Semua Item → Export Excel/CSV/PDF.

### Backup
1. Settings → **Buat & Unduh Backup**.
2. Restore hanya dari dropdown file `backup_*.sqlite` (path traversal ditolak).

### Activity log
1. Login/logout, generate QR, mutasi, reset password OTP, verifikasi email.
2. Admin → Audit Log / dashboard aktivitas (butuh `audit-log.view`).

### Otomatis
```bash
php artisan migrate
php artisan test
```
(Key suite terkait QR/Dashboard/Master/Mutasi sudah hijau pada penyempurnaan ini.)

---

## Catatan deploy

1. `composer install` (lock tanpa Picqer).
2. `php artisan migrate` (audit action string + attachment types).
3. Hard-refresh browser (`Ctrl+F5`) untuk CSS/JS.
4. Pastikan `php artisan storage:link` untuk foto/QR.
5. Set `inventory.scan_base_url` di Settings jika domain produksi berbeda dari `APP_URL`.

---

*Inventaris Universitas Dian Nusantara — penyempurnaan EAM tanpa ganti arsitektur inti.*
