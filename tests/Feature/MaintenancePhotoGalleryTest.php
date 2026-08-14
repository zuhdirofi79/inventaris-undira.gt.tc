<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenancePhoto;
use App\Models\Room;
use App\Models\SubCategory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenancePhotoGalleryTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_show_gallery_renders_host_relative_url_for_single_photo(): void
    {
        Storage::fake('public');

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = $this->makeWo($item);

        $this->actingAs($admin)
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [UploadedFile::fake()->image('wo.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $wo->refresh()->load('photos');
        $this->assertSame(1, $wo->photos->count());

        $photo = $wo->photos->first();
        Storage::disk('public')->assertExists($photo->path);
        $this->assertStringStartsWith('/storage/', (string) $photo->url);
        $this->assertStringNotContainsString('http://', (string) $photo->url);
        $this->assertStringNotContainsString('\\', (string) $photo->path);

        $html = $this->actingAs($admin)
            ->get(route('maintenances.show', $wo))
            ->assertOk()
            ->assertSee('Maks. 1 foto', false)
            ->assertDontSee('Saat Pengerjaan', false)
            ->getContent();

        $this->assertStringContainsString('src="'.$photo->url.'"', $html);
        $this->assertStringNotContainsString('http://localhost/storage/', $html);
        $this->assertStringNotContainsString('Tambah Foto', $html);
    }

    public function test_second_photo_upload_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $wo = $this->makeWo($this->makeItem());

        $this->actingAs($admin)
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [UploadedFile::fake()->image('one.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($admin)
            ->from(route('maintenances.show', $wo))
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'after',
                'photos' => [UploadedFile::fake()->image('two.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('photos');

        $this->assertSame(1, $wo->photos()->count());
    }

    public function test_url_accessor_strips_absolute_app_url_like_inventory_photos(): void
    {
        $path = 'maintenance/url-check/'.uniqid('p_', true).'.jpg';
        Storage::disk('public')->put($path, file_get_contents(public_path('images/favicon-32.png')));

        try {
            config(['filesystems.disks.public.url' => 'http://localhost/storage']);
            Storage::forgetDisk('public');

            $photo = new MaintenancePhoto([
                'maintenance_id' => 1,
                'type' => 'before',
                'path' => $path,
            ]);

            $url = $photo->url;
            $this->assertNotNull($url);
            $this->assertStringStartsWith('/storage/', $url);
            $this->assertStringNotContainsString('http://', $url);
            $this->assertStringNotContainsString('localhost', $url);
        } finally {
            Storage::forgetDisk('public');
            config(['filesystems.disks.public.url' => '/storage']);
            Storage::disk('public')->delete($path);
        }
    }

    public function test_url_accessor_normalizes_legacy_path_prefixes(): void
    {
        $path = 'maintenance/legacy/'.uniqid('p_', true).'.jpg';
        Storage::disk('public')->put($path, file_get_contents(public_path('images/favicon-32.png')));

        try {
            $photo = new MaintenancePhoto([
                'maintenance_id' => 1,
                'type' => 'before',
                'path' => 'storage/'.$path,
            ]);

            $this->assertSame($path, $photo->storagePath());
            $this->assertStringStartsWith('/storage/maintenance/', (string) $photo->url);
            $this->assertStringNotContainsString('/storage/storage/', (string) $photo->url);
        } finally {
            Storage::disk('public')->delete($path);
        }
    }

    public function test_delete_photo_removes_file_and_allows_new_upload(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $wo = $this->makeWo($this->makeItem());

        $this->actingAs($admin)
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [UploadedFile::fake()->image('old.jpg')],
            ])
            ->assertRedirect();

        $photo = $wo->fresh()->photos()->first();
        $this->assertNotNull($photo);
        $path = $photo->path;

        $this->actingAs($admin)
            ->delete(route('maintenances.photos.destroy', [$wo, $photo]))
            ->assertRedirect()
            ->assertSessionHas('success');

        Storage::disk('public')->assertMissing($path);
        $this->assertSame(0, $wo->photos()->count());

        $this->actingAs($admin)
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [UploadedFile::fake()->image('new.jpg')],
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(1, $wo->photos()->count());
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $wo = $this->makeWo($this->makeItem());

        $this->actingAs($admin)
            ->from(route('maintenances.show', $wo))
            ->post(route('maintenances.photos.store', $wo), [
                'type' => 'before',
                'photos' => [UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('photos.0');

        $this->assertSame(0, $wo->photos()->count());
    }

    protected function makeWo(InventoryItem $item): Maintenance
    {
        return Maintenance::query()->create([
            'maintenance_number' => 'MNT-PHOTO-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;
        $s = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'MC'.$s, 'name' => 'Kampus M'.$s, 'slug' => 'kampus-m-'.$s, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'MB'.$s, 'name' => 'Gedung M'.$s, 'slug' => 'gedung-m-'.$s, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'MF'.$s, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'MR'.$s, 'name' => 'Ruang M'.$s, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'MX'.$s, 'name' => 'Elektronik', 'slug' => 'elektronik-m-'.$s, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'MS'.$s, 'name' => 'Laptop', 'slug' => 'laptop-m-'.$s, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'name' => 'Aset M'.$s,
            'inventory_number' => 'MNT-AST-'.$s,
            'barcode' => 'MNT-AST-'.$s,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ], $overrides));
    }
}
