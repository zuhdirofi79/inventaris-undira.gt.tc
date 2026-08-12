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

    public function test_show_gallery_renders_host_relative_urls_for_all_stages(): void
    {
        Storage::fake('public');

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = Maintenance::query()->create([
            'maintenance_number' => 'MNT-PHOTO-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);

        foreach (['before', 'during', 'after'] as $type) {
            $this->actingAs($admin)
                ->post(route('maintenances.photos.store', $wo), [
                    'type' => $type,
                    'photos' => [UploadedFile::fake()->image("{$type}.jpg")],
                ])
                ->assertRedirect();
        }

        $wo->refresh()->load('photos');
        $this->assertSame(3, $wo->photos->count());

        foreach ($wo->photos as $photo) {
            Storage::disk('public')->assertExists($photo->path);
            $this->assertStringStartsWith('/storage/', (string) $photo->url);
            $this->assertStringNotContainsString('http://', (string) $photo->url);
            $this->assertStringNotContainsString('https://', (string) $photo->url);
            $this->assertStringNotContainsString('\\', (string) $photo->path);
        }

        $html = $this->actingAs($admin)
            ->get(route('maintenances.show', $wo))
            ->assertOk()
            ->assertSee('Sebelum')
            ->assertSee('Saat Pengerjaan')
            ->assertSee('Sesudah')
            ->getContent();

        foreach ($wo->photos as $photo) {
            $this->assertStringContainsString('src="'.$photo->url.'"', $html);
        }

        $this->assertStringNotContainsString('http://localhost/storage/', $html);
        $this->assertStringNotContainsString("asset('storage/", $html);
    }

    public function test_url_accessor_strips_absolute_app_url_like_inventory_photos(): void
    {
        // Use the real public disk (not Storage::fake) so FilesystemAdapter honors disk URL config.
        $path = 'maintenance/url-check/'.uniqid('p_', true).'.jpg';
        Storage::disk('public')->put($path, file_get_contents(public_path('images/favicon-32.png')));

        try {
            config(['filesystems.disks.public.url' => 'http://localhost/storage']);
            // Rebuild disk so the new URL config is applied.
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
            $this->assertStringNotContainsString('https://', $url);
            $this->assertStringNotContainsString('localhost', $url);
        } finally {
            Storage::forgetDisk('public');
            config(['filesystems.disks.public.url' => '/storage']);
            Storage::disk('public')->delete($path);
        }
    }

    public function test_delete_photo_removes_file_and_record_for_each_stage(): void
    {
        Storage::fake('public');
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = Maintenance::query()->create([
            'maintenance_number' => 'MNT-DEL-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);

        foreach (['before', 'during', 'after'] as $type) {
            $this->actingAs($admin)
                ->post(route('maintenances.photos.store', $wo), [
                    'type' => $type,
                    'photos' => [UploadedFile::fake()->image("{$type}-del.jpg")],
                ])
                ->assertRedirect();
        }

        $wo->refresh();
        $this->assertSame(3, $wo->photos()->count());

        foreach ($wo->photos()->get() as $photo) {
            $path = $photo->path;
            $this->actingAs($admin)
                ->delete(route('maintenances.photos.destroy', [$wo, $photo]))
                ->assertRedirect();
            Storage::disk('public')->assertMissing($path);
        }

        $this->assertSame(0, $wo->photos()->count());
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
