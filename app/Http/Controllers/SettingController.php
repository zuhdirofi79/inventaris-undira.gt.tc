<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SettingController extends Controller
{
    public function index(): View
    {
        $settings = Setting::query()->orderBy('group')->orderBy('key')->get()->groupBy('group');

        return view('admin.settings.index', compact('settings'));
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

    public function backup(): RedirectResponse
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $dbPath = config("database.connections.{$connection}.database");
            $filename = 'backup_'.now()->format('Ymd_His').'.sqlite';
            Storage::disk('local')->put("backups/{$filename}", file_get_contents($dbPath));

            return back()->with('success', "Backup berhasil: {$filename}");
        }

        return back()->with('error', 'Backup otomatis hanya tersedia untuk SQLite.');
    }

    public function restore(Request $request): RedirectResponse
    {
        $request->validate(['backup_file' => ['required', 'string']]);

        $path = "backups/{$request->input('backup_file')}";
        abort_unless(Storage::disk('local')->exists($path), 404);

        $connection = config('database.default');
        if (config("database.connections.{$connection}.driver") !== 'sqlite') {
            return back()->with('error', 'Restore otomatis hanya tersedia untuk SQLite.');
        }

        $dbPath = config("database.connections.{$connection}.database");
        DB::disconnect();
        copy(Storage::disk('local')->path($path), $dbPath);

        return back()->with('success', 'Database berhasil dipulihkan.');
    }
}
