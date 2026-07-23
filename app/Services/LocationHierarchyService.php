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
     * @return Collection<int, Campus>
     */
    public function activeTree(?int $campusId = null): Collection
    {
        $cacheKey = InventarisCache::key('location.tree.'.($campusId ?? 'all'));

        /** @var Collection<int, Campus> $tree */
        $tree = Cache::remember($cacheKey, InventarisCache::TTL, function () use ($campusId) {
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

            // Avoid N+1 from Room::$appends full_location when serializing to JSON.
            $tree->each(function (Campus $campus) {
                $campus->buildings->each(function (Building $building) {
                    $building->floors->each(function (Floor $floor) {
                        $floor->rooms->each(fn (Room $room) => $room->setAppends([]));
                    });
                });
            });

            return $tree;
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
