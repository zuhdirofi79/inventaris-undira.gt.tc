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
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingPhoneRequiredTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_create_form_marks_phone_as_required(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Telepon / No. HP', $html);
        $this->assertMatchesRegularExpression('/name="borrower_phone"[^>]*required/', $html);
        $this->assertStringContainsString('Unit', $html);
        $this->assertMatchesRegularExpression('/name="borrower_affiliation"[^>]*required/', $html);
        $this->assertMatchesRegularExpression(
            '/name="borrower_phone"[\s\S]*?name="borrower_affiliation"[\s\S]*?<div class="col-12">\s*<div class="row">\s*<div class="col-12 col-md-6 mb-3">[\s\S]*?name="borrow_date"[\s\S]*?name="expected_return_date"/',
            $html
        );
        $this->assertMatchesRegularExpression('/type="datetime-local"[^>]*step="60"[^>]*id="borrow_date"/', $html);
        $this->assertMatchesRegularExpression('/type="datetime-local"[^>]*step="60"[^>]*id="expected_return_date"/', $html);
        $this->assertStringContainsString('Keperluan', $html);
        $this->assertMatchesRegularExpression('/name="notes"[^>]*required/', $html);
        $this->assertStringNotContainsString('Keperluan / Catatan', $html);
        $this->assertStringContainsString('text-danger', $html);
    }

    public function test_store_rejects_empty_borrower_phone(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => '',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors(['borrower_phone' => 'Nomor Telepon / No. HP wajib diisi.']);

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_store_rejects_whitespace_borrower_phone(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => '   ',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('borrower_phone');

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_store_rejects_empty_notes(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'notes' => '',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors(['notes' => 'Keperluan wajib diisi.']);

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_store_rejects_whitespace_notes(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'notes' => '   ',
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('notes');

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    #[DataProvider('validPhoneProvider')]
    public function test_store_accepts_indonesian_mobile_formats(string $phone): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => $phone,
            ]))
            ->assertRedirect(route('borrowings.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('borrowings', [
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Sari Wijaya',
            'borrower_phone' => $phone,
            'status' => BorrowingStatus::MenungguPersetujuan->value,
        ]);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_store_with_selected_items_does_not_create_when_phone_empty(): void
    {
        $admin = $this->makeAdmin();
        $itemA = $this->makeItem(['name' => 'Laptop A']);
        $itemB = $this->makeItem([
            'inventory_number' => 'INV-PHONE-B-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Proyektor B',
            'category_id' => $itemA->category_id,
            'sub_category_id' => $itemA->sub_category_id,
            'room_id' => $itemA->room_id,
        ]);

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), [
                'inventory_item_ids' => [$itemA->id, $itemB->id],
                'borrower_name' => 'Sari Wijaya',
                'borrower_email' => 'sari@undira.ac.id',
                'borrower_phone' => '',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => now()->format('Y-m-d\TH:i'),
                'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
                'notes' => 'Praktikum',
            ])
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors(['borrower_phone' => 'Nomor Telepon / No. HP wajib diisi.'])
            ->assertSessionHasInput([
                'borrower_name' => 'Sari Wijaya',
                'borrower_email' => 'sari@undira.ac.id',
                'notes' => 'Praktikum',
                'inventory_item_ids' => [(string) $itemA->id, (string) $itemB->id],
            ]);

        $this->assertDatabaseCount('borrowings', 0);
        $this->assertDatabaseCount('borrowing_items', 0);
        $this->assertSame(InventoryStatus::Aktif, $itemA->fresh()->status);
        $this->assertSame(InventoryStatus::Aktif, $itemB->fresh()->status);
    }

    public function test_edit_form_shows_existing_phone_and_aligned_schedule_fields(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => '081234567890',
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->first();
        $this->assertNotNull($borrowing);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.edit', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('081234567890', $html);
        $this->assertMatchesRegularExpression(
            '/<div class="row">\s*<div class="col-12 col-md-6 mb-3">[\s\S]*?name="borrow_date"[\s\S]*?name="expected_return_date"/',
            $html
        );
    }

    public function test_update_succeeds_with_valid_phone(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => '081234567890',
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->first();
        $this->assertNotNull($borrowing);

        $this->actingAs($admin)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '+6281234567890',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => $borrowing->borrow_date?->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date?->format('Y-m-d\TH:i'),
                'status' => $borrowing->status?->value,
                'notes' => $borrowing->notes,
            ])
            ->assertRedirect(route('borrowings.show', $borrowing))
            ->assertSessionHas('success', 'Peminjaman berhasil diperbarui.');

        $this->assertSame('+6281234567890', $borrowing->fresh()->borrower_phone);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_update_rejects_empty_phone_and_keeps_existing_record(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->validPayload($item))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->first();
        $this->assertNotNull($borrowing);

        $this->actingAs($admin)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '',
                'borrower_affiliation' => 'PPPA',
                'borrow_date' => $borrowing->borrow_date?->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date?->format('Y-m-d\TH:i'),
                'status' => $borrowing->status?->value,
                'notes' => $borrowing->notes,
            ])
            ->assertRedirect(route('borrowings.edit', $borrowing))
            ->assertSessionHasErrors(['borrower_phone' => 'Nomor Telepon / No. HP wajib diisi.']);

        $this->assertSame('081234567890', $borrowing->fresh()->borrower_phone);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_update_rejects_empty_notes_and_keeps_existing_record(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->validPayload($item))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->first();
        $this->assertNotNull($borrowing);

        $this->actingAs($admin)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => $borrowing->borrower_phone,
                'borrow_date' => $borrowing->borrow_date?->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date?->format('Y-m-d\TH:i'),
                'status' => $borrowing->status?->value,
                'notes' => '',
            ])
            ->assertRedirect(route('borrowings.edit', $borrowing))
            ->assertSessionHasErrors(['notes' => 'Keperluan wajib diisi.']);

        $this->assertSame('Praktikum', $borrowing->fresh()->notes);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'local_08' => ['081234567890'],
            'country_62' => ['6281234567890'],
            'plus_62' => ['+6281234567890'],
            'dashed' => ['0812-3456-7890'],
            'spaced' => ['0812 3456 7890'],
            'plus_spaced_dashed' => ['+62 812-3456-7890'],
            'minimum_after_08' => ['081234'],
            'minimum_after_62' => ['621234'],
            'minimum_after_plus_62' => ['+621234'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function test_store_rejects_phone_without_prefix_or_enough_digits(string $phone): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->validPayload($item, [
                'borrower_phone' => $phone,
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('borrower_phone');

        $this->assertDatabaseCount('borrowings', 0);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'too_short_08' => ['0812'],
            'too_short_plus_62' => ['+6212'],
            'too_short_62' => ['6212'],
            'wrong_prefix' => ['071234567890'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function validPayload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Sari Wijaya',
            'borrower_email' => 'sari@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrower_affiliation' => 'PPPA',
            'borrow_date' => now()->format('Y-m-d\TH:i'),
            'expected_return_date' => now()->addDays(2)->format('Y-m-d\TH:i'),
            'notes' => 'Praktikum',
            'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $suffix = substr(str_replace('.', '', uniqid('', true)), -8);
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kampus Phone', 'slug' => 'kampus-phone-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BH', 'name' => 'Gedung H', 'slug' => 'gedung-h-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'E'.$suffix, 'name' => 'Equipment', 'slug' => 'equipment-phone-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC', 'name' => 'PC', 'slug' => 'pc-phone-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-PHONE-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Laptop Phone',
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
