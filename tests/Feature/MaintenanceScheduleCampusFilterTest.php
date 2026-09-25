<?php

namespace Tests\Feature;

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
use Illuminate\Support\Facades\Notification;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenanceScheduleCampusFilterTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_index_shows_campus_filter_and_column(): void
    {
        $admin = $this->makeAdmin();
        $cibubur = Campus::query()->create([
            'code' => 'CBB', 'name' => 'Undira Cibubur', 'slug' => 'undira-cibubur-'.uniqid(), 'is_active' => true,
        ]);
        Campus::query()->create([
            'code' => 'KRG', 'name' => 'Undira Kranggan', 'slug' => 'undira-kranggan-'.uniqid(), 'is_active' => true,
        ]);
        Campus::query()->create([
            'code' => 'OFF', 'name' => 'Kampus Nonaktif', 'slug' => 'kampus-off-'.uniqid(), 'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index'))
            ->assertOk()
            ->assertSee('Lokasi Kampus', false)
            ->assertSee('Semua Kampus', false)
            ->assertSee('Undira Cibubur', false)
            ->assertSee('Undira Kranggan', false)
            ->assertDontSee('Kampus Nonaktif', false)
            ->assertSee('name="campus_id"', false)
            ->assertSee((string) $cibubur->id, false)
            ->assertSee('Jenis', false)
            ->assertSee('Status', false);
    }

    public function test_campus_filter_returns_only_matching_schedules(): void
    {
        $admin = $this->makeAdmin();
        [$cibubur, $itemCibubur] = $this->makeCampusWithItem('Undira Cibubur', 'CBB', 'PM-CBB-1');
        [, $itemKranggan] = $this->makeCampusWithItem('Undira Kranggan', 'KRG', 'PM-KRG-1');

        $this->makeSchedule($itemCibubur, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'responsible_pic' => 'PIC Cibubur',
        ]);
        $this->makeSchedule($itemKranggan, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'responsible_pic' => 'PIC Kranggan',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index', ['campus_id' => $cibubur->id]))
            ->assertOk()
            ->assertSee('PM-CBB-1', false)
            ->assertSee('Undira Cibubur', false)
            ->assertDontSee('PM-KRG-1', false)
            ->assertDontSee('PIC Kranggan', false);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index'))
            ->assertOk()
            ->assertSee('PM-CBB-1', false)
            ->assertSee('PM-KRG-1', false);
    }

    public function test_campus_type_status_and_search_combine(): void
    {
        $admin = $this->makeAdmin();
        [$cibubur, $itemA] = $this->makeCampusWithItem('Undira Cibubur', 'CB1', 'AST-A');
        [, $itemB] = $this->makeCampusWithItem('Undira Kranggan', 'KR1', 'AST-B');

        $this->makeSchedule($itemA, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'responsible_pic' => 'Andi',
            'next_maintenance_date' => now()->addDays(10)->toDateString(),
        ]);
        $this->makeSchedule($itemA, [
            'maintenance_type' => MaintenanceType::Repair,
            'responsible_pic' => 'Budi',
            'next_maintenance_date' => now()->addDays(12)->toDateString(),
        ]);
        $this->makeSchedule($itemB, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'responsible_pic' => 'Citra',
            'next_maintenance_date' => now()->addDays(14)->toDateString(),
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index', [
                'campus_id' => $cibubur->id,
                'type' => MaintenanceType::Cleaning->value,
                'status' => 'active',
                'q' => 'Andi',
            ]))
            ->assertOk()
            ->assertSee('AST-A', false)
            ->assertSee('Andi', false)
            ->assertDontSee('AST-B', false)
            ->assertDontSee('Budi', false)
            ->assertDontSee('Citra', false);
    }

    public function test_summary_cards_respect_campus_filter(): void
    {
        $admin = $this->makeAdmin();
        [$cibubur, $itemCibubur] = $this->makeCampusWithItem('Undira Cibubur', 'CB2', 'SUM-CBB');
        [, $itemKranggan] = $this->makeCampusWithItem('Undira Kranggan', 'KR2', 'SUM-KRG');

        $this->makeSchedule($itemCibubur, [
            'next_maintenance_date' => now()->toDateString(),
        ]);
        $this->makeSchedule($itemKranggan, [
            'next_maintenance_date' => now()->subDays(3)->toDateString(),
        ]);
        $this->makeSchedule($itemKranggan, [
            'maintenance_type' => MaintenanceType::Inspection,
            'next_maintenance_date' => now()->addDays(5)->toDateString(),
        ]);

        $summaryAll = app(MaintenanceScheduleService::class)->summaryCounts();
        $this->assertSame(1, $summaryAll['due_today']);
        $this->assertSame(1, $summaryAll['overdue']);
        $this->assertSame(3, $summaryAll['active']);

        $summaryCibubur = app(MaintenanceScheduleService::class)->summaryCounts([
            'campus_id' => $cibubur->id,
        ]);
        $this->assertSame(1, $summaryCibubur['due_today']);
        $this->assertSame(0, $summaryCibubur['overdue']);
        $this->assertSame(1, $summaryCibubur['active']);

        $html = $this->actingAs($admin)
            ->get(route('maintenance-schedules.index', ['campus_id' => $cibubur->id]))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression('/Jatuh Tempo Hari Ini[\s\S]*?>1</', $html);
        $this->assertMatchesRegularExpression('/Terlambat \(Overdue\)[\s\S]*?>0</', $html);
    }

    public function test_campus_counts_follow_active_filters(): void
    {
        [$cibubur, $itemCibubur] = $this->makeCampusWithItem('Undira Cibubur', 'CB3', 'CNT-CBB');
        [$kranggan, $itemKranggan] = $this->makeCampusWithItem('Undira Kranggan', 'KR3', 'CNT-KRG');

        $this->makeSchedule($itemCibubur, ['maintenance_type' => MaintenanceType::Cleaning]);
        $this->makeSchedule($itemCibubur, [
            'maintenance_type' => MaintenanceType::Repair,
            'is_active' => false,
            'next_maintenance_date' => now()->addMonth()->toDateString(),
        ]);
        $this->makeSchedule($itemKranggan, ['maintenance_type' => MaintenanceType::Cleaning]);
        $this->makeSchedule($itemKranggan, ['maintenance_type' => MaintenanceType::Cleaning]);

        $service = app(MaintenanceScheduleService::class);
        $counts = collect($service->countsByCampus(['status' => 'active']))
            ->keyBy('id');

        $this->assertSame(1, $counts[$cibubur->id]['total'] ?? 0);
        $this->assertSame(2, $counts[$kranggan->id]['total'] ?? 0);

        $filtered = collect($service->countsByCampus([
            'campus_id' => $kranggan->id,
            'type' => MaintenanceType::Cleaning->value,
            'status' => 'active',
        ]))->keyBy('id');

        $this->assertSame(2, $filtered[$kranggan->id]['total'] ?? 0);
        $this->assertArrayNotHasKey($cibubur->id, $filtered->all());
    }

    public function test_show_page_lists_full_location_hierarchy(): void
    {
        $admin = $this->makeAdmin();
        [, $item] = $this->makeCampusWithItem('Undira Kranggan', 'KRGX', 'DET-KRG-1', [
            'building' => 'Gedung A',
            'floor' => 'Lantai 1',
            'room' => 'Kelas A101',
            'name' => 'Meja Kuliah',
        ]);
        $schedule = $this->makeSchedule($item, [
            'maintenance_type' => MaintenanceType::Cleaning,
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.show', $schedule))
            ->assertOk()
            ->assertSee('Undira Kranggan', false)
            ->assertSee('Gedung A', false)
            ->assertSee('Lantai 1', false)
            ->assertSee('Kelas A101', false)
            ->assertSee('DET-KRG-1', false)
            ->assertSee('Meja Kuliah', false)
            ->assertSee('Buat Work Order', false)
            ->assertSee('maintenance_type='.MaintenanceType::Cleaning->value, false);
    }

    public function test_schedule_reminder_notification_includes_campus(): void
    {
        Notification::fake();

        $admin = $this->makeAdmin();
        [, $item] = $this->makeCampusWithItem('Undira Kranggan', 'KRGN', 'NTF-KRG');
        $schedule = $this->makeSchedule($item, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'next_maintenance_date' => now()->addDays(3)->toDateString(),
            'remind_before_days' => 7,
        ]);

        $admin->notify(new MaintenanceScheduleReminderNotification($schedule->fresh([
            'inventoryItem.room.floor.building.campus',
        ])));

        Notification::assertSentTo($admin, MaintenanceScheduleReminderNotification::class, function ($notification, $channels) use ($admin) {
            $data = $notification->toArray($admin);

            return ($data['campus'] ?? null) === 'Undira Kranggan'
                && str_contains((string) $data['message'], 'Undira Kranggan')
                && str_contains((string) ($data['summary'] ?? ''), 'Undira Kranggan');
        });
    }

    public function test_empty_campus_filter_shows_empty_state(): void
    {
        $admin = $this->makeAdmin();
        $emptyCampus = Campus::query()->create([
            'code' => 'EMP', 'name' => 'Kampus Kosong', 'slug' => 'kampus-kosong-'.uniqid(), 'is_active' => true,
        ]);
        [, $item] = $this->makeCampusWithItem('Undira Cibubur', 'CBE', 'EMP-OTHER');
        $this->makeSchedule($item);

        $this->actingAs($admin)
            ->get(route('maintenance-schedules.index', ['campus_id' => $emptyCampus->id]))
            ->assertOk()
            ->assertSee('Tidak ada jadwal preventif untuk filter ini', false)
            ->assertDontSee('EMP-OTHER', false);
    }

    public function test_index_ignores_poisoned_shared_campus_dropdown_cache(): void
    {
        $admin = $this->makeAdmin();
        [$campus, $item] = $this->makeCampusWithItem('Undira Tanjung Duren', 'TJD', 'PM-TJD-1');
        $this->makeSchedule($item, [
            'maintenance_type' => MaintenanceType::Cleaning,
            'responsible_pic' => 'PIC Tanjung Duren',
        ]);

        \Illuminate\Support\Facades\Cache::put(
            \App\Support\InventarisCache::key('campuses.active.dropdown'),
            collect(['Undira Tanjung Duren', 'Undira Cibubur', 'Undira Greenville']),
            120
        );

        $html = $this->actingAs($admin)
            ->get(route('maintenance-schedules.index', ['campus_id' => $campus->id]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('PM-TJD-1', $html);
        $this->assertStringContainsString('Undira Tanjung Duren', $html);
        $this->assertMatchesRegularExpression('/<option value="'.$campus->id.'"/', $html);
        $this->assertStringNotContainsString('Attempt to read property', $html);

        \Illuminate\Support\Facades\Cache::forget(
            \App\Support\InventarisCache::key('campuses.active.dropdown')
        );
    }

    /**
     * @param  array{building?: string, floor?: string, room?: string, name?: string}  $labels
     * @return array{0: Campus, 1: InventoryItem}
     */
    protected function makeCampusWithItem(string $campusName, string $code, string $inventoryNumber, array $labels = []): array
    {
        $suffix = uniqid();
        $campus = Campus::query()->create([
            'code' => $code, 'name' => $campusName, 'slug' => strtolower($code).'-'.$suffix, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id,
            'code' => 'B'.$code,
            'name' => $labels['building'] ?? ('Gedung '.$code),
            'slug' => 'gedung-'.$code.'-'.$suffix,
            'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id,
            'code' => 'L1'.$code,
            'name' => $labels['floor'] ?? 'Lantai 1',
            'floor_number' => 1,
            'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id,
            'code' => 'R'.$code,
            'name' => $labels['room'] ?? ('Ruang '.$code),
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'C'.$code, 'name' => 'Furniture', 'slug' => 'furniture-'.$code.'-'.$suffix, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'S'.$code, 'name' => 'Meja', 'slug' => 'meja-'.$code.'-'.$suffix, 'is_active' => true,
        ]);

        $item = InventoryItem::query()->create([
            'inventory_number' => $inventoryNumber,
            'barcode' => $inventoryNumber,
            'name' => $labels['name'] ?? ('Barang '.$inventoryNumber),
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'room_id' => $room->id,
            'price' => 100000,
            'quantity' => 1,
            'unit' => 'Unit',
        ]);

        return [$campus, $item];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function makeSchedule(InventoryItem $item, array $overrides = []): MaintenanceSchedule
    {
        return MaintenanceSchedule::query()->create(array_merge([
            'inventory_item_id' => $item->id,
            'maintenance_type' => MaintenanceType::Preventive,
            'interval_value' => 3,
            'interval_unit' => MaintenanceIntervalUnit::Months,
            'next_maintenance_date' => now()->addMonths(3)->toDateString(),
            'remind_before_days' => 7,
            'is_active' => true,
        ], $overrides));
    }
}
