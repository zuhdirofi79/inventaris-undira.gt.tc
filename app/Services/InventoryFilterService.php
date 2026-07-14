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
            ->when($filters['brand'] ?? null, fn (Builder $q, $brand) => $q->where('brand', 'like', "%{$brand}%"))
            ->when(filter_var($filters['borrowed'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->where('status', InventoryStatus::Dipinjam)
                    ->orWhereHas('borrowings', fn (Builder $b) => $b->where('status', BorrowingStatus::Dipinjam));
            }))
            ->when(filter_var($filters['damaged'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where(function (Builder $inner) {
                $inner->whereIn('status', [InventoryStatus::RusakRingan, InventoryStatus::RusakBerat])
                    ->orWhereIn('condition', [InventoryCondition::RusakRingan, InventoryCondition::RusakBerat]);
            }))
            ->when(filter_var($filters['maintenance'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where('status', InventoryStatus::Maintenance))
            ->when(filter_var($filters['lost'] ?? false, FILTER_VALIDATE_BOOLEAN), fn (Builder $q) => $q->where('status', InventoryStatus::Hilang));
    }

    public function extractFilters(Request $request): array
    {
        return [
            'search' => $request->input('search.value') ?? $request->input('search'),
            'campus_id' => $request->input('campus_id'),
            'building_id' => $request->input('building_id'),
            'floor_id' => $request->input('floor_id'),
            'room_id' => $request->input('room_id'),
            'category_id' => $request->input('category_id'),
            'sub_category_id' => $request->input('sub_category_id'),
            'status' => $request->input('status'),
            'condition' => $request->input('condition'),
            'brand' => $request->input('brand'),
            'supplier_id' => $request->input('supplier_id'),
            'vendor_id' => $request->input('vendor_id'),
            'purchase_year' => $request->input('purchase_year') ?? $request->input('year'),
            'price_min' => $request->input('price_min'),
            'price_max' => $request->input('price_max'),
            'purchase_date_from' => $request->input('purchase_date_from') ?? $request->input('date_from'),
            'purchase_date_to' => $request->input('purchase_date_to') ?? $request->input('date_to'),
            'warranty_expiring' => $request->input('warranty_expiring'),
            'warranty_days' => $request->input('warranty_days', 30),
            'borrowed' => $request->input('borrowed'),
            'damaged' => $request->input('damaged'),
            'maintenance' => $request->input('maintenance'),
            'lost' => $request->input('lost'),
            'group_key' => $request->input('group_key'),
        ];
    }
}
