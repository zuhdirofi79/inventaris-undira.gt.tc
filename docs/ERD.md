# ERD — Sistem Inventaris UNDIRA

## Diagram Relasi

```mermaid
erDiagram
    USERS ||--o{ AUDIT_LOGS : performs
    USERS ||--o{ INVENTORY_ITEMS : creates
    USERS ||--o{ INVENTORY_MUTATIONS : moves
    USERS ||--o{ BORROWINGS : approves
    USERS ||--o{ STOCK_OPNAMES : creates
    USERS ||--o{ DELETION_APPROVALS : requests

    CAMPUSES ||--o{ BUILDINGS : contains
    BUILDINGS ||--o{ FLOORS : contains
    FLOORS ||--o{ ROOMS : contains
    ROOMS ||--o{ INVENTORY_ITEMS : stores

    CATEGORIES ||--o{ SUB_CATEGORIES : has
    CATEGORIES ||--o{ INVENTORY_ITEMS : classifies
    SUB_CATEGORIES ||--o{ INVENTORY_ITEMS : sub_classifies

    SUPPLIERS ||--o{ INVENTORY_ITEMS : supplies
    VENDORS ||--o{ INVENTORY_ITEMS : provides
    VENDORS ||--o{ MAINTENANCES : services

    INVENTORY_ITEMS ||--o{ INVENTORY_PHOTOS : has
    INVENTORY_ITEMS ||--o{ INVENTORY_ATTACHMENTS : has
    INVENTORY_ITEMS ||--o{ INVENTORY_MUTATIONS : history
    INVENTORY_ITEMS ||--o{ BORROWINGS : borrowed
    INVENTORY_ITEMS ||--o{ MAINTENANCES : serviced
    INVENTORY_ITEMS ||--o{ STOCK_OPNAME_ITEMS : audited
    INVENTORY_ITEMS ||--o{ DELETION_APPROVALS : pending_delete

    STOCK_OPNAMES ||--o{ STOCK_OPNAME_ITEMS : contains
    CAMPUSES ||--o{ STOCK_OPNAMES : scoped_to
```

## Format Nomor Inventaris

```
UND-{KAMPUS}-{GEDUNG}-L{LANTAI}-{RUANGAN}-{KATEGORI}-{SEQ}
Contoh: UND-CBB-A-L3-A302-MJ-0001
```

## Normalisasi (3NF)

- **1NF:** Semua kolom atomic
- **2NF:** Lokasi dinormalisasi ke campus → building → floor → room
- **3NF:** Supplier, vendor, kategori terpisah

## Soft Delete

campuses, buildings, floors, rooms, categories, sub_categories, suppliers, vendors, inventory_items, borrowings
