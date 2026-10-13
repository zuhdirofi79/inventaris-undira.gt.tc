<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Mail\BorrowingApprovedMail;
use App\Mail\BorrowingRejectedMail;
use App\Models\AuditLog;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingApprovalTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_store_creates_pending_without_changing_inventory(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Ani',
                'borrower_email' => 'ani@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Kuliah',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');

        $borrowing = Borrowing::query()->first();
        $this->assertNotNull($borrowing);
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertSame($admin->id, $borrowing->requested_by);
        $this->assertNull($borrowing->approved_by);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('description', 'BORROWING_CREATED')->exists());
    }

    public function test_admin_can_approve_pending_borrowing(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-req@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-appr@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Budi',
            'borrower_email' => 'budi@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Praktikum jaringan',
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertSame($admin->id, $borrowing->approved_by);
        $this->assertNotNull($borrowing->approved_at);
        $this->assertNotNull($borrowing->approval_number);
        $this->assertMatchesRegularExpression('/^APR-\d{8}-\d{6}$/', $borrowing->approval_number);
        $this->assertSame('disetujui', $borrowing->items->first()->status->value);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertTrue(AuditLog::query()->where('description', 'BORROWING_ITEMS_APPROVED_BATCH')->exists());

        $line = $borrowing->items->first();
        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $line]), [
                'borrowed_at' => now()->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(InventoryStatus::Dipinjam, $item->fresh()->status);
        $this->assertSame('dipinjam', $line->fresh()->status->value);
        $this->assertNotNull($line->fresh()->borrowed_at);

        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) use ($item, $borrowing) {
            $borrowing->refresh();
            $html = $mail->render();

            return $mail->hasTo('budi@undira.ac.id')
                && $mail->envelope()->subject === 'Persetujuan Peminjaman Inventaris - '.$borrowing->approval_number
                && str_contains($html, $item->inventory_number)
                && str_contains($html, 'Laptop Approval')
                && str_contains($html, 'Budi')
                && str_contains($html, 'Praktikum jaringan');
        });

        $this->get(route('borrowing.approval.public', $borrowing->approval_number))
            ->assertOk()
            ->assertSee($borrowing->approval_number)
            ->assertSee('Budi');
    }

    public function test_admin_can_reject_with_reason(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-rej@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-rej@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Citra',
            'borrower_email' => 'citra@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'reject', 'Barang sedang dipakai internal'))
            ->assertRedirect()
            ->assertSessionHas('success', 'Approval disimpan. Seluruh pengajuan ditolak.');

        $borrowing->refresh();
        $this->assertSame(BorrowingStatus::Ditolak, $borrowing->status);
        $this->assertSame($admin->id, $borrowing->approved_by);
        $this->assertNotNull($borrowing->rejected_at);
        $this->assertSame('Barang sedang dipakai internal', $borrowing->rejection_reason);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);

        Mail::assertSent(BorrowingRejectedMail::class, fn ($mail) => $mail->hasTo('citra@undira.ac.id'));
    }

    public function test_staff_cannot_approve(): void
    {
        Mail::fake();

        $staff = $this->makeStaff();
        $item = $this->makeItem();
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Dedi',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $staff->id,
        ]);

        $this->actingAs($staff)
            ->post(route('borrowings.approve', $borrowing))
            ->assertForbidden();

        Mail::assertNothingSent();
    }

    public function test_admin_who_created_the_request_sees_item_actions_and_can_save(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['email' => 'admin-self@undira.ac.id']);
        $item = $this->makeItem();
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Admin Self',
            'borrower_email' => 'peminjam@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
        ]);
        $line = BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('js-decide-btn', $html);
        $this->assertStringContainsString('Setujui', $html);
        $this->assertStringContainsString('Tolak', $html);
        $this->assertStringContainsString('Simpan Approval', $html);
        $this->assertStringContainsString('id="rejectItemModal"', $html);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => [[
                    'id' => $line->id,
                    'action' => 'approve',
                    'rejection_reason' => '',
                ]],
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHas('success');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        $this->assertSame(BorrowingItemStatus::Disetujui, $line->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertNull($line->fresh()->borrowed_at);
    }

    public function test_cannot_approve_twice(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-twice@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-twice@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Eko',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::Disetujui,
            'requested_by' => $requester->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.approve', $borrowing))
            ->assertForbidden();

        Mail::assertNothingSent();
        Mail::assertNotSent(BorrowingApprovedMail::class);
    }

    public function test_reject_requires_reason(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-reason@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-reason@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Fajar',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.reject', $borrowing), [])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('rejection_reason');
    }

    public function test_index_shows_approve_buttons_for_admin_only(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-ui@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-ui@undira.ac.id']);
        $item = $this->makeItem();

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Gita',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);
        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Final User',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'APR-'.now()->format('Ymd').'-000111',
            'requested_by' => $requester->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $adminHtml = $this->actingAs($admin)
            ->get(route('borrowings.index'))
            ->assertOk()
            ->getContent();
        $this->assertSame(1, substr_count($adminHtml, '>Approval</a>'));
        $this->assertStringContainsString(route('borrowings.show', Borrowing::query()->first()), $adminHtml);
        $this->assertStringNotContainsString('>Approve</button>', $adminHtml);
        $this->assertStringNotContainsString('>Reject</button>', $adminHtml);
        $this->assertStringNotContainsString('rejectBorrowingModal', $adminHtml);
        $this->assertStringNotContainsString(route('borrowings.approve', Borrowing::query()->first()), $adminHtml);
        $this->assertStringContainsString('Menunggu Persetujuan', $adminHtml);
        $this->assertStringContainsString('borrowing-row-actions', $adminHtml);

        $staffHtml = $this->actingAs($requester)
            ->get(route('borrowings.index'))
            ->assertOk()
            ->getContent();
        $this->assertStringNotContainsString('>Approval</a>', $staffHtml);
        $this->assertStringNotContainsString('>Approve</button>', $staffHtml);
        $this->assertStringNotContainsString('>Reject</button>', $staffHtml);
        $this->assertStringNotContainsString('rejectBorrowingModal', $staffHtml);
    }

    public function test_transaction_level_endpoints_do_not_bypass_item_approval(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-bypass@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-bypass@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Bypass',
            'borrower_email' => 'bypass@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);
        $line = BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $this->actingAs($admin)
            ->from(route('borrowings.index'))
            ->post(route('borrowings.approve', $borrowing), ['approval_notes' => 'lewati'])
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('error');

        $this->actingAs($admin)
            ->from(route('borrowings.index'))
            ->post(route('borrowings.reject', $borrowing), [
                'rejection_reason' => 'lewati dari daftar',
            ])
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('error');

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);
        $this->assertSame(BorrowingItemStatus::MenungguPersetujuan, $line->fresh()->status);
        $this->assertNull($borrowing->fresh()->approval_number);
        Mail::assertNothingSent();
    }

    public function test_cannot_approve_when_inventory_already_borrowed(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-busy@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-busy@undira.ac.id']);
        $item = $this->makeItem(['status' => InventoryStatus::Dipinjam]);

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Hana',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('inventory_item_id');

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);
        Mail::assertNothingSent();
        Mail::assertNotSent(BorrowingApprovedMail::class);
    }

    public function test_approval_history_and_status_filter(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-hist@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-hist@undira.ac.id']);
        $item = $this->makeItem();

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Indra',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'APR-'.now()->format('Ymd').'-000099',
            'requested_by' => $requester->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('borrowing-approvals.index'))
            ->assertOk()
            ->assertSee('APR-')
            ->assertSee('Indra');

        $this->actingAs($requester)
            ->get(route('borrowing-approvals.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->get(route('borrowings.index', ['status' => BorrowingStatus::Disetujui->value]))
            ->assertOk()
            ->assertSee('Indra');
    }

    public function test_receipt_pdf_available_after_approve(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-pdf@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-pdf@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Joko',
            'borrower_email' => 'joko@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect();
        $borrowing->refresh();

        $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->assertSee($borrowing->approval_number)
            ->assertSee('Joko');

        $this->get(route('borrowing.approval.receipt.pdf', $borrowing->approval_number))
            ->assertOk();
    }

    public function test_store_supports_multi_item_transaction(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin();
        $itemA = $this->makeItem(['inventory_number' => 'INV-MULTI-A-'.uniqid(), 'name' => 'Laptop A']);
        $itemB = InventoryItem::query()->create([
            'inventory_number' => 'INV-MULTI-B-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Proyektor B',
            'category_id' => $itemA->category_id,
            'sub_category_id' => $itemA->sub_category_id,
            'room_id' => $itemA->room_id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$itemA->id, $itemB->id],
                'borrower_name' => 'Multi User',
                'borrower_email' => 'multi@undira.ac.id',
                'borrower_phone' => '081234567890',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Kegiatan akademik',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertNotNull($borrowing);
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertCount(2, $borrowing->items);
        $this->assertSame($itemA->id, $borrowing->inventory_item_id);
        $this->assertSame(InventoryStatus::Aktif, $itemA->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $itemB->fresh()->status);

        $approver = $this->makeAdmin(['email' => 'admin-multi@undira.ac.id']);
        $this->actingAs($approver)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect();

        $borrowing->refresh()->load('items');
        $this->assertSame(InventoryStatus::Aktif, $itemA->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $itemB->fresh()->status);
        $this->assertTrue($borrowing->items->every(fn ($i) => $i->status->value === 'disetujui'));

        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) use ($itemA, $itemB) {
            $html = $mail->render();

            return $mail->hasTo('multi@undira.ac.id')
                && str_contains($html, $itemA->inventory_number)
                && str_contains($html, 'Laptop A')
                && str_contains($html, $itemB->inventory_number)
                && str_contains($html, 'Proyektor B');
        });
        Mail::assertSent(BorrowingApprovedMail::class, 1);

        foreach ($borrowing->items as $line) {
            $this->actingAs($approver)
                ->post(route('borrowings.items.handover', [$borrowing, $line]))
                ->assertRedirect();
        }

        $this->assertSame(InventoryStatus::Dipinjam, $itemA->fresh()->status);
        $this->assertSame(InventoryStatus::Dipinjam, $itemB->fresh()->status);

        $borrowing->refresh()->load('items');
        $this->actingAs($approver)
            ->post(route('borrowings.return-items', $borrowing), [
                'item_ids' => [$borrowing->items()->first()->id],
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh()->load('items');
        $this->assertNotSame(BorrowingStatus::Dikembalikan, $borrowing->status);
        $this->assertTrue(
            $borrowing->items->contains(fn ($i) => $i->status->value === 'dikembalikan')
        );
        $this->assertTrue(
            $borrowing->items->contains(fn ($i) => $i->status->value === 'dipinjam')
        );

        $this->actingAs($approver)
            ->post(route('borrowings.return-items', $borrowing), [
                'return_all' => 1,
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->assertSame(BorrowingStatus::Dikembalikan, $borrowing->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $itemA->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $itemB->fresh()->status);
    }

    public function test_approve_without_borrower_email_still_succeeds(): void
    {
        Mail::fake();

        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message;
        });

        $requester = $this->makeStaff(['email' => 'staff-noemail@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-noemail@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Lina',
            'borrower_email' => null,
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        Mail::assertNothingSent();
        $this->assertTrue(collect($logged)->contains(
            fn ($message) => str_contains((string) $message, 'alamat peminjam kosong')
        ));
    }

    public function test_approve_with_invalid_borrower_email_still_succeeds(): void
    {
        Mail::fake();

        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message;
        });

        $requester = $this->makeStaff(['email' => 'staff-bademail@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-bademail@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Mira',
            'borrower_email' => 'bukan-email-valid',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        Mail::assertNothingSent();
        $this->assertTrue(collect($logged)->contains(
            fn ($message) => str_contains((string) $message, 'alamat peminjam tidak valid')
        ));
    }

    public function test_mail_failure_does_not_rollback_approval(): void
    {
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message;
        });

        Mail::shouldReceive('to')
            ->once()
            ->andThrow(new \RuntimeException('SMTP connection failed'));

        $requester = $this->makeStaff(['email' => 'staff-smtp@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-smtp@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Kiki',
            'borrower_email' => 'kiki@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertTrue(collect($logged)->contains(
            fn ($message) => str_contains((string) $message, 'Gagal mengirim email notifikasi peminjaman')
        ));
    }

    public function test_super_admin_approve_sends_borrower_mail(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-sa@undira.ac.id']);
        $superAdmin = $this->makeSuperAdmin(['email' => 'super-appr@undira.ac.id']);
        $item = $this->makeItem();

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Nia',
            'borrower_email' => 'nia@undira.ac.id',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
        ]);

        $this->actingAs($superAdmin)
            ->post(route('borrowings.save-approvals', $borrowing), $this->decisionPayload($borrowing, 'approve'))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->fresh()->status);
        Mail::assertSent(BorrowingApprovedMail::class, fn ($mail) => $mail->hasTo('nia@undira.ac.id'));
        Mail::assertSent(BorrowingApprovedMail::class, 1);
    }

    /**
     * @return array{decisions: list<array{id: int, action: string, rejection_reason: string}>}
     */
    protected function decisionPayload(Borrowing $borrowing, string $action, string $reason = ''): array
    {
        if (! $borrowing->items()->exists() && $borrowing->inventory_item_id) {
            BorrowingItem::query()->create([
                'borrowing_id' => $borrowing->id,
                'inventory_item_id' => $borrowing->inventory_item_id,
                'qty' => 1,
                'status' => BorrowingItemStatus::MenungguPersetujuan,
            ]);
        }

        return [
            'decisions' => $borrowing->items()->orderBy('id')->get()->map(fn (BorrowingItem $line) => [
                'id' => $line->id,
                'action' => $action,
                'rejection_reason' => $action === 'reject' ? $reason : '',
            ])->values()->all(),
        ];
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CAP', 'name' => 'Kampus Approval', 'slug' => 'kampus-approval-'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BA', 'name' => 'Gedung A', 'slug' => 'gedung-a-'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQ', 'name' => 'Equipment', 'slug' => 'equipment-'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC', 'name' => 'PC', 'slug' => 'pc-'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-APPR-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Laptop Approval',
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
