<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryAttachment;
use App\Models\InventoryItem;
use App\Models\InventoryPhoto;
use App\Models\Maintenance;
use App\Models\Room;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class InventoryAdvancedPhase7Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_show_page_renders_media_history_and_status_badges(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Budi Pinjam',
            'borrow_date' => now()->toDateString(),
            'expected_return_date' => now()->addDays(2)->toDateString(),
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $admin->id,
        ]);

        Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);

        $html = $this->actingAs($admin)
            ->get(route('inventory.show', $item))
            ->assertOk()
            ->assertSee('Galeri Foto')
            ->assertSee('Lampiran')
            ->assertSee('Riwayat Mutasi')
            ->assertSee('Riwayat Peminjaman')
            ->assertSee('Maintenance History')
            ->assertSee('Budi Pinjam')
            ->assertSee('Cetak Label')
            ->assertSee('name="photos[]"', false)
            ->getContent();

        $this->assertStringContainsString('inventory-status-badge', $html);
        $this->assertStringContainsString('inventory-condition-badge', $html);
    }

    public function test_photo_upload_accepts_multiple_files_and_sets_primary(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('inventory.photos.store', $item), [
                'photos' => [
                    UploadedFile::fake()->image('satu.jpg'),
                    UploadedFile::fake()->image('dua.jpg'),
                ],
                'is_primary' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(2, $item->photos()->count());
        $this->assertTrue($item->photos()->where('is_primary', true)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'description' => 'INVENTORY_PHOTOS_UPLOADED',
        ]);
    }

    public function test_set_primary_and_destroy_photo(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $first = InventoryPhoto::query()->create([
            'inventory_item_id' => $item->id,
            'path' => 'inventory/photos/'.$item->id.'/a.jpg',
            'is_primary' => true,
        ]);
        $second = InventoryPhoto::query()->create([
            'inventory_item_id' => $item->id,
            'path' => 'inventory/photos/'.$item->id.'/b.jpg',
            'is_primary' => false,
        ]);

        Storage::disk('public')->put($first->path, 'x');
        Storage::disk('public')->put($second->path, 'y');

        $this->actingAs($admin)
            ->post(route('inventory.photos.primary', [$item, $second]))
            ->assertRedirect();

        $this->assertFalse($first->fresh()->is_primary);
        $this->assertTrue($second->fresh()->is_primary);

        $this->actingAs($admin)
            ->delete(route('inventory.photos.destroy', [$item, $second]))
            ->assertRedirect();

        $this->assertDatabaseMissing('inventory_photos', ['id' => $second->id]);
        $this->assertTrue($first->fresh()->is_primary);
    }

    public function test_attachment_upload_and_delete(): void
    {
        Storage::fake('public');

        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $file = UploadedFile::fake()->create('invoice.pdf', 120, 'application/pdf');

        $this->actingAs($admin)
            ->post(route('inventory.attachments.store', $item), [
                'file' => $file,
                'type' => 'invoice',
            ])
            ->assertRedirect();

        $attachment = InventoryAttachment::query()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('invoice', $attachment->type);
        $this->assertSame('invoice.pdf', $attachment->original_name);
        $this->assertDatabaseHas('audit_logs', [
            'description' => 'INVENTORY_ATTACHMENT_UPLOADED',
        ]);

        $this->actingAs($admin)
            ->delete(route('inventory.attachments.destroy', [$item, $attachment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('inventory_attachments', ['id' => $attachment->id]);
    }

    public function test_viewer_cannot_upload_photos_or_attachments(): void
    {
        Storage::fake('public');

        $viewer = $this->makeViewer();
        $item = $this->makeItem();

        $this->actingAs($viewer)
            ->post(route('inventory.photos.store', $item), [
                'photos' => [UploadedFile::fake()->image('x.jpg')],
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('inventory.attachments.store', $item), [
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
                'type' => 'document',
            ])
            ->assertForbidden();
    }

    public function test_label_print_page_is_available(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->get(route('inventory.label.print', $item))
            ->assertOk()
            ->assertSee($item->inventory_number);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->first() ?? Campus::query()->create([
            'code' => 'P7', 'name' => 'Kampus P7', 'slug' => 'kampus-p7', 'is_active' => true,
        ]);
        $building = Building::query()->first() ?? Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B7', 'name' => 'Gedung P7', 'slug' => 'gedung-p7', 'is_active' => true,
        ]);
        $floor = Floor::query()->first() ?? Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->first() ?? Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R7', 'name' => 'Ruang P7', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->first() ?? Category::query()->create([
            'code' => 'P7C', 'name' => 'Kategori P7', 'slug' => 'kategori-p7', 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-P7-'.uniqid(),
            'barcode' => 'BC-P7-'.uniqid(),
            'name' => 'Barang Phase7',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ], $overrides));
    }
}
