<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ApprovalPublicItemStatusTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_returned_transaction_keeps_each_item_approval_decision(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['email' => 'admin-public-approval@undira.ac.id']);
        $borrowing = $this->makeBorrowing($admin, BorrowingStatus::Dikembalikan, [
            [BorrowingItemStatus::Dikembalikan, 'TJD-APBD-26-ATK-PL-000001', 'Undira Tanjung Duren / Gedung A / Lantai 1 / Kelas A103'],
            [BorrowingItemStatus::Ditolak, 'TJD-APBD-26-ATK-PL-000002', 'Undira Tanjung Duren / Gedung A / Lantai 1 / Kelas A102'],
            [BorrowingItemStatus::Disetujui, 'TJD-APBD-26-ATK-PL-000003', 'Undira Tanjung Duren / Gedung A / Lantai 1 / Kelas A103'],
        ], '2 inventaris disetujui, 1 inventaris ditolak.');
        $borrowing->items()->where('status', BorrowingItemStatus::Ditolak)->update([
            'rejection_reason' => 'Rahasia internal operasional',
        ]);
        $before = $borrowing->fresh()->only(['status', 'approved_at', 'approval_notes']);

        $html = $this->get(route('borrowing.approval.public', $borrowing->approval_number))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('max-width: 980px', $html);
        $this->assertStringContainsString('Status Persetujuan', $html);
        $this->assertStringContainsString('Disetujui: 2', $html);
        $this->assertStringContainsString('Ditolak: 1', $html);
        $this->assertStringContainsString('TJD-APBD-26-ATK-PL-000001', $html);
        $this->assertStringContainsString('Kelas A103', $html);
        $this->assertStringContainsString('table-responsive', $html);
        $this->assertSame(2, substr_count($html, 'text-bg-success">Disetujui'));
        $this->assertSame(1, substr_count($html, 'text-bg-danger">Ditolak'));
        $this->assertDoesNotMatchRegularExpression(
            '/TJD-APBD-26-ATK-PL-000001.{0,700}text-bg-[^"]+">(?:Dikembalikan|Ditolak)/s',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/TJD-APBD-26-ATK-PL-000002.{0,700}Ditolak/s',
            $html
        );
        $this->assertStringNotContainsString('Rahasia internal operasional', $html);

        $borrowing->refresh();
        $this->assertSame($before['status'], $borrowing->status);
        $this->assertSame($before['approved_at']?->toDateTimeString(), $borrowing->approved_at?->toDateTimeString());
        $this->assertSame($before['approval_notes'], $borrowing->approval_notes);
        $this->assertSame(
            [BorrowingItemStatus::Dikembalikan, BorrowingItemStatus::Ditolak, BorrowingItemStatus::Disetujui],
            $borrowing->items()->orderBy('id')->pluck('status')->all()
        );
        Mail::assertNothingSent();
    }

    public function test_pending_is_not_inferred_from_an_approved_parent_and_unknown_is_explicit(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-public-pending@undira.ac.id']);
        $borrowing = $this->makeBorrowing($admin, BorrowingStatus::Disetujui, [
            [BorrowingItemStatus::Disetujui, 'CBB-PUB-OK-'.uniqid(), 'Kampus'],
            [BorrowingItemStatus::MenungguPersetujuan, 'CBB-PUB-WAIT-'.uniqid(), 'Kampus'],
        ]);

        $html = $this->get(route('borrowing.approval.public', $borrowing->approval_number))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Disetujui: 1', $html);
        $this->assertStringContainsString('Menunggu: 1', $html);
        $this->assertSame(1, substr_count($html, 'text-bg-warning">Menunggu'));
        $this->assertSame(['Status Tidak Tersedia', 'secondary'], (new BorrowingItem())->approvalPresentation());

        $this->get(route('borrowing.approval.public', 'APR-20261009-999999'))->assertNotFound();
    }

    public function test_all_rejected_rows_stay_rejected_and_many_items_all_render(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-public-many@undira.ac.id']);
        $rejected = $this->makeBorrowing($admin, BorrowingStatus::Ditolak, [
            [BorrowingItemStatus::Ditolak, 'CBB-PUB-NO-'.uniqid(), 'Kampus'],
            [BorrowingItemStatus::Ditolak, 'CBB-PUB-NO-'.uniqid(), 'Kampus'],
        ]);
        $rejectedHtml = $this->get(route('borrowing.approval.public', $rejected->approval_number))->assertOk()->getContent();
        $this->assertStringContainsString('Disetujui: 0', $rejectedHtml);
        $this->assertStringContainsString('Ditolak: 2', $rejectedHtml);
        $this->assertSame(0, substr_count($rejectedHtml, 'text-bg-success">Disetujui'));

        $rows = [];
        for ($i = 1; $i <= 50; $i++) {
            $rows[] = [
                $i % 2 === 0 ? BorrowingItemStatus::Ditolak : BorrowingItemStatus::Disetujui,
                'CBB-PUB-PAGE-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'Lokasi panjang baris '.$i,
            ];
        }
        $many = $this->makeBorrowing($admin, BorrowingStatus::Disetujui, $rows);
        $manyHtml = $this->get(route('borrowing.approval.public', $many->approval_number))->assertOk()->getContent();
        $this->assertSame(25, substr_count($manyHtml, 'text-bg-success">Disetujui'));
        $this->assertSame(25, substr_count($manyHtml, 'text-bg-danger">Ditolak'));
        $this->assertStringContainsString('CBB-PUB-PAGE-0001', $manyHtml);
        $this->assertStringContainsString('CBB-PUB-PAGE-0050', $manyHtml);
    }

    public function test_soft_deleted_inventory_number_still_appears(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-public-trash@undira.ac.id']);
        $borrowing = $this->makeBorrowing($admin, BorrowingStatus::Dikembalikan, [
            [BorrowingItemStatus::Dikembalikan, 'CBB-PUB-GONE-'.uniqid(), 'Kampus'],
        ]);
        $item = $borrowing->items()->first()->inventoryItem;
        $number = $item->inventory_number;
        $item->delete();

        $this->get(route('borrowing.approval.public', $borrowing->approval_number))
            ->assertOk()
            ->assertSee($number)
            ->assertSee('text-bg-success">Disetujui', false);
    }

    /**
     * @param  list<array{0: BorrowingItemStatus, 1: string, 2: string}>  $rows
     */
    protected function makeBorrowing($admin, BorrowingStatus $status, array $rows, ?string $notes = null): Borrowing
    {
        $first = $this->makeItem($rows[0][1], $rows[0][2]);
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $first->id,
            'borrower_name' => 'Peminjam Publik',
            'borrower_email' => 'publik@undira.ac.id',
            'borrow_date' => '2026-10-09',
            'expected_return_date' => '2026-10-11',
            'notes' => 'ada',
            'approval_notes' => $notes,
            'status' => $status,
            'approval_number' => 'APR-'.now()->format('Ymd').'-'.random_int(100000, 999999),
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        foreach ($rows as $index => [$itemStatus, $number, $location]) {
            $item = $index === 0 ? $first : $this->makeItem($number, $location, $first);
            BorrowingItem::query()->create([
                'borrowing_id' => $borrowing->id,
                'inventory_item_id' => $item->id,
                'qty' => 1,
                'status' => $itemStatus,
            ]);
        }

        return $borrowing->fresh('items');
    }

    protected function makeItem(string $number, string $location, ?InventoryItem $template = null): InventoryItem
    {
        if ($template) {
            return InventoryItem::query()->create([
                'inventory_number' => $number,
                'barcode' => 'BC-'.uniqid(),
                'name' => 'Pulpen Kenko',
                'category_id' => $template->category_id,
                'sub_category_id' => $template->sub_category_id,
                'room_id' => $template->room_id,
                'condition' => InventoryCondition::Bagus,
                'status' => InventoryStatus::Aktif,
                'quantity' => 1,
                'unit' => 'pcs',
                'responsible_person' => 'PIC',
                'description' => $location,
            ]);
        }

        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'PC'.$suffix, 'name' => 'Undira Tanjung Duren', 'slug' => 'pub-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'PB'.$suffix, 'name' => 'Gedung A', 'slug' => 'gedung-pub-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'PF'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'PR'.$suffix, 'name' => $location, 'room_type' => 'Class', 'capacity' => 30, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'PK'.$suffix, 'name' => 'Alat Tulis', 'slug' => 'atk-pub-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PS'.$suffix, 'name' => 'Pulpen', 'slug' => 'pulpen-pub-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => $number,
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Pulpen Kenko',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'pcs',
            'responsible_person' => 'PIC',
        ]);
    }
}
