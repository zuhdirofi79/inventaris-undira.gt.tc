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
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingCampusFilterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_index_without_filter_shows_all_visible_borrowings(): void
    {
        $admin = $this->makeAdmin();
        [$campusA, $itemA] = $this->makeCampusWithItem('Alpha');
        [$campusB, $itemB] = $this->makeCampusWithItem('Beta');

        $this->createBorrowing($itemA, 'Peminjam Alpha', BorrowingStatus::MenungguPersetujuan);
        $this->createBorrowing($itemB, 'Peminjam Beta', BorrowingStatus::Disetujui);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Peminjam Alpha', $html);
        $this->assertStringContainsString('Peminjam Beta', $html);
        $this->assertStringContainsString('Lokasi Kampus', $html);
        $this->assertStringContainsString('Semua Kampus', $html);
        $this->assertStringContainsString($campusA->name, $html);
        $this->assertStringContainsString($campusB->name, $html);
        $this->assertStringContainsString('name="campus_id"', $html);
        // Dropdown options must be models (id attributes), not pluck strings.
        $this->assertMatchesRegularExpression('/<option value="'.$campusA->id.'"/', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$campusB->id.'"/', $html);
    }

    public function test_filter_by_campus_returns_only_matching_transactions(): void
    {
        $admin = $this->makeAdmin();
        [$campusA, $itemA] = $this->makeCampusWithItem('Alpha');
        [, $itemB] = $this->makeCampusWithItem('Beta');

        $this->createBorrowing($itemA, 'Peminjam Alpha', BorrowingStatus::MenungguPersetujuan);
        $this->createBorrowing($itemB, 'Peminjam Beta', BorrowingStatus::MenungguPersetujuan);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusA->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Peminjam Alpha', $html);
        $this->assertStringNotContainsString('Peminjam Beta', $html);
        $this->assertStringContainsString('Kampus Alpha', $html);
    }

    public function test_campus_and_status_filters_combine(): void
    {
        $admin = $this->makeAdmin();
        [$campusA, $itemA1] = $this->makeCampusWithItem('Gamma');
        $itemA2 = $this->makeItemInRoom($itemA1->room_id, $itemA1->category_id, $itemA1->sub_category_id, 'Gamma-2');
        [, $itemB] = $this->makeCampusWithItem('Delta');

        $this->createBorrowing($itemA1, 'Gamma Pending', BorrowingStatus::MenungguPersetujuan);
        $this->createBorrowing($itemA2, 'Gamma Approved', BorrowingStatus::Disetujui);
        $this->createBorrowing($itemB, 'Delta Pending', BorrowingStatus::MenungguPersetujuan);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', [
                'campus_id' => $campusA->id,
                'status' => BorrowingStatus::MenungguPersetujuan->value,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Gamma Pending', $html);
        $this->assertStringNotContainsString('Gamma Approved', $html);
        $this->assertStringNotContainsString('Delta Pending', $html);
    }

    public function test_reset_link_clears_filters(): void
    {
        $admin = $this->makeAdmin();
        [$campusA] = $this->makeCampusWithItem('Epsilon');

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', [
                'campus_id' => $campusA->id,
                'status' => BorrowingStatus::MenungguPersetujuan->value,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString(route('borrowings.index'), $html);
        $this->assertStringContainsString('>Reset</a>', $html);
    }

    public function test_empty_campus_filter_shows_clear_message(): void
    {
        $admin = $this->makeAdmin();
        [$campusEmpty] = $this->makeCampusWithItem('Zeta');
        // No borrowings for Zeta; create one on another campus.
        [, $itemOther] = $this->makeCampusWithItem('Other');
        $this->createBorrowing($itemOther, 'Other User', BorrowingStatus::MenungguPersetujuan);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusEmpty->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Tidak ada peminjaman pada kampus yang dipilih.', $html);
        $this->assertStringNotContainsString('Other User', $html);
    }

    public function test_pagination_preserves_campus_filter_query(): void
    {
        $admin = $this->makeAdmin();
        [$campusA, $seedItem] = $this->makeCampusWithItem('Theta');

        for ($i = 1; $i <= 21; $i++) {
            $item = $this->makeItemInRoom(
                $seedItem->room_id,
                $seedItem->category_id,
                $seedItem->sub_category_id,
                'Theta-'.$i
            );
            $this->createBorrowing($item, 'Pager '.$i, BorrowingStatus::MenungguPersetujuan);
        }

        $response = $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusA->id, 'page' => 2]))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('campus_id='.$campusA->id, $html);
    }

    public function test_show_page_displays_campus_location(): void
    {
        $admin = $this->makeAdmin();
        [$campus, $item] = $this->makeCampusWithItem('Iota');
        $borrowing = $this->createBorrowing($item, 'Detail User', BorrowingStatus::MenungguPersetujuan);

        $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->assertSee('Lokasi Kampus')
            ->assertSee($campus->name);
    }

    public function test_multi_campus_transaction_visible_when_filtering_either_campus(): void
    {
        $admin = $this->makeAdmin();
        [$campusA, $itemA] = $this->makeCampusWithItem('KappaA');
        [$campusB, $itemB] = $this->makeCampusWithItem('KappaB');

        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $itemA->id,
            'borrower_name' => 'Multi Campus User',
            'borrower_email' => 'multi-campus@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Lintas kampus',
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $itemA->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $itemB->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusA->id]))
            ->assertOk()
            ->assertSee('Multi Campus User')
            ->assertSee('Multi Kampus');

        $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusB->id]))
            ->assertOk()
            ->assertSee('Multi Campus User');
    }

    public function test_invalid_campus_id_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        [, $item] = $this->makeCampusWithItem('Valid');
        $this->createBorrowing($item, 'Valid User', BorrowingStatus::MenungguPersetujuan);

        $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => 999999]))
            ->assertOk()
            ->assertSee('Tidak ada peminjaman pada kampus yang dipilih.')
            ->assertDontSee('Valid User');

        $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => 'Tanjung Duren']))
            ->assertOk()
            ->assertSee('Tidak ada peminjaman pada kampus yang dipilih.');
    }

    public function test_tanjung_duren_filter_does_not_treat_campus_as_string(): void
    {
        $admin = $this->makeAdmin();
        [$campusTd, $itemTd] = $this->makeCampusWithItem('Tanjung Duren');
        [, $itemOther] = $this->makeCampusWithItem('Cibubur');

        $this->createBorrowing($itemTd, 'Peminjam Tanjung Duren', BorrowingStatus::MenungguPersetujuan);
        $this->createBorrowing($itemOther, 'Peminjam Cibubur', BorrowingStatus::MenungguPersetujuan);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campusTd->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Peminjam Tanjung Duren', $html);
        $this->assertStringNotContainsString('Peminjam Cibubur', $html);
        $this->assertStringContainsString('Kampus Tanjung Duren', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$campusTd->id.'"[^>]*selected/', $html);
    }

    public function test_index_ignores_poisoned_shared_campus_dropdown_cache(): void
    {
        $admin = $this->makeAdmin();
        [$campus, $item] = $this->makeCampusWithItem('Tanjung Duren');
        $this->createBorrowing($item, 'Poison Cache User', BorrowingStatus::MenungguPersetujuan);

        \Illuminate\Support\Facades\Cache::put(
            \App\Support\InventarisCache::key('campuses.active.dropdown'),
            collect(['Undira Tanjung Duren', 'Undira Cibubur', 'Undira Greenville']),
            120
        );

        $html = $this->actingAs($admin)
            ->get(route('borrowings.index', ['campus_id' => $campus->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Poison Cache User', $html);
        $this->assertStringContainsString('Kampus Tanjung Duren', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$campus->id.'"/', $html);
        $this->assertStringNotContainsString('Attempt to read property', $html);

        \Illuminate\Support\Facades\Cache::flush();
    }

    /**
     * @return array{0: Campus, 1: InventoryItem}
     */
    protected function makeCampusWithItem(string $label): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix,
            'name' => 'Kampus '.$label,
            'slug' => 'kampus-'.strtolower($label).'-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$suffix,
            'name' => 'Gedung '.$label,
            'slug' => 'gedung-'.strtolower($label).'-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1'.$suffix,
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R1'.$suffix,
            'name' => 'Ruang '.$label,
            'room_type' => 'Lab',
            'capacity' => 20,
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQ'.$suffix,
            'name' => 'Cat '.$label,
            'slug' => 'cat-'.strtolower($label).'-'.$suffix,
            'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id,
            'code' => 'SC'.$suffix,
            'name' => 'Sub '.$label,
            'slug' => 'sub-'.strtolower($label).'-'.$suffix,
            'is_active' => true,
        ]);

        $item = $this->makeItemInRoom($room->id, $category->id, $sub->id, $label);

        return [$campus, $item];
    }

    protected function makeItemInRoom(int $roomId, int $categoryId, int $subCategoryId, string $label): InventoryItem
    {
        return InventoryItem::query()->create([
            'inventory_number' => 'INV-'.$label.'-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Barang '.$label,
            'category_id' => $categoryId,
            'sub_category_id' => $subCategoryId,
            'room_id' => $roomId,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }

    protected function createBorrowing(
        InventoryItem $item,
        string $borrower,
        BorrowingStatus $status,
    ): Borrowing {
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => $borrower,
            'borrower_email' => strtolower(str_replace(' ', '', $borrower)).'@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => $status,
            'notes' => 'Filter kampus',
            'approval_number' => $status === BorrowingStatus::Disetujui
                ? 'APR-'.now()->format('Ymd').'-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT)
                : null,
            'approved_at' => $status === BorrowingStatus::Disetujui ? now() : null,
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => match ($status) {
                BorrowingStatus::Disetujui => BorrowingItemStatus::Disetujui,
                BorrowingStatus::Dipinjam, BorrowingStatus::Terlambat => BorrowingItemStatus::Dipinjam,
                BorrowingStatus::Dikembalikan => BorrowingItemStatus::Dikembalikan,
                BorrowingStatus::Ditolak => BorrowingItemStatus::Ditolak,
                default => BorrowingItemStatus::MenungguPersetujuan,
            },
        ]);

        return $borrowing;
    }
}
