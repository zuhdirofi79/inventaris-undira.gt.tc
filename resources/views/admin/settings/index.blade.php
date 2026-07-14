@extends('layouts.app')

@section('title', 'Settings - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item">Admin</li>
    <li class="breadcrumb-item active">Settings</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-cog me-2"></i>Pengaturan Sistem</h1>
</div>

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

<div class="card card-undira mt-4">
    <div class="card-header"><h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup & Restore Database</h5></div>
    <div class="card-body">
        <form action="{{ route('admin.settings.backup') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="fas fa-download me-1"></i>Buat Backup</button>
        </form>
        <form action="{{ route('admin.settings.restore') }}" method="POST" class="d-inline ms-2">
            @csrf
            <div class="input-group input-group-sm d-inline-flex" style="width:auto;">
                <input type="text" name="backup_file" class="form-control" placeholder="nama_file.sqlite" required style="width:200px;">
                <button type="submit" class="btn btn-outline-warning" data-confirm="Yakin restore database? Data saat ini akan diganti."><i class="fas fa-upload me-1"></i>Restore</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">Backup otomatis tersedia untuk database SQLite.</p>
    </div>
</div>
@endsection
