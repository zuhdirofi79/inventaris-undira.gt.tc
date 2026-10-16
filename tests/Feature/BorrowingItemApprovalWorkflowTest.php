<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Mail\BorrowingApprovedMail;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingItemApprovalWorkflowTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_five_item_partial_approval_handover_and_return_workflow(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'flow@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-flow@undira.ac.id']);
        $items = collect(range(1, 5))->map(fn ($i) => $this->makeItem([
            'inventory_number' => 'INV-FLOW-'.$i.'-'.uniqid(),
            'name' => 'Barang Flow '.$i,
        ]));

        $this->actingAs($requester)
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => $items->pluck('id')->all(),
                'borrower_name' => 'Flow User',
                'borrower_email' => 'flow@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Tes workflow per item',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertNotNull($borrowing);
        $this->assertSame('flow@undira.ac.id', $borrowing->borrower_email);
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertCount(5, $borrowing->items);
        $this->assertTrue($borrowing->items->every(
            fn ($line) => $line->status === BorrowingItemStatus::MenungguPersetujuan
        ));
        foreach ($items as $item) {
            $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        }

        $lines = $borrowing->items()->orderBy('id')->get();
        $approveLines = $lines->take(3);
        $rejectLines = $lines->skip(3)->values();

        foreach ($approveLines as $line) {
            $this->actingAs($admin)
                ->post(route('borrowings.items.approve', [$borrowing, $line]))
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);

        foreach ($rejectLines as $line) {
            $this->actingAs($admin)
                ->post(route('borrowings.items.reject', [$borrowing, $line]), [
                    'rejection_reason' => 'Inventaris sedang digunakan untuk kegiatan lain.',
                ])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertNotNull($borrowing->approval_number);

        $approved = $borrowing->items->where('status', BorrowingItemStatus::Disetujui);
        $rejected = $borrowing->items->where('status', BorrowingItemStatus::Ditolak);
        $this->assertCount(3, $approved);
        $this->assertCount(2, $rejected);
        $this->assertTrue($rejected->every(
            fn ($line) => $line->rejection_reason === 'Inventaris sedang digunakan untuk kegiatan lain.'
        ));

        foreach ($items as $item) {
            $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        }

        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('flow@undira.ac.id')
                && str_contains($mail->envelope()->subject, 'Sebagian')
                && str_contains($html, 'Sebagian inventaris pada pengajuan Anda telah disetujui')
                && str_contains($html, 'INVENTARIS DISETUJUI')
                && str_contains($html, 'INVENTARIS TIDAK DISETUJUI');
        });
        Mail::assertSent(BorrowingApprovedMail::class, 1);

        // Rejected items must not be handable / borrowable via this transaction.
        $rejectedLine = $rejected->first();
        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $rejectedLine]))
            ->assertRedirect()
            ->assertSessionHasErrors('item');

        $borrowedAt = now()->subHour()->seconds(0);
        foreach ($approved as $line) {
            $this->actingAs($admin)
                ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                    'borrowed_at' => $borrowedAt->format('Y-m-d H:i:s'),
                ])
                ->assertRedirect()
                ->assertSessionHas('success');
        }

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Dipinjam, $borrowing->status);

        foreach ($approved as $line) {
            $fresh = $line->fresh();
            $this->assertSame(BorrowingItemStatus::Dipinjam, $fresh->status);
            $this->assertNotNull($fresh->borrowed_at);
            $this->assertSame(InventoryStatus::Dipinjam, $fresh->inventoryItem->fresh()->status);
        }

        foreach ($rejected as $line) {
            $this->assertSame(BorrowingItemStatus::Ditolak, $line->fresh()->status);
            $this->assertSame(InventoryStatus::Aktif, $line->inventoryItem->fresh()->status);
        }

        // Duplicate approve must fail.
        $this->actingAs($admin)
            ->post(route('borrowings.items.approve', [$borrowing, $approveLines->first()]))
            ->assertForbidden();

        $returnedAt = now()->format('Y-m-d H:i:s');
        foreach ($approved as $line) {
            $this->actingAs($admin)
                ->post(route('borrowings.return-items', $borrowing), [
                    'item_ids' => [$line->id],
                    'returned_at' => $returnedAt,
                ])
                ->assertRedirect(route('borrowings.show', $borrowing));
        }

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Dikembalikan, $borrowing->status);

        foreach ($approved as $line) {
            $fresh = $line->fresh();
            $this->assertSame(BorrowingItemStatus::Dikembalikan, $fresh->status);
            $this->assertNotNull($fresh->returned_at);
            $this->assertSame(InventoryStatus::Aktif, $fresh->inventoryItem->fresh()->status);
        }

        // Rejected inventory remains available for a new borrowing.
        $rejectedInventory = $rejected->first()->inventoryItem;
        $this->actingAs($requester)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $rejectedInventory->id,
                'borrower_name' => 'Flow User 2',
                'borrower_email' => 'flow2@undira.ac.id',
                'borrower_phone' => '081298765432',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDay()->format('Y-m-d\TH:i'),
                'notes' => 'Pinjam ulang item ditolak',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'));

        $this->assertSame(InventoryStatus::Aktif, $rejectedInventory->fresh()->status);
    }

    public function test_show_page_exposes_per_item_actions(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-ui-item@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-ui-item@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'UI Item',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
            'notes' => 'UI',
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Setujui', $html);
        $this->assertStringContainsString('Tolak', $html);
        $this->assertStringContainsString('Simpan Approval', $html);
        $this->assertStringContainsString('Status Persetujuan', $html);
        $this->assertStringContainsString('Tgl Pinjam Aktual', $html);
        $this->assertStringContainsString('saveApprovalModal', $html);
        $this->assertStringContainsString('js-item-decision', $html);
        $this->assertStringContainsString('borrowing-items-table', $html);
        $this->assertStringContainsString('col-aksi', $html);
        $this->assertStringNotContainsString('approveItemModal', $html);
    }

    public function test_cannot_approve_item_twice(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-twice-item@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-twice-item@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Twice',
            'borrower_email' => 'twice@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
            'notes' => 'Twice',
        ]);
        $line = BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.items.approve', [$borrowing, $line]))
            ->assertRedirect();

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);

        $this->actingAs($admin)
            ->post(route('borrowings.items.approve', [$borrowing, $line]))
            ->assertForbidden();
    }

    public function test_handover_stores_actual_datetime_and_rejects_a_second_attempt(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-hand@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-hand@undira.ac.id']);
        [$borrowing, $line, $item] = $this->approvedLine($requester, $admin, 'Pulpen Serah');

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Tandai Dipinjam', $html);
        $this->assertStringContainsString('Serahkan Barang', $html);
        $this->assertStringContainsString('datetime-local', $html);
        $this->assertGreaterThan(strpos($html, 'main-footer'), strpos($html, 'id="handoverItemModal"'));

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => '',
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('borrowed_at');
        $this->assertSame(BorrowingItemStatus::Disetujui, $line->fresh()->status);
        $this->assertNull($line->fresh()->borrowed_at);

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => 'bukan-tanggal',
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('borrowed_at');

        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => '2026-10-09T11:26',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $line->fresh();
        $this->assertSame(BorrowingItemStatus::Dipinjam, $fresh->status);
        $this->assertSame('2026-10-09 11:26', $fresh->borrowed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'));
        $this->assertSame(InventoryStatus::Dipinjam, $item->fresh()->status);
        $this->assertSame(BorrowingStatus::Dipinjam, $borrowing->fresh()->status);

        $after = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('09/10/2026 11:26', $after);
        $this->assertStringNotContainsString('data-bs-target="#handoverItemModal"', $after);
        $this->assertStringContainsString('Tandai Dikembalikan', $after);

        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => '2026-10-09T18:00',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('item');
        $this->assertSame('2026-10-09 11:26', $line->fresh()->borrowed_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'));
    }

    public function test_handover_rejects_pending_foreign_unauthorized_and_non_active_inventory(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-hand-guard@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-hand-guard@undira.ac.id']);
        [$borrowing, $line] = $this->approvedLine($requester, $admin, 'Pulpen Guard');
        $line->update(['status' => BorrowingItemStatus::MenungguPersetujuan]);
        $borrowing->update(['status' => BorrowingStatus::MenungguPersetujuan]);

        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => '2026-10-09T11:26',
            ])
            ->assertForbidden();
        $this->assertSame(BorrowingItemStatus::MenungguPersetujuan, $line->fresh()->status);

        [$approvedBorrowing, $approvedLine, $approvedItem] = $this->approvedLine($requester, $admin, 'Pulpen Aktif');
        [$otherBorrowing, $otherLine] = $this->approvedLine($requester, $admin, 'Pulpen Lain');

        $this->actingAs($admin)
            ->from(route('borrowings.show', $approvedBorrowing))
            ->post(route('borrowings.items.handover', [$approvedBorrowing, $otherLine]), [
                'borrowed_at' => '2026-10-09T11:26',
            ])
            ->assertRedirect(route('borrowings.show', $approvedBorrowing))
            ->assertSessionHasErrors('item');
        $this->assertSame(BorrowingItemStatus::Disetujui, $otherLine->fresh()->status);

        $this->actingAs($requester)
            ->post(route('borrowings.items.handover', [$approvedBorrowing, $approvedLine]), [
                'borrowed_at' => '2026-10-09T11:26',
            ])
            ->assertForbidden();

        $approvedItem->update(['status' => InventoryStatus::Maintenance]);
        $this->actingAs($admin)
            ->from(route('borrowings.show', $approvedBorrowing))
            ->post(route('borrowings.items.handover', [$approvedBorrowing, $approvedLine]), [
                'borrowed_at' => '2026-10-09T11:26',
            ])
            ->assertRedirect(route('borrowings.show', $approvedBorrowing))
            ->assertSessionHasErrors('inventory_item_id');
        $this->assertSame(BorrowingItemStatus::Disetujui, $approvedLine->fresh()->status);
        $this->assertNull($approvedLine->fresh()->borrowed_at);
        $this->assertSame(InventoryStatus::Maintenance, $approvedItem->fresh()->status);
        $this->assertSame(BorrowingStatus::Disetujui, $approvedBorrowing->fresh()->status);
    }

    /**
     * @return array{0: Borrowing, 1: BorrowingItem, 2: InventoryItem}
     */
    protected function approvedLine($requester, $admin, string $name): array
    {
        $item = $this->makeItem([
            'inventory_number' => 'CBB-HAND-'.uniqid(),
            'name' => $name,
        ]);
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Serah',
            'borrower_email' => 'serah@undira.ac.id',
            'borrow_date' => '2026-10-09',
            'expected_return_date' => '2026-10-10',
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'APR-'.now()->format('Ymd').'-'.random_int(100000, 999999),
            'requested_by' => $requester->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);
        $line = BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Disetujui,
        ]);

        return [$borrowing, $line, $item];
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CF'.$suffix, 'name' => 'Kampus Flow', 'slug' => 'kampus-flow-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BF'.$suffix, 'name' => 'Gedung F', 'slug' => 'gedung-f-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1'.$suffix, 'name' => 'Ruang Flow', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQF'.$suffix, 'name' => 'Equipment Flow', 'slug' => 'equipment-flow-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PCF'.$suffix, 'name' => 'PC Flow', 'slug' => 'pc-flow-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-FLOW-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Laptop Flow',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ], $overrides));
    }
}
