<?php

namespace Tests\Feature;

use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
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
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingFormAjaxValidationTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_vehicle_ajax_keeps_validation_json_and_rejects_passenger_mismatch_without_saving(): void
    {
        $viewer = $this->makeViewer();
        $campus = $this->campus();
        $route = OperationalRoute::query()->create(['name' => 'Dalam Kota', 'is_active' => true]);
        $document = UploadedFile::fake()->image('bukti.jpg');

        $html = $this->actingAs($viewer)->get(route('vehicle-borrowings.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-ajax-submit', $html);
        $this->assertStringContainsString('data-error-bag="passengers"', $html);
        $this->assertStringContainsString('form-ajax-submit.js', $html);

        $response = $this->actingAs($viewer)
            ->withHeaders($this->ajaxHeaders())
            ->post(route('vehicle-borrowings.store'), [
                'campus_id' => $campus->id,
                'route_id' => $route->id,
                'borrower_name' => 'Azwar Syifa',
                'borrower_email' => 'azwar@undira.ac.id',
                'borrower_phone' => '081234567890',
                'destination' => 'Undira Tandur',
                'purpose' => 'Kunjungan',
                'service_type' => 'round_trip',
                'passenger_count' => 1,
                'passengers' => [
                    ['name' => 'Azwar', 'unit' => 'PPPA', 'title' => 'Staf'],
                    ['name' => 'Budi', 'unit' => 'PPPA', 'title' => 'Staf'],
                    ['name' => 'Citra', 'unit' => 'PPPA', 'title' => 'Staf'],
                ],
                'starts_at' => '2026-11-02T09:00',
                'ends_at' => '2026-11-02T11:00',
                'supporting_document' => $document,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['passengers']);
        $this->assertSame(
            'Jumlah nama penumpang harus sama dengan jumlah penumpang.',
            $response->json('errors.passengers.0'),
        );
        $this->assertSame(0, VehicleBorrowing::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_vehicle_ajax_rejects_invalid_phone_and_succeeds_when_valid(): void
    {
        $viewer = $this->makeViewer();
        $campus = $this->campus();
        $route = OperationalRoute::query()->create(['name' => 'Cibubur Grogol', 'is_active' => true]);

        $this->actingAs($viewer)
            ->withHeaders($this->ajaxHeaders())
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($campus->id, $route->id, [
                'borrower_phone' => '123',
                'supporting_document' => UploadedFile::fake()->image('kartu.jpg'),
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrower_phone']);

        $this->assertSame(0, VehicleBorrowing::query()->count());

        $ok = $this->actingAs($viewer)
            ->withHeaders($this->ajaxHeaders())
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($campus->id, $route->id, [
                'supporting_document' => UploadedFile::fake()->image('kartu.jpg'),
            ]))
            ->assertOk()
            ->assertJsonStructure(['message', 'redirect']);

        $saved = VehicleBorrowing::query()->firstOrFail();
        $this->assertStringContainsString(route('vehicle-borrowings.show', $saved), $ok->json('redirect'));
        $this->assertNotNull($saved->supporting_document_path);
        Storage::disk('local')->assertExists($saved->supporting_document_path);
    }

    public function test_room_ajax_rejects_schedule_and_keeps_no_orphan_files(): void
    {
        $viewer = $this->makeViewer();
        $place = $this->place();

        $html = $this->actingAs($viewer)->get(route('room-borrowings.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-ajax-submit', $html);

        $this->actingAs($viewer)
            ->withHeaders($this->ajaxHeaders())
            ->post(route('room-borrowings.store'), [
                'room_id' => $place['room']->id,
                'borrower_name' => 'Sari',
                'borrower_email' => 'sari@undira.ac.id',
                'borrower_phone' => '081234567890',
                'activity_type' => 'rapat',
                'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
                'participant_internal' => '1',
                'participant_internal_count' => 20,
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
                'starts_at' => '2026-11-02T11:00',
                'ends_at' => '2026-11-02T09:00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['ends_at']);

        $this->assertSame(0, RoomBorrowing::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_inventory_ajax_create_form_is_wired_and_rejects_invalid_schedule(): void
    {
        $admin = $this->makeAdmin(['email' => 'ajax.admin@undira.ac.id']);
        $item = $this->item();

        $html = $this->actingAs($admin)->get(route('borrowings.create'))->assertOk()->getContent();
        $this->assertStringContainsString('data-ajax-submit', $html);
        $this->assertStringContainsString('data-error-bag="items"', $html);
        $this->assertStringContainsString('value="ajax.admin@undira.ac.id"', $html);

        // Client-supplied invalid email is ignored; schedule validation still returns 422.
        $this->actingAs($admin)
            ->withHeaders($this->ajaxHeaders())
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$item->id],
                'borrower_name' => 'Peminjam',
                'borrower_email' => 'bukan-email',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->format('Y-m-d\TH:i'),
                'notes' => 'Rapat',
                'status' => 'menunggu_persetujuan',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['expected_return_date'])
            ->assertJsonMissingValidationErrors(['borrower_email']);

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_non_ajax_vehicle_store_still_redirects_on_success(): void
    {
        $viewer = $this->makeViewer();
        $campus = $this->campus();
        $route = OperationalRoute::query()->create(['name' => 'Legacy Redirect', 'is_active' => true]);

        $this->actingAs($viewer)
            ->post(route('vehicle-borrowings.store'), $this->vehiclePayload($campus->id, $route->id))
            ->assertRedirect();

        $this->assertSame(1, VehicleBorrowing::query()->count());
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function vehiclePayload(int $campusId, int $routeId, array $overrides = []): array
    {
        return array_merge([
            'campus_id' => $campusId,
            'route_id' => $routeId,
            'borrower_name' => 'Sari Kendaraan',
            'borrower_email' => 'sari.kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Antar dokumen',
            'service_type' => 'drop_off',
            'destination' => 'Grogol, Jakarta Barat',
            'passenger_count' => 1,
            'passengers' => [
                ['name' => 'Sari', 'unit' => 'Biro', 'title' => 'Staff'],
            ],
            'starts_at' => '2026-11-02T09:00',
            'ends_at' => '2026-11-02T11:00',
        ], $overrides);
    }

    private function campus(): Campus
    {
        $suffix = uniqid();

        return Campus::query()->create([
            'code' => 'CV'.$suffix,
            'name' => 'Kampus Cibubur',
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
    }

    /**
     * @return array{campus: Campus, room: Room}
     */
    private function place(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CR'.$suffix, 'name' => 'Kampus Ruang', 'slug' => 'kampus-ruang-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BR'.$suffix, 'name' => 'Gedung', 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'FR'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RR'.$suffix, 'name' => 'Ruang Rapat', 'room_type' => 'Kelas', 'capacity' => 20, 'is_active' => true,
        ]);

        return compact('campus', 'room');
    }

    private function item(): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CI'.$suffix, 'name' => 'Kampus Item', 'slug' => 'kampus-item-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BI'.$suffix, 'name' => 'Gedung', 'slug' => 'gedung-item-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'FI'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RI'.$suffix, 'name' => 'Lab', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQ'.$suffix, 'name' => 'Equipment', 'slug' => 'eq-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC'.$suffix, 'name' => 'PC', 'slug' => 'pc-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => 'INV-AJAX-'.$suffix,
            'barcode' => 'BC-'.$suffix,
            'name' => 'Laptop',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }
}
