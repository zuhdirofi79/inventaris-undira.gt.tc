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
        $this->assertStringContainsString('Wajib diisi. Format PDF, JPG, atau JPEG, maksimal 5 MB.', $html);
        $this->assertStringContainsString('Email peminjam wajib diisi.', $html);
        $this->assertStringContainsString('Format email tidak valid.', $html);
        $this->assertStringContainsString('Dokumen pendukung wajib diunggah.', $html);
        $this->assertStringContainsString('accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg"', $html);
        $this->assertStringContainsString('enctype="multipart/form-data"', $html);
        $this->assertMatchesRegularExpression('/name="supporting_document"[^>]*required/', $html);
        $this->assertMatchesRegularExpression('/name="borrower_email"[^>]*required/', $html);
    }

    public function test_store_without_document_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors([
                'supporting_document' => 'Dokumen pendukung wajib diunggah.',
            ]);

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame([], Storage::disk(BorrowingSupportingDocumentService::DISK)->allFiles());
    }

    public function test_empty_form_reports_every_required_field(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), [
                'borrower_name' => '',
                'borrower_email' => '   ',
                'borrower_phone' => '',
                'borrow_date' => '',
                'expected_return_date' => '',
                'notes' => '',
            ])
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors([
                'borrower_name' => 'Nama peminjam wajib diisi.',
                'borrower_email' => 'Email peminjam wajib diisi.',
                'borrower_phone' => 'Nomor Telepon / No. HP wajib diisi.',
                'borrow_date' => 'Tanggal dan jam pinjam wajib diisi.',
                'expected_return_date' => 'Tanggal dan jam kembali wajib diisi.',
                'notes' => 'Keperluan wajib diisi.',
                'supporting_document' => 'Dokumen pendukung wajib diunggah.',
                'items' => 'Tambahkan minimal satu inventaris sebelum menyimpan peminjaman.',
            ]);

        $this->assertSame(0, Borrowing::query()->count());
    }

    public function test_invalid_email_is_rejected_and_old_input_is_kept(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $html = $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'borrower_name' => 'Wawan',
                'borrower_email' => 'bukan-email',
                'supporting_document' => UploadedFile::fake()->image('bukti.jpg'),
            ]))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertStringContainsString('Format email tidak valid.', $html);
        $this->assertStringContainsString('value="Wawan"', $html);
        $this->assertStringContainsString('value="bukan-email"', $html);
    }

    public function test_spoofed_pdf_and_png_renamed_as_jpg_are_rejected(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $phpPath = tempnam(sys_get_temp_dir(), 'php');
        file_put_contents($phpPath, "<?php echo 'no';");
        $spoofedPdf = new UploadedFile($phpPath, 'lampiran.pdf', 'application/pdf', UPLOAD_ERR_OK, true);
        $png = UploadedFile::fake()->image('asli.png');
        $spoofedJpg = new UploadedFile($png->getPathname(), 'palsu.jpg', 'image/jpeg', UPLOAD_ERR_OK, true);

        try {
            foreach ([$spoofedPdf, $spoofedJpg] as $file) {
                $this->actingAs($admin)
                    ->from(route('borrowings.create'))
                    ->post(route('borrowings.store'), $this->payload($item, [
                        'supporting_document' => $file,
                    ]))
                    ->assertRedirect(route('borrowings.create'))
                    ->assertSessionHasErrors([
                        'supporting_document' => 'Format dokumen harus PDF, JPG, atau JPEG.',
                    ]);
            }
        } finally {
            if (is_file($phpPath)) {
                unlink($phpPath);
            }
        }

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame([], Storage::disk(BorrowingSupportingDocumentService::DISK)->allFiles());
    }

    public function test_store_accepts_valid_pdf_under_5mb(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $pdf = $this->realUpload('laporan_kegiatan.pdf', 1800 * 1024, "%PDF-1.4\n", 'application/pdf');

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
            ->assertSessionHasErrors([
                'supporting_document' => 'Ukuran dokumen maksimal 5 MB.',
            ]);

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
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
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

        $newPdf = $this->realUpload('pengganti.pdf', 20 * 1024, "%PDF-1.4\n", 'application/pdf');

        $this->actingAs($admin)
            ->put(route('borrowings.update', $borrowing), [
                'borrower_name' => $borrowing->borrower_name,
                'borrower_email' => $borrowing->borrower_email,
                'borrower_phone' => '081234567890',
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
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

    public function test_remove_document_without_replacement_is_rejected(): void
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
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
                'status' => $borrowing->status->value,
                'notes' => $borrowing->notes,
                'remove_supporting_document' => '1',
            ])
            ->assertRedirect(route('borrowings.edit', $borrowing))
            ->assertSessionHasErrors([
                'supporting_document' => 'Dokumen pendukung wajib diunggah.',
            ]);

        $borrowing->refresh();
        $this->assertSame($oldPath, $borrowing->supporting_document_path);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($oldPath);
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
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
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
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
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
                'borrow_date' => $borrowing->borrow_date->format('Y-m-d\TH:i'),
                'expected_return_date' => $borrowing->expected_return_date->format('Y-m-d\TH:i'),
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
                'supporting_document' => $this->realUpload('bukti.pdf', 8 * 1024, "%PDF-1.4\n", 'application/pdf'),
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

    public function test_real_pdf_around_3mb_is_stored_and_can_be_opened(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $name = 'TOC Undira - Penambahan Gardener Kranggan (1).pdf';
        $pdf = $this->realUpload($name, 3 * 1024 * 1024, "%PDF-1.4\n", 'application/pdf');

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $pdf,
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertNotNull($borrowing);
        $this->assertSame($name, $borrowing->supporting_document_original_name);
        $this->assertGreaterThan(3 * 1024 * 1024, (int) $borrowing->supporting_document_size);
        $this->assertLessThan(5 * 1024 * 1024, (int) $borrowing->supporting_document_size);
        Storage::disk(BorrowingSupportingDocumentService::DISK)->assertExists($borrowing->supporting_document_path);
        $this->assertStringStartsWith('borrowings/'.$borrowing->id.'/', $borrowing->supporting_document_path);
        $this->assertStringEndsWith('.pdf', $borrowing->supporting_document_path);
        $this->assertStringNotContainsString('..', $borrowing->supporting_document_path);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);

        $this->actingAs($admin)
            ->get(route('borrowings.supporting-document.view', $borrowing))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_real_pdf_at_5mb_is_accepted(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $pdf = $this->realUpload('batas.pdf', (5120 * 1024) - 16, "%PDF-1.4\n", 'application/pdf');

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $pdf,
            ]))
            ->assertRedirect(route('borrowings.index'));

        $borrowing = Borrowing::query()->latest('id')->first();
        $this->assertTrue($borrowing->hasSupportingDocument());
        $this->assertLessThanOrEqual(5120 * 1024, (int) $borrowing->supporting_document_size);
    }

    public function test_real_pdf_over_5mb_is_rejected_with_size_message_once(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $pdf = $this->realUpload('terlalu-besar.pdf', (5121 * 1024), "%PDF-1.4\n", 'application/pdf');

        $html = $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $pdf,
            ]))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame(1, substr_count($html, '<div class="invalid-feedback d-block">Ukuran dokumen maksimal 5 MB.</div>'));
        $this->assertSame(0, substr_count($html, '<li>Ukuran dokumen maksimal 5 MB.</li>'));
        $this->assertStringNotContainsString('gagal diunggah', $html);
        $this->assertStringNotContainsString('supporting document', strtolower($html));
        $this->assertStringNotContainsString('Terjadi kesalahan', $html);
    }

    public function test_php_ini_rejection_uses_size_message_and_does_not_save(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, "%PDF-1.4\n");
        $pdf = new UploadedFile($path, 'ditolak-php.pdf', 'application/pdf', UPLOAD_ERR_INI_SIZE, true);

        try {
            $html = $this->followingRedirects()
                ->actingAs($admin)
                ->from(route('borrowings.create'))
                ->post(route('borrowings.store'), $this->payload($item, [
                    'supporting_document' => $pdf,
                ]))
                ->assertOk()
                ->getContent();
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertSame(1, substr_count($html, '<div class="invalid-feedback d-block">Ukuran dokumen maksimal 5 MB.</div>'));
        $this->assertSame(0, substr_count($html, '<li>Ukuran dokumen maksimal 5 MB.</li>'));
        $this->assertStringNotContainsString('gagal diunggah', $html);
    }

    public function test_real_jpeg_is_accepted_and_other_formats_are_rejected(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();
        $jpeg = UploadedFile::fake()->image('bukti kegiatan.jpeg')->size(400);

        $this->actingAs($admin)
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $jpeg,
            ]))
            ->assertRedirect(route('borrowings.index'));

        $this->assertSame('bukti kegiatan.jpeg', Borrowing::query()->latest('id')->first()->supporting_document_original_name);

        $item = $this->makeItem();
        $html = $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => UploadedFile::fake()->create('catatan.txt', 20, 'text/plain'),
            ]))
            ->assertOk()
            ->getContent();

        $this->assertSame(1, Borrowing::query()->count());
        $this->assertSame(1, substr_count($html, '<div class="invalid-feedback d-block">Format dokumen harus PDF, JPG, atau JPEG.</div>'));
        $this->assertStringNotContainsString('Terjadi kesalahan', $html);
    }

    public function test_invalid_form_keeps_old_input_and_does_not_save_partial_borrowing(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $html = $this->followingRedirects()
            ->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'borrower_name' => 'Wawan',
                'notes' => '',
                'supporting_document' => UploadedFile::fake()->create('lampiran.pdf', 200, 'application/pdf'),
            ]))
            ->assertOk()
            ->getContent();

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertStringContainsString('Keperluan wajib diisi.', $html);
        $this->assertStringContainsString('value="Wawan"', $html);
        $this->assertStringContainsString('Terjadi kesalahan', $html);
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
    }

    public function test_storage_failure_does_not_leave_a_borrowing(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->partialMock(BorrowingSupportingDocumentService::class, function ($mock): void {
            $mock->shouldReceive('store')->once()->andThrow(new \RuntimeException('disk'));
        });

        $this->actingAs($admin)
            ->from(route('borrowings.create'))
            ->post(route('borrowings.store'), $this->payload($item, [
                'supporting_document' => $this->realUpload('lampiran.pdf', 8 * 1024, "%PDF-1.4\n", 'application/pdf'),
            ]))
            ->assertRedirect(route('borrowings.create'))
            ->assertSessionHasErrors([
                'supporting_document' => 'Dokumen tidak dapat disimpan saat ini. Silakan coba kembali.',
            ]);

        $this->assertSame(0, Borrowing::query()->count());
        $this->assertSame(InventoryStatus::Aktif, $item->fresh()->status);
        $this->assertSame([], Storage::disk(BorrowingSupportingDocumentService::DISK)->allFiles());
    }

    public function test_php_limits_allow_a_5mb_upload(): void
    {
        $upload = ini_parse_quantity((string) ini_get('upload_max_filesize'));
        $post = ini_parse_quantity((string) ini_get('post_max_size'));

        $this->assertGreaterThanOrEqual(8 * 1024 * 1024, $upload);
        $this->assertGreaterThanOrEqual(12 * 1024 * 1024, $post);
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
            'borrow_date' => now()->format('Y-m-d\TH:i'),
            'expected_return_date' => now()->addDays(3)->format('Y-m-d\TH:i'),
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

    protected function realUpload(string $name, int $bytes, string $header, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'doc');
        $handle = fopen($path, 'wb');
        fwrite($handle, $header);
        $written = ftell($handle);
        $pad = str_repeat('a', 8192);
        while ($written < $bytes) {
            $chunk = substr($pad, 0, min(8192, $bytes - $written));
            fwrite($handle, $chunk);
            $written += strlen($chunk);
        }
        if (str_ends_with($name, '.pdf')) {
            fwrite($handle, "\n%%EOF\n");
        }
        fclose($handle);

        return new UploadedFile($path, $name, $mime, UPLOAD_ERR_OK, true);
    }
}
