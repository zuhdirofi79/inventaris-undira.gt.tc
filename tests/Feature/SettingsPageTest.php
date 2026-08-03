<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\DatabaseBackupService;
use App\Services\InventoryCodeService;
use App\Support\AppSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\CreatesAuthenticatedUsers;
use Tests\TestCase;

class SettingsPageTest extends TestCase
{
    use CreatesAuthenticatedUsers;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_super_admin_can_view_settings_sections(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('Informasi Aplikasi');
        $response->assertSee('Pengaturan Inventaris');
        $response->assertSee('Backup Database');
        $response->assertSee('Tentang Aplikasi');
        $response->assertSee('Developed by');
        $response->assertSee('zwars');
        $response->assertDontSee('primary_super_admin_user_id');
    }

    public function test_settings_update_persists_editable_fields(): void
    {
        $admin = $this->makeSuperAdmin();
        AppSettings::ensureDefaults();

        $response = $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_name' => 'Inventaris Test',
            'university_name' => 'Universitas Test',
            'footer_text' => '© 2026 Test Footer',
            'sequence_padding' => 5,
            'scan_base_url' => 'https://scan.example.test',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame('Inventaris Test', Setting::get(AppSettings::KEY_APP_NAME));
        $this->assertSame('Universitas Test', Setting::get(AppSettings::KEY_UNIVERSITY_NAME));
        $this->assertSame('© 2026 Test Footer', Setting::get(AppSettings::KEY_FOOTER_TEXT));
        $this->assertSame('5', Setting::get(AppSettings::KEY_SEQUENCE_PADDING));
        $this->assertSame('https://scan.example.test', Setting::get(AppSettings::KEY_SCAN_BASE_URL));
        $this->assertSame(5, app(InventoryCodeService::class)->padding());
    }

    public function test_empty_scan_base_url_falls_back_to_app_url(): void
    {
        config(['app.url' => 'https://inventaris.undira.test']);
        Setting::set(AppSettings::KEY_SCAN_BASE_URL, '', AppSettings::GROUP_INVENTORY);

        $url = app(InventoryCodeService::class)->scanUrl('CBB-HIBAH-26-EL-LPT-000001');

        $this->assertSame(
            'https://inventaris.undira.test/scan/CBB-HIBAH-26-EL-LPT-000001',
            $url
        );
    }

    public function test_custom_scan_base_url_is_used_when_set(): void
    {
        Setting::set(AppSettings::KEY_SCAN_BASE_URL, 'https://qr.example.test/', AppSettings::GROUP_INVENTORY);

        $url = app(InventoryCodeService::class)->scanUrl('CBB-HIBAH-26-EL-LPT-000002');

        $this->assertSame('https://qr.example.test/scan/CBB-HIBAH-26-EL-LPT-000002', $url);
    }

    public function test_number_format_remains_enforced_and_read_only(): void
    {
        $admin = $this->makeSuperAdmin();
        AppSettings::ensureDefaults();

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'app_name' => 'Inventaris UNDIRA',
            'university_name' => 'Universitas Dian Nusantara',
            'footer_text' => '© 2026 Biro PPPA',
            'sequence_padding' => 6,
            'scan_base_url' => null,
        ])->assertRedirect();

        $this->assertSame(AppSettings::NUMBER_FORMAT, Setting::get(AppSettings::KEY_NUMBER_FORMAT));
    }

    public function test_backup_service_rejects_non_sqlite_driver(): void
    {
        $service = app(DatabaseBackupService::class);
        $original = config('database.default');

        try {
            config([
                'database.default' => 'mysql',
                'database.connections.mysql.driver' => 'mysql',
            ]);

            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('SQLite');
            $service->createBackup();
        } finally {
            config(['database.default' => $original]);
        }
    }

    public function test_restore_rejects_invalid_upload(): void
    {
        $admin = $this->makeSuperAdmin();

        if (config('database.connections.'.config('database.default').'.driver') !== 'sqlite') {
            $this->markTestSkipped('Restore validation test requires SQLite.');
        }

        $file = UploadedFile::fake()->createWithContent('evil.txt', 'not a sqlite database');

        $response = $this->actingAs($admin)->post(route('admin.settings.restore'), [
            'backup_upload' => $file,
        ]);

        $response->assertSessionHasErrors(['backup_upload']);
    }

    public function test_restore_rejects_invalid_filename_from_list(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->actingAs($admin)->post(route('admin.settings.restore'), [
            'backup_file' => '../evil.sqlite',
        ]);

        $response->assertSessionHasErrors(['backup_file']);
    }

    public function test_download_backup_rejects_path_traversal(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin)
            ->get(route('admin.settings.backup.download', ['file' => '..%2Fevil.sqlite']))
            ->assertNotFound();
    }

    public function test_admin_without_settings_permission_cannot_access(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_backup_service_validates_sqlite_header(): void
    {
        $service = app(DatabaseBackupService::class);
        $path = storage_path('framework/testing/not-sqlite.bin');
        file_put_contents($path, 'HELLO WORLD 12345');

        $this->assertFalse($service->isValidSqliteFile($path));

        @unlink($path);
    }
}
