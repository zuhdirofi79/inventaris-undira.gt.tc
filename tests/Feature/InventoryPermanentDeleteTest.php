<?php

namespace Tests\Feature;

use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\Building;
use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryPermanentDeleteTest extends TestCase
{
    use CreatesAuthenticatedUsers;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeInventoryItem(): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'TDL', 'name' => 'Test Delete', 'slug' => 'test-del', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B1', 'name' => 'Building 1', 'slug' => 'b1-del', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F1', 'name' => 'Floor 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R101', 'name' => 'Room 101', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'TST', 'name' => 'Test Category', 'slug' => 'test-cat', 'is_active' => true,
        ]);
        $subCategory = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'SUB', 'name' => 'Test SubCat', 'slug' => 'test-sub', 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $subCategory->id,
            'name' => 'Test Item To Delete',
            'inventory_number' => 'TEST-DEL-001',
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);
    }

    public function test_soft_delete_moves_item_to_trash(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();
        $categoryId = $item->category_id;

        $this->actingAs($admin)
            ->delete(route('inventory.destroy', $item))
            ->assertRedirect(route('inventory.category', $categoryId))
            ->assertSessionHas('success');

        // Item should be soft deleted (still in DB but with deleted_at)
        $this->assertSoftDeleted('inventory_items', ['id' => $item->id]);

        // Should NOT appear in normal queries
        $this->assertNull(InventoryItem::query()->find($item->id));

        // Should appear in trashed queries
        $this->assertNotNull(InventoryItem::onlyTrashed()->find($item->id));
    }

    public function test_force_delete_removes_item_permanently_when_no_history(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();
        $itemId = $item->id;
        $categoryId = $item->category_id;

        $this->actingAs($admin)
            ->delete(route('inventory.force-destroy', $item))
            ->assertRedirect(route('inventory.category', $categoryId))
            ->assertSessionHas('success');

        // Item should be completely gone
        $this->assertDatabaseMissing('inventory_items', ['id' => $itemId]);
        $this->assertNull(InventoryItem::withTrashed()->find($itemId));
    }

    public function test_force_delete_blocked_when_item_has_borrowing_history(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();

        // Create a borrowing for this item
        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'requested_by' => $admin->id,
            'borrower_name' => 'Test Borrower',
            'borrower_contact' => '081234567890',
            'borrow_date' => now(),
            'expected_return_date' => now()->addDays(7),
            'purpose' => 'Testing',
            'status' => 'menunggu_persetujuan',
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.force-destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Item should still exist
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_force_delete_blocked_when_item_has_maintenance_history(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();

        // Create a maintenance for this item
        Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'created_by' => $admin->id,
            'maintenance_number' => 'MNT-TEST-001',
            'maintenance_type' => 'corrective',
            'service_date' => now(),
            'status' => 'waiting',
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.force-destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('error');

        // Item should still exist
        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_force_delete_cleans_up_photos(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();
        $itemId = $item->id;

        // Create a photo for the item
        $photoPath = 'inventory/photos/test-photo.jpg';
        Storage::disk('public')->put($photoPath, 'fake image content');

        InventoryPhoto::query()->create([
            'inventory_item_id' => $item->id,
            'path' => $photoPath,
            'original_name' => 'test.jpg',
            'size' => 100,
            'is_primary' => true,
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.force-destroy', $item))
            ->assertRedirect()
            ->assertSessionHas('success');

        // Photo file should be deleted
        Storage::disk('public')->assertMissing($photoPath);

        // Photo record should be deleted
        $this->assertDatabaseMissing('inventory_photos', ['inventory_item_id' => $itemId]);
    }

    public function test_deleted_item_not_visible_in_room_view(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();
        $room = $item->room;

        // Verify item appears in room before delete
        $response = $this->actingAs($admin)->get(route('rooms.show', $room));
        $response->assertOk();

        // Delete the item
        $this->actingAs($admin)->delete(route('inventory.destroy', $item));

        // Reload room view - item should not appear
        $freshResponse = $this->actingAs($admin)->get(route('rooms.show', $room));
        $freshResponse->assertOk();
        $freshResponse->assertDontSee($item->inventory_number);
    }

    public function test_deleted_item_not_available_in_borrowing_selector(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeInventoryItem();

        // Delete the item
        $this->actingAs($admin)->delete(route('inventory.destroy', $item));

        // Search for the item in borrowing selector - should not find it
        $response = $this->actingAs($admin)
            ->getJson(route('inventory.selector.search', ['q' => $item->inventory_number, 'status' => 'aktif']));

        $response->assertOk();
        $results = $response->json('results');
        $this->assertEmpty(array_filter($results, fn($r) => $r['id'] === $item->id));
    }

    public function test_inventory_numbers_remain_immutable_after_delete(): void
    {
        $admin = $this->makeSuperAdmin();

        // Create 3 items with sequential numbers
        $item1 = $this->makeInventoryItem();
        $item1->update(['inventory_number' => 'TEST-000001']);

        $item2 = InventoryItem::query()->create([
            'room_id' => $item1->room_id,
            'category_id' => $item1->category_id,
            'sub_category_id' => $item1->sub_category_id,
            'name' => 'Test Item 2',
            'inventory_number' => 'TEST-000002',
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);

        $item3 = InventoryItem::query()->create([
            'room_id' => $item1->room_id,
            'category_id' => $item1->category_id,
            'sub_category_id' => $item1->sub_category_id,
            'name' => 'Test Item 3',
            'inventory_number' => 'TEST-000003',
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ]);

        // Delete item2
        $this->actingAs($admin)->delete(route('inventory.destroy', $item2));

        // Verify item1 and item3 numbers haven't changed
        $this->assertEquals('TEST-000001', $item1->fresh()->inventory_number);
        $this->assertEquals('TEST-000003', $item3->fresh()->inventory_number);
    }

    public function test_unauthenticated_user_cannot_force_delete(): void
    {
        $item = $this->makeInventoryItem();

        $this->delete(route('inventory.force-destroy', $item))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }

    public function test_user_without_permission_cannot_force_delete(): void
    {
        $viewer = $this->makeViewer();
        $item = $this->makeInventoryItem();

        $this->actingAs($viewer)
            ->delete(route('inventory.force-destroy', $item))
            ->assertForbidden();

        $this->assertDatabaseHas('inventory_items', ['id' => $item->id]);
    }
}
