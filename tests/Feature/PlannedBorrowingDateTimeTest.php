<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class PlannedBorrowingDateTimeTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_create_form_uses_datetime_local_and_round_trips_through_sqlite(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['email' => 'admin-dt@undira.ac.id']);
        $viewer = $this->makeViewer(['email' => 'viewer-dt@undira.ac.id']);
        $item = $this->makeItem('CBB-DT-'.uniqid());

        $create = $this->actingAs($viewer)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->getContent();
        $this->assertSame(2, substr_count($create, 'type="datetime-local"'));
        $this->assertStringContainsString('step="60"', $create);
        $this->assertStringContainsString('Tanggal &amp; Jam Pinjam', $create);
        $this->assertStringContainsString('Tanggal &amp; Jam Kembali', $create);

        $this->actingAs($viewer)
            ->post(route('borrowings.store'), $this->payload($item, [
                'borrow_date' => '2026-10-12T09:30',
                'expected_return_date' => '2026-10-13T16:00',
            ]))
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('success', 'Peminjaman berhasil diajukan dan menunggu persetujuan.');

        $borrowing = Borrowing::query()->where('requested_by', $viewer->id)->firstOrFail();
        $raw = DB::table('borrowings')->where('id', $borrowing->id)->first();
        $this->assertSame('2026-10-12 09:30:00', $raw->borrow_date);
        $this->assertSame('2026-10-13 16:00:00', $raw->expected_return_date);
        $this->assertNull($raw->return_date);

        $borrowing->refresh();
        $this->assertSame('2026-10-12T09:30', $borrowing->plannedInputValue('borrow_date'));
        $this->assertSame('09:30', $borrowing->plannedScheduleTime('borrow_date'));
        $this->assertSame('16:00', $borrowing->plannedScheduleTime('expected_return_date'));
        $this->assertStringContainsString('09:30 WIB', $borrowing->plannedScheduleText('borrow_date'));
        $this->assertNull($borrowing->items()->first()->borrowed_at);

        $show = $this->actingAs($viewer)->get(route('borrowings.show', $borrowing))->assertOk()->getContent();
        $this->assertStringContainsString('09:30 WIB', $show);
        $this->assertStringContainsString('16:00 WIB', $show);
        $this->assertStringNotContainsString('02:30', $show);
        $this->assertStringNotContainsString('16:30 WIB', $show);

        $list = $this->actingAs($viewer)->get(route('borrowings.index'))->assertOk()->getContent();
        $this->assertStringContainsString('12/10/2026', $list);
        $this->assertStringContainsString('09:30', $list);

        $receipt = $this->actingAs($admin)->get(route('borrowings.show', $borrowing))->assertOk()->getContent();
        $this->assertStringContainsString('09:30 WIB', $receipt);

        $edit = $this->actingAs($admin)->get(route('borrowings.edit', $borrowing))->assertOk()->getContent();
        $this->assertStringContainsString('2026-10-12T09:30', $edit);
        $this->assertStringContainsString('2026-10-13T16:00', $edit);

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), $this->updatePayload($borrowing))
            ->assertRedirect();

        $this->assertSame(
            '2026-10-12 09:30:00',
            DB::table('borrowings')->where('id', $borrowing->id)->value('borrow_date')
        );

        $changed = $this->updatePayload($borrowing, [
            'borrow_date' => '2026-10-12T10:15',
        ]);
        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), $changed)
            ->assertRedirect();
        $this->assertSame(
            '2026-10-12 10:15:00',
            DB::table('borrowings')->where('id', $borrowing->id)->value('borrow_date')
        );
        $this->assertNull($borrowing->fresh()->return_date);
        $this->assertNull($borrowing->items()->first()->borrowed_at);
    }

    public function test_same_day_is_valid_and_equal_or_reversed_times_are_rejected(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['email' => 'admin-dt-rules@undira.ac.id']);
        $item = $this->makeItem('CBB-DT-RULE-'.uniqid());

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'borrow_date' => '2026-10-12T09:00',
                'expected_return_date' => '2026-10-12T16:00',
            ]))
            ->assertRedirect(route('borrowings.index'));

        $second = $this->makeItem('CBB-DT-REV-'.uniqid(), $item);
        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($second, [
                'borrow_date' => '2026-10-12T16:00',
                'expected_return_date' => '2026-10-12T09:00',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors([
                'expected_return_date' => 'Tanggal dan jam pengembalian harus setelah tanggal dan jam peminjaman.',
            ]);

        $third = $this->makeItem('CBB-DT-EQ-'.uniqid(), $item);
        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($third, [
                'borrow_date' => '2026-10-12T09:00',
                'expected_return_date' => '2026-10-12T09:00',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('expected_return_date');

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($third, [
                'borrow_date' => '',
                'expected_return_date' => 'bukan-tanggal',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors(['borrow_date', 'expected_return_date']);

        $this->assertSame(1, Borrowing::query()->count());
    }

    public function test_failed_update_keeps_submitted_datetime_and_legacy_dates_are_not_rewritten(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-dt-legacy@undira.ac.id']);
        $item = $this->makeItem('CBB-DT-OLD-'.uniqid());
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Lama',
            'borrower_email' => 'lama@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-12',
            'expected_return_date' => '2026-10-13',
            'notes' => 'Data lama',
            'status' => BorrowingStatus::MenungguPersetujuan,
            'requested_by' => $admin->id,
        ]);

        $this->assertSame('2026-10-12', DB::table('borrowings')->where('id', $borrowing->id)->value('borrow_date'));

        $show = $this->actingAs($admin)->get(route('borrowings.show', $borrowing))->assertOk()->getContent();
        $this->assertStringNotContainsString('00:00', $show);

        $edit = $this->actingAs($admin)->get(route('borrowings.edit', $borrowing))->assertOk()->getContent();
        $this->assertStringNotContainsString('2026-10-12T00:00', $edit);
        $this->assertStringContainsString('Jadwal lama tersimpan tanpa jam', $edit);

        $borrowing->notes = 'Catatan lain';
        $borrowing->save();
        $this->assertSame('2026-10-12', DB::table('borrowings')->where('id', $borrowing->id)->value('borrow_date'));
        $this->assertSame('2026-10-13', DB::table('borrowings')->where('id', $borrowing->id)->value('expected_return_date'));

        $this->actingAs($admin)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => 'Lama',
                'borrower_phone' => '081234567890',
                'borrow_date' => '2026-10-12T11:45',
                'expected_return_date' => '2026-10-13T11:45',
                'notes' => '',
            ])
            ->assertRedirect(route('borrowings.edit', $borrowing))
            ->assertSessionHasErrors('notes')
            ->assertSessionHasInput([
                'borrow_date' => '2026-10-12T11:45',
                'expected_return_date' => '2026-10-13T11:45',
            ]);

        $this->assertSame('2026-10-12', DB::table('borrowings')->where('id', $borrowing->id)->value('borrow_date'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Jam',
            'borrower_email' => 'jam@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-12T09:30',
            'expected_return_date' => '2026-10-13T16:00',
            'notes' => 'Praktikum',
            'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function updatePayload(Borrowing $borrowing, array $overrides = []): array
    {
        return array_merge([
            'borrower_name' => $borrowing->borrower_name,
            'borrower_email' => $borrowing->borrower_email,
            'borrower_phone' => '081234567890',
            'borrow_date' => '2026-10-12T09:30',
            'expected_return_date' => '2026-10-13T16:00',
            'notes' => 'Praktikum',
            'status' => $borrowing->status->value,
        ], $overrides);
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
            'code' => 'DC'.$suffix, 'name' => 'Undira', 'slug' => 'dt-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'DB'.$suffix, 'name' => 'Gedung', 'slug' => 'gedung-dt-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'DF'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'DR'.$suffix, 'name' => 'Ruang', 'room_type' => 'Office', 'capacity' => 8, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'DK'.$suffix, 'name' => 'Alat', 'slug' => 'alat-dt-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'DS'.$suffix, 'name' => 'Unit', 'slug' => 'unit-dt-'.$suffix, 'is_active' => true,
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
