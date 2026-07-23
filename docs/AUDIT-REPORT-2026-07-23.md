# Audit Menyeluruh — Inventaris UNDIRA

| Meta | Nilai |
|------|--------|
| **Tanggal** | 23 Juli 2026 |
| **Stack** | Laravel 13.19 · PHP 8.3 · Bootstrap 5 · Spatie Permission 8.3 |
| **Scope** | `app/`, `routes/`, `resources/views/`, `database/`, `tests/`, `public/css/` |
| **Metode** | Static analysis kode + `php artisan test` + `pint --test` + penelusuran route/model/migration |
| **Tes** | **170 passed** / 0 failed (868 assertions) |
| **Pint** | 23 file gagal style check (bukan bug runtime) |

> Laporan ini membedakan **bug nyata**, **risiko keamanan**, **utang desain**, dan **hal yang sengaja (by design)**.

---

## Ringkasan eksekutif

Project ini sudah melewati beberapa fase hardening (validasi, RBAC, audit, dashboard, master polish, inventory advanced, performance). Fondasi ERP-nya **kuat dan dapat diandalkan untuk development**, dengan **170 tes hijau**.

Titik lemah terbesar saat ini:

1. **Keamanan restore backup** (path traversal / overwrite DB) — Critical  
2. **Policy layer hampir mati** (hanya UserController yang `authorize`) — High  
3. **Dual SSOT kondisi inventaris** (enum item vs master `BAIK/LAYAK/RUSAK`) — High  
4. **Deletion approval tanpa guard status Pending** — High  
5. **Working tree besar belum di-commit** + orphan views / dead export class — Maintainability

**Skor keseluruhan: 7.2 / 10** (siap produksi setelah perbaikan Critical + High keamanan).

---

## Skor kualitas

| Dimensi | Skor | Catatan singkat |
|---------|------|-----------------|
| **Architecture** | **7.5** | Controllers → Services → Models jelas; FormRequest banyak; Policy underused; dual condition/year |
| **Security** | **6.0** | Spatie + CSRF bagus; backup restore berbahaya; public scan disclosure; audit leak di dashboard |
| **Performance** | **7.5** | Cache fase 8, index alert, AJAX lookup; queue masih `sync`; SQLite untuk prod perlu keputusan |
| **Maintainability** | **7.0** | Tes luas, locale `id`; orphan views; policies idle; diff uncommitted besar |
| **UI/UX** | **8.0** | Layer `undira-ui` + komponen Blade ERP; belum semua halaman migrasi penuh |
| **Code Quality** | **7.5** | Enums, FormRequest, 0 TODO; Pint style debt; beberapa inline validate |

```
Architecture     ████████░░  7.5
Security         ██████░░░░  6.0
Performance      ████████░░  7.5
Maintainability  ███████░░░  7.0
UI/UX            ████████░░  8.0
Code Quality     ████████░░  7.5
─────────────────────────────
Overall          ███████░░░  7.2
```

---

## 1. Bug

| ID | Severity | Temuan | Bukti | Status |
|----|----------|--------|-------|--------|
| B1 | **Critical** | Restore backup memakai input `backup_file` langsung di path storage tanpa `basename` / allowlist → risiko path traversal + overwrite DB SQLite | `SettingController::restore` | Open |
| B2 | **High** | Approve/reject deletion approval **tanpa cek status Pending** → bisa re-approve / re-reject | `DeletionApprovalController::approve/reject` | Open |
| B3 | **High** | Dashboard memuat `AuditLog::latest()` untuk **semua user auth**, tanpa `audit-log.view` | `DashboardController` | Open |
| B4 | **Medium** | Mapping kondisi inventaris heuristik (`baik`→`Bagus`, `layak`→`Cukup`) mudah drift dari master seed `BAIK/LAYAK/RUSAK` | `InventoryCreationService::mapCondition` | Open (desain) |
| B5 | **Medium** | API `InventoryController@store` masih bisa create item; web `store` hanya redirect → inkonsistensi produk | Web stub vs API | Open |
| B6 | **Low** | Kode inventaris fallback `'XXX'` jika master code kosong → nomor inventaris kotor | `InventoryCodeService` | Open |

