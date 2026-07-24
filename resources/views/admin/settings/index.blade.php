@extends('layouts.app')

@section('title', 'Settings - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Pengaturan Sistem"
    subtitle="Konfigurasi aplikasi inventaris"
    icon="bi bi-gear"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Admin', 'url' => route('admin.settings.index')],
        ['label' => 'Settings'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

<form action="{{ route('admin.settings.update') }}" method="POST">
    @csrf @method('PUT')
    @php $settingIndex = 0; @endphp
    @forelse($settings as $group => $groupSettings)
    <div class="card card-undira mb-4">
        <div class="card-header"><h5 class="mb-0">{{ ucfirst($group ?? 'Umum') }}</h5></div>
        <div class="card-body">
            @foreach($groupSettings as $setting)
            <div class="mb-3">
                <label class="form-label">{{ $setting->key }}</label>
                <input type="hidden" name="settings[{{ $settingIndex }}][key]" value="{{ $setting->key }}">
                <input type="hidden" name="settings[{{ $settingIndex }}][group]" value="{{ $setting->group }}">
                <input type="text" name="settings[{{ $settingIndex }}][value]" class="form-control" value="{{ $setting->value }}">
            </div>
            @php $settingIndex++; @endphp
            @endforeach
        </div>
    </div>
    @empty
    <div class="alert alert-info">Belum ada pengaturan. Tambahkan melalui seeder atau database.</div>
    @endforelse

    @if($settings->isNotEmpty())
    <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan Pengaturan</button>
    @endif
</form>

@can('backup.manage')
<div class="card card-undira mt-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup Database</h5></div>
    <div class="card-body">
        <form action="{{ route('admin.settings.backup') }}" method="POST" class="mb-3">
            @csrf
            <button type="submit" class="btn btn-undira btn-sm" data-no-loader="1">
                <i class="fas fa-download me-1"></i>Buat & Unduh Backup
            </button>
        </form>

        @if(($backups ?? collect())->isNotEmpty())
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-3">
                <thead>
                    <tr><th>File</th><th>Ukuran</th><th>Tanggal</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($backups as $backup)
                    <tr>
                        <td><code>{{ $backup['name'] }}</code></td>
                        <td>{{ number_format(($backup['size'] ?? 0) / 1024, 1) }} KB</td>
                        <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['modified'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                        <td class="text-end">
                            <a href="{{ route('admin.settings.backup.download', $backup['name']) }}" class="btn btn-outline-primary btn-sm" data-no-loader="1">
                                <i class="fas fa-download"></i>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <form action="{{ route('admin.settings.restore') }}" method="POST">
            @csrf
            <label class="form-label small">Restore dari file backup</label>
            <div class="input-group input-group-sm" style="max-width:420px;">
                <select name="backup_file" class="form-select" required>
                    <option value="">Pilih file…</option>
                    @foreach($backups ?? [] as $backup)
                        <option value="{{ $backup['name'] }}">{{ $backup['name'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-outline-warning" data-confirm="Yakin restore database? Data saat ini akan diganti.">
                    <i class="fas fa-upload me-1"></i>Restore
                </button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">Backup otomatis tersedia untuk database SQLite. Restore memakai allowlist nama file aman.</p>
    </div>
</div>
@endcan
@endsection
