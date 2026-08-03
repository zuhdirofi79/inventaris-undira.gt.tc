@extends('layouts.app')

@section('title', 'Settings - '.($values['app_name'] ?? 'Inventaris UNDIRA'))

@section('content')
<x-page-header
    title="Pengaturan Sistem"
    subtitle="Konfigurasi aplikasi, inventaris, dan backup database"
    icon="bi bi-gear"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Sistem'],
        ['label' => 'Settings'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data" class="undira-settings-form">
    @csrf
    @method('PUT')

    {{-- ========== Informasi Aplikasi ========== --}}
    <div class="card card-undira mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-building me-2"></i>Informasi Aplikasi</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="app_name">Nama Aplikasi</label>
                    <input type="text" id="app_name" name="app_name" class="form-control @error('app_name') is-invalid @enderror"
                           value="{{ old('app_name', $values['app_name']) }}" required maxlength="120">
                    @error('app_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="university_name">Nama Universitas</label>
                    <input type="text" id="university_name" name="university_name" class="form-control @error('university_name') is-invalid @enderror"
                           value="{{ old('university_name', $values['university_name']) }}" required maxlength="160">
                    @error('university_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="footer_text">Footer</label>
                    <input type="text" id="footer_text" name="footer_text" class="form-control @error('footer_text') is-invalid @enderror"
                           value="{{ old('footer_text', $values['footer_text']) }}" required maxlength="255">
                    <div class="form-text">Teks copyright di bagian bawah halaman.</div>
                    @error('footer_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="app_url_display">APP_URL</label>
                    <input type="text" id="app_url_display" class="form-control" value="{{ $values['app_url'] }}" readonly>
                    <div class="form-text">Berasal dari <code>.env</code> (<code>APP_URL</code>). Tidak diubah dari halaman ini.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="logo">Logo</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <img src="{{ $values['logo_url'] }}" alt="Logo saat ini" class="undira-settings-logo-preview">
                        @if(!empty($values['logo_path']))
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label" for="remove_logo">Kembalikan logo default</label>
                            </div>
                        @endif
                    </div>
                    <input type="file" id="logo" name="logo" class="form-control @error('logo') is-invalid @enderror" accept=".png,.jpg,.jpeg,.webp,.svg">
                    <div class="form-text">Opsional. PNG/JPG/SVG, maks. 2 MB. Digunakan di sidebar.</div>
                    @error('logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="favicon">Favicon</label>
                    <div class="d-flex align-items-center gap-3 mb-2">
                        @if(!empty($values['favicon_url']))
                            <img src="{{ $values['favicon_url'] }}" alt="Favicon" class="undira-settings-favicon-preview">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_favicon" value="1" id="remove_favicon">
                                <label class="form-check-label" for="remove_favicon">Kembalikan favicon default</label>
                            </div>
                        @else
                            <span class="text-muted small">Menggunakan favicon bawaan UNDIRA</span>
                        @endif
                    </div>
                    <input type="file" id="favicon" name="favicon" class="form-control @error('favicon') is-invalid @enderror" accept=".png,.jpg,.jpeg,.ico,.webp">
                    <div class="form-text">Opsional. PNG/ICO, maks. 512 KB.</div>
                    @error('favicon')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
    </div>

    {{-- ========== Pengaturan Inventaris ========== --}}
    <div class="card card-undira mb-4">
        <div class="card-header">
            <h5 class="mb-0"><i class="fas fa-boxes me-2"></i>Pengaturan Inventaris</h5>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label" for="number_format">Format Nomor Inventaris</label>
                    <input type="text" id="number_format" class="form-control" value="{{ $values['number_format'] }}" readonly>
                    <div class="form-text">
                        Format resmi UNDIRA (read-only). Contoh:
                        <code>{{ $values['number_format_example'] }}</code>
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="prefix_info">Prefix</label>
                    <input type="text" id="prefix_info" class="form-control"
                           value="Kode master: Kampus · Pengadaan · Tahun · Kategori · Sub Kategori"
                           readonly>
                    <div class="form-text">
                        Prefix nomor diambil dari master data, bukan teks bebas — agar nomor inventaris yang sudah ada tidak rusak.
                    </div>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sequence_padding">Sequence Padding</label>
                    <input type="number" id="sequence_padding" name="sequence_padding"
                           class="form-control @error('sequence_padding') is-invalid @enderror"
                           value="{{ old('sequence_padding', $values['sequence_padding']) }}"
                           min="1" max="12" required>
                    <div class="form-text">
                        Jumlah digit urutan (contoh: 6 → <code>000001</code>).
                        Perubahan hanya berlaku untuk nomor <strong>baru</strong>; data lama tidak diubah.
                    </div>
                    @error('sequence_padding')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="scan_base_url">Scan Base URL</label>
                    <input type="url" id="scan_base_url" name="scan_base_url"
                           class="form-control @error('scan_base_url') is-invalid @enderror"
                           value="{{ old('scan_base_url', $values['scan_base_url']) }}"
                           placeholder="{{ $values['app_url'] }}" maxlength="255">
                    <div class="form-text">
                        Kosongkan untuk memakai <code>APP_URL</code> otomatis.
                        Saat ini: <code>{{ $values['scan_base_resolved'] }}</code>
                    </div>
                    @error('scan_base_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>
        <div class="card-footer bg-transparent d-flex justify-content-end">
            <button type="submit" class="btn btn-undira">
                <i class="fas fa-save me-1"></i>Simpan Pengaturan
            </button>
        </div>
    </div>
</form>

@can('backup.manage')
{{-- ========== Backup Database ========== --}}
<div class="card card-undira mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-database me-2"></i>Backup Database</h5>
    </div>
    <div class="card-body">
        @if(! $isSqlite)
            <div class="alert alert-warning mb-0">
                Backup/restore otomatis saat ini hanya tersedia untuk database <strong>SQLite</strong>.
                Driver aktif: <code>{{ $about['database'] }}</code>.
            </div>
        @else
            @if($backupError)
                <div class="alert alert-danger">{{ $backupError }}</div>
            @endif

            <div class="d-flex flex-wrap gap-2 mb-3">
                <form action="{{ route('admin.settings.backup') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-undira btn-sm" data-no-loader="1">
                        <i class="fas fa-download me-1"></i>Generate &amp; Download Backup
                    </button>
                </form>
            </div>

            @if($backups->isNotEmpty())
            <div class="table-responsive mb-3">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Ukuran</th>
                            <th>Tanggal</th>
                            <th class="text-end">Unduh</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($backups as $backup)
                        <tr>
                            <td><code>{{ $backup['name'] }}</code></td>
                            <td>{{ number_format(($backup['size'] ?? 0) / 1024, 1) }} KB</td>
                            <td>{{ \Illuminate\Support\Carbon::createFromTimestamp($backup['modified'])->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</td>
                            <td class="text-end">
                                <a href="{{ route('admin.settings.backup.download', $backup['name']) }}"
                                   class="btn btn-outline-primary btn-sm" data-no-loader="1" title="Download backup">
                                    <i class="fas fa-download"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
                <p class="text-muted small">Belum ada file backup. Klik tombol di atas untuk membuat cadangan pertama.</p>
            @endif

            <hr>

            <form action="{{ route('admin.settings.restore') }}" method="POST" enctype="multipart/form-data" class="row g-3">
                @csrf
                <div class="col-12">
                    <h6 class="mb-0">Restore Backup</h6>
                    <p class="text-muted small mb-0">Restore akan mengganti seluruh database aktif. Pastikan Anda sudah membuat backup terbaru.</p>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="backup_file">Pilih dari daftar</label>
                    <select name="backup_file" id="backup_file" class="form-select @error('backup_file') is-invalid @enderror">
                        <option value="">— Pilih file —</option>
                        @foreach($backups as $backup)
                            <option value="{{ $backup['name'] }}" @selected(old('backup_file') === $backup['name'])>{{ $backup['name'] }}</option>
                        @endforeach
                    </select>
                    @error('backup_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="backup_upload">Atau unggah file .sqlite</label>
                    <input type="file" name="backup_upload" id="backup_upload"
                           class="form-control @error('backup_upload') is-invalid @enderror"
                           accept=".sqlite,.db">
                    @error('backup_upload')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-outline-warning"
                            data-confirm="Yakin restore database? Data saat ini akan diganti dengan isi backup."
                            data-confirm-title="Restore Database"
                            data-confirm-button="Ya, Restore"
                            data-confirm-color="#d97706">
                        <i class="fas fa-upload me-1"></i>Restore Database
                    </button>
                </div>
            </form>
            <p class="text-muted small mt-3 mb-0">
                Hanya file SQLite valid yang diterima. Nama file di server harus cocok pola
                <code>backup_*.sqlite</code>.
            </p>
        @endif
    </div>
</div>
@endcan

{{-- ========== Tentang Aplikasi ========== --}}
<div class="card card-undira mb-4">
    <div class="card-header">
        <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i>Tentang Aplikasi</h5>
    </div>
    <div class="card-body">
        <dl class="row mb-0 undira-settings-about">
            <dt class="col-sm-4">Developed by</dt>
            <dd class="col-sm-8">{{ $about['developer'] }}</dd>
            <dt class="col-sm-4">Version</dt>
            <dd class="col-sm-8">{{ $about['version'] }}</dd>
            <dt class="col-sm-4">Laravel Version</dt>
            <dd class="col-sm-8"><code>{{ $about['laravel'] }}</code></dd>
            <dt class="col-sm-4">PHP Version</dt>
            <dd class="col-sm-8"><code>{{ $about['php'] }}</code></dd>
            <dt class="col-sm-4">Database Driver</dt>
            <dd class="col-sm-8"><code>{{ $about['database'] }}</code></dd>
        </dl>
    </div>
</div>
@endsection

@push('styles')
<style>
.undira-settings-logo-preview {
    max-height: 48px;
    max-width: 180px;
    object-fit: contain;
    background: #fff;
    border: 1px solid var(--ui-border, #e2e8f0);
    border-radius: 8px;
    padding: 4px 8px;
}
.undira-settings-favicon-preview {
    width: 32px;
    height: 32px;
    object-fit: contain;
    border-radius: 6px;
    border: 1px solid var(--ui-border, #e2e8f0);
}
.undira-settings-about dt {
    font-weight: 600;
    color: var(--text-heading, #0f172a);
}
.undira-settings-about dd {
    margin-bottom: 0.65rem;
}
</style>
@endpush