**Tidak ditemukan:** route yang menunjuk method controller hilang; model tanpa referensi; SoftDeletes tanpa kolom `deleted_at`.

---

## 2. Warning & code smell

| ID | Severity | Temuan |
|----|----------|--------|
| W1 | Medium | Pint gagal di **23 file** (imports, braces, unused imports) |
| W2 | Medium | Index FK duplikat di banyak migration create (`foreignId` + `$table->index` yang sama) |
| W3 | Medium | `InventorySequence.classifier_type/id` tanpa FK / morphTo |
| W4 | Medium | `stock_opname_items.status`, `inventory_attachments.type` — enum DB tanpa PHP enum/cast |
| W5 | Low | `optional()` legacy di DashboardController |
| W6 | Low | Alias middleware `verified` & `super-admin` terdaftar tapi **tidak dipakai** di route |
| W7 | Low | `APP_DEBUG=ENABLED` di environment local (normal); pastikan OFF di production |
| W8 | Info | Queue driver `sync` — alert terjadwal jalan sinkron saat cron |

---

## 3. TODO / komentar utang

| Hasil | Detail |
|-------|--------|
| **TODO / FIXME / HACK / WIP** | **0** di `app/`, `routes/`, `resources/`, `tests/` |
| `XXX` | Hanya placeholder kode inventaris di `InventoryCodeService` (bukan TODO) |

---

## 4. Fitur belum selesai / stub

| Item | Jenis | Catatan |
|------|-------|---------|
| `InventoryController@create/store` | Stub redirect | **By design** → Konfigurasi Inventaris |
| `inventory.import.index` | Closure redirect | Index UI import web dinonaktifkan; `preview`/`process` masih hidup |
| `resources/views/inventory/import.blade.php` | Orphan view | Tidak di-return controller manapun |
| `resources/views/inventory/create.blade.php` | Orphan view | Create lewat Konfigurasi |
| `resources/views/inventory/trash.blade.php` | Orphan view | Trash lewat `trash.index` |
| Deletion approval → soft-delete saja | Product gap | Approve memanggil `delete()` SoftDeletes, bukan force-delete |
| Policies domain (Borrowing, Maintenance, dll.) | Dead defense | Terdaftar di `AppServiceProvider`, hampir tidak dipanggil |
| API token cache 30 hari | Incomplete hardening | Tidak revoke otomatis saat password/role change |
| Email verification di API login | Gap | Web cek verifikasi; API hanya `is_active` |

---

## 5. Route mati / orphan

| Item | Verdict |
|------|---------|
| Route → method hilang | **0** |
| Controller hidup tanpa route | **0** |
| `ResetPasswordController` | **Dihapus** dari working tree; diganti OTP `ForgotPasswordController` — commit penghapusan |
| `InventoryExport` (Maatwebsite class) | **Dihapus** dari disk; diganti `InventoryHierarchicalExport` — commit penghapusan |
| Named routes | ~190 named / ~205 total |
| Unnamed notable | `POST /login`, `POST /register`, sebagian API |

**Bukan mati (by design):** mutations tanpa edit/destroy; stock opname tanpa classic update; audit log read-only; lokasi browse read-only.

---

## 6. Controller tidak dipakai

**Tidak ada** controller hidup yang tidak ter-wire ke `web.php` / `api.php`.

Legacy yang perlu dibersihkan dari git:

- `app/Http/Controllers/Auth/ResetPasswordController.php` (deleted, belum committed)

---

## 7. Model tidak dipakai

**Tidak ada.** 27 model di `app/Models/` semua direferensikan (controller / service / seeder / policy / test).

Factory hanya `UserFactory` — model lain tanpa factory (bukan unused).

---

## 8. Migration bermasalah

| Issue | Severity | Detail |
|-------|----------|--------|
| Index FK duplikat | Medium | Banyak create migration: `foreignId` lalu `index()` ulang |
| `->change()` nullable stock opname | Medium | Butuh driver support; tanpa `doctrine/dbal` di composer (Laravel 11+ native change — verifikasi driver) |
| Defensive `Schema::hasColumn` | Medium | Menandakan riwayat drift / re-run |
| IMC tanpa unique DB | High (data) | Uniqueness hanya Form Request → duplikat soft-delete-safe di DB masih mungkin |
| Phase 8 indexes | Info | File `2026_07_23_100000_add_phase8_alert_indexes.php` masih **untracked** — pastikan di-migrate di semua environment |

