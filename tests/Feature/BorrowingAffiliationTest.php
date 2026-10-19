<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\OperationalRoute;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\VehicleBorrowing;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingAffiliationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
    }

    public function test_inventory_viewer_stores_and_shows_affiliation(): void
    {
        $viewer = $this->makeViewer([
            'name' => 'Azwar Viewer',
            'email' => 'azwar.aff@undira.ac.id',
        ]);
        $item = $this->item();

        $createHtml = $this->actingAs($viewer)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->getContent();
        $this->assertMatchesRegularExpression('/>Unit\s*<span class="text-danger">\*<\/span>/', $createHtml);
        $this->assertStringContainsString('name="borrower_affiliation"', $createHtml);
        $this->assertStringContainsString('Wajib diisi. Masukkan nama biro, fakultas, program studi, Ormawa, atau UKM peminjam.', $createHtml);

        $this->actingAs($viewer)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$item->id],
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'palsu@example.com',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => '   ',
                'borrow_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Praktikum',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors(['borrower_affiliation' => 'Kolom Unit wajib diisi.']);

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$item->id],
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'palsu@example.com',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Praktikum',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->firstOrFail();
        $this->assertSame('Azwar Viewer', $borrowing->borrower_name);
        $this->assertSame('azwar.aff@undira.ac.id', $borrowing->borrower_email);
        $this->assertSame('PPPA', $borrowing->borrower_affiliation);

        $this->actingAs($viewer)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('PPPA')
            ->assertSee('Unit');
    }

    public function test_room_stores_affiliation_on_detail(): void
    {
        $viewer = $this->makeViewer();
        $place = $this->place();

        $this->actingAs($viewer)
            ->post(route('room-borrowings.store'), [
                'room_id' => $place['room']->id,
                'borrower_name' => $viewer->name,
                'borrower_email' => $viewer->email,
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'Teknik Perminyakan',
                'activity_type' => 'rapat',
                'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
                'participant_internal' => '1',
                'participant_internal_count' => 20,
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
                'starts_at' => '2026-11-20T09:00',
                'ends_at' => '2026-11-20T11:00',
            ])
            ->assertRedirect();

        $borrowing = RoomBorrowing::query()->firstOrFail();
        $this->assertSame('Teknik Perminyakan', $borrowing->borrower_affiliation);

        $this->actingAs($this->makeAdmin())
            ->get(route('room-borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('Teknik Perminyakan');
    }

    public function test_vehicle_reuses_unit_as_affiliation(): void
    {
        $viewer = $this->makeViewer();
        $campus = Campus::query()->create([
            'code' => 'CA'.uniqid(),
            'name' => 'Kampus Aff',
            'slug' => 'kampus-aff-'.uniqid(),
            'is_active' => true,
        ]);
        $route = OperationalRoute::query()->create([
            'name' => 'Rute Aff '.uniqid(),
            'is_active' => true,
        ]);

        $vehicleCreate = $this->actingAs($viewer)
            ->get(route('vehicle-borrowings.create'))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Unit', $vehicleCreate);
        $this->assertStringContainsString('name="unit"', $vehicleCreate);
        $this->assertMatchesRegularExpression(
            '/name="borrower_name"[\s\S]*?name="borrower_email"[\s\S]*?name="borrower_phone"[\s\S]*?name="unit"/',
            $vehicleCreate
        );
        $this->assertDoesNotMatchRegularExpression(
            '/name="supporting_document"[\s\S]*?name="borrower_phone"/',
            $vehicleCreate
        );

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), [
                'campus_id' => $campus->id,
                'route_id' => $route->id,
                'borrower_name' => $viewer->name,
                'borrower_email' => $viewer->email,
                'borrower_phone' => '081234567890',
                'unit' => 'BEM Universitas Dian Nusantara',
                'purpose' => 'Antar dokumen',
                'service_type' => 'drop_off',
                'destination' => 'Grogol, Jakarta Barat',
                'passenger_count' => 1,
                'passengers' => [
                    ['name' => 'Azwar', 'unit' => 'BEM', 'title' => 'Staff'],
                ],
                'starts_at' => '2026-11-20T09:00',
                'ends_at' => '2026-11-20T11:00',
            ])
            ->assertRedirect();

        $borrowing = VehicleBorrowing::query()->firstOrFail();
        $this->assertSame('BEM Universitas Dian Nusantara', $borrowing->unit);

        $this->actingAs($this->makeAdmin())
            ->get(route('vehicle-borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('BEM Universitas Dian Nusantara');
    }

    public function test_inventory_rejects_overlong_affiliation_and_accepts_common_chars(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->item();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->inventoryPayload($item, [
                'borrower_affiliation' => str_repeat('A', 256),
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('borrower_affiliation');

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->inventoryPayload($item, [
                'borrower_affiliation' => 'PPPA / Bagian Operasional (FTI) & UKM',
            ]))
            ->assertRedirect(route('borrowings.index'));

        $this->assertSame(
            'PPPA / Bagian Operasional (FTI) & UKM',
            Borrowing::query()->latest('id')->value('borrower_affiliation'),
        );
    }

    public function test_legacy_null_affiliation_renders_fallback(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->item();
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Lama',
            'borrower_email' => 'lama@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => null,
            'borrow_date' => now()->addDay(),
            'expected_return_date' => now()->addDays(2),
            'notes' => 'Legacy',
            'status' => 'menunggu_persetujuan',
            'requested_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('Unit')
            ->assertSee('—');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function inventoryPayload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_ids' => [$item->id],
            'borrower_name' => 'Admin Aff',
            'borrower_email' => 'admin.aff@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'borrow_date' => now()->addDay()->format('Y-m-d\TH:i'),
            'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'notes' => 'Uji afiliasi',
            'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room}
     */
    private function place(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CR'.$suffix, 'name' => 'Kampus Room', 'slug' => 'kampus-room-'.$suffix, 'is_active' => true,
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

    private function item(): InventoryItem
    {
        $place = $this->place();
        $suffix = uniqid();
        $category = Category::query()->create([
            'code' => 'K'.$suffix, 'name' => 'Kat Aff', 'slug' => 'kat-aff-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$suffix, 'name' => 'Sub Aff', 'slug' => 'sub-aff-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => 'INV-AFF-'.$suffix,
            'name' => 'Proyektor Aff',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $place['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }
}
