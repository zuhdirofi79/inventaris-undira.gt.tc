<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryBorrowingAvailabilityTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_available_item_is_listed_as_selectable(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Bebas', InventoryStatus::Aktif);

        $results = $this->search($admin, 'Pulpen Bebas', '2026-10-12T11:00', '2026-10-12T16:00');

        $this->assertCount(1, $results);
        $this->assertSame($item->id, $results[0]['id']);
        $this->assertSame('Tersedia', $results[0]['status_label']);
        $this->assertSame('available', $results[0]['availability_state']);
        $this->assertFalse($results[0]['disabled']);
    }

    public function test_approved_overlap_is_hidden_and_rejected_on_store(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Disetujui', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Disetujui, BorrowingStatus::Disetujui, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $this->assertSame([], $this->search($admin, 'Pulpen Disetujui', '2026-10-12T11:00', '2026-10-12T16:00'));

        $this->store($admin, $item, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('items');

        $this->assertSame(1, Borrowing::query()->count());
    }

    public function test_pending_overlap_stays_visible_but_cannot_be_selected_or_stored(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Pending', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::MenungguPersetujuan, BorrowingStatus::MenungguPersetujuan, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $results = $this->search($admin, 'Pulpen Pending', '2026-10-12T11:00', '2026-10-12T16:00');

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['disabled']);
        $this->assertSame('Menunggu Persetujuan', $results[0]['availability_label']);
        $this->assertSame('Sedang diajukan oleh pengguna lain.', $results[0]['availability_reason']);
        $this->assertStringNotContainsString('rahasia-peminjam@undira.ac.id', json_encode($results));

        $this->actingAs($admin)
            ->getJson(route('inventory.selector.borrow-siblings', [
                'item' => $item->id,
                'qty' => 1,
                'borrow_date' => '2026-10-12T11:00',
                'expected_return_date' => '2026-10-12T16:00',
            ]))
            ->assertStatus(422);

        $this->store($admin, $item, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('items');
        $this->assertSame(1, Borrowing::query()->count());
    }

    public function test_rejected_request_does_not_keep_blocking(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Ditolak', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Ditolak, BorrowingStatus::Ditolak, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $results = $this->search($admin, 'Pulpen Ditolak', '2026-10-12T11:00', '2026-10-12T16:00');
        $this->assertSame('available', $results[0]['availability_state']);

        $this->store($admin, $item, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertRedirect(route('borrowings.index'));
    }

    public function test_non_overlapping_schedule_can_be_selected_including_adjacent_end(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Jadwal', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Disetujui, BorrowingStatus::Disetujui, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $results = $this->search($admin, 'Pulpen Jadwal', '2026-10-12T15:00', '2026-10-12T17:00');
        $this->assertSame('available', $results[0]['availability_state']);

        $this->store($admin, $item, '2026-10-12T15:00', '2026-10-12T17:00')
            ->assertRedirect(route('borrowings.index'));
        $this->assertSame(2, Borrowing::query()->count());
    }

    public function test_approved_room_hides_its_inventory_and_pending_room_disables_it(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocation();
        $approvedItem = $this->makeItem($ctx, 'Pulpen Ruang Disetujui', InventoryStatus::Aktif);
        $pendingRoom = Room::query()->create([
            'floor_id' => $ctx['floor']->id,
            'code' => 'A202',
            'name' => 'Ruang A202',
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $pendingItem = $this->makeItem($ctx, 'Pulpen Ruang Pending', InventoryStatus::Aktif, $pendingRoom->id);

        $this->roomLoan($ctx['room'], $admin, FacilityRequestStatus::Disetujui, '2026-10-12 09:00:00', '2026-10-12 15:00:00');
        $this->roomLoan($pendingRoom, $admin, FacilityRequestStatus::MenungguPersetujuan, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $this->assertSame([], $this->search($admin, 'Pulpen Ruang Disetujui', '2026-10-12T11:00', '2026-10-12T16:00'));
        $pending = $this->search($admin, 'Pulpen Ruang Pending', '2026-10-12T11:00', '2026-10-12T16:00');
        $this->assertSame('available', $pending[0]['availability_state']);
        $this->assertFalse($pending[0]['disabled']);

        $this->store($admin, $approvedItem, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertSessionHasErrors('items');
        $this->store($admin, $pendingItem, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertRedirect(route('borrowings.index'));
        $this->assertSame(1, Borrowing::query()->count());

        $later = $this->search($admin, 'Pulpen Ruang Disetujui', '2026-10-13T09:00', '2026-10-13T12:00');
        $this->assertSame('available', $later[0]['availability_state']);
    }

    public function test_approved_inventory_blocks_a_room_request_on_the_same_schedule(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocation();
        $item = $this->makeItem($ctx, 'Pulpen Kunci Ruang', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Disetujui, BorrowingStatus::Disetujui, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $this->actingAs($admin)
            ->from(route('room-borrowings.create'))
            ->post(route('room-borrowings.store'), [
                'room_id' => $ctx['room']->id,
                'borrower_name' => 'Peminjam Ruangan',
                'borrower_email' => 'ruang@undira.ac.id',
                'borrower_phone' => '081234567890',
                'activity_type' => 'rapat',
                'purpose' => 'Rapat koordinasi persiapan kegiatan universitas',
                'participant_internal' => '1',
                'participant_internal_count' => 15,
                'starts_at' => '2026-10-12T11:00',
                'ends_at' => '2026-10-12T16:00',
                'approval_document' => UploadedFile::fake()->create('persetujuan.pdf', 80, 'application/pdf'),
            ])
            ->assertSessionHasErrors('starts_at');

        $this->assertSame(0, RoomBorrowing::query()->count());
    }

    public function test_rejected_room_does_not_block_inventory(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocation();
        $item = $this->makeItem($ctx, 'Pulpen Ruang Ditolak', InventoryStatus::Aktif);
        $this->roomLoan($ctx['room'], $admin, FacilityRequestStatus::Ditolak, '2026-10-12 09:00:00', '2026-10-12 15:00:00');

        $results = $this->search($admin, 'Pulpen Ruang Ditolak', '2026-10-12T11:00', '2026-10-12T16:00');
        $this->assertSame('available', $results[0]['availability_state']);
    }

    public function test_handed_over_item_stays_unavailable_after_planned_return(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Belum Kembali', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Dipinjam, BorrowingStatus::Dipinjam, '2026-10-01 09:00:00', '2026-10-01 15:00:00');

        $this->assertSame([], $this->search($admin, 'Pulpen Belum Kembali', '2026-10-20T09:00', '2026-10-20T12:00'));
        $this->store($admin, $item, '2026-10-20T09:00', '2026-10-20T12:00')
            ->assertSessionHasErrors('items');
    }

    public function test_returned_item_can_be_borrowed_again(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Kembali', InventoryStatus::Aktif);
        $this->loan($item, $admin, BorrowingItemStatus::Dikembalikan, BorrowingStatus::Dikembalikan, '2026-10-01 09:00:00', '2026-10-01 15:00:00');

        $results = $this->search($admin, 'Pulpen Kembali', '2026-10-12T09:00', '2026-10-12T12:00');
        $this->assertSame('available', $results[0]['availability_state']);
        $this->store($admin, $item, '2026-10-12T09:00', '2026-10-12T12:00')
            ->assertRedirect(route('borrowings.index'));
    }

    public function test_maintenance_item_cannot_be_selected(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Servis', InventoryStatus::Maintenance);

        $this->assertSame([], $this->search($admin, 'Pulpen Servis', '2026-10-12T09:00', '2026-10-12T12:00'));
        $this->store($admin, $item, '2026-10-12T09:00', '2026-10-12T12:00')
            ->assertSessionHasErrors('inventory_item_id');
        $this->assertSame(0, Borrowing::query()->count());
    }

    public function test_second_overlapping_store_does_not_create_a_duplicate(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem($this->seedLocation(), 'Pulpen Balapan', InventoryStatus::Aktif);

        $this->store($admin, $item, '2026-10-12T09:00', '2026-10-12T15:00')
            ->assertRedirect(route('borrowings.index'));
        $this->store($admin, $item, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertSessionHasErrors('items');

        $this->assertSame(1, Borrowing::query()->where('inventory_item_id', $item->id)->count());
    }

    public function test_partial_approval_only_blocks_the_approved_item(): void
    {
        $admin = $this->makeAdmin();
        $ctx = $this->seedLocation();
        $approved = $this->makeItem($ctx, 'Pulpen Sebagian Setuju', InventoryStatus::Aktif);
        $rejected = $this->makeItem($ctx, 'Pulpen Sebagian Tolak', InventoryStatus::Aktif);
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $approved->id,
            'borrower_name' => 'Peminjam Lain',
            'borrower_email' => 'rahasia-peminjam@undira.ac.id',
            'borrower_phone' => '081111111111',
            'borrow_date' => '2026-10-12 09:00:00',
            'expected_return_date' => '2026-10-12 15:00:00',
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Sebagian',
            'requested_by' => $admin->id,
        ]);
        $borrowing->items()->create([
            'inventory_item_id' => $approved->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);
        $borrowing->items()->create([
            'inventory_item_id' => $rejected->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Ditolak,
        ]);

        $this->assertSame([], $this->search($admin, 'Pulpen Sebagian Setuju', '2026-10-12T11:00', '2026-10-12T16:00'));
        $open = $this->search($admin, 'Pulpen Sebagian Tolak', '2026-10-12T11:00', '2026-10-12T16:00');
        $this->assertSame('available', $open[0]['availability_state']);
        $this->store($admin, $rejected, '2026-10-12T11:00', '2026-10-12T16:00')
            ->assertRedirect(route('borrowings.index'));
    }

    public function test_create_form_sends_the_schedule_to_the_selector(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)->get(route('borrowings.create'))->assertOk()->getContent();

        $this->assertStringContainsString('data-schedule-start="#borrow_date"', $html);
        $this->assertStringContainsString('data-schedule-end="#expected_return_date"', $html);
        $this->assertStringContainsString(
            'Menunggu Persetujuan',
            (string) file_get_contents(public_path('js/inventory-selector.js'))
        );
    }

    /**
     * @return array{campus: Campus, building: Building, floor: Floor, room: Room, category: Category, sub: SubCategory}
     */
    private function seedLocation(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CB'.$suffix,
            'name' => 'Kampus '.$suffix,
            'slug' => 'kampus-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$suffix,
            'slug' => 'gedung-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L'.$suffix,
            'name' => 'Lantai '.$suffix,
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R'.$suffix,
            'name' => 'Ruang '.$suffix,
            'room_type' => 'Kelas',
            'capacity' => 30,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kategori '.$suffix,
            'slug' => 'kategori-'.$suffix,
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'S'.$suffix,
            'name' => 'Sub '.$suffix,
            'slug' => 'sub-'.$suffix,
            'is_active' => true,
        ]);

        return compact('campus', 'building', 'floor', 'room', 'category', 'sub');
    }

    /**
     * @param  array{campus: Campus, building: Building, floor: Floor, room: Room, category: Category, sub: SubCategory}  $ctx
     */
    private function makeItem(array $ctx, string $name, InventoryStatus $status, ?int $roomId = null): InventoryItem
    {
        return InventoryItem::query()->create([
            'inventory_number' => 'INV-AVL-'.uniqid(),
            'barcode' => 'BC-AVL-'.uniqid(),
            'name' => $name,
            'category_id' => $ctx['category']->id,
            'sub_category_id' => $ctx['sub']->id,
            'room_id' => $roomId ?? $ctx['room']->id,
            'condition' => InventoryCondition::Bagus,
            'status' => $status,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC Lab',
        ]);
    }

    private function loan(
        InventoryItem $item,
        User $user,
        BorrowingItemStatus $itemStatus,
        BorrowingStatus $headerStatus,
        string $start,
        string $end,
    ): Borrowing {
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Lain',
            'borrower_email' => 'rahasia-peminjam@undira.ac.id',
            'borrower_phone' => '081111111111',
            'borrow_date' => $start,
            'expected_return_date' => $end,
            'status' => $headerStatus,
            'notes' => 'Kegiatan internal',
            'requested_by' => $user->id,
        ]);
        $borrowing->items()->create([
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => $itemStatus,
        ]);

        return $borrowing;
    }

    private function roomLoan(Room $room, User $user, FacilityRequestStatus $status, string $start, string $end): void
    {
        RoomBorrowing::query()->create([
            'room_id' => $room->id,
            'campus_id' => $room->floor?->building?->campus_id,
            'borrower_name' => 'Peminjam Ruangan',
            'borrower_email' => 'rahasia-ruangan@undira.ac.id',
            'borrower_phone' => '081222222222',
            'purpose' => 'Rapat',
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => $status,
            'requested_by' => $user->id,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function search(User $user, string $q, string $start, string $end): array
    {
        return $this->actingAs($user)
            ->getJson(route('inventory.selector.search', [
                'q' => $q,
                'status' => 'aktif',
                'borrow_date' => $start,
                'expected_return_date' => $end,
            ]))
            ->assertOk()
            ->json('results');
    }

    private function store(User $user, InventoryItem $item, string $start, string $end): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($user)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Pengaju Baru',
                'borrower_email' => 'pengaju@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrow_date' => $start,
                'expected_return_date' => $end,
                'notes' => 'Praktikum',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ]);
    }
}