**SoftDeletes tanpa `deleted_at`:** tidak ditemukan.

---

## 9. Relasi database

| Severity | Temuan |
|----------|--------|
| High | SoftDeletes **tanpa** `TracksTrashMeta` / tab Trash: `Borrowing`, `RoomFunction`, `PurchaseYear`, `InventoryConditionMaster` |
| High | Dual condition: `inventory_items.condition` enum vs `inventory_conditions` (BAIK/LAYAK/RUSAK) |
| High | Dual purchase year: kolom int item vs master `purchase_years` |
| Medium | `User` tanpa inverse relations (`borrowings`, `mutations`, dll.) |
| Medium | Parent lokasi/master kurang inverse `configurations()` / mutation |
| Medium | `InventorySequence` pseudo-morph tanpa constraint |
| Low | Tidak ada `morphMany` audit di subject models |

FK utama (room, category, mutations from/to room) **terlihat benar**.

---

## 10. Validasi yang kurang

| Severity | Temuan |
|----------|--------|
| High | `ImportMasterRequest::authorize()` hanya cek user login (permission diandalkan ke route middleware saja) |
| Medium | Inline `$request->validate` masih di: Settings, SupportingMaster, Report, Scanner, QR labels, DeletionApproval reject, IMC preview/storeOption |
| Medium | `User` fillable mencakup `email_verified_at`, `is_active` — permukaan mass-assignment lebar (mitigasi: create/update saat ini eksplisit) |
| Low | Guest FormRequest `authorize() return true` — expected |

**Positif:** tidak ditemukan `$request->all()` langsung ke `create`/`update` di controller.

---

## 11. Permission yang tidak bekerja / lemah

| Severity | Temuan |
|----------|--------|
| High | Domain policies terdaftar tapi **hanya User** yang `$this->authorize()` — layer policy tidak aktif untuk inventaris/ops |
| High | `CategoryController@index` load data semua tab berdasarkan query string; permission OR di route — UI `@can` saja, server bisa query tab yang user tidak punya |
| Medium | `report.export` dicek di dalam controller, bukan di route middleware |
| Medium | Dashboard tree hierarchy: `auth` only (tanpa `location.view`) |
| Medium | `deletion-approvals.reject` memakai permission `deletion-approval.approve` (naming mismatch) |
| Medium | `verified` middleware tidak diterapkan ke grup auth |
| OK | Seeder Spatie selaras nama permission utama; orphan legacy dibersihkan di seeder |

---

## 12. CRUD belum lengkap

| Resource | Gap | Verdict |
|----------|-----|---------|
| Mutations | No edit/update/destroy | **Intentional** (append-only) |
| Stock opname | No classic edit; ada start/complete/scan | **Intentional** |
| Deletion approvals | Workflow approve/reject | **Intentional** |
| Inventory create (web) | Redirect ke Konfigurasi | **Intentional** |
| Master hub | Modal CRUD, bukan halaman create/edit terpisah | **Intentional** |
| Location browse | Read-only; write di admin/locations | **Intentional** |
| Soft-delete masters kecil | Tidak di Trash UI | **Gap** |

---

## 13. Halaman yang menghasilkan error

| Check | Hasil |
|-------|-------|
| Feature tests (dashboard, inventory, master, ops, RBAC, trash, scan, performance, UI shell) | **170/170 hijau** |
| Route → controller method | Semua resolve |
| Halaman orphan (`import`/`create`/`trash` inventaris) | Tidak di-serve → **tidak error**, tapi dead UI |
| Runtime browser penuh semua role × semua halaman | **Belum dilakukan** di audit ini |

Kesimpulan: tidak ada indikasi halaman inti “putih/500” dari coverage tes. Residual risk: halaman admin settings restore (setelah input jahat), dan edge case tab Master tanpa permission ketat.

---

## 14. Temuan keamanan tambahan

