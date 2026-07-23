<?php

namespace Tests\Feature;

use App\Enums\BorrowingStatus;
use App\Enums\InventoryCondition;
use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\StockOpnameStatus;
use App\Models\AuditLog;
use App\Models\Borrowing;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\Room;
use App\Models\StockOpname;
use App\Notifications\BorrowingCreatedNotification;
use App\Notifications\MaintenanceDueNotification;
use App\Notifications\OverdueBorrowingNotification;
use App\Notifications\StockOpnameStatusNotification;
use App\Notifications\WarrantyExpiringNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class AuditAndNotificationPhase4Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_borrowing_store_writes_audit_and_notifies_operators(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $staff = $this->makeStaff(['email' => 'staff-phase4@undira.ac.id']);
        $item = $this->makeInventoryItem();

        $this->actingAs($admin)
            ->post(route('borrowings.store'), [
                'inventory_item_id' => $item->id,
                'borrower_name' => 'Budi',
                'borrow_date' => now()->toDateString(),
                'expected_return_date' => now()->addDays(3)->toDateString(),
            ])
            ->assertRedirect(route('borrowings.index'));

        $this->assertTrue(AuditLog::query()->where('description', 'BORROWING_CREATED')->exists());
        $this->assertSame(InventoryStatus::Dipinjam, $item->fresh()->status);

        Notification::assertSentTo($admin, BorrowingCreatedNotification::class);
        Notification::assertSentTo($staff, BorrowingCreatedNotification::class);
    }

    public function test_maintenance_store_writes_audit(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeInventoryItem();

        $response = $this->actingAs($admin)
            ->post(route('maintenances.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => 'repair',
                'priority' => 'medium',
                'service_date' => now()->toDateString(),
                'next_schedule' => now()->addDays(30)->toDateString(),
                'description' => 'Servis rutin',
                'status' => 'waiting',
            ]);

        $maintenance = Maintenance::query()->latest('id')->first();
        $response->assertRedirect(route('maintenances.show', $maintenance));

        $this->assertTrue(AuditLog::query()->where('description', 'MAINTENANCE_CREATED')->exists());
        $this->assertSame(InventoryStatus::Maintenance, $item->fresh()->status);
    }

    public function test_category_store_writes_audit(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('categories.store'), [
                'code' => 'AUD',
                'name' => 'Audit Cat',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertTrue(AuditLog::query()->where('description', 'CATEGORY_CREATED')->exists());
    }

    public function test_stock_opname_start_notifies_operators(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $opname = StockOpname::query()->create([
            'title' => 'Opname Notif',
            'campus_id' => Campus::query()->create([
                'code' => 'NOP', 'name' => 'Kampus Notif', 'slug' => 'kampus-notif', 'is_active' => true,
            ])->id,
            'status' => StockOpnameStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('stock-opnames.start', $opname))
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($admin, StockOpnameStatusNotification::class);
    }

    public function test_notification_service_targets_admin_and_staff_not_viewer(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $staff = $this->makeStaff(['email' => 'staff-alert@undira.ac.id']);
        $viewer = $this->makeViewer(['email' => 'viewer-alert@undira.ac.id']);
        $item = $this->makeInventoryItem([
            'warranty_expiry' => now()->addDays(10)->toDateString(),
        ]);

        $count = app(NotificationService::class)->dispatchWarrantyExpiringAlerts(30);

        $this->assertGreaterThan(0, $count);
        Notification::assertSentTo($admin, WarrantyExpiringNotification::class);
        Notification::assertSentTo($staff, WarrantyExpiringNotification::class);
        Notification::assertNotSentTo($viewer, WarrantyExpiringNotification::class);
        $this->assertSame($item->id, $item->id);
    }

    public function test_dispatch_alerts_command_marks_overdue_borrowings(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        $item = $this->makeInventoryItem(['status' => InventoryStatus::Dipinjam]);

        Borrowing::query()->create([
            'inventory_item_id' => $item->id,
            'borrower_name' => 'Andi',
            'borrow_date' => now()->subDays(5)->toDateString(),
            'expected_return_date' => now()->subDay()->toDateString(),
            'status' => BorrowingStatus::Dipinjam,
            'approved_by' => $admin->id,
        ]);

        Artisan::call('notifications:dispatch-alerts');

        $this->assertSame(BorrowingStatus::Terlambat, Borrowing::query()->first()->status);
        Notification::assertSentTo($admin, OverdueBorrowingNotification::class);
    }

    public function test_maintenance_due_alert_is_deduplicated_within_a_day(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeInventoryItem();

        Maintenance::query()->create([
            'inventory_item_id' => $item->id,
            'service_date' => now()->subDay()->toDateString(),
            'next_schedule' => now()->addDays(2)->toDateString(),
            'status' => MaintenanceStatus::Waiting,
        ]);

        $service = app(NotificationService::class);
        $first = $service->dispatchMaintenanceDueAlerts();
        $second = $service->dispatchMaintenanceDueAlerts();

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
        $this->assertSame(
            1,
            $admin->notifications()->where('type', MaintenanceDueNotification::class)->count()
        );
    }

    public function test_schedule_registers_notification_dispatch_command(): void
    {
        $events = collect(app('Illuminate\Console\Scheduling\Schedule')->events());

        $this->assertTrue(
            $events->contains(fn ($event) => str_contains($event->command ?? $event->description ?? '', 'notifications:dispatch-alerts')
                || str_contains((string) ($event->command ?? ''), 'notifications:dispatch-alerts'))
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeInventoryItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->first() ?? Campus::query()->create([
            'code' => 'P4', 'name' => 'Kampus P4', 'slug' => 'kampus-p4', 'is_active' => true,
        ]);
        $building = Building::query()->first() ?? Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'B', 'name' => 'Gedung B', 'slug' => 'gedung-b-p4', 'is_active' => true,
        ]);
        $floor = Floor::query()->first() ?? Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->first() ?? Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'room_type' => 'Lab', 'capacity' => 10, 'is_active' => true,
        ]);
        $category = Category::query()->first() ?? Category::query()->create([
            'code' => 'P4C', 'name' => 'Kategori P4', 'slug' => 'kategori-p4', 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-P4-'.uniqid(),
            'barcode' => 'BC-P4-'.uniqid(),
            'name' => 'Barang Phase4',
            'category_id' => $category->id,
            'room_id' => $room->id,
            'condition' => InventoryCondition::Bagus,
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'unit',
        ], $overrides));
    }
}
