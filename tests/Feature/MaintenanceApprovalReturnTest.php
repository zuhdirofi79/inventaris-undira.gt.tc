<?php

namespace Tests\Feature;

use App\Enums\InventoryStatus;
use App\Enums\MaintenanceStatus;
use App\Models\Building;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Floor;
use App\Models\InventoryItem;
use App\Models\Maintenance;
use App\Models\MaintenanceStatusHistory;
use App\Models\Room;
use App\Models\SubCategory;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MaintenanceApprovalReturnTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_reject_returns_wo_to_revision_and_removes_from_pending_list(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $this->actingAs($admin)
            ->from(route('maintenance-approvals.index'))
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Foto sesudah belum jelas, perbaiki lagi.',
            ])
            ->assertRedirect(route('maintenance-approvals.index'))
            ->assertSessionHas('success');

        $wo->refresh();
        $item->refresh();

        $this->assertSame(MaintenanceStatus::Revision, $wo->status);
        $this->assertSame('Foto sesudah belum jelas, perbaiki lagi.', $wo->rejection_reason);
        $this->assertNull($wo->submitted_for_approval_at);
        $this->assertSame(InventoryStatus::RevisiPerbaikan, $item->status);

        $this->assertDatabaseHas('maintenance_status_histories', [
            'maintenance_id' => $wo->id,
            'from_status' => MaintenanceStatus::PendingApproval->value,
            'to_status' => MaintenanceStatus::Revision->value,
            'note' => 'Foto sesudah belum jelas, perbaiki lagi.',
        ]);

        $this->actingAs($admin)
            ->get(route('maintenance-approvals.index'))
            ->assertOk()
            ->assertDontSee($wo->maintenance_number);

        $this->actingAs($admin)
            ->get(route('maintenances.index', ['status' => 'revision']))
            ->assertOk()
            ->assertSee($wo->maintenance_number)
            ->assertSee('Revisi Perbaikan');

        $this->actingAs($admin)
            ->get(route('maintenances.show', $wo))
            ->assertOk()
            ->assertSee('Revisi Perbaikan')
            ->assertSee('Foto sesudah belum jelas, perbaiki lagi.');
    }

    public function test_reject_requires_reason(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $this->actingAs($admin)
            ->from(route('maintenance-approvals.index'))
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => '',
            ])
            ->assertRedirect(route('maintenance-approvals.index'))
            ->assertSessionHasErrors('rejection_reason');

        $wo->refresh();
        $this->assertSame(MaintenanceStatus::PendingApproval, $wo->status);
        $this->assertNull($wo->rejection_reason);
    }

    public function test_reject_invalid_id_returns_not_found(): void
    {
        Notification::fake();
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.reject', ['maintenance' => 999999]), [
                'rejection_reason' => 'Tidak ada',
            ])
            ->assertNotFound();
    }

    public function test_reject_non_pending_wo_shows_error_and_keeps_status(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem();
        $wo = Maintenance::query()->create([
            'maintenance_number' => 'MNT-OPEN-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::InProgress,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->from(route('maintenance-approvals.index'))
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Tidak seharusnya',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $wo->refresh();
        $this->assertSame(MaintenanceStatus::InProgress, $wo->status);
        $this->assertSame(0, MaintenanceStatusHistory::query()->where('maintenance_id', $wo->id)->count());
    }

    public function test_approve_still_works_after_reject_flow(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.approve', $wo), [])
            ->assertRedirect(route('maintenance-approvals.index'))
            ->assertSessionHas('success');

        $wo->refresh();
        $item->refresh();
        $this->assertSame(MaintenanceStatus::Completed, $wo->status);
        $this->assertSame(InventoryStatus::Aktif, $item->status);
    }

    public function test_revision_wo_can_be_resubmitted_for_approval(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Perlu foto tambahan',
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertSame(MaintenanceStatus::Revision, $wo->status);

        $this->actingAs($admin)
            ->post(route('maintenances.complete.store', $wo), [
                'completed_at' => now()->toDateString(),
                'work_performed' => 'Sudah diperbaiki ulang sesuai catatan revisi.',
                'cost' => 150000,
            ])
            ->assertRedirect();

        $wo->refresh();
        $item->refresh();
        $this->assertSame(MaintenanceStatus::PendingApproval, $wo->status);
        $this->assertNull($wo->rejection_reason);
        $this->assertSame(InventoryStatus::MenungguApproval, $item->status);

        $this->actingAs($admin)
            ->get(route('maintenance-approvals.index'))
            ->assertOk()
            ->assertSee($wo->maintenance_number);
    }

    public function test_reject_preserves_existing_wo_fields(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin, [
            'technician' => 'Teknisi Tetap',
            'cost' => 275000,
            'work_performed' => 'Ganti sparepart LCD',
            'notes' => 'Catatan awal tetap ada',
            'damage_type' => 'LCD retak',
        ]);

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Dokumentasi kurang',
            ])
            ->assertRedirect();

        $wo->refresh();
        $this->assertSame('Teknisi Tetap', $wo->technician);
        $this->assertEquals(275000, (float) $wo->cost);
        $this->assertSame('Ganti sparepart LCD', $wo->work_performed);
        $this->assertSame('Catatan awal tetap ada', $wo->notes);
        $this->assertSame('LCD retak', $wo->damage_type);
        $this->assertSame($item->id, $wo->inventory_item_id);
    }

    public function test_approvals_index_renders_shared_reject_modal_outside_table(): void
    {
        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $html = $this->actingAs($admin)
            ->get(route('maintenance-approvals.index'))
            ->assertOk()
            ->assertSee('Kembalikan')
            ->assertSee('id="rejectMaintenanceModal"', false)
            ->assertSee('data-reject-url="'.route('maintenance-approvals.reject', $wo).'"', false)
            ->getContent();

        $tablePos = strpos($html, 'table-responsive');
        $modalPos = strpos($html, 'id="rejectMaintenanceModal"');
        $this->assertNotFalse($tablePos);
        $this->assertNotFalse($modalPos);
        $this->assertGreaterThan($tablePos, $modalPos, 'Reject modal must be rendered outside table-responsive');
    }

    public function test_double_reject_does_not_duplicate_history(): void
    {
        Notification::fake();

        $admin = $this->makeSuperAdmin();
        $item = $this->makeItem(['status' => InventoryStatus::MenungguApproval->value]);
        $wo = $this->makePendingWo($item, $admin);

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Alasan pertama',
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('maintenance-approvals.reject', $wo), [
                'rejection_reason' => 'Alasan kedua',
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $wo->refresh();
        $this->assertSame(MaintenanceStatus::Revision, $wo->status);
        $this->assertSame('Alasan pertama', $wo->rejection_reason);
        $this->assertSame(
            1,
            MaintenanceStatusHistory::query()
                ->where('maintenance_id', $wo->id)
                ->where('to_status', MaintenanceStatus::Revision->value)
                ->count()
        );
    }

    protected function makePendingWo(InventoryItem $item, User $actor, array $overrides = []): Maintenance
    {
        return Maintenance::query()->create(array_merge([
            'maintenance_number' => 'MNT-APPR-'.uniqid(),
            'inventory_item_id' => $item->id,
            'maintenance_type' => 'repair',
            'priority' => 'medium',
            'service_date' => now()->toDateString(),
            'status' => MaintenanceStatus::PendingApproval,
            'submitted_for_approval_at' => now(),
            'work_performed' => 'Perbaikan selesai',
            'result' => 'repaired',
            'created_by' => $actor->id,
            'technician_user_id' => $actor->id,
            'technician' => $actor->name,
        ], $overrides));
    }

    protected function makeItem(array $overrides = []): InventoryItem
    {
        static $n = 0;
        $n++;
        $s = (string) $n;

        $campus = Campus::query()->create([
            'code' => 'AC'.$s, 'name' => 'Kampus A'.$s, 'slug' => 'kampus-a-'.$s, 'is_active' => true,
        ]);
        $building = Building::query()->create([
            'campus_id' => $campus->id, 'code' => 'AB'.$s, 'name' => 'Gedung A'.$s, 'slug' => 'gedung-a-'.$s, 'is_active' => true,
        ]);
        $floor = Floor::query()->create([
            'building_id' => $building->id, 'code' => 'AF'.$s, 'name' => 'Lantai 1', 'floor_number' => 1, 'is_active' => true,
        ]);
        $room = Room::query()->create([
            'floor_id' => $floor->id, 'code' => 'AR'.$s, 'name' => 'Ruang A'.$s, 'is_active' => true,
        ]);
        $category = Category::query()->create([
            'code' => 'AX'.$s, 'name' => 'Elektronik', 'slug' => 'elektronik-a-'.$s, 'is_active' => true,
        ]);
        $sub = SubCategory::query()->create([
            'category_id' => $category->id, 'code' => 'AS'.$s, 'name' => 'Laptop', 'slug' => 'laptop-a-'.$s, 'is_active' => true,
        ]);

        return InventoryItem::query()->create(array_merge([
            'name' => 'Aset A'.$s,
            'inventory_number' => 'APR-AST-'.$s,
            'barcode' => 'APR-AST-'.$s,
            'room_id' => $room->id,
            'category_id' => $category->id,
            'sub_category_id' => $sub->id,
            'condition' => 'bagus',
            'status' => 'aktif',
            'quantity' => 1,
        ], $overrides));
    }
}
