<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomInventoryBadgeContrastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        return $user;
    }

    /**
     * @return array{room: Room, category: Category}
     */
    protected function makeRoomWithCategory(): array
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur-badge', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a-badge', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A104', 'name' => 'Kelas A104', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'FUR', 'name' => 'Furniture', 'slug' => 'furniture-badge', 'is_active' => true,
        ]);

        return compact('room', 'category');
    }

    protected function makeItem(Room $room, Category $category, array $overrides = []): InventoryItem
    {
        $sub = SubCategory::query()->firstOrCreate(
            ['category_id' => $category->id, 'code' => 'KR'],
            ['name' => 'Kursi', 'slug' => 'kursi-badge', 'is_active' => true]
        );

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-'.uniqid(),
            'name' => 'Kursi Kuliah',
            'group_key' => 'group-kursi-1',
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => InventoryCondition::Bagus->value,
            'status' => InventoryStatus::Aktif->value,
            'quantity' => 1,
            'unit' => 'Unit',
        ], $overrides));
    }

    public function test_room_inventory_table_shows_readable_condition_and_status_badges(): void
    {
        $admin = $this->makeAdmin();
        ['room' => $room, 'category' => $category] = $this->makeRoomWithCategory();
        $this->makeItem($room, $category);

        $html = $this->actingAs($admin)
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Inventaris di Ruangan')
            ->assertSee('Kursi Kuliah')
            ->assertSee('Furniture')
            ->assertSee('Bagus')
            ->assertSee('Aktif')
            ->assertDontSee('Total Nilai', false)
            ->getContent();

        $this->assertStringContainsString('room-inventory-table', $html);
        $this->assertStringContainsString('inventory-condition-badge', $html);
        $this->assertStringContainsString('condition-good', $html);
        $this->assertStringContainsString('inventory-status-badge', $html);
        $this->assertStringContainsString('status-active', $html);
        $this->assertStringNotContainsString('badge-condition-', $html);
        $this->assertStringNotContainsString('badge-status-', $html);
        $this->assertStringNotContainsString('inventory-condition-badge text-white', $html);
        $this->assertStringNotContainsString('inventory-status-badge text-white', $html);
    }

    public function test_borrowed_and_damaged_use_semantic_classes(): void
    {
        $admin = $this->makeAdmin();
        ['room' => $room, 'category' => $category] = $this->makeRoomWithCategory();
        $this->makeItem($room, $category, [
            'inventory_number' => 'INV-BORROW',
            'name' => 'Meja Pinjam',
            'group_key' => 'group-meja',
            'condition' => InventoryCondition::RusakRingan->value,
            'status' => InventoryStatus::Dipinjam->value,
        ]);

        $html = $this->actingAs($admin)->get(route('rooms.show', $room))->assertOk()->getContent();

        $this->assertStringContainsString('Meja Pinjam', $html);
        $this->assertStringContainsString('Dipinjam', $html);
        $this->assertStringContainsString('Rusak Ringan', $html);
        $this->assertStringContainsString('status-borrowed', $html);
        $this->assertStringContainsString('condition-damaged', $html);
    }

    public function test_enum_badge_tones_are_stable(): void
    {
        $this->assertSame('good', InventoryCondition::Bagus->badgeTone());
        $this->assertSame('fair', InventoryCondition::Cukup->badgeTone());
        $this->assertSame('damaged', InventoryCondition::RusakBerat->badgeTone());
        $this->assertSame('active', InventoryStatus::Aktif->badgeTone());
        $this->assertSame('borrowed', InventoryStatus::Dipinjam->badgeTone());
        $this->assertSame('maintenance', InventoryStatus::Maintenance->badgeTone());
        $this->assertSame('inactive', InventoryStatus::Hilang->badgeTone());
    }
}
