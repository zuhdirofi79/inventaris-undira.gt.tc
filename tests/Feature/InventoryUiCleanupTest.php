<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryUiCleanupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    public function test_room_detail_does_not_show_total_nilai(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        $campus = Campus::query()->create([
            'code' => 'UIC', 'name' => 'Kampus UI Cleanup', 'slug' => 'kampus-ui-cleanup', 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'gedung-a-uic', 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'A104', 'name' => 'Kelas A104', 'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('rooms.show', $room))
            ->assertOk()
            ->assertDontSee('Total Nilai', false)
            ->assertSee('Total Item', false)
            ->assertSee('Grup Barang', false);
    }

    public function test_dashboard_does_not_show_total_nilai_card(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        $html = $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('Total Nilai', $html);
        $this->assertStringContainsString('Total Inventaris', $html);
        $this->assertStringNotContainsString('Nilai Inventaris per Kampus', $html);
        $this->assertStringNotContainsString('chartValueCampus', $html);
    }

    public function test_konfigurasi_page_exposes_create_modal_and_hides_price_field(): void
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        $html = $this->actingAs($user)
            ->get(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="modalCreateConfig"', $html);
        $this->assertStringContainsString('Simpan Inventaris', $html);
        $this->assertStringNotContainsString('id="modalManageOption"', $html);
        $this->assertStringNotContainsString('name="price"', $html);
        $this->assertStringNotContainsString('Harga (Rp)', $html);

        $contentPos = strpos($html, 'class="content-wrapper"');
        $modalPos = strpos($html, 'id="modalCreateConfig"');
        $this->assertNotFalse($contentPos);
        $this->assertNotFalse($modalPos);
        $this->assertGreaterThan($contentPos, $modalPos);
    }
}
