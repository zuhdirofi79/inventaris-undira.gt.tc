<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\View\View;

class RoomController extends Controller
{
    public function show(Room $room): View
    {
        abort_unless($room->is_active, 404);

        $room->load('floor.building.campus');

        $stats = [
            'total_items' => $room->inventoryItems()->count(),
            'total_groups' => $room->inventoryItems()
                ->select('group_key')
                ->whereNotNull('group_key')
                ->groupBy('group_key')
                ->get()
                ->count(),
            'by_status' => $room->inventoryItems()
                ->selectRaw('status, COUNT(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];

        $categoryCounts = $room->inventoryItems()
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->selectRaw('category_id, COUNT(*) as unit_count')
            ->pluck('unit_count', 'category_id');

        $categories = Category::query()
            ->whereIn('id', $categoryCounts->keys())
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(function (Category $category) use ($categoryCounts, $room) {
                $base = $room->inventoryItems()->where('category_id', $category->id);

                $subCounts = (clone $base)
                    ->whereNotNull('sub_category_id')
                    ->groupBy('sub_category_id')
                    ->selectRaw('sub_category_id, COUNT(*) as unit_count')
                    ->pluck('unit_count', 'sub_category_id');

                $subs = SubCategory::query()
                    ->where('category_id', $category->id)
                    ->whereIn('id', $subCounts->keys())
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'code', 'name'])
                    ->map(fn (SubCategory $sub) => [
                        'id' => $sub->id,
                        'code' => $sub->code,
                        'name' => $sub->name,
                        'unit_count' => (int) ($subCounts[$sub->id] ?? 0),
                    ])
                    ->values();

                $uncategorized = (clone $base)
                    ->where(fn ($q) => $q->whereNull('sub_category_id')->orWhere('sub_category_id', 0))
                    ->count();

                if ($uncategorized > 0) {
                    $subs->push([
                        'id' => 0,
                        'code' => null,
                        'name' => 'Tanpa Sub Kategori',
                        'unit_count' => $uncategorized,
                    ]);
                }

                return [
                    'id' => $category->id,
                    'code' => $category->code,
                    'name' => $category->name,
                    'unit_count' => (int) ($categoryCounts[$category->id] ?? 0),
                    'sub_categories' => $subs,
                ];
            })
            ->filter(fn (array $row) => $row['unit_count'] > 0)
            ->values();

        return view('locations.rooms.show', [
            'room' => $room,
            'stats' => $stats,
            'categories' => $categories,
            'exploreUrls' => [
                'itemsBase' => url('/locations/explore/rooms/'.$room->id.'/categories'),
            ],
        ]);
    }
}