| Severity | Temuan |
|----------|--------|
| High | Public QR scan (`InventoryQrController@scan`) tanpa auth — ekspos nama, lokasi penuh, kondisi, status, brand (sebagian disengaja + ada tes privacy) |
| High | API login tanpa email verification; token opaque di Cache 30 hari |
| Medium | Upload ke disk `public` — URL dapat ditebak jika path diketahui (validasi mime/size ada) |
| Low | Tidak ada `{!! !!}` XSS sink di Blade |
| Low | Tidak ada CSRF exception / `withoutMiddleware` mencurigakan |
| OK | Photo/attachment destroy cek parent `inventory_item_id` (IDOR mitigated) |

---

## 15. Inventaris aset teknis

| Aset | Jumlah (approx) |
|------|-----------------|
| Named routes | ~190 |
| Controllers (concrete) | ~43 (+ base) |
| Models | 27 |
| Migrations | 36 |
| Feature tests | 34 file / 170 cases |
| UI components baru | `x-ui.*` + `undira-ui.css` |

---

## Roadmap perbaikan

### Critical (segera)

1. **Harden `SettingController::restore`**
   - `basename()` + allowlist file di folder `backups/`
   - Tolak `..`, path absolut, ekstensi non-sqlite
   - Tambah feature test path traversal
2. **Guard deletion approval**
   - `abort_unless($deletionApproval->status === Pending)`
   - Pastikan approve/reject idempotent
3. **Commit / migrate phase-8 indexes** di semua environment + bersihkan deleted files (`ResetPasswordController`, `InventoryExport`)

### High (sprint berikutnya)

4. Sembunyikan / gate aktivitas audit di dashboard dengan `audit-log.view`
5. Enforce per-tab permission di `CategoryController@index`
6. Wire `$this->authorize()` di controller domain **atau** hapus policies idle (pilih satu strategi)
7. Samakan SSOT kondisi inventaris (satu enum/master; hilangkan fuzzy map)
8. Harden API auth: verifikasi email + invalidate token saat password/role change
9. Putuskan produk: deletion approve = soft-delete vs force-delete + dokumentasikan
10. Review ulang public scan disclosure (redact lokasi/brand jika perlu)

### Medium

11. FormRequest untuk sisa inline validate (Settings, Report, Scanner, SupportingMaster, reject reason)
12. SoftDeletes masters (`RoomFunction`, `PurchaseYear`, `InventoryCondition`, `Borrowing`) → Trash meta **atau** dokumentasi “soft-only”
13. Constraint/unique IMC di DB (partial unique jika didukung)
14. Hapus orphan views (`inventory/create`, `import`, `trash`) atau reconnect UI
15. Bersihkan index FK duplikat di migration baru (jangan rewrite history tanpa hati-hati)
16. Terapkan `verified` middleware pada grup authenticated jika OTP wajib
17. Jalankan Pint + CI style gate
18. Putuskan driver DB produksi (SQLite vs MySQL) + queue `database`/`redis`

### Low

19. Hapus alias middleware tidak terpakai atau pakai
20. Ganti `optional()` → nullsafe
21. PHP enum untuk `StockOpnameItem.status` & `InventoryAttachment.type`
22. Inverse relations di `User` / parent models untuk eager-load
23. Migrasi sisa page-header ke `x-ui.page-header`
24. Factories untuk model domain (percepat tes)

---

## Rekomendasi keputusan produk (bukan bug teknis)

| Topik | Opsi |
|-------|------|
| Public QR scan | Tetap publik (privacy terbatas) vs wajib login |
| Inventory create API | Matikan seperti web, atau dokumentasikan sebagai channel resmi |
| Import inventaris web | Hidupkan UI lagi vs hapus route preview/process |
| Backup/restore | Hanya Super Admin + konfirmasi password + audit log wajib |

---

## Lampiran: checklist verifikasi audit

- [x] Scan TODO/FIXME
- [x] Route ↔ controller method
- [x] Controllers unused
- [x] Models unused
- [x] SoftDeletes ↔ `deleted_at`
- [x] Permission seeder ↔ route middleware (spot)
- [x] Policies ↔ authorize usage
- [x] Full test suite (170)
- [x] Pint style sample
- [ ] Manual smoke semua halaman × semua role di browser
- [ ] Penetration test restore backup di staging
- [ ] Load test dashboard dengan data besar

---

*Generated for Universitas Dian Nusantara — Inventaris UNDIRA · 2026-07-23*
