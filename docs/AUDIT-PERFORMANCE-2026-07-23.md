# Performance Report — Inventaris UNDIRA (100k+ target)

**Date:** 2026-07-23  
**Target:** ≥ 100,000 `inventory_items` rows with interactive dashboard + explore.

## Scorecard

| Area | Score | Notes |
|------|------:|-------|
| Indexes (inventory) | 8/10 | Core + composite present; FK/scale indexes added |
| Aggregate queries | 8/10 | Dashboard uses GROUP BY + 60s cache |
| Hot list paths | 6/10 | Explore paginated; category browse was unbounded (capped) |
| Cache invalidation | 8/10 | Observer now flushes on status/room/category changes |
| Background jobs | 2/10 | No export/import jobs yet |
| Blade/JS weight | 5/10 | Large inline explore/campus scripts |

## Critical risks (remaining)

1. **Sync export/import** — `InventoryReportService` loads up to 5k rows; no queue. At 100k, must stream/chunk + job.
2. **Explore search** — nested `orWhereHas` + `LIKE %q%` will degrade; prefer column search + join.
3. **Dashboard tree cold path** — per-node `exists` + stats (if used) still costly; prefer explore-style grouped counts.
4. **Leading-wildcard name search** — B-tree cannot help; plan FULLTEXT / Meilisearch for product search.

## Applied in this pass

- `scopeForCampus|Building|Floor` → `whereIn` subqueries (no nested `whereHas` EXISTS chains).
- `InventoryItemObserver` cache bust on create/update(relevant)/delete/restore/forceDelete.
- `byCategory` hard limit 500 + `truncated` flag.
- Migration `2026_07_23_220000_add_scale_indexes_to_inventory_items` (supplier, vendor, org_unit, procurement, created_by, updated_by, purchase_year, deleted_by).
- Notifications implement `ShouldQueue`.

## Index inventory (inventory_items)

**Already present:** inventory_number UNIQUE, status, condition, room_id, category_id, sub_category_id, group_key, deleted_at, barcode, created_at, serial_number, warranty_expiry, composites `(room_id,status)`, `(room_id,category_id,sub_category_id,status)`.

**Added:** FK filter indexes listed above.

## Guidance for 100k

| Feature | Pattern |
|---------|---------|
| Lists | Always paginate ≤ 50–100 |
| Dashboards | Cached COUNT/GROUP BY only |
| Trees | Lazy AJAX per level (Explore model) |
| Export | Queued job + cursor/chunk |
| Import | Chunked + code maps preload |
| Search | Indexed columns first; async for fuzzy |

## Cache

`InventarisCache` bust token + 60s TTL. Keys: `dashboard.summary`, `campus.stats`, location/dashboard trees. Flush via `DashboardTreeService::flushCache()` / observer.
