<?php

namespace Tests\Feature;

use App\Enums\BorrowingItemStatus;
use App\Enums\BorrowingStatus;
use App\Enums\FacilityRequestStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\RoomActivityType;
use App\Enums\VehicleServiceType;
use App\Models\Borrowing;
use App\Models\BorrowingItem;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Room;
use App\Models\RoomBorrowing;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\VehicleBorrowing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class ApprovalDocumentPreviewTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_inventory_pdf_opens_inline_and_downloads_without_changing_approval(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->inventoryBorrowing($admin);
        $bytes = "%PDF-1.4\npreview-inventaris\n%%EOF\n";
        $this->attachInventory($borrowing, 'laporan_kegiatan.pdf', $bytes);

        $html = $this->actingAs($admin)->get(route('borrowings.show', $borrowing))->assertOk()->getContent();
        $this->assertStringContainsString('laporan_kegiatan.pdf', $html);
        $this->assertStringContainsString('text-break', $html);
        $this->assertStringContainsString('flex-wrap', $html);
        $this->assertStringContainsString('target="_blank"', $html);
        $this->assertStringContainsString('fa-eye', $html);
        $this->assertStringContainsString('fa-download', $html);
        $this->assertStringContainsString('Lihat Dokumen', $html);
        $this->assertStringContainsString('Download', $html);

        $before = $this->snapshot($borrowing);
        $files = $this->storedFiles();

        $preview = $this->actingAs($admin)->get(route('borrowings.supporting-document.view', $borrowing));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'application/pdf');
        $preview->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertStringNotContainsString('attachment', (string) $preview->headers->get('content-disposition'));
        $this->assertStringContainsString('laporan_kegiatan.pdf', (string) $preview->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($preview));

        $download = $this->actingAs($admin)->get(route('borrowings.supporting-document.download', $borrowing));
        $download->assertOk();
        $download->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($download));

        $this->assertUnchanged($borrowing, $before);
        $this->assertSame($files, $this->storedFiles());
    }

    public function test_inventory_jpeg_opens_as_image_and_still_downloads(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->inventoryBorrowing($admin);
        $bytes = $this->jpegBytes();
        $this->attachInventory($borrowing, 'foto_kegiatan.jpeg', $bytes);

        $preview = $this->actingAs($admin)->get(route('borrowings.supporting-document.view', $borrowing));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($preview));

        $download = $this->actingAs($admin)->get(route('borrowings.supporting-document.download', $borrowing));
        $download->assertOk();
        $download->assertHeader('content-type', 'image/jpeg');
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($download));
    }

    public function test_room_preview_and_download_use_only_that_request_document(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'room-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'room-other@undira.ac.id']);
        $room = $this->roomBorrowing($owner);
        $approval = "%PDF-1.4\nroom-approval\n%%EOF\n";
        $layout = $this->jpegBytes();
        $this->attachRoom($room, 'approval', 'persetujuan.pdf', $approval);
        $this->attachRoom($room, 'layout', 'layout.jpg', $layout);

        $html = $this->actingAs($admin)->get(route('room-borrowings.show', $room))->assertOk()->getContent();
        $this->assertStringContainsString('persetujuan.pdf', $html);
        $this->assertStringContainsString('layout.jpg', $html);
        $this->assertStringContainsString('Dokumen pendukung tidak tersedia.', $html);
        $this->assertStringContainsString(route('room-borrowings.documents.preview', [$room, 'approval']), $html);
        $this->assertStringNotContainsString(route('room-borrowings.documents.preview', [$room, 'participants']), $html);

        $preview = $this->actingAs($admin)->get(route('room-borrowings.documents.preview', [$room, 'approval']));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertSame($approval, $this->body($preview));

        $image = $this->actingAs($admin)->get(route('room-borrowings.documents.preview', [$room, 'layout']));
        $image->assertOk();
        $image->assertHeader('content-type', 'image/jpeg');
        $this->assertSame($layout, $this->body($image));

        $download = $this->actingAs($admin)->get(route('room-borrowings.documents', [$room, 'approval']));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertSame($approval, $this->body($download));

        $this->actingAs($admin)->get(route('room-borrowings.documents.preview', [$room, 'participants']))->assertNotFound();
        $this->actingAs($other)->get(route('room-borrowings.documents.preview', [$room, 'approval']))->assertForbidden();
        $this->actingAs($other)->get(route('room-borrowings.documents', [$room, 'layout']))->assertForbidden();
        $this->actingAs($owner)->get(route('room-borrowings.documents.preview', [$room, 'approval']))->assertOk();
    }

    public function test_vehicle_preview_and_download_use_only_that_request_document(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'vehicle-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'vehicle-other@undira.ac.id']);
        $vehicle = $this->vehicleBorrowing($owner);
        $bytes = "%PDF-1.4\nvehicle-doc\n%%EOF\n";
        $before = $this->snapshot($vehicle);
        $this->attachVehicle($vehicle, 'surat_tugas.pdf', $bytes);
        $before['updated_at'] = $vehicle->fresh()->updated_at?->toISOString();

        $html = $this->actingAs($admin)->get(route('vehicle-borrowings.show', $vehicle))->assertOk()->getContent();
        $this->assertStringContainsString('surat_tugas.pdf', $html);
        $this->assertStringContainsString('Lihat Dokumen', $html);
        $this->assertStringContainsString('Download', $html);
        $this->assertStringContainsString('target="_blank"', $html);

        $files = $this->storedFiles();
        $preview = $this->actingAs($admin)->get(route('vehicle-borrowings.document.preview', $vehicle));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('inline', (string) $preview->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($preview));

        $download = $this->actingAs($admin)->get(route('vehicle-borrowings.document', $vehicle));
        $download->assertOk();
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertStringContainsString('surat_tugas.pdf', (string) $download->headers->get('content-disposition'));
        $this->assertSame($bytes, $this->body($download));

        $this->assertUnchanged($vehicle, $before);
        $this->assertSame($files, $this->storedFiles());
        $this->actingAs($other)->get(route('vehicle-borrowings.document.preview', $vehicle))->assertForbidden();
        $this->actingAs($other)->get(route('vehicle-borrowings.document', $vehicle))->assertForbidden();
        $this->actingAs($owner)->get(route('vehicle-borrowings.document.preview', $vehicle))->assertOk();
    }

    public function test_missing_optional_and_absent_files_do_not_error(): void
    {
        $admin = $this->makeAdmin();
        $empty = $this->inventoryBorrowing($admin);
        $missing = $this->inventoryBorrowing($admin);
        $missing->update([
            'supporting_document_path' => 'borrowings/'.$missing->id.'/hilang.pdf',
            'supporting_document_original_name' => 'hilang.pdf',
            'supporting_document_size' => 100,
        ]);

        $emptyHtml = $this->actingAs($admin)->get(route('borrowings.show', $empty))->assertOk()->getContent();
        $this->assertStringContainsString('Dokumen pendukung tidak tersedia.', $emptyHtml);
        $this->assertStringNotContainsString('Lihat Dokumen', $emptyHtml);

        $missingHtml = $this->actingAs($admin)->get(route('borrowings.show', $missing))->assertOk()->getContent();
        $this->assertStringContainsString('Dokumen pendukung tidak ditemukan.', $missingHtml);
        $this->assertStringNotContainsString('>Lihat Dokumen<', $missingHtml);

        $this->actingAs($admin)->get(route('borrowings.supporting-document.view', $missing))->assertNotFound();
        $this->actingAs($admin)->get(route('borrowings.supporting-document.download', $missing))->assertNotFound();

        $room = $this->roomBorrowing($admin);
        $vehicle = $this->vehicleBorrowing($admin);
        $this->actingAs($admin)->get(route('room-borrowings.show', $room))->assertOk()->assertSee('Dokumen pendukung tidak tersedia.');
        $this->actingAs($admin)->get(route('vehicle-borrowings.show', $vehicle))->assertOk()->assertSee('Dokumen pendukung tidak tersedia.');
        $this->actingAs($admin)->get(route('room-borrowings.documents.preview', [$room, 'approval']))->assertNotFound();
        $this->actingAs($admin)->get(route('vehicle-borrowings.document', $vehicle))->assertNotFound();
    }

    public function test_long_filename_wraps_and_unsupported_type_stays_downloadable(): void
    {
        $admin = $this->makeAdmin();
        $borrowing = $this->inventoryBorrowing($admin);
        $name = str_repeat('NAMAFILEPANJANG', 12).'.pdf';
        $this->attachInventory($borrowing, $name, "%PDF-1.4\npanjang\n%%EOF\n");

        $html = $this->actingAs($admin)->get(route('borrowings.show', $borrowing))->assertOk()->getContent();
        $this->assertStringContainsString($name, $html);
        $this->assertStringContainsString('text-break', $html);
        $this->assertStringContainsString('overflow-wrap:anywhere', $html);
        $this->assertStringContainsString('flex-wrap', $html);

        $odd = $this->inventoryBorrowing($admin);
        $this->attachInventory($odd, 'catatan.txt', 'bukan berkas pratinjau');

        $preview = $this->actingAs($admin)->get(route('borrowings.supporting-document.view', $odd));
        $preview->assertOk();
        $preview->assertHeader('content-type', 'text/plain; charset=UTF-8');
        $this->assertStringContainsString('tidak dapat ditampilkan langsung di browser', $this->body($preview));

        $download = $this->actingAs($admin)->get(route('borrowings.supporting-document.download', $odd));
        $download->assertOk();
        $download->assertHeader('content-type', 'application/octet-stream');
        $this->assertStringContainsString('attachment', (string) $download->headers->get('content-disposition'));
        $this->assertSame('bukan berkas pratinjau', $this->body($download));
    }

    public function test_document_routes_reject_other_records_guests_and_path_escape(): void
    {
        $admin = $this->makeAdmin();
        $owner = $this->makeViewer(['email' => 'doc-owner@undira.ac.id']);
        $other = $this->makeViewer(['email' => 'doc-other@undira.ac.id']);
        $teknisi = $this->makeTeknisi();
        $borrowing = $this->inventoryBorrowing($owner);
        $foreign = $this->inventoryBorrowing($admin);
        $own = "%PDF-1.4\nown-document\n%%EOF\n";
        $secret = "%PDF-1.4\nsecret-marker\n%%EOF\n";
        $this->attachInventory($borrowing, 'milik-sendiri.pdf', $own);
        Storage::disk('local')->put('outside/secret.pdf', $secret);
        Storage::disk('local')->put('borrowings/'.$foreign->id.'/milik-lain.pdf', $secret);

        $this->get(route('borrowings.supporting-document.view', $borrowing))->assertRedirect(route('login'));
        $this->actingAs($other)->get(route('borrowings.supporting-document.view', $borrowing))->assertForbidden();
        $this->actingAs($other)->get(route('borrowings.supporting-document.download', $borrowing))->assertForbidden();
        $this->actingAs($teknisi)->get(route('borrowings.supporting-document.view', $borrowing))->assertForbidden();

        $borrowing->update(['supporting_document_path' => 'outside/secret.pdf']);
        $escaped = $this->actingAs($admin)->get(route('borrowings.supporting-document.view', $borrowing));
        $escaped->assertNotFound();
        $this->assertStringNotContainsString('secret-marker', $escaped->getContent());

        $borrowing->update(['supporting_document_path' => 'borrowings/'.$foreign->id.'/milik-lain.pdf']);
        $swapped = $this->actingAs($admin)->get(route('borrowings.supporting-document.download', $borrowing));
        $swapped->assertNotFound();
        $this->assertStringNotContainsString('secret-marker', $swapped->getContent());

        $borrowing->update(['supporting_document_path' => 'borrowings/'.$borrowing->id.'/berkas.pdf']);
        $this->actingAs($owner)->get(route('borrowings.supporting-document.view', $borrowing))->assertOk();

        $this->get('/storage/borrowings/'.$borrowing->id.'/milik-sendiri.pdf')->assertForbidden();
        $this->actingAs($admin)->get('/storage/borrowings/'.$borrowing->id.'/milik-sendiri.pdf')->assertForbidden();
    }

    private function inventoryBorrowing(User $user): Borrowing
    {
        $item = $this->item();
        $borrowing = Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Peminjam Dokumen',
            'borrower_email' => 'dokumen@undira.ac.id',
            'borrower_phone' => '081234567890',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDay()->toDateString(),
            'status' => BorrowingStatus::MenungguPersetujuan,
            'notes' => 'Ada dokumen',
            'approval_notes' => 'Catatan awal',
            'requested_by' => $user->id,
        ]);
        BorrowingItem::query()->create([
            'borrowing_id' => $borrowing->id,
            'inventory_item_id' => $item->id,
            'qty' => 1,
            'status' => BorrowingItemStatus::MenungguPersetujuan,
        ]);

        return $borrowing->fresh();
    }

    private function attachInventory(Borrowing $borrowing, string $name, string $bytes): void
    {
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $path = 'borrowings/'.$borrowing->id.'/berkas.'.$extension;
        Storage::disk('local')->put($path, $bytes);
        $borrowing->update([
            'supporting_document_path' => $path,
            'supporting_document_original_name' => $name,
            'supporting_document_size' => strlen($bytes),
        ]);
    }

    private function roomBorrowing(User $user): RoomBorrowing
    {
        $place = $this->place();

        return RoomBorrowing::query()->create([
            'room_id' => $place['room']->id,
            'campus_id' => $place['campus']->id,
            'borrower_name' => 'Peminjam Ruangan',
            'borrower_email' => 'ruangan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'activity_type' => RoomActivityType::Rapat->value,
            'purpose' => 'Rapat koordinasi',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $user->id,
            'rejection_reason' => null,
        ]);
    }

    private function attachRoom(RoomBorrowing $borrowing, string $kind, string $name, string $bytes): void
    {
        $columns = [
            'approval' => 'approval_document',
            'layout' => 'layout_document',
            'participants' => 'participant_document',
        ][$kind];
        $path = 'room-borrowings/'.$borrowing->id.'/'.$kind.'.'.pathinfo($name, PATHINFO_EXTENSION);
        Storage::disk('local')->put($path, $bytes);
        $borrowing->update([
            $columns.'_path' => $path,
            $columns.'_original_name' => $name,
            $columns.'_size' => strlen($bytes),
        ]);
    }

    private function vehicleBorrowing(User $user): VehicleBorrowing
    {
        $campus = Campus::query()->create([
            'code' => 'CV'.uniqid(),
            'name' => 'Kampus Kendaraan',
            'slug' => 'kampus-kendaraan-'.uniqid(),
            'is_active' => true,
        ]);

        return VehicleBorrowing::query()->create([
            'campus_id' => $campus->id,
            'borrower_name' => 'Peminjam Kendaraan',
            'borrower_email' => 'kendaraan@undira.ac.id',
            'borrower_phone' => '081234567890',
            'purpose' => 'Kunjungan dinas',
            'service_type' => VehicleServiceType::DropOff,
            'origin' => 'Kampus Cibubur',
            'destination' => 'Kampus Sudirman',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(3),
            'status' => FacilityRequestStatus::MenungguPersetujuan,
            'requested_by' => $user->id,
            'approval_notes' => null,
        ]);
    }

    private function attachVehicle(VehicleBorrowing $borrowing, string $name, string $bytes): void
    {
        $path = 'vehicle-borrowings/'.$borrowing->id.'/berkas.pdf';
        Storage::disk('local')->put($path, $bytes);
        $borrowing->update([
            'supporting_document_path' => $path,
            'supporting_document_original_name' => $name,
            'supporting_document_size' => strlen($bytes),
        ]);
    }

    /**
     * @return array{campus: Campus, room: Room}
     */
    private function place(): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CR'.$suffix,
            'name' => 'Kampus Ruang',
            'slug' => 'kampus-ruang-'.$suffix,
            'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'BR'.$suffix,
            'name' => 'Gedung Ruang',
            'slug' => 'gedung-ruang-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'FR'.$suffix,
            'name' => 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'RR'.$suffix,
            'name' => 'Ruang Rapat',
            'room_type' => 'Kelas',
            'capacity' => 20,
            'is_active' => true,
        ]);

        return compact('campus', 'room');
    }

    private function item(): InventoryItem
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => 'CI'.$suffix, 'name' => 'Kampus Item', 'slug' => 'kampus-item-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'BI'.$suffix, 'name' => 'Gedung Item', 'slug' => 'gedung-item-'.$suffix, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'FI'.$suffix, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'RI'.$suffix, 'name' => 'Ruang Item', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'EQ'.$suffix, 'name' => 'Equipment', 'slug' => 'equipment-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'PC'.$suffix, 'name' => 'PC', 'slug' => 'pc-'.$suffix, 'is_active' => true,
        ]);

        return InventoryItem::query()->create([
            'inventory_number' => 'INV-PREV-'.$suffix,
            'barcode' => 'BC-'.$suffix,
            'name' => 'Laptop Preview',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
            'responsible_person' => 'PIC',
        ]);
    }

    /**
     * @return array{status: string, approved_at: string|null, approval_notes: string|null, rejection_reason: string|null, updated_at: string|null}
     */
    private function snapshot(Borrowing|RoomBorrowing|VehicleBorrowing $record): array
    {
        return [
            'status' => $record->status->value,
            'approved_at' => $record->approved_at?->toISOString(),
            'approval_notes' => $record->approval_notes ?? null,
            'rejection_reason' => $record->rejection_reason ?? null,
            'updated_at' => $record->updated_at?->toISOString(),
        ];
    }

    /**
     * @param  array{status: string, approved_at: string|null, approval_notes: string|null, rejection_reason: string|null, updated_at: string|null}  $before
     */
    private function assertUnchanged(Borrowing|RoomBorrowing|VehicleBorrowing $record, array $before): void
    {
        $record->refresh();
        $this->assertSame($before['status'], $record->status->value);
        $this->assertSame($before['approved_at'], $record->approved_at?->toISOString());
        $this->assertSame($before['approval_notes'], $record->approval_notes ?? null);
        $this->assertSame($before['rejection_reason'], $record->rejection_reason ?? null);
        $this->assertSame($before['updated_at'], $record->updated_at?->toISOString());
    }

    private function jpegBytes(): string
    {
        return "\xFF\xD8\xFF\xD9";
    }

    private function body(TestResponse $response): string
    {
        $content = $response->baseResponse instanceof StreamedResponse
            ? $response->streamedContent()
            : $response->getContent();
        $this->assertIsString($content);

        return $content;
    }

    /**
     * @return list<string>
     */
    private function storedFiles(): array
    {
        $files = Storage::disk('local')->allFiles();
        sort($files);

        return $files;
    }
}
