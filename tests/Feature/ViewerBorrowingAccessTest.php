<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Mail\BorrowingPendingAdminMail;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use App\Services\BorrowingSupportingDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ViewerBorrowingAccessTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_viewer_sidebar_links_create_before_own_history_and_hides_approval(): void
    {
        $viewer = $this->makeViewer(['email' => 'viewer-menu@undira.ac.id']);

        $html = $this->actingAs($viewer)
            ->get(route('dashboard'))
            ->assertOk()
            ->getContent();

        $pinjam = strpos($html, 'Pinjam Inventaris');
        $riwayat = strpos($html, 'Riwayat Peminjaman');

        $this->assertNotFalse($pinjam);
        $this->assertNotFalse($riwayat);
        $this->assertLessThan($riwayat, $pinjam);
        $this->assertStringContainsString(route('borrowings.create'), $html);
        $this->assertStringContainsString(route('borrowing-records.index'), $html);
        $this->assertStringContainsString('Pinjam Ruangan', $html);
        $this->assertStringContainsString('Pinjam Kendaraan', $html);
        $this->assertStringNotContainsString(route('borrowing-approvals.index'), $html);
        $this->assertStringNotContainsString(route('room-borrowings.approvals'), $html);
        $this->assertStringNotContainsString(route('vehicle-borrowings.approvals'), $html);
        $this->assertStringNotContainsString('Riwayat Approval', $html);
    }

    public function test_viewer_can_open_create_and_search_inventory_but_cannot_administer(): void
    {
        $viewer = $this->makeViewer(['email' => 'viewer-create@undira.ac.id']);

        $this->actingAs($viewer)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->assertSee('name="borrower_name"', false)
            ->assertSee('name="notes"', false);

        $this->actingAs($viewer)->get(route('room-borrowings.create'))->assertOk()->assertSee('Pinjam Ruangan');
        $this->actingAs($viewer)->get(route('vehicle-borrowings.create'))->assertOk()->assertSee('Pinjam Kendaraan');

        $this->actingAs($viewer)
            ->get(route('inventory.selector.search', ['q' => 'a']))
            ->assertOk();

        $this->actingAs($viewer)->get(route('borrowings.edit', $this->ownedBorrowing($viewer)))->assertForbidden();
        $this->actingAs($viewer)->post(route('borrowings.approve', $this->ownedBorrowing($viewer)))->assertForbidden();
        $this->actingAs($viewer)->post(route('borrowings.reject', $this->ownedBorrowing($viewer)))->assertForbidden();
        $this->actingAs($viewer)->post(route('borrowings.save-approvals', $this->ownedBorrowing($viewer)), [])->assertForbidden();
        $this->actingAs($viewer)->post(route('borrowings.return-items', $this->ownedBorrowing($viewer)))->assertForbidden();
        $this->actingAs($viewer)->get(route('borrowing-approvals.index'))->assertForbidden();
    }

    public function test_viewer_store_records_session_owner_and_ignores_forged_status(): void
    {
        Mail::fake();

        $viewer = $this->makeViewer(['email' => 'viewer-store@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-viewer-store@undira.ac.id']);
        $first = $this->makeItem('CBB-VIEW-A-'.uniqid());
        $second = $this->makeItem('CBB-VIEW-B-'.uniqid(), $first);

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$first->id, $second->id],
                'borrower_name' => 'Nama Palsu',
                'borrower_email' => 'bukan-owner@example.com',
                'borrower_phone' => '081234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => '2026-10-10T09:30',
                'expected_return_date' => '2026-10-12T16:00',
                'notes' => 'Praktikum viewer',
                'status' => BorrowingStatus::Disetujui->value,
                'requested_by' => $admin->id,
                'approved_by' => $admin->id,
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');

        $borrowing = Borrowing::query()->where('requested_by', $viewer->id)->first();
        $this->assertNotNull($borrowing);
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertSame($viewer->id, $borrowing->requested_by);
        $this->assertNull($borrowing->approved_by);
        $this->assertNull($borrowing->approved_at);
        $this->assertNull($borrowing->approval_number);
        $this->assertCount(2, $borrowing->items);
        $this->assertTrue($borrowing->items->every(
            fn ($line) => $line->status === BorrowingItemStatus::MenungguPersetujuan
        ));
        $this->assertSame(InventoryStatus::Aktif, $first->fresh()->status);

        Mail::assertSent(BorrowingPendingAdminMail::class, 1);

        $history = $this->actingAs($viewer)->get(route('borrowings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('CBB-VIEW-A-', $history);
        $this->assertStringContainsString('Menunggu', $history);

        $this->actingAs($viewer)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('CBB-VIEW-B-', false)
            ->assertDontSee(route('borrowings.save-approvals', $borrowing), false);
    }

    public function test_viewer_cannot_read_another_viewers_borrowing_or_document(): void
    {
        Mail::fake();
        Storage::fake(BorrowingSupportingDocumentService::DISK);

        $owner = $this->makeViewer(['email' => 'viewer-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'viewer-other@undira.ac.id']);
        $item = $this->makeItem('CBB-OWNER-'.uniqid());

        $this->actingAs($owner)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => UploadedFile::fake()->image('surat.jpg'),
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->where('requested_by', $owner->id)->firstOrFail();
        $borrowing->update(['approval_number' => 'APR-20261009-111111']);
        $this->assertNotNull($borrowing->supporting_document_path);

        $this->actingAs($other)->get(route('borrowings.show', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('borrowings.receipt', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('borrowings.receipt.pdf', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('borrowings.supporting-document.download', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('borrowings.supporting-document.view', $borrowing))->assertForbidden();

        $hidden = $this->actingAs($other)->get(route('borrowings.index'))->assertOk()->getContent();
        $this->assertStringNotContainsString($item->inventory_number, $hidden);
        $this->assertStringNotContainsString('viewer-owner@undira.ac.id', $hidden);

        $this->actingAs($owner)
            ->get(route('borrowings.supporting-document.download', $borrowing))
            ->assertOk();

        $staff = $this->makeStaff(['email' => 'staff-sees-viewer@undira.ac.id']);
        $this->actingAs($staff)->get(route('borrowings.show', $borrowing))->assertOk();
    }

    public function test_viewer_store_rejects_invalid_schedule_and_unavailable_inventory(): void
    {
        Mail::fake();

        $viewer = $this->makeViewer(['email' => 'viewer-invalid@undira.ac.id']);
        $item = $this->makeItem('CBB-BAD-'.uniqid());

        $this->actingAs($viewer)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'borrow_date' => '2026-10-12T16:00',
                'expected_return_date' => '2026-10-12T09:00',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('expected_return_date');

        $item->update(['status' => InventoryStatus::Maintenance]);

        $this->actingAs($viewer)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors();

        $this->assertSame(0, Borrowing::query()->where('requested_by', $viewer->id)->count());
    }

    public function test_admin_can_approve_a_viewer_request_and_guest_cannot_enter(): void
    {
        Mail::fake();

        $viewer = $this->makeViewer(['email' => 'viewer-flow@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-flow-viewer@undira.ac.id']);
        $item = $this->makeItem('CBB-FLOW-'.uniqid());

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), $this->payload($item))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->where('requested_by', $viewer->id)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => [[
                    'id' => $borrowing->items()->first()->id,
                    'action' => 'approve',
                    'rejection_reason' => '',
                ]],
            ])
            ->assertRedirect();

        $borrowing->refresh();
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertSame(BorrowingItemStatus::Disetujui, $borrowing->items()->first()->status);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);

        $line = $borrowing->items()->first();
        $this->actingAs($viewer)
            ->post(route('borrowings.items.handover', [$borrowing, $line]))
            ->assertForbidden();
        $this->assertSame(BorrowingItemStatus::Disetujui, $line->fresh()->status);

        auth()->logout();
        $this->get(route('borrowings.create'))->assertRedirect();
        $this->post(route('borrowings.store'), $this->payload($item))->assertRedirect();
        $this->get(route('borrowings.show', $borrowing))->assertRedirect();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Viewer Pemohon',
            'borrower_email' => 'viewer-pemohon@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'borrow_date' => '2026-10-10T09:00',
            'expected_return_date' => '2026-10-12T16:00',
            'notes' => 'Keperluan kuliah',
            'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    protected function ownedBorrowing($viewer): Borrowing
    {
        $item = $this->makeItem('CBB-OWN-'.uniqid());

        return Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Viewer',
            'borrower_email' => $viewer->email,
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'notes' => 'Milik viewer',
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $viewer->id,
        ]);
    }

    protected function makeItem(string $number, ?InventoryItem $template = null): InventoryItem
    {
        if ($template) {
            return InventoryItem::query()->create([
                'inventory_number' => $number,
                'barcode' => 'BC-'.uniqid(),
                'name' => 'Barang '.$number,
                'category_id' => $template->category_id,
                'sub_category_id' => $template->sub_category_id,
                'room_id' => $template->room_id,
                'condition' => InventoryCondition::Bagus,
                'status' => InventoryStatus::Aktif,
                'quantity' => 1,
                'unit' => 'pcs',
                'responsible_person' => 'PIC',
            ]);
        }

        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'VC'.$suffix, 'name' => 'Undira Cibubur', 'slug' => 'viewer-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'VB'.$suffix, 'name' => 'Gedung', 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'VF'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'VR'.$suffix, 'name' => 'Ruang', 'room_type' => 'Office', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'VK'.$suffix, 'name' => 'Alat', 'slug' => 'alat-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'VS'.$suffix, 'name' => 'Unit', 'slug' => 'unit-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => $number,
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Barang '.$number,
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
