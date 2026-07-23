<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\MaintenanceType;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\MaintenanceSchedule;
use App\Models\Room;
use App\Models\SubCategory;
use App\Notifications\MaintenanceScheduleReminderNotification;
use App\Services\MaintenanceScheduleService;
use App\Services\NotificationService;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenanceSchedulePhaseBTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_can_create_preventive_schedule(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();

        $this->actingAs($admin)
            ->post(route('maintenance-schedules.store'), [
                'inventory_item_id' => $item->id,
                'maintenance_type' => MaintenanceType::Preventive->value,
                'interval_value' => 3,
                'interval_unit' => MaintenanceIntervalUnit::Months->value,
                'next_maintenance_date' => now()->addMonths(3)->toDateString(),
                'remind_before_days' => 14,
                'responsible_pic' => 'PIC Lab',
                'is_active' => 1,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('maintenance_schedules', [
            'inventory_item_id' => $item->id,
            'interval_value' => 3,
            'interval_unit' => 'months',
            'remind_before_days' => 14,
            'responsible_pic' => 'PIC Lab',
        ]);
    }

    public function test_summary_detects_due_today_week_and_overdue(): void
    {
        $item = $this->makeItem();

        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 6,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);

        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Inspection,
            'interval_value' => 1,
            'interval_unit' => MaintenanceIntervalUnit::Years,
            'next_maintenance_date' => now()->subDays(2)->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);

        $summary = app(MaintenanceScheduleService::class)->summaryCounts();

        $this->assertSame(1, $summary['due_today']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertGreaterThanOrEqual(1, $summary['due_this_week']);
        $this->assertSame(2, $summary['active']);
    }

    public function test_advance_rolls_next_date_by_interval(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $schedule = MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance-schedules.advance', $schedule))
            ->assertRedirect();

        $schedule->refresh();
        $this->assertTrue(
            $schedule->next_maintenance_date->equalTo(now()->startOfDay()->addMonthsNoOverflow(3))
        );
        $this->assertNotNull($schedule->last_completed_at);
    }

    public function test_schedule_reminder_dispatched_and_deduped(): void
    {
        $admin = $this->makeAdmin();
        $item = $this->makeItem();

        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 1,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addDays(3)->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ]);

        $service = app(NotificationService::class);
        $first = $service->dispatchMaintenanceScheduleAlerts();
        $second = $service->dispatchMaintenanceScheduleAlerts();

        $this->assertGreaterThan(0, $first);
        $this->assertSame(0, $second);
        $this->assertSame(
            1,
            $admin->notifications()->where('type', MaintenanceScheduleReminderNotification::class)->count()
        );
    }

    public function test_index_shows_summary_cards(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index'))
            ->assertOk()
            ->assertSee('Jatuh Tempo Hari Ini')
            ->assertSee('Terlambat (Overdue)')
            ->assertSee('Jadwal Preventif');
    }

    public function test_inventory_show_lists_schedules(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        MaintenanceSchedule::query()->create([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Cleaning,
            'interval_value' => 6,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addMonths(1)->toDateString(),
            'remind_before_days' => 30,
            'responsible_pic' => 'Teknik',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('inventory.show', $item))
            ->assertOk()
            ->assertSee('Jadwal Preventif')
            ->assertSee('Teknik');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeItem(array $overrides = []): InventoryItem
    {
        $campus = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Cibubur', 'slug' => 'cibubur-b'.uniqid(), 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'A', 'name' => 'Gedung A', 'slug' => 'a-b'.uniqid(), 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'L1', 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'R1', 'name' => 'Ruang 1', 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'AC', 'name' => 'AC', 'slug' => 'ac-b'.uniqid(), 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'SPL', 'name' => 'Split', 'slug' => 'spl-b'.uniqid(), 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'inventory_number' => 'INV-SCH-'.uniqid(),
            'name' => 'AC Split',
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'room_id' => $room->id,
            'condition' => 'bagus',
            'status' => InventoryStatus::Aktif,
            'quantity' => 1,
            'unit' => 'Unit',
            'responsible_person' => 'PIC Lab',
        ], $overrides));
    }
}
