<?php

namespace Tests\Feature;

use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\SubCategory;
use App\Notifications\MaintenanceApprovalNotification;
use App\Support\NotificationPresenter;
use Illuminate\Notifications\DatabaseNotification;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class NotificationDropdownUiTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_unread_endpoint_returns_structured_dropdown_payload(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem([
            'name' => 'Lemari Arsip',
            'inventory_number' => 'CB-001',
            'barcode' => 'CB-001',
        ]);
        $maintenance = Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_number' => 'MNT-20260811-0001',
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'description' => 'Perbaikan',
            'status' => MaintenanceStatus::Waiting,
            'created_by' => $admin->id,
        ]);

        $admin->notify(new MaintenanceApprovalNotification($maintenance->fresh('inventoryItem'), 'submitted'));

        $response = $this->actingAs($admin)->getJson(route('notifications.unread'));

        $response->assertOk()->assertJsonPath('count', 1);

        $payload = $response->json('notifications.0');
        $this->assertNotEmpty($payload['id']);
        $this->assertTrue($payload['unread']);
        $this->assertSame('Approval Maintenance', $payload['title']);
        $this->assertStringContainsString('MNT-20260811-0001', (string) $payload['subject']);
        $this->assertStringContainsString('Lemari Arsip', (string) $payload['subtitle']);
        $this->assertSame('Menunggu persetujuan admin', $payload['summary']);
        $this->assertSame('fas fa-wrench', $payload['icon']);
        $this->assertNotEmpty($payload['time_ago']);
        $this->assertStringContainsString('/maintenances/', (string) $payload['url']);
    }

    public function test_presenter_parses_legacy_maintenance_message_without_structured_fields(): void
    {
        $notification = new DatabaseNotification([
            'id' => '00000000-0000-0000-0000-000000000001',
            'type' => MaintenanceApprovalNotification::class,
            'notifiable_type' => 'App\\Models\\User',
            'notifiable_id' => 1,
            'data' => [
                'type' => 'maintenance_approval_submitted',
                'title' => 'Approval Maintenance',
                'message' => 'WO MNT-20260811-0001 untuk Lemari Arsip (CB-001) menunggu approval admin.',
                'event' => 'submitted',
                'url' => '/maintenances/1',
            ],
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $presented = NotificationPresenter::forDropdown($notification);

        $this->assertSame('WO MNT-20260811-0001', $presented['subject']);
        $this->assertStringContainsString('Lemari Arsip', (string) $presented['subtitle']);
        $this->assertSame('Menunggu persetujuan admin', $presented['summary']);
    }

    public function test_layout_contains_redesigned_notification_dropdown_shell(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('undira-notif-menu', false)
            ->assertSee('Tandai semua dibaca', false)
            ->assertSee('Lihat semua notifikasi', false)
            ->assertSee('aria-label="Notifikasi"', false);
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;
        $s = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'NC'.$s, 'name' => 'Kampus N'.$s, 'slug' => 'kampus-n-'.$s, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'NB'.$s, 'name' => 'Gedung N'.$s, 'slug' => 'gedung-n-'.$s, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'NF'.$s, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'NR'.$s, 'name' => 'Ruang N'.$s, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'NX'.$s, 'name' => 'Elektronik', 'slug' => 'elektronik-n-'.$s, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'NS'.$s, 'name' => 'Laptop', 'slug' => 'laptop-n-'.$s, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'name' => 'Aset N'.$s,
            'inventory_number' => 'NOTIF-AST-'.$s,
            'barcode' => 'NOTIF-AST-'.$s,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ], $overrides));
    }
}
