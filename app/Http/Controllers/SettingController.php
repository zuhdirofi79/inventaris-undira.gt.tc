<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Models\Setting;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SettingController extends Controller
{
    public function __construct(
        protected AuditLogService $audit,
    ) {}

    public function index(): View
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        $backups = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $path) => str_ends_with(strtolower($path), '.sqlite'))
            ->map(fn (string $path) => [
                'name' => basename($path),
                'size' => Storage::disk('local')->size($path),
                'modified' => Storage::disk('local')->lastModified($path),
            ])
            ->sortByDesc('modified')
            ->values();

        return view('admin.settings.index', compact('settings', 'backups'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*.key' => ['required', 'string'],
            'settings.*.value' => ['nullable'],
            'settings.*.group' => ['nullable', 'string'],
        ]);

        foreach ($data['settings'] as $setting) {
            Setting::set($setting['key'], $setting['value'] ?? null, $setting['group'] ?? null);
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function backup(): RedirectResponse|StreamedResponse
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver !== 'sqlite') {
            return back()->with('error', 'Backup otomatis hanya tersedia untuk SQLite.');
        }

        $dbPath = config("database.connections.{$connection}.database");
        $filename = 'backup_'.now()->format('Ymd_His').'.sqlite';
        Storage::disk('local')->put("backups/{$filename}", file_get_contents($dbPath));

        $this->audit->log(
            auth()->user(),
            AuditAction::Download,
            null,
            null,
            ['backup_file' => $filename],
            'Backup database dibuat',
        );

        return Storage::disk('local')->download("backups/{$filename}", $filename);
    }

    public function downloadBackup(string $file): StreamedResponse
    {
        $filename = basename($file);
        abort_unless(preg_match('/^backup_[\w\-]+\.sqlite$/i', $filename) === 1, 404);

        $path = "backups/{$filename}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        $this->audit->log(
            auth()->user(),
            AuditAction::Download,
            null,
            null,
            ['backup_file' => $filename],
            'Unduh backup database',
        );

        return Storage::disk('local')->download($path, $filename);
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate(['backup_file' => ['required', 'string', 'max:255']]);

        $filename = basename((string) $request->input('backup_file'));
        abort_unless(preg_match('/^backup_[\w\-]+\.sqlite$/i', $filename) === 1, 422);

        $path = "backups/{$filename}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        $connection = config('database.default');
        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            return back()->with('error', 'Restore otomatis hanya tersedia untuk SQLite.');
        }

        $dbPath = config("database.connections.{$connection}.database");
        DB::disconnect();
        copy(Storage::disk('local')->path($path), $dbPath);

        $this->audit->log(
            auth()->user(),
            AuditAction::Update,
            null,
            null,
            ['backup_file' => $filename],
            'Database dipulihkan dari backup',
        );

        return back()->with('success', 'Database berhasil dipulihkan.');
    }
}
