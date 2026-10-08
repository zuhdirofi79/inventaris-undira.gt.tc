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
use App\Mail\BorrowingApprovedMail;
use App\Services\BorrowingSupportingDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class BorrowingSupportingDocumentTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake(BorrowingSupportingDocumentService::DISK);
    }

    public function test_create_form_shows_optional_document_field(): void
    {
        $admin = $this->makeAdmin();

        $html = $this->actingAs($admin)
            ->get(route('borrowings.create'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dokumen Pendukung', $html);
        $this->assertStringContainsString('name="supporting_document"', $html);
        $this->assertStringContainsString('Opsional. Format PDF, JPG, atau JPEG, maksimal 5 MB.', $html);
        $this->assertStringContainsString('Format file harus PDF, JPG, atau JPEG.', $html);
        $this->assertStringContainsString('Ukuran file maksimal 5 MB.', $html);
        $this->assertStringContainsString('accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertDoesNotMatchRegularExpression('/name="supporting_document"[^>]*required/', $html);
    }

    public function test_store_without_document_still_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertNotNull($borrowing);
        $this->assertFalse($borrowing->hasSupportingDocument());
        $this->assertNull($borrowing->supporting_document_path);
    }

    public function test_store_accepts_valid_pdf_under_5mb(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $pdf = UploadedFile::fake()->create('laporan_kegiatan.pdf', 1800, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $pdf,
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertTrue($borrowing->hasSupportingDocument());
        $this->assertSame('laporan_kegiatan.pdf', $borrowing->supporting_document_original_name);
        $this->assertNotNull($borrowing->supporting_document_size);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($borrowing->supporting_document_path);
        $this->assertStringEndsWith('.pdf', $borrowing->supporting_document_path);
        $this->assertStringNotContainsString('laporan_kegiatan', $borrowing->supporting_document_path);
    }

    public function test_reject_pdf_over_5mb(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $pdf = UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf');

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $pdf,
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors('supporting_document');

        $this->assertSame(0, Borrowing::query()->count());
    }

    public function test_store_accepts_jpg_and_jpeg(): void
    {
        $admin = $this->makeAdmin();

        foreach (['foto_kegiatan.jpg', 'foto_kegiatan.jpeg'] as $name) {
            $item = $this->makeItem();
            $image = UploadedFile::fake()->image($name)->size(1024);

            $this->actingAs($admin)
                ->post(route('borrowings.store'), $this->payload($item, [
                    'supporting_document' => $image,
                ]))
                ->assertRedirect(route('borrowings.index'));

            $borrowing = Borrowing::query()->latest('id')->first();
            $this->assertSame($name, $borrowing->supporting_document_original_name);
            $this->assertStringEndsWith('.'.pathinfo($name, PATHINFO_EXTENSION), $borrowing->supporting_document_path);
            Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($borrowing->supporting_document_path);

            $html = $this->actingAs($admin)
                ->get(route('borrowings.show', $borrowing))
                ->assertOk()
                ->getContent();
            $this->assertStringContainsString($name, $html);
            $this->assertStringContainsString(route('borrowings.supporting-document.view', $borrowing), $html);

            $this->actingAs($admin)
                ->get(route('borrowings.supporting-document.view', $borrowing))
                ->assertOk()
                ->assertHeader('content-type', 'image/jpeg');
        }
    }

    public function test_store_accepts_image_just_under_5mb(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $image = UploadedFile::fake()->image('hampir.jpg')->size(5120);

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $image,
            ]))
            ->assertRedirect(route('borrowings.index'));

        $this->assertTrue(Borrowing::query()->latest('id')->first()->hasSupportingDocument());
    }

    public function test_reject_non_pdf_uploads(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        foreach ([
            UploadedFile::fake()->image('foto.png'),
            UploadedFile::fake()->create('gambar.webp', 100, 'image/webp'),
            UploadedFile::fake()->create('dokumen.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('sheet.xlsx', 100, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            UploadedFile::fake()->create('arsip.zip', 100, 'application/zip'),
            UploadedFile::fake()->create('virus.exe', 100, 'application/octet-stream'),
            UploadedFile::fake()->image('disamarkan.jpg.png'),
        ] as $file) {
            $this->actingAs($admin)
                ->from(route('borrowings.create'))
                ->post(route('borrowings.store'), $this->payload($item, [
                    'supporting_document' => $file,
                ]))
                ->assertRedirect(route('borrowings.create'))
                ->assertSessionHasErrors('supporting_document');
        }

        $this->assertSame(0, Borrowing::query()->count());
    }

    public function test_detail_shows_document_and_authorized_download_works(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);

        $html = $this->actingAs($admin)
            ->get(route('borrowings.show', $borrowing))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Dokumen Pendukung', $html);
        $this->assertStringContainsString('laporan_kegiatan.pdf', $html);
        $this->assertStringContainsString('Lihat Dokumen', $html);
        $this->assertStringContainsString('Download', $html);

        $this->actingAs($admin)
            ->get(route('borrowings.supporting-document.view', $borrowing))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($admin)
            ->get(route('borrowings.supporting-document.download', $borrowing))
            ->assertOk();
    }

    public function test_edit_without_replacing_keeps_existing_document(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $path = $borrowing->supporting_document_path;

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => 'Keperluan diperbarui',
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertSame($path, $borrowing->supporting_document_path);
        $this->assertSame('laporan_kegiatan.pdf', $borrowing->supporting_document_original_name);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($path);
    }

    public function test_replace_document_stores_new_and_removes_old_file(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $oldPath = $borrowing->supporting_document_path;

        $newPdf = UploadedFile::fake()->create('pengganti.pdf', 500, 'application/pdf');

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'supporting_document' => $newPdf,
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertNotSame($oldPath, $borrowing->supporting_document_path);
        $this->assertSame('pengganti.pdf', $borrowing->supporting_document_original_name);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($borrowing->supporting_document_path);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertMissing($oldPath);
    }

    public function test_remove_document_checkbox_clears_file(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $oldPath = $borrowing->supporting_document_path;

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'remove_supporting_document' => '1',
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertFalse($borrowing->hasSupportingDocument());
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertMissing($oldPath);
    }

    public function test_guest_cannot_access_document(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);

        $this->get(route('borrowings.supporting-document.view', $borrowing))
            ->assertRedirect();

        $this->get(route('borrowings.supporting-document.download', $borrowing))
            ->assertRedirect();
    }

    public function test_user_without_borrowing_view_cannot_open_document(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $teknisi = $this->makeTeknisi();

        $this->actingAs($teknisi)
            ->get(route('borrowings.supporting-document.view', $borrowing))
            ->assertForbidden();
    }

    public function test_replace_pdf_with_jpg_removes_old_file(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $oldPath = $borrowing->supporting_document_path;

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'supporting_document' => UploadedFile::fake()->image('foto.jpg')->size(400),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertSame('foto.jpg', $borrowing->supporting_document_original_name);
        $this->assertStringEndsWith('.jpg', $borrowing->supporting_document_path);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertMissing($oldPath);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($borrowing->supporting_document_path);
    }

    public function test_replace_jpg_with_jpeg(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin, UploadedFile::fake()->image('awal.jpg')->size(300));
        $oldPath = $borrowing->supporting_document_path;

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'supporting_document' => UploadedFile::fake()->image('baru.jpeg')->size(350),
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertSame('baru.jpeg', $borrowing->supporting_document_original_name);
        $this->assertStringEndsWith('.jpeg', $borrowing->supporting_document_path);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertMissing($oldPath);
    }

    public function test_failed_replacement_keeps_existing_document(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->makeBorrowingWithDocument($admin);
        $oldPath = $borrowing->supporting_document_path;

        $this->actingAs($admin)
            ->from(route('borrowings.edit', $borrowing))
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'supporting_document' => UploadedFile::fake()->image('salah.png'),
            ])
            ->assertSessionHasErrors('supporting_document');

        $borrowing->refresh();
        $this->assertSame($oldPath, $borrowing->supporting_document_path);
        $this->assertSame('laporan_kegiatan.pdf', $borrowing->supporting_document_original_name);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($oldPath);
    }

    public function test_approval_email_does_not_attach_supporting_document(): void
    {
        Mail::fake();
        $requester = $this->makeStaff(['email' => 'staff-doc-mail@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-doc-mail@undira.ac.id']);
        $item = $this->makeItem();

        $this->actingAs($requester)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => UploadedFile::fake()->image('lampiran.jpg')->size(800),
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $line = $borrowing->items()->first();

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => [
                    ['id' => $line->id, 'action' => 'approve', 'rejection_reason' => ''],
                ],
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        Mail::assertSent(BorrowingApprovedMail::class, 1);
        $html = Mail::sent(BorrowingApprovedMail::class)->first()->render();
        $this->assertStringNotContainsString('lampiran.jpg', $html);
        $this->assertTrue($borrowing->fresh()->hasSupportingDocument());
    }

    public function test_approval_workflow_unaffected_when_document_present(): void
    {
        $requester = $this->makeStaff(['email' => 'staff-doc@undira.ac.id']);
        $admin = $this->makeAdmin(['email' => 'admin-doc@undira.ac.id']);
        $item = $this->makeItem();

        $this->actingAs($requester)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => UploadedFile::fake()->create('bukti.pdf', 200, 'application/pdf'),
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $line = $borrowing->items()->first();

        $this->actingAs($admin)
            ->post(route('borrowings.save-approvals', $borrowing), [
                'decisions' => [
                    ['id' => $line->id, 'action' => 'approve', 'rejection_reason' => ''],
                ],
            ])
            ->assertRedirect(route('borrowings.show', $borrowing));

        $borrowing->refresh();
        $this->assertSame(BorrowingStatus::Disetujui, $borrowing->status);
        $this->assertTrue($borrowing->hasSupportingDocument());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(InventoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Dokumen',
            'borrower_email' => 'dokumen@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(3)->toDateString(),
            'notes' => 'Rapat',
            'status' => BorrowingStatus::MenungguPersetujuan->value,
        ], $overrides);
    }

    protected function makeBorrowingWithDocument($user, ?UploadedFile $file = null): Borrowing
    {
        $item = $this->makeItem();
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Ada Dokumen',
            'borrower_email' => 'ada@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Ada dokumen',
            'requested_by' => $user->id,
        ]);

        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        $pdf = $file ?? UploadedFile::fake()->create('laporan_kegiatan.pdf', 900, 'application/pdf');
        $meta = app(BorrowingSupportingDocumentService::class)->store($borrowing, $pdf);
        $borrowing->update($meta);

        return $borrowing->fresh();
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CD'.$suffix, 'name' => 'Kampus Dok', 'slug' => 'kampus-dok-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BD'.$suffix, 'name' => 'Gedung D', 'slug' => 'gedung-d-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1'.$suffix, 'name' => 'Ruang Dok', 'room_type' => 'Lab', 'capacity' => 20, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQD'.$suffix, 'name' => 'Equipment Dok', 'slug' => 'equipment-dok-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PCD'.$suffix, 'name' => 'PC Dok', 'slug' => 'pc-dok-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-DOC-'.uniqid(),
            'barcode' => 'BC-'.uniqid(),
            'name' => 'Laptop Dokumen',
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
