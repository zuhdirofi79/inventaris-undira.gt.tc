# Architecture Report — Inventaris UNDIRA

**Date:** 2026-07-23  
**Scope:** Full codebase audit for 100,000+ inventory readiness  
**Verdict:** Service-heavy, repository-thin. Suitable foundation; not yet fully layered for scale.

## Target architecture

```
HTTP (Controller + FormRequest + Policy)
        ↓
    Application Service
        ↓
    Repository (query boundary)
        ↓
    Eloquent Model + Observer + Events
        ↓
    Queue Jobs / Notifications / AuditLog
```

## Current layer map

| Layer | Count | Status |
|-------|------:|--------|
| Controllers | 49 | Mixed fat/thin; `InventoryController` ~822 LOC |
| Services | 20 | Strong; primary business logic home |
| Repositories | 1 | Only `InventoryRepository` (no interfaces) |
| Policies | 9 | Registered; underused vs Spatie route middleware |
| Middleware | 4 custom + Spatie | Solid route gating |
| Notifications | 7 | Now `ShouldQueue` (async-ready) |
| Observers | 1 | QR + **cache bust** on inventory mutations |
| Events / Listeners | 0 | Missing — recommended next |
| Queue Jobs | 0 | **Gap** for import/export at 100k |

## Domain coverage gaps (Repository Pattern)

Present: Inventory search/location cascade.  
Missing dedicated repos: Borrowing, Maintenance, Mutation, StockOpname, Location masters, AuditLog, Trash, Reports.

## Recommended phased refactor (do not big-bang)

1. **P0 (done in this audit pass):** join/subquery location scopes, status helpers, cache observer, scale indexes, queueable notifications, byCategory hard limit.
2. **P1:** Extract `InventoryBrowseController` from fat controller; add `ExportInventoryJob` / `ImportInventoryJob`.
3. **P2:** Repository interfaces + bindings; Events (`InventoryStatusChanged`, `InventoryRelocated`) → Listeners (audit, notify, cache).
4. **P3:** Unify Vendor/Supplier via shared master CRUD trait/service; extract explore JS to `public/js`.

## Activity log

Central writer: `AuditLogService`. Explicit calls from controllers/services (good auditability). No automatic model observer for all CRUD — intentional.

## Policy vs permission

Spatie `permission:` middleware is the primary gate. Policies exist for core ops but controllers rarely call `$this->authorize`. Acceptable for single-tenant campus ERP; add object-level policies only if multi-tenant scoping is required.
