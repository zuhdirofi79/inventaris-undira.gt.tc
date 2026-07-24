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
     * @return array{room: Room, category: Category, sub: SubCategory}
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
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'KR', 'name' => 'Kursi', 'slug' => 'kursi-badge', 'is_active' => true,
        ]);

        return compact('room', 'category', 'sub');
    }

    protected function makeItem(Room $room, Category $category, SubCategory $sub, array $overrides = []): InventoryItem
    {
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

    public function test_room_page_shows_category_accordion_and_items_api_returns_badge_tones(): void
    {
        $admin = $this->makeAdmin();
        ['room' => $room, 'category' => $category, 'sub' => $sub] = $this->makeRoomWithCategory();
        $this->makeItem($room, $category, $sub);

        $this->actingAs($admin)
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('Isi Ruangan')
            ->assertSee('Furniture')
            ->assertSee('Kursi')
            ->assertDontSee('Kursi Kuliah')
            ->assertDontSee('Total Nilai', false);

        $row = $this->actingAs($admin)
            ->getJson(route('locations.explore.items', [$room, $category, $sub->id]))
            ->assertOk()
            ->json('rows.0');

        $this->assertSame('Kursi Kuliah', $row['name']);
        $this->assertSame('Bagus', $row['condition_label']);
        $this->assertSame('Aktif', $row['status_label']);
        $this->assertSame('good', $row['condition_tone']);
        $this->assertSame('active', $row['status_tone']);
    }

    public function test_borrowed_and_damaged_use_semantic_tones(): void
    {
        $admin = $this->makeAdmin();
        ['room' => $room, 'category' => $category, 'sub' => $sub] = $this->makeRoomWithCategory();
        $this->makeItem($room, $category, $sub, [
            'inventory_number' => 'INV-BORROW',
            'name' => 'Meja Pinjam',
            'group_key' => 'group-meja',
            'condition' => InventoryCondition::RusakRingan->value,
            'status' => InventoryStatus::Dipinjam->value,
        ]);

        $row = $this->actingAs($admin)
            ->getJson(route('locations.explore.items', [$room, $category, $sub->id]))
            ->assertOk()
            ->json('rows.0');

        $this->assertSame('Meja Pinjam', $row['name']);
        $this->assertSame('Dipinjam', $row['status_label']);
        $this->assertSame('Rusak Ringan', $row['condition_label']);
        $this->assertSame('borrowed', $row['status_tone']);
        $this->assertSame('damaged', $row['condition_tone']);
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
