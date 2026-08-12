<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\SubCategory;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenanceWorkOrderCampusFilterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_index_shows_campus_filter_and_lists_active_campuses(): void
    {
        $admin = $this->makeAdmin();
        $cibubur = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Undira Cibubur', 'slug' => 'undira-cibubur-'.uniqid(), 'is_active' => true,
        ]);
        Campus::query()->create([
            'code' => 'KRG', 'name' => 'Undira Kranggan', 'slug' => 'undira-kranggan-'.uniqid(), 'is_active' => true,
        ]);
        Campus::query()->create([
            'code' => 'OFF', 'name' => 'Kampus Nonaktif', 'slug' => 'kampus-off-'.uniqid(), 'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee('Lokasi Kampus', false)
            ->assertSee('Semua Kampus', false)
            ->assertSee('Undira Cibubur', false)
            ->assertSee('Undira Kranggan', false)
            ->assertDontSee('Kampus Nonaktif', false)
            ->assertSee('name="campus_id"', false)
            ->assertSee((string) $cibubur->id, false);
    }

    public function test_campus_filter_returns_only_matching_work_orders(): void
    {
        $admin = $this->makeAdmin();
        [$cibubur, $itemCibubur] = $this->makeCampusWithItem('Undira Cibubur', 'CBB', 'WO-CBB-1');
        [, $itemKranggan] = $this->makeCampusWithItem('Undira Kranggan', 'KRG', 'WO-KRG-1');

        $woCibubur = $this->makeWo($itemCibubur, [
            'maintenance_number' => 'MNT-CBB-001',
            'status' => MaintenanceStatus::PendingApproval,
            'maintenance_type' => 'repair',
        ]);
        $woKranggan = $this->makeWo($itemKranggan, [
            'maintenance_number' => 'MNT-KRG-001',
            'status' => MaintenanceStatus::PendingApproval,
            'maintenance_type' => 'repair',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.index', ['campus_id' => $cibubur->id]))
            ->assertOk()
            ->assertSee('MNT-CBB-001', false)
            ->assertDontSee('MNT-KRG-001', false);

        // Semua Kampus: kedua WO terlihat
        $this->actingAs($admin)
            ->get(route('maintenances.index'))
            ->assertOk()
            ->assertSee($woCibubur->maintenance_number, false)
            ->assertSee($woKranggan->maintenance_number, false);
    }

    public function test_campus_filter_combines_with_status_type_and_search(): void
    {
        $admin = $this->makeAdmin();
        [$cibubur, $itemA] = $this->makeCampusWithItem('Undira Cibubur', 'CB1', 'AST-A');
        [, $itemB] = $this->makeCampusWithItem('Undira Kranggan', 'KR1', 'AST-B');

        $match = $this->makeWo($itemA, [
            'maintenance_number' => 'MNT-MATCH-001',
            'status' => MaintenanceStatus::PendingApproval,
            'maintenance_type' => 'repair',
            'technician' => 'Budi Teknisi',
        ]);
        $this->makeWo($itemA, [
            'maintenance_number' => 'MNT-OTHER-STATUS',
            'status' => MaintenanceStatus::Waiting,
            'maintenance_type' => 'repair',
        ]);
        $this->makeWo($itemA, [
            'maintenance_number' => 'MNT-OTHER-TYPE',
            'status' => MaintenanceStatus::PendingApproval,
            'maintenance_type' => 'preventive',
        ]);
        $this->makeWo($itemB, [
            'maintenance_number' => 'MNT-OTHER-CAMPUS',
            'status' => MaintenanceStatus::PendingApproval,
            'maintenance_type' => 'repair',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenances.index', [
                'campus_id' => $cibubur->id,
                'status' => MaintenanceStatus::PendingApproval->value,
                'type' => 'repair',
                'q' => 'MATCH',
            ]))
            ->assertOk()
            ->assertSee($match->maintenance_number, false)
            ->assertDontSee('MNT-OTHER-STATUS', false)
            ->assertDontSee('MNT-OTHER-TYPE', false)
            ->assertDontSee('MNT-OTHER-CAMPUS', false);
    }

    public function test_invalid_campus_id_is_rejected(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('maintenances.index', ['campus_id' => 999999]))
            ->assertSessionHasErrors('campus_id');
    }

    public function test_teknisi_scope_still_applies_with_campus_filter(): void
    {
        $teknisi = $this->makeTeknisi();
        $other = $this->makeTeknisi(['email' => 'teknisi-other@undira.ac.id']);
        [$campus, $item] = $this->makeCampusWithItem('Undira Cibubur', 'CBX', 'AST-T');

        $own = $this->makeWo($item, [
            'maintenance_number' => 'MNT-OWN-TEK',
            'technician_user_id' => $teknisi->id,
            'created_by' => $teknisi->id,
        ]);
        $this->makeWo($item, [
            'maintenance_number' => 'MNT-OTHER-TEK',
            'technician_user_id' => $other->id,
            'created_by' => $other->id,
        ]);

        $this->actingAs($teknisi)
            ->get(route('maintenances.index', ['campus_id' => $campus->id]))
            ->assertOk()
            ->assertSee($own->maintenance_number, false)
            ->assertDontSee('MNT-OTHER-TEK', false);
    }

    /**
     * @return array{0: Campus, 1: InventoryItem}
     */
    protected function makeCampusWithItem(string $campusName, string $codePrefix, string $inventoryNumber): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => $codePrefix, 'name' => $campusName, 'slug' => strtolower($codePrefix).'-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$codePrefix, 'name' => 'Gedung A', 'slug' => 'b-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Kelas A101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'C'.$codePrefix, 'name' => 'Elektronik', 'slug' => 'cat-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$codePrefix, 'name' => 'Furniture', 'slug' => 'sub-'.$suffix, 'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'name' => 'Aset '.$inventoryNumber,
            'inventory_number' => $inventoryNumber,
            'barcode' => $inventoryNumber,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);

        return [$campus, $item];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeWo(InventoryItem $item, array $overrides = []): Maintenance
    {
        return Maintenance::query()->create(array_merge([
            'maintenance_number' => 'MNT-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ], $overrides));
    }
}
