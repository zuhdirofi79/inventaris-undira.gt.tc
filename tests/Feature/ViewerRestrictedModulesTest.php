<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\SubCategory;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ViewerRestrictedModulesTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_viewer_dashboard_hides_preventive_and_reports_and_routes_are_forbidden(): void
    {
        $viewer = $this->makeViewer();

        $html = $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Jelajahi Lokasi', $html);
        $this->assertStringContainsString('Lihat Inventaris', $html);
        $this->assertStringContainsString('Scanner', $html);
        $this->assertStringContainsString(route('borrowings.create'), $html);
        $this->assertStringNotContainsString(route('maintenance-schedules.index'), $html);
        $this->assertStringNotContainsString(route('reports.index'), $html);
        $this->assertStringNotContainsString('Jadwal perawatan berkala', $html);
        $this->assertStringNotContainsString('Cetak / export laporan', $html);
        $this->assertStringNotContainsString('Jadwal Preventif', $html);
        $this->assertStringNotContainsString('Laporan Inventaris', $html);
        $this->assertStringNotContainsString('Laporan Peminjaman', $html);

        $schedule = $this->makeSchedule();

        $this->actingAs($viewer)->get(route('maintenance-schedules.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('maintenance-schedules.show', $schedule))->assertForbidden();
        $this->actingAs($viewer)->get(route('maintenance-schedules.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('maintenance-schedules.preview'))->assertForbidden();
        $this->actingAs($viewer)->post(route('maintenance-schedules.store'), [])->assertForbidden();
        $this->actingAs($viewer)->put(route('maintenance-schedules.update', $schedule), [])->assertForbidden();
        $this->actingAs($viewer)->delete(route('maintenance-schedules.destroy', $schedule))->assertForbidden();
        $this->actingAs($viewer)->post(route('maintenance-schedules.advance', $schedule), [])->assertForbidden();
        $this->actingAs($viewer)->get(route('maintenance-reports.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('maintenance-reports.generate'), [])->assertForbidden();

        $this->actingAs($viewer)->get(route('reports.index'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.show', 'dipinjam'))->assertForbidden();
        $this->actingAs($viewer)->get(route('reports.data', 'dipinjam'))->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.export', 'dipinjam'), ['format' => 'pdf'])->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.export', 'lengkap'), ['format' => 'excel'])->assertForbidden();
        $this->actingAs($viewer)->post(route('reports.generate'), ['format' => 'pdf'])->assertForbidden();

        $this->actingAs($viewer)->get(route('inventory.index'))->assertOk();
        $this->actingAs($viewer)->get(route('scanner.index'))->assertOk();
        $this->actingAs($viewer)->get(route('borrowings.index'))->assertOk();
        $this->actingAs($viewer)->get(route('campuses.index'))->assertOk();
        $this->actingAs($viewer)->get(route('notifications.index'))->assertOk();

        $this->assertNotNull($schedule->fresh());
    }

    private function makeSchedule(): MaintenanceSchedule
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'VW'.$suffix, 'name' => 'Kampus Viewer', 'slug' => 'viewer-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung', 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R'.$suffix, 'name' => 'Ruang', 'room_type' => 'Office', 'capacity' => 1, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kategori', 'slug' => 'kat-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$suffix, 'name' => 'Sub', 'slug' => 'sub-'.$suffix, 'is_active' => true,
        ]);
        $item = InventoryItem::query()->create([
            'inventory_number' => 'INV-VW-'.$suffix,
            'name' => 'Barang Viewer',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'Unit',
        ]);

        return MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addMonth()->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);
    }

    public function test_admin_and_super_admin_keep_preventive_and_report_shortcuts(): void
    {
        foreach ([$this->makeAdmin(), $this->makeSuperAdmin()] as $user) {
            $html = $this->actingAs($user)
                ->get(route('dashboard'))
                ->assertOk()
                ->getContent();

            $this->assertStringContainsString(route('maintenance-schedules.index'), $html);
            $this->assertStringContainsString(route('reports.index'), $html);
            $this->assertStringContainsString('Jadwal perawatan berkala', $html);
            $this->assertStringContainsString('Cetak / export laporan', $html);

            $this->actingAs($user)->get(route('maintenance-schedules.index'))->assertOk();
            $this->actingAs($user)->get(route('reports.index'))->assertOk();
        }
    }
}
