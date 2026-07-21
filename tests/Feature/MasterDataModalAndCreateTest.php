<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataModalAndCreateTest extends TestCase
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

    protected function assertModalOutsideWrapper(string $html, string $modalId): void
    {
        $footerPos = strpos($html, 'class="main-footer');
        $modalPos = strpos($html, 'id="'.$modalId.'"');
        $this->assertNotFalse($footerPos, 'main-footer missing');
        $this->assertNotFalse($modalPos, $modalId.' missing');
        $this->assertGreaterThan($footerPos, $modalPos, $modalId.' must render after main-footer (outside content-wrapper)');
    }

    public function test_campus_create_modal_is_outside_wrapper_and_store_works(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('admin.locations.campuses.index'))
            ->assertOk()
            ->getContent();

        $this->assertModalOutsideWrapper($html, 'modalCreate');
        $this->assertStringContainsString('data-bs-toggle="modal"', $html);
        $this->assertStringNotContainsString('data-toggle="modal"', $html);

        $this->actingAs($admin)
            ->post(route('admin.locations.campuses.store'), [
                'code' => 'TST',
                'name' => 'Kampus Test Modal',
                'description' => 'Deskripsi uji',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campuses', [
            'code' => 'TST',
            'name' => 'Kampus Test Modal',
            'is_active' => 1,
        ]);
    }

    public function test_building_floor_room_create_chain_persists(): void
    {
        $admin = $this->makeAdmin();
        $campus = Campus::query()->create([
            'code' => 'CBX', 'name' => 'Kampus Chain', 'slug' => 'kampus-chain', 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.locations.buildings.index', $campus))
            ->assertOk()
            ->assertSee('id="modalCreate"', false);

        $this->actingAs($admin)
            ->post(route('admin.locations.buildings.store'), [
                'campus_id' => $campus->id,
                'code' => 'Z',
                'name' => 'Gedung Z',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $building = Building::query()->where('campus_id', $campus->id)->where('code', 'Z')->firstOrFail();

        $htmlFloor = $this->actingAs($admin)
            ->get(route('admin.locations.floors.index', $building))
            ->assertOk()
            ->getContent();
        $this->assertModalOutsideWrapper($htmlFloor, 'modalCreate');

        $this->actingAs($admin)
            ->post(route('admin.locations.floors.store'), [
                'building_id' => $building->id,
                'code' => 'L9',
                'floor_number' => 9,
                'name' => 'Lantai 9',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $floor = Floor::query()->where('building_id', $building->id)->where('code', 'L9')->firstOrFail();

        $htmlRoom = $this->actingAs($admin)
            ->get(route('admin.locations.rooms.index', $floor))
            ->assertOk()
            ->getContent();
        $this->assertModalOutsideWrapper($htmlRoom, 'modalCreate');

        $this->actingAs($admin)
            ->post(route('admin.locations.rooms.store'), [
                'floor_id' => $floor->id,
                'code' => 'Z901',
                'name' => 'Ruang Z901',
                'room_type' => 'Kelas',
                'capacity' => 40,
                'notes' => 'Uji modal',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('rooms', [
            'floor_id' => $floor->id,
            'code' => 'Z901',
            'name' => 'Ruang Z901',
            'room_type' => 'Kelas',
            'capacity' => 40,
        ]);
    }

    public function test_category_create_modal_outside_wrapper_and_store_works(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'klasifikasi']))
            ->assertOk()
            ->getContent();

        $this->assertModalOutsideWrapper($html, 'modalCreateCategory');

        $this->actingAs($admin)
            ->post(route('categories.store'), [
                'code' => 'TSTCAT',
                'name' => 'Kategori Test Modal',
                'description' => 'Uji',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('categories', [
            'code' => 'TSTCAT',
            'name' => 'Kategori Test Modal',
        ]);
    }

    public function test_duplicate_campus_code_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        Campus::query()->create([
            'code' => 'DUP', 'name' => 'Existing', 'slug' => 'existing-dup', 'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.locations.campuses.index'))
            ->post(route('admin.locations.campuses.store'), [
                'code' => 'DUP',
                'name' => 'Another',
                'is_active' => '1',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_viewer_cannot_create_campus(): void
    {
        $viewer = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $viewer->syncRoles(['viewer']);

        $this->actingAs($viewer)
            ->post(route('admin.locations.campuses.store'), [
                'code' => 'VWR',
                'name' => 'Viewer Kampus',
                'is_active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('campuses', ['code' => 'VWR']);
    }
}
