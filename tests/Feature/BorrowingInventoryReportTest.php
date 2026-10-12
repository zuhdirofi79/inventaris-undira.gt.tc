<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
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

class BorrowingInventoryReportTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_borrowed_report_counts_unique_inventory_and_actual_duration(): void
    {
        $this->travelTo('2026-10-12 11:15:00');
        $admin = $this->makeAdmin(['name' => 'Admin Laporan', 'email' => 'admin-borrow-report@undira.ac.id']);
        [$campus, $room, $category, $sub] = $this->seedPlace('Cibubur');
        [$otherCampus, $otherRoom] = $this->seedPlace('Tanjung', $category, $sub);

        $openItem = $this->item($room, $category, $sub, 'CBB-RPT-OPEN', InventoryStatus::Dipinjam, 'Pulpen Kenko Open');
        $returnedItem = $this->item($room, $category, $sub, 'CBB-RPT-BACK', InventoryStatus::Aktif, 'Pulpen Kenko Back');
        $otherItem = $this->item($otherRoom, $category, $sub, 'TJD-RPT-OTHER', InventoryStatus::Dipinjam, 'Pulpen Kampus Lain');

        $open = $this->loan($admin, $openItem, BorrowingStatus::Dipinjam, 'Peminjam Masih', '2026-10-12 09:10:00', null, '2026-10-12 09:17:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $open->id,
            'inventory_item_id' => $openItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dipinjam,
            'borrowed_at' => '2026-10-12 09:10:00',
        ]);

        $closed = $this->loan($admin, $returnedItem, BorrowingStatus::Dikembalikan, 'Peminjam Kembali', '2026-10-10 09:13:00', '2026-10-10', '2026-10-10 18:00:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $closed->id,
            'inventory_item_id' => $returnedItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dikembalikan,
            'borrowed_at' => '2026-10-10 09:13:00',
            'returned_at' => '2026-10-10 15:43:00',
        ]);

        $elsewhere = $this->loan($admin, $otherItem, BorrowingStatus::Dipinjam, 'Peminjam Lain', '2026-10-12 08:00:00', null, '2026-10-12 08:07:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $elsewhere->id,
            'inventory_item_id' => $otherItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dipinjam,
            'borrowed_at' => '2026-10-12 08:00:00',
        ]);

        $closed->load('items');
        $this->assertSame('6 jam 30 menit', $closed->loanDurationLabel());

        $dateOnly = $this->loan($admin, $otherItem, BorrowingStatus::Dikembalikan, 'Tanggal Saja', '2026-10-11 09:13:00', '2026-10-11', '2026-10-11 18:00:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $dateOnly->id,
            'inventory_item_id' => $otherItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dikembalikan,
            'borrowed_at' => '2026-10-11 09:13:00',
        ]);
        $dateOnly->load('items');
        $this->assertSame('Kurang dari 1 hari', $dateOnly->loanDurationLabel());

        $html = $this->actingAs($admin)
            ->post(route('reports.export', 'dipinjam'), [
                'format' => 'print',
                'campus_id' => $campus->id,
            ])
            ->assertOk()
            ->getContent();

        preg_match('/<table class="rpt-summary">.*?<tbody>\s*<tr>(.*?)<\/tr>/s', $html, $summary);
        preg_match_all('/<td>(.*?)<\/td>/', $summary[1], $cells);
        $this->assertSame(['2', '1', '1', '1', '1', '0', '1', '0'], array_map('trim', $cells[1]));

        $this->assertStringNotContainsString('Tidak ada data', $html);
        $this->assertStringContainsString($category->name, $html);
        $this->assertStringContainsString($sub->name, $html);
        $this->assertStringContainsString('Bagus', $html);
        $this->assertStringContainsString('<td>Kampus</td><td class="rpt-recap-qty">1</td>', $html);
        $this->assertStringContainsString('<td>Gedung</td><td class="rpt-recap-qty">1</td>', $html);
        $this->assertStringContainsString('<td>Lantai</td><td class="rpt-recap-qty">1</td>', $html);
        $this->assertStringContainsString('<td>Ruangan</td><td class="rpt-recap-qty">1</td>', $html);
        $this->assertStringContainsString('<td>Aktif</td>', $html);
        $this->assertStringContainsString('<td>Dipinjam</td>', $html);
        $this->assertStringContainsString('Peminjam Masih', $html);
        $this->assertStringContainsString('Peminjam Kembali', $html);
        $this->assertStringNotContainsString('Peminjam Lain', $html);
        $this->assertStringContainsString('Berjalan: 2 jam 5 menit', $html);
        $this->assertStringContainsString('6 jam 30 menit', $html);
        $this->assertStringNotContainsString('0.004', $html);
        $this->assertStringNotContainsString('-0.', $html);
        $this->assertStringContainsString('Admin Laporan', $html);
        $this->assertSame(2, substr_count($html, 'PIN-'));
        $this->assertStringContainsString('Total Peminjaman:</strong> 2', $html);

        $pdf = $this->actingAs($admin)
            ->post(route('reports.export', 'dipinjam'), [
                'format' => 'pdf-portrait',
                'campus_id' => $campus->id,
            ])
            ->assertOk();
        $binary = $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/', $binary);

        $json = $this->actingAs($admin)
            ->get(route('reports.data', ['type' => 'dipinjam', 'campus_id' => $campus->id]))
            ->assertOk()
            ->json('data');
        $durations = collect($json)->pluck('duration')->all();
        $this->assertContains('Berjalan: 2 jam 5 menit', $durations);
        $this->assertContains('6 jam 30 menit', $durations);
        $this->travelBack();
    }

    public function test_borrow_date_filter_does_not_require_the_purchase_date(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-borrow-dates@undira.ac.id']);
        [$campus, $room, $category, $sub] = $this->seedPlace('Tanggal');
        $item = $this->item($room, $category, $sub, 'CBB-RPT-DATE', InventoryStatus::Aktif, 'Barang Lama Beli');
        $item->forceFill(['purchase_date' => '2020-01-01'])->save();

        $inside = $this->loan($admin, $item, BorrowingStatus::Dipinjam, 'Dalam Rentang', '2026-10-12 09:00:00', null, '2026-10-13 09:00:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $inside->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dipinjam,
            'borrowed_at' => '2026-10-12 09:00:00',
        ]);

        $outsideItem = $this->item($room, $category, $sub, 'CBB-RPT-OLD', InventoryStatus::Aktif, 'Barang Lama Pinjam');
        $outside = $this->loan($admin, $outsideItem, BorrowingStatus::Dikembalikan, 'Luar Rentang', '2026-09-01 09:00:00', '2026-09-02', '2026-09-02 09:00:00');
        BorrowingItem::query()->create([
            'borrowing_id' => $outside->id,
            'inventory_item_id' => $outsideItem->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::Dikembalikan,
            'borrowed_at' => '2026-09-01 09:00:00',
            'returned_at' => '2026-09-02 09:00:00',
        ]);

        $html = $this->actingAs($admin)
            ->post(route('reports.export', 'dipinjam'), [
                'format' => 'print',
                'campus_id' => $campus->id,
                'tanggal_awal' => '2026-10-10',
                'tanggal_akhir' => '2026-10-12',
                'purchase_date_from' => '2026-10-10',
                'purchase_date_to' => '2026-10-12',
            ])
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dalam Rentang', $html);
        $this->assertStringNotContainsString('Luar Rentang', $html);
        $this->assertStringContainsString('Alat Tulis Tanggal', $html);
        $this->assertStringNotContainsString('Tidak ada data', $html);
    }

    /**
     * @return array{0: Campus, 1: Room, 2: Category, 3: SubCategory}
     */
    private function seedPlace(string $name, ?Category $category = null, ?SubCategory $sub = null): array
    {
        $suffix = strtolower($name).uniqid();
        $campus = Campus::query()->create([
            'code' => 'C'.$suffix, 'name' => 'Kampus '.$name, 'slug' => 'kampus-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B'.$suffix, 'name' => 'Gedung '.$name, 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'F'.$suffix, 'name' => 'Lantai '.$name, 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R'.$suffix, 'name' => 'Ruang '.$name, 'room_type' => 'Office', 'capacity' => 10, 'is_active' => true,
        ]);
        $category ??= Category::query()->create([
            'code' => 'ATK'.$suffix, 'name' => 'Alat Tulis '.$name, 'slug' => 'atk-'.$suffix, 'is_active' => true,
        ]);
        $sub ??= SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PL'.$suffix, 'name' => 'Pulpen '.$name, 'slug' => 'pulpen-'.$suffix, 'is_active' => true,
        ]);

        return [$campus, $room, $category, $sub];
    }

    private function item(Room $room, Category $category, SubCategory $sub, string $number, InventoryStatus $status, string $name): InventoryItem
    {
        return InventoryItem::query()->create([
            'inventory_number' => $number.'-'.uniqid(),
            'barcode' => 'BC-'.$number.'-'.uniqid(),
            'name' => $name,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => $status->value,
            'quantity' => 1,
            'unit' => 'pcs',
            'purchase_date' => '2020-01-01',
        ]);
    }

    private function loan($admin, InventoryItem $item, BorrowingStatus $status, string $borrower, string $borrowDate, ?string $returnDate, ?string $expected): Borrowing
    {
        return Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => $borrower,
            'borrower_email' => 'laporan@undira.ac.id',
            'borrow_date' => $borrowDate,
            'return_date' => $returnDate,
            'expected_return_date' => $expected,
            'status' => $status,
            'approval_number' => 'APR-RPT-'.strtoupper(substr($borrower, 0, 4)).'-'.random_int(1000, 9999),
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => '2026-10-10 09:26:00',
        ]);
    }
}
