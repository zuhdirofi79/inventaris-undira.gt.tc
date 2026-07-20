<?php

namespace App\Services;

use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class DashboardTreeService
{
    private const CACHE_TTL = 60;

    /**
     * @return list<array<string, mixed>>
     */
    public function campuses(): array
    {
        return Cache::remember($this->key('campuses'), self::CACHE_TTL, function () {
            return Campus::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Campus $campus) => $this->node(
                    type: 'campus',
                    id: $campus->id,
                    label: $campus->name,
                    code: $campus->code,
                    hasChildren: $campus->buildings()->where('is_active', true)->exists(),
                    stats: $this->statsForQuery(InventoryItem::query()->forCampus($campus->id), "campus:{$campus->id}"),
                    childrenUrl: route('dashboard.tree.buildings', $campus),
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function buildings(Campus $campus): array
    {
        return Cache::remember($this->key("buildings.{$campus->id}"), self::CACHE_TTL, function () use ($campus) {
            return $campus->buildings()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Building $building) => $this->node(
                    type: 'building',
                    id: $building->id,
                    label: $building->name,
                    code: $building->code,
                    hasChildren: $building->floors()->where('is_active', true)->exists(),
                    stats: $this->statsForQuery(InventoryItem::query()->forBuilding($building->id), "building:{$building->id}"),
                    childrenUrl: route('dashboard.tree.floors', $building),
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function floors(Building $building): array
    {
        return Cache::remember($this->key("floors.{$building->id}"), self::CACHE_TTL, function () use ($building) {
            return $building->floors()
                ->where('is_active', true)
                ->orderBy('floor_number')
                ->get()
                ->map(fn (Floor $floor) => $this->node(
                    type: 'floor',
                    id: $floor->id,
                    label: $floor->name,
                    code: $floor->code,
                    hasChildren: $floor->rooms()->where('is_active', true)->exists(),
                    stats: $this->statsForQuery(InventoryItem::query()->forFloor($floor->id), "floor:{$floor->id}"),
                    childrenUrl: route('dashboard.tree.rooms', $floor),
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rooms(Floor $floor): array
    {
        return Cache::remember($this->key("rooms.{$floor->id}"), self::CACHE_TTL, function () use ($floor) {
            return $floor->rooms()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (Room $room) => $this->node(
                    type: 'room',
                    id: $room->id,
                    label: $room->name,
                    code: $room->code,
                    hasChildren: InventoryItem::query()->inRoom($room->id)->exists(),
                    stats: $this->statsForQuery(InventoryItem::query()->inRoom($room->id), "room:{$room->id}"),
                    childrenUrl: route('dashboard.tree.categories', $room),
                ))
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(Room $room): array
    {
        return Cache::remember($this->key("categories.{$room->id}"), self::CACHE_TTL, function () use ($room) {
            $categoryIds = InventoryItem::query()
                ->inRoom($room->id)
                ->distinct()
                ->pluck('category_id');

            return Category::query()
                ->whereIn('id', $categoryIds)
                ->orderBy('name')
                ->get()
                ->map(function (Category $category) use ($room) {
                    $query = InventoryItem::query()
                        ->inRoom($room->id)
                        ->where('category_id', $category->id);

                    return $this->node(
                        type: 'category',
                        id: $category->id,
                        label: $category->name,
                        code: $category->code,
                        hasChildren: (clone $query)->exists(),
                        stats: $this->statsForQuery($query, "room:{$room->id}:category:{$category->id}"),
                        childrenUrl: route('dashboard.tree.items', [$room, $category]),
                    );
                })
                ->values()
                ->all();
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(Room $room, Category $category): array
    {
        return InventoryItem::query()
            ->inRoom($room->id)
            ->where('category_id', $category->id)
            ->with('subCategory')
            ->orderBy('inventory_number')
            ->limit(200)
            ->get()
            ->map(fn (InventoryItem $item) => [
                'type' => 'item',
                'id' => $item->id,
                'label' => $item->name,
                'code' => $item->inventory_number,
                'status' => $item->status?->value,
                'condition' => $item->condition?->value,
                'sub_category' => $item->subCategory?->name,
                'url' => route('inventory.show', $item),
                'has_children' => false,
                'stats' => null,
            ])
            ->values()
            ->all();
    }

    public static function flushCache(): void
    {
        Cache::increment('dashboard.tree.bust');
    }

    protected function key(string $suffix): string
    {
        $bust = (int) Cache::get('dashboard.tree.bust', 0);

        return "dashboard.tree.{$bust}.{$suffix}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function node(
        string $type,
        int $id,
        string $label,
        ?string $code,
        bool $hasChildren,
        array $stats,
        string $childrenUrl,
    ): array {
        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'code' => $code,
            'has_children' => $hasChildren,
            'stats' => $stats,
            'children_url' => $childrenUrl,
        ];
    }

    /**
     * @return array<string, int>
     */
    protected function statsForQuery(Builder $query, string $cacheKey): array
    {
        return Cache::remember($this->key("stats.{$cacheKey}"), self::CACHE_TTL, function () use ($query) {
            $aktif = InventoryStatus::Aktif->value;
            $dipinjam = InventoryStatus::Dipinjam->value;
            $maintenance = InventoryStatus::Maintenance->value;
            $rusakRingan = InventoryStatus::RusakRingan->value;
            $rusakBerat = InventoryStatus::RusakBerat->value;

            $row = (clone $query)
                ->toBase()
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = '{$dipinjam}' THEN 1 ELSE 0 END) as borrowed,
                    SUM(CASE WHEN status = '{$maintenance}' THEN 1 ELSE 0 END) as maintenance,
                    SUM(CASE WHEN status IN ('{$rusakRingan}', '{$rusakBerat}') THEN 1 ELSE 0 END) as damaged
                ")
                ->first();

            $deleted = (clone $query)->onlyTrashed()->count();

            $categoryCounts = (clone $query)
                ->join('categories', 'categories.id', '=', 'inventory_items.category_id')
                ->toBase()
                ->selectRaw("
                    SUM(CASE WHEN categories.code LIKE 'FUR%' THEN 1 ELSE 0 END) as furniture,
                    SUM(CASE WHEN categories.code IN ('ELK', 'JAR', 'IT') THEN 1 ELSE 0 END) as electronic
                ")
                ->first();

            return [
                'total' => (int) ($row->total ?? 0),
                'borrowed' => (int) ($row->borrowed ?? 0),
                'maintenance' => (int) ($row->maintenance ?? 0),
                'damaged' => (int) ($row->damaged ?? 0),
                'deleted' => (int) $deleted,
                'furniture' => (int) ($categoryCounts->furniture ?? 0),
                'electronic' => (int) ($categoryCounts->electronic ?? 0),
            ];
        });
    }
}
