<?php

namespace App\Services;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class InventoryFilterService
{
    public function apply(Request $request, ?Builder $query = null): Builder
    {
        $query ??= InventoryItem::query();

        $filters = $this->extractFilters($request);

        return $query
            ->filter($filters)
            ->when(filter_var($filters['include_trash'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->withTrashed())
            ->when(filter_var($filters['only_trash'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->onlyTrashed())
            ->when($filters['brand'] ?? null, function (Builder $q, $brand) {
                $q->where('brand', 'like', '%'.$brand.'%');
            })
            ->when($filters['inventory_number'] ?? null, fn (Builder $q, $v) => $q->where('inventory_number', 'like', '%'.$v.'%'))
            ->when($filters['name'] ?? null, fn (Builder $q, $v) => $q->where('name', 'like', '%'.$v.'%'))
            ->when($filters['serial_number'] ?? null, fn (Builder $q, $v) => $q->where('serial_number', 'like', '%'.$v.'%'))
            ->when($filters['procurement_type_id'] ?? null, fn (Builder $q, $id) => $q->where('procurement_type_id', (int) $id))
            ->when(filter_var($filters['borrowed'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('status', InventoryStatus::Dipinjam)
                    ->orWhereHas('borrowings', fn (Builder $b) => $b->whereIn('status', [
                        BorrowingStatus::Dipinjam,
                        BorrowingStatus::Disetujui,
                        BorrowingStatus::Terlambat,
                    ]));
            }))
            ->when(filter_var($filters['damaged'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->whereIn('status', InventoryStatus::damagedValues())
                    ->orWhereIn('condition', [
                        InventoryCondition::RusakRingan->value,
                        InventoryCondition::RusakBerat->value,
                    ]);
            }))
            ->when(filter_var($filters['maintenance'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->whereIn('status', [
                InventoryStatus::Maintenance->value,
                InventoryStatus::MenungguApproval->value,
                InventoryStatus::RevisiPerbaikan->value,
            ]))
            ->when(filter_var($filters['inactive'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->whereIn('status', array_merge(
                InventoryStatus::terminalValues(),
                [
                    InventoryStatus::TidakDapatDiperbaiki->value,
                    InventoryStatus::PerluPenggantian->value,
                ]
            )))
            ->when(filter_var($filters['lost'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where('status', InventoryStatus::Hilang));
    }

    /**
     * @return array<string, mixed>
     */
    public function extractFilters(Request $request): array
    {
        $brand = $request->input('merk') ?? $request->input('brand');

        return [
            // DataTables sends search[value]; empty string becomes null via ConvertEmptyStringsToNull,
            // so never fall back to the whole search[] array (would TypeError in scopeSearch).
            'search' => $this->normalizeSearchTerm($request),
            'keyword' => $request->input('keyword'),
            'campus_id' => $request->input('campus_id'),
            'building_id' => $request->input('building_id'),
            'floor_id' => $request->input('floor_id'),
            'room_id' => $request->input('room_id'),
            'category_id' => $request->input('category_id'),
            'sub_category_id' => $request->input('sub_category_id'),
            'org_unit_id' => $request->input('org_unit_id'),
            'pic_id' => $request->input('pic_id'),
            'status' => $request->input('status'),
            'condition' => $request->input('condition'),
            'brand' => $brand,
            'merk' => $brand,
            'inventory_number' => $request->input('inventory_number'),
            'name' => $request->input('name') ?? $request->input('item_name'),
            'serial_number' => $request->input('serial_number'),
            'supplier_id' => $request->input('supplier_id'),
            'vendor_id' => $request->input('vendor_id'),
            'procurement_type_id' => $request->input('procurement_type_id'),
            'purchase_year' => $request->input('purchase_year') ?? $request->input('year') ?? $request->input('tahun_pengadaan'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'purchase_date_from' => $request->input('purchase_date_from') ?? $request->input('date_from') ?? $request->input('tanggal_awal'),
            'purchase_date_to' => $request->input('purchase_date_to') ?? $request->input('date_to') ?? $request->input('tanggal_akhir'),
            'warranty_expiring' => $request->input('warranty_expiring'),
            'warranty_days' => $request->input('warranty_days', 30),
            'borrowed' => $request->input('borrowed'),
            'damaged' => $request->input('damaged'),
            'maintenance' => $request->input('maintenance'),
            'inactive' => $request->input('inactive'),
            'lost' => $request->input('lost'),
            'group_key' => $request->input('group_key'),
            'include_trash' => $request->boolean('include_trash'),
            'only_trash' => $request->boolean('only_trash'),
        ];
    }

    /**
     * Normalize DataTables / plain search input to a nullable string.
     */
    protected function normalizeSearchTerm(Request $request): ?string
    {
        $term = $request->input('search.value');

        if ($term === null && ! is_array($request->input('search'))) {
            $term = $request->input('search');
        }

        if ($term === null) {
            $term = $request->input('keyword');
        }

        if (is_array($term)) {
            $term = $term['value'] ?? null;
        }

        if (! is_string($term)) {
            return null;
        }

        $term = trim($term);

        return $term === '' ? null : $term;
    }
}