<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomActivityType;
use App\Models\RoomBorrowing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class RoomActivityTypeMasterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_migration_seeds_default_activity_types(): void
    {
        $codes = RoomActivityType::query()->ordered()->pluck('name', 'code')->all();

        $this->assertSame('Rapat', $codes['rapat'] ?? null);
        $this->assertSame('Kegiatan Akademik', $codes['akademik'] ?? null);
        $this->assertSame('Seminar', $codes['seminar'] ?? null);
        $this->assertSame('Kegiatan Lainnya', $codes['lainnya'] ?? null);
    }

    public function test_admin_can_crud_activity_types_and_viewer_is_forbidden(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)->get(route('room-activity-types.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('room-activity-types.index'))
            ->assertOk()
            ->assertSee('Master Jenis Kegiatan Ruangan')
            ->assertSee('Rapat');

        $this->actingAs($admin)
            ->post(route('room-activity-types.store'), ['name' => 'Pelatihan UKM'])
            ->assertRedirect(route('room-activity-types.index'))
            ->assertSessionHas('success');

        $created = RoomActivityType::query()->where('name', 'Pelatihan UKM')->firstOrFail();
        $this->assertTrue($created->is_active);
        $this->assertNotSame('', $created->code);

        $this->actingAs($admin)
            ->put(route('room-activity-types.update', $created), [
                'name' => 'Pelatihan UKM Futsal',
                'is_active' => '1',
            ])
            ->assertRedirect(route('room-activity-types.index'));

        $this->assertSame('Pelatihan UKM Futsal', $created->fresh()->name);

        $this->actingAs($viewer)
            ->post(route('room-activity-types.store'), ['name' => 'Dilarang'])
            ->assertForbidden();
    }

    public function test_create_form_loads_active_types_from_database_and_rejects_inactive(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();
        $place = $this->place();

        RoomActivityType::query()->create([
            'code' => 'pelatihan-ukm',
            'name' => 'Pelatihan UKM',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $inactive = RoomActivityType::query()->create([
            'code' => 'arsip-lama',
            'name' => 'Arsip Lama',
            'is_active' => false,
            'sort_order' => 11,
        ]);

        $html = $this->actingAs($viewer)
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('value="pelatihan-ukm"', $html);
        $this->assertStringContainsString('Pelatihan UKM', $html);
        $this->assertStringNotContainsString('value="arsip-lama"', $html);
        $this->assertStringContainsString('Contoh: Kelas Online, Rapat BEM, Rapat Hima, atau Pelatihan UKM Futsal', $html);
        $this->assertStringContainsString('Jelaskan tujuan penggunaan ruangan secara singkat', $html);

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'activity_type' => $inactive->code,
            ]))
            ->assertRedirect(route('room-borrowings.create'))
            ->assertSessionHasErrors('activity_type');

        $this->actingAs($admin)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'activity_type' => 'pelatihan-ukm',
                'purpose' => 'Pelatihan UKM Futsal',
                'borrower_name' => 'Pemohon UKM',
                'borrower_email' => 'ukm@undira.ac.id',
            ]))
            ->assertRedirect();

        $saved = RoomBorrowing::query()->where('purpose', 'Pelatihan UKM Futsal')->firstOrFail();
        $this->assertSame('pelatihan-ukm', $saved->activity_type);
        $this->assertSame('Pelatihan UKM', $saved->activityTypeLabel());

        $this->actingAs($admin)
            ->get(route('room-borrowings.show', $saved))
            ->assertOk()
            ->assertSee('Pelatihan UKM')
            ->assertSee('Pelatihan UKM Futsal');
    }

    public function test_used_type_cannot_be_deleted_but_can_be_deactivated_and_legacy_still_shows(): void
    {
        $admin = $this->makeAdmin();
        $place = $this->place();
        $type = RoomActivityType::query()->where('code', 'rapat')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'activity_type' => 'rapat',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('room-activity-types.destroy', $type))
            ->assertRedirect(route('room-activity-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('room_activity_types', ['code' => 'rapat']);

        $this->actingAs($admin)
            ->put(route('room-activity-types.update', $type), [
                'name' => 'Rapat',
                'is_active' => '0',
            ])
            ->assertRedirect(route('room-activity-types.index'));

        $this->assertFalse($type->fresh()->is_active);

        $html = $this->actingAs($this->makeViewer())
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('value="rapat"', $html);

        $borrowing = RoomBorrowing::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('room-borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('Rapat');
    }

    public function test_unused_type_can_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $type = RoomActivityType::query()->create([
            'code' => 'sementara',
            'name' => 'Sementara',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $this->actingAs($admin)
            ->delete(route('room-activity-types.destroy', $type))
            ->assertRedirect(route('room-activity-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('room_activity_types', ['code' => 'sementara']);
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'RA'.$suffix, 'name' => 'Kampus RA', 'slug' => 'kampus-ra-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B1', 'name' => 'Gedung 1', 'slug' => 'gedung-1-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function roomPayload(int $roomId, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $roomId,
            'borrower_name' => 'Sari Ruangan',
            'borrower_email' => 'sari.ruangan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'Teknik Perminyakan',
            'activity_type' => 'rapat',
            'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
            'participant_internal' => '1',
            'participant_internal_count' => 30,
            'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T11:00',
        ], $overrides);
    }
}
