<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Campus;
use App\Models\Floor;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\RoomBudgetType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class RoomBudgetTypeMasterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_migration_seeds_default_budget_types(): void
    {
        $codes = RoomBudgetType::query()->ordered()->pluck('name', 'code')->all();

        $this->assertSame('Anggaran Unit / Biro', $codes['anggaran-unit'] ?? null);
        $this->assertSame('Anggaran Fakultas', $codes['anggaran-fakultas'] ?? null);
        $this->assertSame('Anggaran Ormawa / UKM', $codes['anggaran-ormawa'] ?? null);
        $this->assertSame('Anggaran Lainnya', $codes['anggaran-lainnya'] ?? null);
    }

    public function test_admin_can_crud_budget_types_and_viewer_is_forbidden(): void
    {
        $admin = $this->makeAdmin();
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)->get(route('room-budget-types.index'))->assertForbidden();

        $this->actingAs($admin)
            ->get(route('room-budget-types.index'))
            ->assertOk()
            ->assertSee('Master Anggaran Peminjaman Ruangan')
            ->assertSee('Anggaran Unit / Biro');

        $this->actingAs($admin)
            ->post(route('room-budget-types.store'), ['name' => 'Dana Hibah'])
            ->assertRedirect(route('room-budget-types.index'))
            ->assertSessionHas('success');

        $created = RoomBudgetType::query()->where('name', 'Dana Hibah')->firstOrFail();
        $this->assertTrue($created->is_active);
        $this->assertNotSame('', $created->code);

        $this->actingAs($admin)
            ->put(route('room-budget-types.update', $created), [
                'name' => 'Dana Hibah Internal',
                'is_active' => '1',
            ])
            ->assertRedirect(route('room-budget-types.index'));

        $this->assertSame('Dana Hibah Internal', $created->fresh()->name);

        $this->actingAs($viewer)
            ->post(route('room-budget-types.store'), ['name' => 'Dilarang'])
            ->assertForbidden();
    }

    public function test_create_form_phone_budget_dropdown_and_no_documentation_url(): void
    {
        $viewer = $this->makeViewer();
        $place = $this->place();

        RoomBudgetType::query()->create([
            'code' => 'dana-hibah',
            'name' => 'Dana Hibah',
            'is_active' => true,
            'sort_order' => 10,
        ]);
        $inactive = RoomBudgetType::query()->create([
            'code' => 'arsip-anggaran',
            'name' => 'Arsip Anggaran',
            'is_active' => false,
            'sort_order' => 11,
        ]);

        $html = $this->actingAs($viewer)
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('/id="documentation_url"/', $html);
        $this->assertStringNotContainsString('Link Dokumentasi', $html);
        $this->assertStringNotContainsString('Gunakan Anggaran', $html);
        $this->assertStringContainsString('Anggaran (Opsional)', $html);
        $this->assertStringContainsString('Pilih jenis anggaran (opsional)', $html);
        $this->assertStringContainsString('value="dana-hibah"', $html);
        $this->assertStringNotContainsString('value="arsip-anggaran"', $html);
        $this->assertMatchesRegularExpression(
            '/name="borrower_name"[\s\S]*?name="borrower_email"[\s\S]*?name="borrower_phone"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="starts_at"[\s\S]*?name="borrower_phone"/',
            $html
        );

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'budget_type' => '',
            ]))
            ->assertRedirect();

        $withoutBudget = RoomBorrowing::query()->latest('id')->firstOrFail();
        $this->assertFalse($withoutBudget->uses_budget);
        $this->assertNull($withoutBudget->budget_type);
        $this->assertNull($withoutBudget->documentation_url);

        $this->actingAs($viewer)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'budget_type' => $inactive->code,
                'starts_at' => '2026-11-05T09:00',
                'ends_at' => '2026-11-05T11:00',
            ]))
            ->assertRedirect(route('room-borrowings.create'))
            ->assertSessionHasErrors('budget_type');

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'budget_type' => 'dana-hibah',
                'starts_at' => '2026-11-06T09:00',
                'ends_at' => '2026-11-06T11:00',
            ]))
            ->assertRedirect();

        $withBudget = RoomBorrowing::query()->where('budget_type', 'dana-hibah')->firstOrFail();
        $this->assertTrue($withBudget->uses_budget);
        $this->assertSame('Dana Hibah', $withBudget->budgetDisplayLabel());

        $this->actingAs($viewer)
            ->get(route('room-borrowings.show', $withBudget))
            ->assertOk()
            ->assertSee('Dana Hibah')
            ->assertDontSee('Link dokumentasi');
    }

    public function test_used_budget_type_cannot_be_deleted_but_legacy_label_still_shows(): void
    {
        $admin = $this->makeAdmin();
        $place = $this->place();
        $type = RoomBudgetType::query()->where('code', 'anggaran-unit')->firstOrFail();

        $this->actingAs($admin)
            ->post(route('room-borrowings.store'), $this->roomPayload($place['room']->id, [
                'budget_type' => 'anggaran-unit',
            ]))
            ->assertRedirect();

        $this->actingAs($admin)
            ->delete(route('room-budget-types.destroy', $type))
            ->assertRedirect(route('room-budget-types.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('room_budget_types', ['code' => 'anggaran-unit']);

        $this->actingAs($admin)
            ->put(route('room-budget-types.update', $type), [
                'name' => 'Anggaran Unit / Biro',
                'is_active' => '0',
            ])
            ->assertRedirect(route('room-budget-types.index'));

        $this->assertFalse($type->fresh()->is_active);

        $html = $this->actingAs($this->makeViewer())
            ->get(route('room-borrowings.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('value="anggaran-unit"', $html);

        $borrowing = RoomBorrowing::query()->firstOrFail();
        $this->actingAs($admin)
            ->get(route('room-borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('Anggaran Unit / Biro');

        $legacy = RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'requested_by' => $admin->id,
            'borrower_name' => 'Legacy Budget',
            'borrower_email' => 'legacy@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'Biro PPPA',
            'activity_type' => 'rapat',
            'purpose' => 'Kegiatan lama',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
            'uses_budget' => true,
            'budget_type' => 'Anggaran unit lama bebas',
            'documentation_url' => 'https://drive.google.com/file/d/legacy-docs',
            'participant_internal' => true,
            'participant_internal_count' => 5,
            'participant_external' => false,
            'participant_external_count' => 0,
            'approval_document_path' => 'room-borrowings/legacy/approval.pdf',
            'approval_document_original_name' => 'approval.pdf',
            'status' => \App\Enums\FacilityRequestStatus::MenungguPersetujuan,
        ]);

        $this->actingAs($admin)
            ->get(route('room-borrowings.show', $legacy))
            ->assertOk()
            ->assertSee('Anggaran unit lama bebas')
            ->assertSee('https://drive.google.com/file/d/legacy-docs')
            ->assertSee('Link dokumentasi');
    }

    public function test_unused_budget_type_can_be_deleted(): void
    {
        $admin = $this->makeAdmin();
        $type = RoomBudgetType::query()->create([
            'code' => 'sementara-anggaran',
            'name' => 'Sementara Anggaran',
            'is_active' => true,
            'sort_order' => 99,
        ]);

        $this->actingAs($admin)
            ->delete(route('room-budget-types.destroy', $type))
            ->assertRedirect(route('room-budget-types.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('room_budget_types', ['code' => 'sementara-anggaran']);
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'RB'.$suffix, 'name' => 'Kampus RB', 'slug' => 'kampus-rb-'.$suffix, 'is_active' => true,
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
