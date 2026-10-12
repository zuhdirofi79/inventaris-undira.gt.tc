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

class BorrowingReceiptPdfTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_mixed_receipt_uses_item_status_and_real_pdf_is_read_only(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['email' => 'admin-receipt@undira.ac.id']);
        [$borrowing, $lines] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Disetujui, 'CBB-APBD-26-ATK-PL-000001', 'Pulpen Kenko Satu', 'Gedung Rektorat Lantai 1 Ruang Administrasi Umum'],
            [BorrowingItemStatus::Ditolak, 'CBB-APBD-26-ATK-PL-000002', 'Pulpen Kenko Dua', 'Undira Cibubur'],
            [BorrowingItemStatus::Disetujui, 'CBB-APBD-26-ATK-PL-000003', 'Pulpen Kenko Tiga', 'Undira Cibubur'],
            [BorrowingItemStatus::Ditolak, 'CBB-APBD-26-ATK-PL-000004', 'Pulpen Kenko Empat', 'Undira Cibubur'],
            [BorrowingItemStatus::Ditolak, 'CBB-APBD-26-ATK-PL-000005', 'Pulpen Kenko Lima', 'Undira Cibubur'],
        ]);

        $before = [
            'status' => $borrowing->status->value,
            'approved_at' => $borrowing->approved_at?->toDateTimeString(),
            'items' => $lines->map(fn (BorrowingItem $line) => [$line->id, $line->status->value])->all(),
        ];

        $html = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Status Persetujuan', $html);
        $this->assertStringContainsString('Disetujui: 2', $html);
        $this->assertStringContainsString('Ditolak: 3', $html);
        $this->assertStringContainsString('Menunggu: 0', $html);
        $this->assertMatchesRegularExpression('/width:\s*22%/', $html);
        $this->assertMatchesRegularExpression('/width:\s*13%/', $html);

        foreach ($lines as $line) {
            $number = $line->inventoryItem->inventory_number;
            $expected = $line->status === BorrowingItemStatus::Ditolak ? 'Ditolak' : 'Disetujui';
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($number, '/').'.{0,900}'.preg_quote($expected, '/').'/s',
                $html
            );
        }

        $pdf = $this->get(route('borrowing.approval.receipt.pdf', $borrowing->approval_number))
            ->assertOk();
        $binary = $pdf->getContent();
        $this->assertStringStartsWith('%PDF', $binary);
        $path = storage_path('framework/testing/receipt-mixed.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $binary);
        $this->assertSame(2, $this->pdfPageCount($binary));
        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\s*\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/', $binary);

        $detail = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();
        foreach ($lines as $line) {
            $number = $line->inventoryItem->inventory_number;
            $expected = $line->status === BorrowingItemStatus::Ditolak ? 'Ditolak' : 'Disetujui';
            $this->assertMatchesRegularExpression(
                '/'.preg_quote($number, '/').'.{0,2500}'.preg_quote($expected, '/').'/s',
                $detail
            );
        }

        $this->get(route('borrowing.approval.receipt.pdf', $borrowing->approval_number))->assertOk();

        $borrowing->refresh()->load('items');
        $this->assertSame($before['status'], $borrowing->status->value);
        $this->assertSame($before['approved_at'], $borrowing->approved_at?->toDateTimeString());
        $this->assertSame(
            $before['items'],
            $borrowing->items->map(fn (BorrowingItem $line) => [$line->id, $line->status->value])->all()
        );
        Mail::assertNothingSent();
    }

    public function test_pending_item_is_not_marked_approved_because_the_transaction_is_approved(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-receipt-pending@undira.ac.id']);
        [$borrowing] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Disetujui, 'CBB-PENDING-OK-'.uniqid(), 'Barang Disetujui', 'Kampus'],
            [BorrowingItemStatus::MenungguPersetujuan, 'CBB-PENDING-WAIT-'.uniqid(), 'Barang Menunggu', 'Kampus'],
        ]);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Menunggu: 1', $html);
        $this->assertStringContainsString('Disetujui: 1', $html);
        $this->assertMatchesRegularExpression('/CBB-PENDING-WAIT-[^<]+.{0,900}Menunggu/s', $html);
    }

    public function test_header_only_receipt_does_not_invent_an_approved_item_status(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-receipt-legacy@undira.ac.id']);
        $item = $this->makeItem('CBB-LEGACY-'.uniqid(), 'Barang Lama', 'Lokasi Lama');
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Legacy',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'APR-'.now()->format('Ymd').'-'.random_int(100000, 999999),
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString($item->inventory_number, $html);
        $this->assertStringContainsString('Status Persetujuan', $html);
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($item->inventory_number, '/').'.{0,1200}Tidak Tersedia/s',
            $html
        );
    }

    public function test_many_items_produce_a_multipage_pdf(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-receipt-pages@undira.ac.id']);
        $rows = [];
        for ($i = 1; $i <= 40; $i++) {
            $rows[] = [
                $i % 2 === 0 ? BorrowingItemStatus::Ditolak : BorrowingItemStatus::Disetujui,
                'CBB-PAGE-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT).'-'.uniqid(),
                'Barang Halaman '.$i,
                'Universitas Dian Nusantara Kampus Cibubur Gedung A Lantai '.$i.' Ruang Administrasi',
            ];
        }
        [$borrowing] = $this->makeReceiptBorrowing($admin, $rows);

        $binary = $this->actingAs($admin)
            ->get(route('borrowings.receipt.pdf', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringStartsWith('%PDF', $binary);
        $this->assertGreaterThan(1, $this->pdfPageCount($binary));

        $path = storage_path('framework/testing/receipt-multipage.pdf');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $binary);
    }

    public function test_all_rejected_and_physical_loan_statuses_keep_the_approval_label(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-receipt-labels@undira.ac.id']);
        [$rejected] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Ditolak, 'CBB-ALL-NO-'.uniqid(), 'Barang Ditolak', 'Kampus'],
            [BorrowingItemStatus::Ditolak, 'CBB-ALL-NO-'.uniqid(), 'Barang Ditolak Dua', 'Kampus'],
            [BorrowingItemStatus::Ditolak, 'CBB-ALL-NO-'.uniqid(), 'Barang Ditolak Tiga', 'Kampus'],
        ]);
        $rejected->update(['status' => BorrowingStatus::Ditolak]);

        $rejectedHtml = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $rejected))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Disetujui: 0', $rejectedHtml);
        $this->assertStringContainsString('Ditolak: 3', $rejectedHtml);
        $this->assertSame(3, preg_match_all('/<td class="c-status st-no">Ditolak<\/td>/', $rejectedHtml));
        $this->assertSame(0, preg_match_all('/<td class="c-status st-ok">Disetujui<\/td>/', $rejectedHtml));

        [$physical] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Dipinjam, 'CBB-LOAN-OUT-'.uniqid(), 'Barang Dipinjam', 'Kampus'],
            [BorrowingItemStatus::Dikembalikan, 'CBB-LOAN-BACK-'.uniqid(), 'Barang Kembali', 'Kampus'],
        ]);
        $physical->update(['status' => BorrowingStatus::Dipinjam]);

        $physicalHtml = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $physical))
            ->assertOk()
            ->getContent();
        $this->assertStringContainsString('Disetujui: 2', $physicalHtml);
        $this->assertStringContainsString('Ditolak: 0', $physicalHtml);
        $this->assertSame(2, preg_match_all('/<td class="c-status st-ok">Disetujui<\/td>/', $physicalHtml));
        $this->assertDoesNotMatchRegularExpression('/<td class="c-status[^"]*">(?:Dipinjam|Dikembalikan)<\/td>/', $physicalHtml);
    }

    public function test_signature_block_is_aligned_once_and_does_not_invent_an_officer(): void
    {
        Mail::fake();

        $admin = $this->makeAdmin(['name' => 'Bukan Petugas PPPA', 'email' => 'admin-sign@undira.ac.id']);
        [$short] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Disetujui, 'CBB-SIGN-1-'.uniqid(), 'Barang Satu', 'Kampus'],
            [BorrowingItemStatus::Ditolak, 'CBB-SIGN-2-'.uniqid(), 'Barang Dua', 'Kampus'],
            [BorrowingItemStatus::Disetujui, 'CBB-SIGN-3-'.uniqid(), 'Barang Tiga', 'Kampus'],
        ]);
        $short->update(['borrower_name' => 'Nama Lengkap Peminjam Dengan Gelar Akademik yang Panjang']);

        $html = $this->actingAs($admin)->get(route('borrowings.receipt', $short))->assertOk()->getContent();
        $this->assertStringContainsString('Peminjam,', $html);
        $this->assertStringContainsString('Biro PPPA', $html);
        $this->assertStringContainsString('Nama Lengkap Peminjam Dengan Gelar Akademik yang Panjang', $html);
        $this->assertStringContainsString('(Biro PPPA)', $html);
        $this->assertStringNotContainsString('(Nama Petugas Biro PPPA)', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);
        $this->assertStringNotContainsString('position: absolute', $html);
        $this->assertStringNotContainsString('position: fixed', $html);
        $this->assertSame(1, substr_count($html, 'class="signatures"'));
        $this->assertSame(1, substr_count($html, 'ATURAN DAN TANGGUNG JAWAB PEMINJAM'));
        $noteAt = strpos($html, 'Dokumen ini digenerate otomatis');
        $termsAt = strpos($html, 'ATURAN DAN TANGGUNG JAWAB PEMINJAM');
        $signAt = strpos($html, 'class="signatures"');
        $this->assertTrue($noteAt !== false && $termsAt !== false && $signAt !== false);
        $this->assertLessThan($termsAt, $noteAt);
        $this->assertLessThan($signAt, $termsAt);
        foreach ([
            'fasilitas dari kampus untuk mahasiswa, karyawan, dosen, dan pimpinan',
            'Perlengkapan dipinjamkan selama periode tertentu.',
            'di luar ketentuan garansi menjadi tanggung jawab peminjam.',
            'sudah tidak memegang jabatan atau mengundurkan diri',
            'mengganti perlengkapan yang rusak dengan yang baru.',
            'tanpa seizin atau pemberitahuan kepada Biro PPPA.',
        ] as $term) {
            $this->assertStringContainsString($term, $html);
        }

        $binary = $this->get(route('borrowing.approval.receipt.pdf', $short->approval_number))->assertOk()->getContent();
        $this->assertStringStartsWith('%PDF', $binary);
        $placed = $this->pdfTextRows($binary);
        $borrower = $this->pdfRow($placed, 'Peminjam,');
        $office = $this->pdfExact($placed, 'BiroPPPA');
        $this->assertNotNull($borrower);
        $this->assertNotNull($office);
        $this->assertSame($borrower['page'], $office['page']);
        $this->assertLessThan($office['x'], $borrower['x']);
        $this->assertEqualsWithDelta($borrower['y'], $office['y'], 2.0);
        $borrowerName = $this->pdfRow($placed, 'Nama Lengkap Peminjam', $borrower['x']);
        $officerName = $this->pdfRow($placed, '\\(Biro PPPA\\)', $office['x']);
        $this->assertNotNull($borrowerName);
        $this->assertNotNull($officerName);
        $this->assertEqualsWithDelta($borrower['x'], $borrowerName['x'], 8);
        $this->assertEqualsWithDelta($office['x'], $officerName['x'], 8);
        $this->assertLessThan($borrower['y'], $borrowerName['y']);
        $this->assertEqualsWithDelta($borrowerName['y'], $officerName['y'], 3);
        $this->assertLessThanOrEqual(2, $this->pdfPageCount($binary));
        $terms = $this->pdfRow($placed, 'ATURANDANTANGGUNGJAWABPEMINJAM');
        $this->assertNotNull($terms);
        $this->assertSame($borrower['page'], $terms['page']);
        $this->assertGreaterThan($borrower['y'], $terms['y']);
        foreach ([
            'fasilitasdarikampus',
            'selamaperiodetertentu',
            'ketentuangaransi',
            'mengundurkandiri',
            'menggantiperlengkapan',
            'tanpaseizin',
        ] as $fragment) {
            $this->assertNotNull($this->pdfRow($placed, $fragment), $fragment);
        }
        $wrappedName = array_values(array_filter(
            $placed,
            fn (array $row) => $row['page'] === $borrower['page'] && str_contains($row['text'], 'yangPanjang')
        ));
        $this->assertNotEmpty($wrappedName);
        $this->assertLessThan($office['x'], max(array_column($wrappedName, 'x')));

        $rows = [];
        for ($i = 1; $i <= 40; $i++) {
            $rows[] = [
                $i % 2 === 0 ? BorrowingItemStatus::Ditolak : BorrowingItemStatus::Disetujui,
                'CBB-SIGN-PAGE-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'-'.uniqid(),
                'Barang '.$i,
                'Kampus',
            ];
        }
        [$long] = $this->makeReceiptBorrowing($admin, $rows);
        $longPdf = $this->actingAs($admin)->get(route('borrowings.receipt.pdf', $long))->assertOk()->getContent();
        $longRows = $this->pdfTextRows($longPdf);
        $this->assertGreaterThan(1, $this->pdfPageCount($longPdf));
        $this->assertCount(1, array_filter($longRows, fn (array $row) => $row['text'] === 'Peminjam,'));
        $this->assertCount(1, array_filter($longRows, fn (array $row) => $row['text'] === 'BiroPPPA'));
        $left = $this->pdfRow($longRows, 'Peminjam,');
        $right = $this->pdfExact($longRows, 'BiroPPPA');
        $longTerms = $this->pdfRow($longRows, 'ATURANDANTANGGUNGJAWABPEMINJAM');
        $this->assertNotNull($left);
        $this->assertNotNull($right);
        $this->assertNotNull($longTerms);
        $this->assertSame($left['page'], $right['page']);
        $this->assertSame($left['page'], $longTerms['page']);
        $this->assertGreaterThan($left['y'], $longTerms['y']);
        $this->assertSame($this->pdfPageCount($longPdf), $left['page']);
        $this->assertCount(1, array_filter($longRows, fn (array $row) => $row['text'] === 'ATURANDANTANGGUNGJAWABPEMINJAM'));

        $short->refresh();
        $this->assertSame(BorrowingStatus::Disetujui, $short->status);
        Mail::assertNothingSent();
    }

    public function test_borrowed_and_returned_receipts_share_one_format(): void
    {
        Mail::fake();
        $this->travelTo('2026-10-12 10:15:00');

        $admin = $this->makeAdmin(['email' => 'admin-receipt-status@undira.ac.id']);
        [$borrowing, $lines] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Disetujui, 'CBB-FMT-000001-'.uniqid(), 'Barang Satu', 'Gedung Rektorat Lantai 1 Ruang Administrasi Umum'],
            [BorrowingItemStatus::Disetujui, 'CBB-FMT-000002-'.uniqid(), 'Barang Dua', 'Undira Cibubur'],
            [BorrowingItemStatus::Disetujui, 'CBB-FMT-000003-'.uniqid(), 'Barang Tiga Nomor Panjang', 'Universitas Dian Nusantara Kampus Cibubur Gedung Rektorat Lantai 3 Ruang Administrasi Keuangan'],
            [BorrowingItemStatus::Ditolak, 'CBB-FMT-000004-'.uniqid(), 'Barang Empat', 'Undira Cibubur'],
            [BorrowingItemStatus::Ditolak, 'CBB-FMT-000005-'.uniqid(), 'Barang Lima', 'Undira Cibubur'],
        ]);
        $borrowing->update([
            'status' => BorrowingStatus::Dipinjam,
            'borrower_name' => 'Nama Lengkap Peminjam Dengan Gelar yang Cukup Panjang',
        ]);
        $approvedIds = $lines
            ->filter(fn (BorrowingItem $line) => $line->status === BorrowingItemStatus::Disetujui)
            ->pluck('id');
        BorrowingItem::query()->whereIn('id', $approvedIds)->update([
            'status' => BorrowingItemStatus::Dipinjam,
            'borrowed_at' => '2026-10-12 09:00:00',
        ]);

        $dipinjam = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();
        $this->assertReceiptShell($dipinjam, 3, 2, 0);
        $this->assertStringContainsString('class="badge">Dipinjam<', $dipinjam);
        $this->assertStringNotContainsString('Dikembalikan', $dipinjam);

        $pdfDipinjam = $this->get(route('borrowing.approval.receipt.pdf', $borrowing->approval_number))
            ->assertOk()
            ->getContent();

        $borrowing->update(['status' => BorrowingStatus::Dikembalikan, 'return_date' => '2026-10-12']);
        BorrowingItem::query()->whereIn('id', $approvedIds)->update([
            'status' => BorrowingItemStatus::Dikembalikan,
            'returned_at' => '2026-10-12 16:00:00',
        ]);

        $returned = $this->actingAs($admin)
            ->get(route('borrowings.receipt', $borrowing))
            ->assertOk()
            ->getContent();
        $this->assertReceiptShell($returned, 3, 2, 0);
        $this->assertStringContainsString('class="badge">Dikembalikan<', $returned);
        $this->assertSame(
            str_replace('class="badge">Dikembalikan<', 'class="badge">STATUS<', $returned),
            str_replace('class="badge">Dipinjam<', 'class="badge">STATUS<', $dipinjam),
        );

        $pdfReturned = $this->get(route('borrowing.approval.receipt.pdf', $borrowing->approval_number))
            ->assertOk()
            ->getContent();
        $this->assertStringStartsWith('%PDF', $pdfDipinjam);
        $this->assertStringStartsWith('%PDF', $pdfReturned);
        $this->assertMatchesRegularExpression('/\/Type\s*\/Page\s*\/MediaBox\s*\[\s*0(?:\.\d+)?\s+0(?:\.\d+)?\s+595(?:\.\d+)?\s+841(?:\.\d+)?\s*\]/', $pdfReturned);
        $this->assertSame($this->pdfPageCount($pdfDipinjam), $this->pdfPageCount($pdfReturned));

        $dipinjamText = $this->pdfPlain($pdfDipinjam);
        $returnedText = $this->pdfPlain($pdfReturned);
        $this->assertStringContainsString('Dipinjam', $dipinjamText);
        $this->assertStringNotContainsString('Dikembalikan', $dipinjamText);
        $this->assertStringContainsString('Dikembalikan', $returnedText);
        foreach ([
            'ATURANDANTANGGUNGJAWABPEMINJAM',
            'fasilitasdarikampus',
            'selamaperiodetertentu',
            'ketentuangaransi',
            'mengundurkandiri',
            'menggantiperlengkapan',
            'tanpaseizin',
            'Peminjam,',
            'BiroPPPA',
            '\\(BiroPPPA\\)',
            'NamaLengkapPeminjam',
            'Disetujui:3',
            'Ditolak:2',
            'Menunggu:0',
        ] as $fragment) {
            $this->assertStringContainsString($fragment, $dipinjamText, $fragment);
            $this->assertStringContainsString($fragment, $returnedText, $fragment);
        }

        $left = $this->pdfRow($this->pdfTextRows($pdfReturned), 'Peminjam,');
        $right = $this->pdfExact($this->pdfTextRows($pdfReturned), 'BiroPPPA');
        $terms = $this->pdfRow($this->pdfTextRows($pdfReturned), 'ATURANDANTANGGUNGJAWABPEMINJAM');
        $this->assertNotNull($left);
        $this->assertNotNull($right);
        $this->assertNotNull($terms);
        $this->assertSame($left['page'], $right['page']);
        $this->assertSame($left['page'], $terms['page']);
        $this->assertLessThan($right['x'], $left['x']);
        $this->assertGreaterThan($left['y'], $terms['y']);

        $borrowing->refresh()->load('items');
        $this->assertSame(BorrowingStatus::Dikembalikan, $borrowing->status);
        $this->assertSame(3, $borrowing->items->where('status', BorrowingItemStatus::Dikembalikan)->count());
        $this->assertSame(2, $borrowing->items->where('status', BorrowingItemStatus::Ditolak)->count());
        Mail::assertNothingSent();
        $this->travelBack();
    }

    public function test_guest_cannot_download_the_authenticated_receipt(): void
    {
        $admin = $this->makeAdmin(['email' => 'admin-receipt-auth@undira.ac.id']);
        [$borrowing] = $this->makeReceiptBorrowing($admin, [
            [BorrowingItemStatus::Disetujui, 'CBB-AUTH-'.uniqid(), 'Barang Auth', 'Kampus'],
        ]);

        $this->get(route('borrowings.receipt.pdf', $borrowing))->assertRedirect();

        $teknisi = $this->makeTeknisi(['email' => 'teknisi-receipt@undira.ac.id']);
        $this->actingAs($teknisi)
            ->get(route('borrowings.receipt.pdf', $borrowing))
            ->assertForbidden();
    }

    /**
     * @param  list<array{0: BorrowingItemStatus, 1: string, 2: string, 3: string}>  $rows
     * @return array{0: Borrowing, 1: \Illuminate\Support\Collection<int, BorrowingItem>}
     */
    protected function makeReceiptBorrowing($admin, array $rows): array
    {
        $first = $this->makeItem($rows[0][1], $rows[0][2], $rows[0][3]);
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $first->id,
            'borrower_name' => 'Peminjam Receipt',
            'borrower_email' => 'receipt@undira.ac.id',
            'borrow_date' => '2026-10-09',
            'expected_return_date' => '2026-10-12',
            'notes' => 'Praktikum',
            'approval_notes' => '2 inventaris disetujui, 3 inventaris ditolak.',
            'status' => BorrowingStatus::Disetujui,
            'approval_number' => 'APR-'.now()->format('Ymd').'-'.random_int(100000, 999999),
            'requested_by' => $admin->id,
            'approved_by' => $admin->id,
            'approved_at' => now(),
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $first->id,
            'qty' => 1,
            'status' => $rows[0][0],
        ]);

        foreach (array_slice($rows, 1) as [$status, $number, $name, $location]) {
            $item = $this->makeItem($number, $name, $location, $first);
            BorrowingItem::query()->create([
                'borrowing_id' => $borrowing->id,
                'inventory_item_id' => $item->id,
                'qty' => 1,
                'status' => $status,
            ]);
        }

        return [$borrowing->fresh(), $borrowing->items()->with('inventoryItem')->orderBy('id')->get()];
    }

    protected function makeItem(string $number, string $name, string $location, ?InventoryItem $template = null): InventoryItem
    {
        if ($template) {
            return InventoryItem::query()->create([
                'inventory_number' => $number,
                'barcode' => 'BC-'.uniqid(),
                'name' => $name,
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
            'code' => 'RC'.$suffix, 'name' => 'Undira Cibubur', 'slug' => 'receipt-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'RB'.$suffix, 'name' => 'Gedung Rektorat', 'slug' => 'gedung-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'RF'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RR'.$suffix, 'name' => $location, 'room_type' => 'Office', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'ATK'.$suffix, 'name' => 'Alat Tulis', 'slug' => 'atk-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PL'.$suffix, 'name' => 'Pulpen', 'slug' => 'pulpen-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => $number,
            'barcode' => 'BC-'.uniqid(),
            'name' => $name,
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

    protected function assertReceiptShell(string $html, int $approved, int $rejected, int $pending): void
    {
        $this->assertStringContainsString('Receipt Approval Peminjaman', $html);
        $this->assertStringContainsString('Universitas Dian Nusantara', $html);
        $this->assertStringContainsString('Tanggal &amp; Jam Pinjam', $html);
        $this->assertStringContainsString('Tanggal &amp; Jam Kembali', $html);
        $this->assertStringContainsString('Ringkasan Persetujuan', $html);
        $this->assertStringContainsString('Jumlah Item: '.($approved + $rejected + $pending), $html);
        $this->assertStringContainsString('Disetujui: '.$approved, $html);
        $this->assertStringContainsString('Ditolak: '.$rejected, $html);
        $this->assertStringContainsString('Menunggu: '.$pending, $html);
        $this->assertStringContainsString('Status Persetujuan', $html);
        $this->assertStringContainsString('ATURAN DAN TANGGUNG JAWAB PEMINJAM', $html);
        $this->assertStringContainsString('(Biro PPPA)', $html);
        $this->assertStringNotContainsString('Nama Petugas Biro PPPA', $html);
        $this->assertSame(1, substr_count($html, 'class="signatures"'));
        $this->assertSame($approved, preg_match_all('/<td class="c-status st-ok">Disetujui<\/td>/', $html));
        $this->assertSame($rejected, preg_match_all('/<td class="c-status st-no">Ditolak<\/td>/', $html));
        $this->assertDoesNotMatchRegularExpression('/<td class="c-status[^"]*">(?:Dipinjam|Dikembalikan)<\/td>/', $html);
        foreach ([
            'fasilitas dari kampus untuk mahasiswa, karyawan, dosen, dan pimpinan',
            'Perlengkapan dipinjamkan selama periode tertentu.',
            'di luar ketentuan garansi menjadi tanggung jawab peminjam.',
            'sudah tidak memegang jabatan atau mengundurkan diri',
            'mengganti perlengkapan yang rusak dengan yang baru.',
            'tanpa seizin atau pemberitahuan kepada Biro PPPA.',
        ] as $term) {
            $this->assertStringContainsString($term, $html);
        }
    }

    protected function pdfPlain(string $binary): string
    {
        return implode('', array_column($this->pdfTextRows($binary), 'text'));
    }

    protected function pdfPageCount(string $binary): int
    {
        preg_match_all('/\/Type\s*\/Page(?!s)/', $binary, $pages);

        return count($pages[0]);
    }

    /**
     * @return list<array{page: int, x: float, y: float, text: string}>
     */
    protected function pdfTextRows(string $binary): array
    {
        preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $binary, $streams);
        $rows = [];
        $page = 0;
        foreach ($streams[1] as $raw) {
            $data = @gzuncompress($raw);
            if ($data === false || ! str_contains($data, ' Td ')) {
                continue;
            }
            $page++;
            $clean = preg_replace('/[^\x09\x0a\x0d\x20-\x7e]/', ' ', $data) ?? '';
            preg_match_all('/BT\s+([0-9.]+)\s+([0-9.]+)\s+Td\s+\/F\d+\s+[0-9.]+\s+Tf\s+\[\((.*?)\)\]\s+TJ\s+ET/', $clean, $found, PREG_SET_ORDER);
            foreach ($found as $hit) {
                $rows[] = [
                    'page' => $page,
                    'x' => (float) $hit[1],
                    'y' => (float) $hit[2],
                    'text' => preg_replace('/\s+/', '', $hit[3]) ?? '',
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  list<array{page: int, x: float, y: float, text: string}>  $rows
     * @return array{page: int, x: float, y: float, text: string}|null
     */
    protected function pdfExact(array $rows, string $text): ?array
    {
        foreach ($rows as $row) {
            if ($row['text'] === $text) {
                return $row;
            }
        }

        return null;
    }

    /**
     * @param  list<array{page: int, x: float, y: float, text: string}>  $rows
     * @return array{page: int, x: float, y: float, text: string}|null
     */
    protected function pdfRow(array $rows, string $needle, ?float $nearX = null): ?array
    {
        $needle = preg_replace('/\s+/', '', $needle) ?? $needle;
        $match = null;
        foreach ($rows as $row) {
            if (! str_contains($row['text'], $needle)) {
                continue;
            }
            if ($nearX === null) {
                return $row;
            }
            if ($match === null || abs($row['x'] - $nearX) < abs($match['x'] - $nearX)) {
                $match = $row;
            }
        }

        return $match;
    }
}
