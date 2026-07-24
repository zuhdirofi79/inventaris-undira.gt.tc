# Code Quality Report — Inventaris UNDIRA

**Date:** 2026-07-23

## Scores

| Dimension | Score | Comment |
|-----------|------:|---------|
| Consistency | 6.5 | FormRequest + inline validate mix |
| DRY | 6.0 | Vendor≈Supplier; status buckets duplicated |
| Test signal | 7.5 | ~45 Feature + 2 Unit; phase coverage strong |
| Complexity | 5.5 | God controllers/services |
| Typing / Enums | 8.0 | Good enum usage; terminal statuses added |
| Docs / ops | 7.0 | Phase docs exist; audit reports added |

## Hotspots (LOC)

| File | Approx LOC | Issue |
|------|-----------:|-------|
| `InventoryController` | ~830 | Browse + CRUD + relocate + datatable |
| `LocationExploreController` | ~440 | Aggregation + JSON tree |
| `DashboardStatsService` | ~480 | Many breakdown queries |
| `layouts/app.blade.php` | ~680 | Global chrome + scripts |
| Explore Blade | ~680 | Large inline JS |

## Duplication to eliminate next

1. VendorController ↔ SupplierController (+ twin FormRequests)
2. Damaged status lists → use `InventoryStatus::damagedValues()` everywhere (started)
3. Location cascade JS in Blade vs `inventory-selector.js`
4. API Dashboard string statuses vs enum helpers

## Test gaps

- API token auth / revoke
- Settings backup restore edge cases
- Queued export/import (once jobs exist)
- DeletionApproval full flow
- Damaged + Lelang/Musnah filter UX

## Applied quality fixes (this pass)

- `InventoryStatus::damagedValues()` / `terminalValues()`
- Explore `non_aktif` includes Hilang + Lelang + Musnah
- Observer single place for cache invalidation
- Notifications queue-ready (`ShouldQueue`)

## Definition of done for “refactored”

Do **not** rewrite everything at once. Done means:

1. Controllers thin (<150 LOC typical)
2. Every domain has Service + optional Repository interface
3. Mutating HTTP uses FormRequest + authorize/policy
4. Heavy I/O is queued
5. Feature tests cover each domain happy-path + permission deny
