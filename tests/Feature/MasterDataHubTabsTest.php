<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InventoryConditionMaster;
use App\Models\OrgUnit;
use App\Models\ProcurementType;
use App\Models\PurchaseYear;
use App\Models\RoomFunction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataHubTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $user->syncRoles(['super-admin']);

        return $user;
    }

    public function test_each_valid_tab_loads_and_invalid_falls_back(): void
    {
        $admin = $this->makeAdmin();

        foreach (['konfigurasi', 'klasifikasi', 'units', 'pengadaan', 'lokasi'] as $tab) {
            $this->actingAs($admin)
                ->get(route('categories.index', ['tab' => $tab]))
                ->assertOk();
        }

        $html = $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'tidak-ada']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Konfigurasi Inventaris', $html);
    }

    public function test_klasifikasi_tab_has_contextual_add_and_modal_outside_wrapper(): void
    {
        $admin = $this->makeAdmin();
        $html = $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'klasifikasi']))
            ->assertOk()
            ->assertSee('Tambah Klasifikasi')
            ->getContent();

        $footerPos = strpos($html, 'class="main-footer');
        $modalPos = strpos($html, 'id="modalCreateCategory"');
        $this->assertNotFalse($footerPos);
        $this->assertNotFalse($modalPos);
        $this->assertGreaterThan($footerPos, $modalPos);
    }

    public function test_super_admin_can_create_classification_unit_and_procurement(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->post(route('categories.store'), [
                'code' => 'LAB',
                'name' => 'Laboratorium',
                'description' => 'Uji',
                'is_active' => '1',
            ])
            ->assertRedirect(route('categories.index', ['tab' => 'klasifikasi']));

        $this->assertDatabaseHas('categories', ['code' => 'LAB', 'name' => 'Laboratorium', 'is_active' => 1]);

        $this->actingAs($admin)
            ->post(route('org-units.store'), [
                'code' => 'BAK',
                'name' => 'Bagian Akademik',
                'is_active' => '1',
            ])
            ->assertRedirect(route('categories.index', ['tab' => 'units']));

        $this->assertDatabaseHas('org_units', ['code' => 'BAK', 'name' => 'Bagian Akademik']);

        $this->actingAs($admin)
            ->post(route('procurement-types.store'), [
                'code' => 'YYS',
                'name' => 'Yayasan',
                'is_active' => '1',
            ])
            ->assertRedirect(route('categories.index', ['tab' => 'pengadaan']));

        $this->assertDatabaseHas('procurement_types', ['code' => 'YYS', 'name' => 'Yayasan']);
    }

    public function test_duplicate_codes_are_rejected(): void
    {
        $admin = $this->makeAdmin();

        Category::query()->create(['code' => 'DUP', 'name' => 'Dup', 'slug' => 'dup', 'is_active' => true]);
        OrgUnit::query()->create(['code' => 'DUPU', 'name' => 'Dup Unit', 'is_active' => true]);
        ProcurementType::query()->create(['code' => 'DUPP', 'name' => 'Dup Proc', 'is_active' => true]);

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'klasifikasi']))
            ->post(route('categories.store'), ['code' => 'DUP', 'name' => 'Other', 'is_active' => '1'])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'units']))
            ->post(route('org-units.store'), ['code' => 'DUPU', 'name' => 'Other', 'is_active' => '1'])
            ->assertSessionHasErrors('code');

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'pengadaan']))
            ->post(route('procurement-types.store'), ['code' => 'DUPP', 'name' => 'Other', 'is_active' => '1'])
            ->assertSessionHasErrors('code');
    }

    public function test_new_masters_appear_in_konfigurasi_dropdowns(): void
    {
        $admin = $this->makeAdmin();

        Category::query()->create(['code' => 'NET', 'name' => 'Jaringan', 'slug' => 'jaringan', 'is_active' => true]);
        OrgUnit::query()->create(['code' => 'IT', 'name' => 'Unit IT', 'is_active' => true]);
        ProcurementType::query()->create(['code' => 'HIBAH', 'name' => 'Hibah', 'is_active' => true]);
        PurchaseYear::query()->create(['year' => 2098, 'is_active' => true]);
        InventoryConditionMaster::query()->create(['code' => 'LAYAK', 'name' => 'Layak', 'is_active' => true, 'sort_order' => 1]);
        RoomFunction::query()->create(['code' => 'LAB', 'name' => 'Laboratorium', 'is_active' => true, 'sort_order' => 1]);

        $html = $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Jaringan', $html);
        $this->assertStringContainsString('Unit IT', $html);
        $this->assertStringContainsString('Hibah', $html);
        $this->assertStringContainsString('2098', $html);
        $this->assertStringContainsString('Layak', $html);
        $this->assertStringContainsString('Laboratorium', $html);
    }

    public function test_supporting_masters_crud_on_lokasi_tab(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'lokasi']))
            ->assertOk()
            ->assertSee('Kelola Kampus')
            ->assertSee('Tambah Tahun')
            ->assertSee('Tambah Kondisi')
            ->assertSee('Tambah Fungsi Ruang');

        $this->actingAs($admin)
            ->post(route('purchase-years.store'), ['year' => 2097, 'is_active' => '1'])
            ->assertRedirect();

        $this->assertDatabaseHas('purchase_years', ['year' => 2097, 'is_active' => 1]);

        $this->actingAs($admin)
            ->from(route('categories.index', ['tab' => 'lokasi']))
            ->post(route('purchase-years.store'), ['year' => 2097, 'is_active' => '1'])
            ->assertSessionHasErrors('year');

        $this->actingAs($admin)
            ->post(route('inventory-conditions.store'), [
                'code' => 'RUSAK',
                'name' => 'Rusak',
                'sort_order' => 3,
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_conditions', ['code' => 'RUSAK', 'name' => 'Rusak']);

        $this->actingAs($admin)
            ->post(route('room-functions.store'), [
                'code' => 'AUD',
                'name' => 'Auditorium',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('room_functions', ['code' => 'AUD', 'name' => 'Auditorium']);
    }

    public function test_viewer_cannot_create_classification_or_supporting_masters(): void
    {
        $viewer = User::factory()->create(['is_active' => true, 'email_verified_at' => now()]);
        $viewer->syncRoles(['viewer']);

        $this->actingAs($viewer)
            ->post(route('categories.store'), ['code' => 'X', 'name' => 'X', 'is_active' => '1'])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->post(route('purchase-years.store'), ['year' => 2096, 'is_active' => '1'])
            ->assertForbidden();
    }

    public function test_inactive_masters_hidden_from_konfigurasi_lookups(): void
    {
        $admin = $this->makeAdmin();

        OrgUnit::query()->create(['code' => 'OLD', 'name' => 'Unit Nonaktif', 'is_active' => false]);
        OrgUnit::query()->create(['code' => 'NEW', 'name' => 'Unit Aktif', 'is_active' => true]);

        $html = $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'konfigurasi']))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Unit Aktif', $html);
        $this->assertStringNotContainsString('Unit Nonaktif', $html);
    }
}
