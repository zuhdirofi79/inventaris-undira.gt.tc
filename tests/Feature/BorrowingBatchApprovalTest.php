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
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingBatchApprovalTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_partial_batch_save_updates_items_sends_one_email_and_keeps_inventory_available(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-batch@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-batch@undira.ac.id']);
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 5, 'batch@undira.ac.id', 'Rapat');

        $payload = $this->decisions($lines, [0, 1, 2], [3, 4], 'Dipakai kegiatan lain');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), ['decisions' => $payload])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHas('success');

        $borrowing->refresh()->load('items.inventoryItem');
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertNotNull($borrowing->approval_number);
        $this->assertCount(3, $borrowing->items->where('status', BorrowingItemStatus::Disetujui));
        $this->assertCount(2, $borrowing->items->where('status', BorrowingItemStatus::Ditolak));
        $this->assertTrue($borrowing->items->where('status', BorrowingItemStatus::Ditolak)->every(
            fn ($line) => $line->rejection_reason === 'Dipakai kegiatan lain'
        ));

        foreach ($borrowing->items as $line) {
            $this->assertSame(InventoryStatus::Aktif, $line->inventoryItem->fresh()->status);
            $this->assertNull($line->borrowed_at);
        }

        Mail::assertSent(BorrowingApprovedMail::class, 1);
        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) use ($lines) {
            $html = $mail->render();
            $approvedNumbers = $lines->take(3)->map(fn ($line) => $line->inventoryItem->inventory_number);
            $rejectedNumbers = $lines->skip(3)->map(fn ($line) => $line->inventoryItem->inventory_number);

            return $mail->hasTo('batch@undira.ac.id')
                && str_contains($mail->envelope()->subject, 'Sebagian')
                && str_contains($html, 'Sebagian inventaris pada pengajuan Anda telah disetujui')
                && str_contains($html, 'INVENTARIS DISETUJUI')
                && str_contains($html, 'INVENTARIS TIDAK DISETUJUI')
                && $approvedNumbers->every(fn ($number) => str_contains($html, $number))
                && $rejectedNumbers->every(fn ($number) => str_contains($html, $number));
        });
        Mail::assertNotSent(BorrowingRejectedMail::class);
        $this->assertTrue(AuditLog::query()->where('description', 'BORROWING_ITEMS_APPROVED_BATCH')->exists());
    }

    public function test_all_approved_batch_sends_approved_email(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-all-ok@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 3, 'allok@undira.ac.id');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0, 1, 2], []),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertTrue($borrowing->items->every(fn ($line) => $line->status === BorrowingItemStatus::Disetujui));

        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) use ($borrowing) {
            $html = $mail->render();

            return $mail->hasTo('allok@undira.ac.id')
                && $mail->envelope()->subject === 'Persetujuan Peminjaman Inventaris - '.$borrowing->approval_number
                && str_contains($html, 'Seluruh inventaris pada pengajuan Anda telah disetujui')
                && ! str_contains($html, 'Sebagian inventaris pada pengajuan Anda telah disetujui');
        });
        Mail::assertSent(BorrowingApprovedMail::class, 1);
    }

    public function test_all_rejected_batch_sends_rejected_email(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-all-no@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 3, 'allno@undira.ac.id');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [], [0, 1, 2], 'Stok tidak tersedia'),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Ditolak, $borrowing->status);
        $this->assertTrue($borrowing->items->every(fn ($line) => $line->status === BorrowingItemStatus::Ditolak));
        $this->assertSame('Stok tidak tersedia', $borrowing->rejection_reason);

        Mail::assertSent(BorrowingRejectedMail::class, 1);
        Mail::assertSent(BorrowingRejectedMail::class, function (BorrowingRejectedMail $mail) {
            $html = $mail->render();

            return $mail->hasTo('allno@undira.ac.id')
                && $mail->envelope()->subject === 'Pengajuan Peminjaman Inventaris Tidak Disetujui'
                && str_contains($html, 'Pengajuan peminjaman inventaris Anda tidak disetujui.')
                && str_contains($html, 'Stok tidak tersedia')
                && ! str_contains($html, 'disetujui untuk dipinjam');
        });
        Mail::assertNotSent(BorrowingApprovedMail::class);
    }

    public function test_incomplete_decisions_are_rejected_without_db_or_email_changes(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-incomplete@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 5, 'incomplete@undira.ac.id');

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0, 1], []),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('items');

        $this->assertSame(
            'Masih ada inventaris yang belum diproses. Silakan pilih Setujui atau Tolak untuk seluruh inventaris.',
            session('errors')->first('items')
        );

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertTrue($borrowing->items->every(
            fn ($line) => $line->status === BorrowingItemStatus::MenungguPersetujuan
        ));
        Mail::assertNothingSent();
    }

    public function test_duplicate_save_does_not_resend_email(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-dup@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 2, 'dup@undira.ac.id');
        $payload = ['decisions' => $this->decisions($lines, [0, 1], [])];

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $payload)
            ->assertRedirect(route('borrowings.show', $borrowing));

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), $payload)
            ->assertForbidden();

        Mail::assertSent(BorrowingApprovedMail::class, 1);
        $this->assertSame(1, AuditLog::query()->where('description', 'BORROWING_ITEMS_APPROVED_BATCH')->count());
        $this->assertTrue((bool) $borrowing->fresh()->notification_sent);
    }

    public function test_failed_availability_check_rolls_back_all_item_changes(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-roll@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 3, 'roll@undira.ac.id');

        $lines[1]->inventoryItem->update(['status' => InventoryStatus::Dipinjam]);

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0, 1, 2], []),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('inventory_item_id');

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->status);
        $this->assertTrue($borrowing->items->every(
            fn ($line) => $line->status === BorrowingItemStatus::MenungguPersetujuan
        ));
        Mail::assertNothingSent();
    }

    public function test_empty_borrower_email_still_saves_approval_without_sending_mail(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-noemail@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 2, '');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0, 1], []),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHas('success');

        $borrowing->refresh();
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertFalse((bool) $borrowing->notification_sent);
        Mail::assertNothingSent();
    }

    public function test_user_without_approve_permission_cannot_save(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-no-perm@undira.ac.id']);
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 1, 'noperm@undira.ac.id');

        $this->actingAs($requester)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0], []),
            ])
            ->assertForbidden();

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_show_page_after_save_hides_decision_buttons_and_keeps_handover_for_approved_items(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-refresh@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 2, 'refresh@undira.ac.id');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0], [1], 'Tidak tersedia'),
            ])
            ->assertRedirect();

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('class="borrowing-decision js-item-decision"', $html);
        $this->assertStringNotContainsString('id="openSaveApprovalModal"', $html);
        $this->assertStringNotContainsString('id="saveApprovalModal"', $html);
        $this->assertStringContainsString('Tandai Dipinjam', $html);
        $this->assertStringContainsString('Belum Dipinjam', $html);
        $this->assertStringContainsString('Ditolak', $html);
        $this->assertStringContainsString('Tidak tersedia', $html);
    }

    public function test_show_page_includes_dark_mode_decision_styles(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-ui-dark@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing] = $this->makePendingBorrowing($requester, 1, 'uidark@undira.ac.id');

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('body.dark-mode .borrowing-save-bar', $html);
        $this->assertStringContainsString('body.dark-mode .borrowing-decision__btns .btn-success', $html);
        $this->assertStringContainsString('saveApprovalModal', $html);
        $this->assertStringContainsString('openSaveApprovalModal', $html);
        $this->assertStringContainsString('disabled', $html);
    }

    public function test_pending_rows_render_approve_and_reject_in_the_action_column(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-rows@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing] = $this->makePendingBorrowing($requester, 5, 'rows@undira.ac.id');

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertSame(5, substr_count($html, 'data-action="approve"'));
        $this->assertSame(5, substr_count($html, 'data-action="reject"'));
        $this->assertStringContainsString('btn btn-success btn-sm js-decide-btn', $html);
        $this->assertStringContainsString('btn btn-danger btn-sm js-decide-btn', $html);
        $this->assertStringContainsString('id="openSaveApprovalModal"', $html);
        $this->assertStringContainsString('id="rejectItemModal"', $html);
    }

    public function test_one_approved_and_four_rejected_keeps_transaction_approved(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-one@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 5, 'one@undira.ac.id');

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [0], [1, 2, 3, 4], 'Tidak tersedia'),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh()->load('items.inventoryItem');
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertCount(1, $borrowing->items->where('status', BorrowingItemStatus::Disetujui));
        $this->assertCount(4, $borrowing->items->where('status', BorrowingItemStatus::Ditolak));
        $this->assertTrue($borrowing->items->every(
            fn ($line) => $line->inventoryItem->fresh()->status === InventoryStatus::Aktif
        ));

        $rejected = $borrowing->items->firstWhere('status', BorrowingItemStatus::Ditolak);

        $this->actingAs($admin)
            ->post(route('borrowings.items.handover', [$borrowing, $rejected]))
            ->assertRedirect()
            ->assertSessionHasErrors('item');

        $this->assertSame(BorrowingItemStatus::Ditolak, $rejected->fresh()->status);

        Mail::assertSent(BorrowingApprovedMail::class, function (BorrowingApprovedMail $mail) {
            $html = $mail->render();

            return str_contains($html, 'Sebagian inventaris pada pengajuan Anda telah disetujui')
                && str_contains($html, 'INVENTARIS DISETUJUI')
                && str_contains($html, 'INVENTARIS TIDAK DISETUJUI');
        });
    }

    public function test_foreign_item_decision_is_rejected_without_changes(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-foreign@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 1, 'foreign@undira.ac.id');
        [$other, $otherLines] = $this->makePendingBorrowing($requester, 1, 'foreign-other@undira.ac.id');

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => [[
                    'id' => $otherLines[0]->id,
                    'action' => 'approve',
                    'rejection_reason' => '',
                ]],
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('decisions');

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);
        $this->assertSame(BorrowingItemStatus::MenungguPersetujuan, $lines[0]->fresh()->status);
        $this->assertSame(BorrowingItemStatus::MenungguPersetujuan, $otherLines[0]->fresh()->status);
        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $other->fresh()->status);
        Mail::assertNothingSent();
    }

    public function test_reject_without_reason_does_not_save(): void
    {
        Mail::fake();

        $requester = $this->makeStaff(['email' => 'staff-reason-batch@undira.ac.id']);
        $admin = $this->makeAdmin();
        [$borrowing, $lines] = $this->makePendingBorrowing($requester, 1, 'reason@undira.ac.id');

        $this->actingAs($admin)
            ->from(route('borrowings.show', $borrowing))
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => $this->decisions($lines, [], [0], ''),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHasErrors('decisions.0.rejection_reason');

        $this->assertSame(BorrowingStatus::MenungguPersetujuan, $borrowing->fresh()->status);
        $this->assertSame(BorrowingItemStatus::MenungguPersetujuan, $lines[0]->fresh()->status);
        Mail::assertNothingSent();
    }

    /**
     * @return array{0: Borrowing, 1: \Illuminate\Support\Collection<int, BorrowingItem>}
     */
    protected function makePendingBorrowing($requester, int $count, string $email, string $notes = 'Keperluan tes'): array
    {
        $items = collect(range(1, $count))->map(fn ($i) => $this->makeItem([
            'inventory_number' => 'CBB-UNDIRA-26-ATK-PL-'.str_pad((string) $i, 6, '0', STR_PAD_LEFT).'-'.uniqid(),
            'name' => 'Pulpen Kenko '.$i,
        ]));

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $items->first()->id,
            'borrower_name' => 'Peminjam Batch',
            'borrower_email' => $email,
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(8)->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $requester->id,
            'notes' => $notes,
            'notification_sent' => false,
        ]);

        foreach ($items as $item) {
            BorrowingItem::query()->create([
                'borrowing_id' => $borrowing->id,
                'inventory_item_id' => $item->id,
                'qty' => 1,
                'status' => BorrowingItemStatus::MenungguPersetujuan,
            ]);
        }

        return [$borrowing->fresh(), $borrowing->items()->with('inventoryItem')->orderBy('id')->get()];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, BorrowingItem>  $lines
     * @param  list<int>  $approveIndexes
     * @param  list<int>  $rejectIndexes
     * @return list<array{id: int, action: string, rejection_reason: string}>
     */
    protected function decisions($lines, array $approveIndexes, array $rejectIndexes, string $reason = ''): array
    {
        $rows = [];
        foreach ($approveIndexes as $index) {
            $rows[] = [
                'id' => (int) $lines[$index]->id,
                'action' => 'approve',
                'rejection_reason' => '',
            ];
        }
        foreach ($rejectIndexes as $index) {
            $rows[] = [
                'id' => (int) $lines[$index]->id,
                'action' => 'reject',
                'rejection_reason' => $reason,
            ];
        }

        return $rows;
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CB'.$suffix, 'name' => 'Undira Cibubur', 'slug' => 'cibubur-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A'.$suffix, 'name' => 'Gedung A', 'slug' => 'gedung-a-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1'.$suffix, 'name' => 'Ruang Rapat', 'room_type' => 'Meeting Room', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'ATK'.$suffix, 'name' => 'Alat Tulis', 'slug' => 'atk-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PL'.$suffix, 'name' => 'Pulpen', 'slug' => 'pulpen-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-BATCH-'.uniqid(),
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
        ], $overrides));
    }
}
