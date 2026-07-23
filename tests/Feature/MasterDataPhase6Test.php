<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Campus;
use App\Models\Category;
use App\Models\Supplier;
use App\Models\Vendor;
use Illuminate\Http\UploadedFile;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class MasterDataPhase6Test extends TestCase
{
    use CreatesAuthenticatedUsers;

    public function test_supplier_index_supports_search_filter_and_sort(): void
    {
        $admin = $this->makeAdmin();

        Supplier::query()->create(['name' => 'Alpha Supply', 'email' => 'a@example.com', 'is_active' => true]);
        Supplier::query()->create(['name' => 'Beta Supply', 'email' => 'b@example.com', 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('suppliers.index', ['q' => 'Alpha', 'is_active' => '1', 'sort' => 'name', 'dir' => 'asc']))
            ->assertOk()
            ->assertSee('Alpha Supply')
            ->assertDontSee('Beta Supply')
            ->assertSee('master-table-toolbar', false);
    }

    public function test_supplier_export_and_import_roundtrip(): void
    {
        $admin = $this->makeAdmin();

        Supplier::query()->create([
            'name' => 'Export Supplier',
            'contact_person' => 'Andi',
            'phone' => '08111',
            'email' => 'export@example.com',
            'address' => 'Jakarta',
            'is_active' => true,
        ]);

        $export = $this->actingAs($admin)->get(route('suppliers.export'));
        $export->assertOk();
        $this->assertStringContainsString(
            'attachment',
            (string) $export->headers->get('content-disposition'),
        );
        $this->assertStringContainsString('name', $export->streamedContent());

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::Export->value,
            'description' => 'SUPPLIER_EXPORTED',
        ]);

        $csv = "name,contact_person,phone,email,address,is_active\n"
            ."Import Supplier,Budi,08222,import@example.com,Depok,1\n";

        $file = UploadedFile::fake()->createWithContent('suppliers.csv', $csv);

        $this->actingAs($admin)
            ->post(route('suppliers.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Import Supplier',
            'email' => 'import@example.com',
            'is_active' => 1,
        ]);

        $this->assertTrue(
            AuditLog::query()->where('description', 'SUPPLIER_IMPORTED')->exists()
        );
    }

    public function test_vendor_index_filters_inactive(): void
    {
        $admin = $this->makeAdmin();

        Vendor::query()->create(['name' => 'Aktif Vendor', 'is_active' => true]);
        Vendor::query()->create(['name' => 'Nonaktif Vendor', 'is_active' => false]);

        $this->actingAs($admin)
            ->get(route('vendors.index', ['is_active' => '0']))
            ->assertOk()
            ->assertSee('Nonaktif Vendor')
            ->assertDontSee('Aktif Vendor');
    }

    public function test_category_klasifikasi_toolbar_and_import(): void
    {
        $admin = $this->makeAdmin();

        Category::query()->create([
            'code' => 'AAA',
            'name' => 'Klasifikasi A',
            'slug' => 'klasifikasi-a',
            'is_active' => true,
        ]);
        Category::query()->create([
            'code' => 'BBB',
            'name' => 'Klasifikasi B',
            'slug' => 'klasifikasi-b',
            'is_active' => false,
        ]);

        $this->actingAs($admin)
            ->get(route('categories.index', ['tab' => 'klasifikasi', 'q' => 'Klasifikasi A', 'is_active' => '1']))
            ->assertOk()
            ->assertSee('Klasifikasi A')
            ->assertDontSee('Klasifikasi B');

        $csv = "code,name,slug,description,is_active\n"
            ."CCC,Klasifikasi C,klasifikasi-c,Desc,1\n";
        $file = UploadedFile::fake()->createWithContent('categories.csv', $csv);

        $this->actingAs($admin)
            ->post(route('categories.import'), ['file' => $file])
            ->assertRedirect(route('categories.index', ['tab' => 'klasifikasi']));

        $this->assertDatabaseHas('categories', [
            'code' => 'CCC',
            'name' => 'Klasifikasi C',
            'is_active' => 1,
        ]);
    }

    public function test_campus_export_template_and_import(): void
    {
        $admin = $this->makeAdmin();

        Campus::query()->create([
            'code' => 'C1',
            'name' => 'Kampus Satu',
            'slug' => 'kampus-satu',
            'is_active' => true,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.locations.campuses.index', ['q' => 'Satu']))
            ->assertOk()
            ->assertSee('Kampus Satu');

        $this->actingAs($admin)
            ->get(route('admin.locations.campuses.import.template'))
            ->assertOk();

        $csv = "code,name,slug,description,is_active\n"
            ."C2,Kampus Dua,kampus-dua,Baru,1\n";
        $file = UploadedFile::fake()->createWithContent('campuses.csv', $csv);

        $this->actingAs($admin)
            ->post(route('admin.locations.campuses.import'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('campuses', [
            'code' => 'C2',
            'name' => 'Kampus Dua',
            'slug' => 'kampus-dua',
        ]);
    }

    public function test_viewer_cannot_access_supplier_export(): void
    {
        $viewer = $this->makeViewer();

        $this->actingAs($viewer)
            ->get(route('suppliers.export'))
            ->assertForbidden();
    }
}
