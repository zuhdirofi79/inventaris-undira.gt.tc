<?php

namespace App\Services;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Support\InventarisCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Single source of truth for active location hierarchy (Kelola Lokasi master data).
 * Used by Jelajahi-related consumers, inventory forms/filters, and cascade JSON.
 */
class LocationHierarchyService
{
    /**
     * Full active campus → building → floor → room tree for cascade dropdowns.
     *
     * Always returns a Collection — never null, incomplete class, or raw arrays
     * from a stale/corrupt cache entry (those previously caused HTTP 500 TypeErrors
     * on inventory filter pages).
     *
     * @return Collection<int, Campus>
     */
    public function activeTree(?int $campusId = null): Collection
    {
        $cacheKey = InventarisCache::key('location.tree.'.($campusId ?? 'all'));

        $cached = Cache::get($cacheKey);
        if ($cached instanceof Collection) {
            return $cached;
        }

        // Stale / corrupt cache (null miss, array, __PHP_Incomplete_Class, etc.)
        if ($cached !== null) {
            Cache::forget($cacheKey);
        }

        $tree = $this->buildActiveTree($campusId);

        Cache::put($cacheKey, $tree, InventarisCache::TTL);

        return $tree;
    }

    /**
     * @return Collection<int, Campus>
     */
    protected function buildActiveTree(?int $campusId = null): Collection
    {
        $query = Campus::query()
            ->where('is_active', true)
            ->with([
                'buildings' => fn ($q) => $q->where('is_active', true)->orderBy('name')
                    ->with([
                        'floors' => fn ($q) => $q->where('is_active', true)->orderBy('floor_number')
                            ->with([
                                'rooms' => fn ($q) => $q->where('is_active', true)->orderBy('name'),
                            ]),
                    ]),
            ])
            ->orderBy('name');

        if ($campusId) {
            $query->where('id', $campusId);
        }

        $tree = $query->get();

        if ($tree->isEmpty()) {
            return collect();
        }

        // Avoid N+1 from Room::$appends full_location when serializing to JSON.
        $tree->each(function (Campus $campus) {
            $buildings = $campus->buildings ?? collect();
            $buildings->each(function (Building $building) {
                $floors = $building->floors ?? collect();
                $floors->each(function (Floor $floor) {
                    $rooms = $floor->rooms ?? collect();
                    $rooms->each(fn (Room $room) => $room->setAppends([]));
                });
            });
        });

        return $tree;
    }

    /**
     * @return Collection<int, Campus>
     */
    public function activeCampuses(): Collection
    {
        return Campus::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);
    }
}
