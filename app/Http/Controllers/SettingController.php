<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Http\Requests\RestoreBackupRequest;
use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Services\AuditLogService;
use App\Services\DatabaseBackupService;
use App\Support\AppSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
        protected DatabaseBackupService $backups,
    ) {}

    public function index(): View
    {
        AppSettings::ensureDefaults();

        $values = [
            'app_name' => AppSettings::appName(),
            'university_name' => AppSettings::universityName(),
            'footer_text' => AppSettings::footerText(),
            'logo_url' => AppSettings::logoUrl(),
            'logo_path' => (string) Setting::get(AppSettings::KEY_LOGO_PATH, ''),
            'favicon_url' => AppSettings::faviconUrl(),
            'favicon_path' => (string) Setting::get(AppSettings::KEY_FAVICON_PATH, ''),
            'app_url' => rtrim((string) config('app.url'), '/'),
            'number_format' => AppSettings::NUMBER_FORMAT,
            'number_format_example' => AppSettings::NUMBER_FORMAT_EXAMPLE,
            'sequence_padding' => AppSettings::sequencePadding(),
            'scan_base_url' => (string) Setting::get(AppSettings::KEY_SCAN_BASE_URL, ''),
            'scan_base_resolved' => AppSettings::scanBaseUrl(),
        ];

        $about = [
            'developer' => 'zwars',
            'version' => '1.0.0',
            'laravel' => app()->version(),
            'php' => PHP_VERSION,
            'database' => $this->backups->driver(),
        ];

        $backupList = [];
        $backupError = null;
        try {
            if ($this->backups->isSqlite()) {
                $backupList = $this->backups->listBackups();
            }
        } catch (RuntimeException $e) {
            $backupError = $e->getMessage();
        }

        return view('admin.settings.index', [
            'values' => $values,
            'about' => $about,
            'backups' => collect($backupList),
            'isSqlite' => $this->backups->isSqlite(),
            'backupError' => $backupError,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        AppSettings::ensureDefaults();

        foreach ($request->settingValues() as $key => $value) {
            $group = str_starts_with($key, 'app.')
                ? AppSettings::GROUP_APP
                : AppSettings::GROUP_INVENTORY;
            Setting::set($key, $value, $group);
        }

        // Keep enforced format synchronized (read-only in UI).
        Setting::set(
            AppSettings::KEY_NUMBER_FORMAT,
            AppSettings::NUMBER_FORMAT,
            AppSettings::GROUP_INVENTORY
        );

        if ($request->boolean('remove_logo')) {
            $this->deleteBrandingFile((string) Setting::get(AppSettings::KEY_LOGO_PATH, ''));
            Setting::set(AppSettings::KEY_LOGO_PATH, '', AppSettings::GROUP_APP);
        }

        if ($request->boolean('remove_favicon')) {
            $this->deleteBrandingFile((string) Setting::get(AppSettings::KEY_FAVICON_PATH, ''));
            Setting::set(AppSettings::KEY_FAVICON_PATH, '', AppSettings::GROUP_APP);
        }

        if ($request->hasFile('logo')) {
            $this->deleteBrandingFile((string) Setting::get(AppSettings::KEY_LOGO_PATH, ''));
            $path = $request->file('logo')->store('branding', 'public');
            Setting::set(AppSettings::KEY_LOGO_PATH, $path, AppSettings::GROUP_APP);
        }

        if ($request->hasFile('favicon')) {
            $this->deleteBrandingFile((string) Setting::get(AppSettings::KEY_FAVICON_PATH, ''));
            $path = $request->file('favicon')->store('branding', 'public');
            Setting::set(AppSettings::KEY_FAVICON_PATH, $path, AppSettings::GROUP_APP);
        }

        $this->audit->log(
            auth()->user(),
            AuditAction::Update,
            null,
            null,
            ['keys' => array_keys($request->settingValues())],
            'Pengaturan sistem diperbarui',
        );

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function backup(): RedirectResponse|StreamedResponse
    {
        try {
            $filename = $this->backups->createBackup();
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log(
            auth()->user(),
            AuditAction::Download,
            null,
            null,
            ['backup_file' => $filename],
            'Backup database dibuat',
        );

        return Storage::disk(DatabaseBackupService::DISK)
            ->download(DatabaseBackupService::DIRECTORY.'/'.$filename, $filename);
    }

    public function downloadBackup(string $file): StreamedResponse
    {
        try {
            $filename = $this->backups->assertSafeFilename($file);
            $absolute = $this->backups->absolutePath($filename);
        } catch (RuntimeException) {
            abort(404);
        }

        abort_unless($this->backups->isValidSqliteFile($absolute), 404);

        $this->audit->log(
            auth()->user(),
            AuditAction::Download,
            null,
            null,
            ['backup_file' => $filename],
            'Unduh backup database',
        );

        return Storage::disk(DatabaseBackupService::DISK)
            ->download(DatabaseBackupService::DIRECTORY.'/'.$filename, $filename);
    }

    public function restore(RestoreBackupRequest $request): RedirectResponse
    {
        try {
            if ($request->hasFile('backup_upload')) {
                $filename = $this->backups->restoreFromUpload($request->file('backup_upload'));
            } else {
                $filename = $this->backups->assertSafeFilename((string) $request->input('backup_file'));
                $this->backups->restoreFromStoredBackup($filename);
            }
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log(
            auth()->user(),
            AuditAction::Update,
            null,
            null,
            ['backup_file' => $filename],
            'Database dipulihkan dari backup',
        );

        return back()->with('success', 'Database berhasil dipulihkan dari backup.');
    }

    protected function deleteBrandingFile(string $path): void
    {
        $path = trim($path);
        if ($path === '' || ! str_starts_with($path, 'branding/')) {
            return;
        }

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
