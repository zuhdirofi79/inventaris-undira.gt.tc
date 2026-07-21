@extends('layouts.app')

@section('title', $inventory->inventory_number . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    @if($inventory->category)
    <li class="breadcrumb-item"><a href="{{ route('inventory.category', $inventory->category) }}">{{ $inventory->category->name }}</a></li>
    @endif
    <li class="breadcrumb-item active">{{ $inventory->inventory_number }}</li>
@endsection

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-start flex-wrap gap-2">
    <div class="min-w-0">
        <h1 class="text-break"><i class="fas fa-box me-2"></i>{{ $inventory->name }}</h1>
        <p class="text-muted mb-0">
            <code class="text-break">{{ $inventory->inventory_number }}</code>
            <span class="badge bg-info ms-1">{{ $inventory->condition?->label() }}</span>
            <span class="badge bg-secondary ms-1">{{ $inventory->status?->label() }}</span>
            @if($inventory->group_key)
                &middot; <a href="{{ route('inventory.group-units', $inventory->group_key) }}">Lihat semua unit</a>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('borrowing.create')
        <a href="{{ route('borrowings.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-primary btn-sm" title="Buat peminjaman"><i class="fas fa-hand-holding me-1"></i>Pinjam</a>
        @endcan
        @can('maintenance.create')
        <a href="{{ route('maintenances.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-info btn-sm" title="Jadwalkan maintenance"><i class="fas fa-wrench me-1"></i>Maintenance</a>
        @endcan
        @can('mutation.create')
        <a href="{{ route('mutations.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-secondary btn-sm" title="Pindahkan lokasi"><i class="fas fa-exchange-alt me-1"></i>Mutasi</a>
        @endcan
        @can('inventory.update')
        <a href="{{ route('inventory.edit', $inventory) }}" class="btn btn-warning btn-sm" title="Ubah data"><i class="fas fa-edit me-1"></i>Edit</a>
        @endcan
        @can('inventory.delete')
        <form action="{{ route('inventory.destroy', $inventory) }}" method="POST" data-confirm="Yakin ingin menghapus inventaris ini?">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="fas fa-trash me-1"></i>Hapus</button>
        </form>
        @endcan
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-info-circle me-2 text-undira-green"></i>Informasi Umum</h5></div>
            <div class="card-body">
                <div class="row g-3 detail-grid">
                    <div class="col-md-6"><div class="detail-label">No. Inventaris</div><div class="detail-value text-break"><code>{{ $inventory->inventory_number }}</code></div></div>
                    <div class="col-md-6"><div class="detail-label">Barcode</div><div class="detail-value text-break">{{ $inventory->barcode ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Kategori</div><div class="detail-value">{{ $inventory->category?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Sub Kategori</div><div class="detail-value">{{ $inventory->subCategory?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Merek / Tipe</div><div class="detail-value">{{ $inventory->brand ?? '-' }} / {{ $inventory->type ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Model</div><div class="detail-value">{{ $inventory->model ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">No. Seri</div><div class="detail-value">{{ $inventory->serial_number ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Jumlah / Satuan</div><div class="detail-value">1 {{ $inventory->unit ?? 'Unit' }}</div></div>
                    @if($inventory->specification)
                    <div class="col-12"><div class="detail-label">Spesifikasi</div><div class="detail-value text-muted">{{ $inventory->specification }}</div></div>
                    @endif
                    @if($inventory->notes)
                    <div class="col-12"><div class="detail-label">Catatan</div><div class="detail-value text-muted">{{ $inventory->notes }}</div></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-map-marker-alt me-2 text-undira-blue"></i>Lokasi</h5></div>
            <div class="card-body">
                <div class="row g-3 detail-grid">
                    <div class="col-md-6"><div class="detail-label">Kampus</div><div class="detail-value">{{ $inventory->room?->floor?->building?->campus?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Gedung</div><div class="detail-value">{{ $inventory->room?->floor?->building?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Lantai</div><div class="detail-value">{{ $inventory->room?->floor?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Ruangan</div><div class="detail-value">{{ $inventory->room?->name ?? '-' }} <code class="small">{{ $inventory->room?->code }}</code></div></div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-file-invoice-dollar me-2 text-undira-green"></i>Pengadaan</h5></div>
            <div class="card-body">
                <div class="row g-3 detail-grid">
                    <div class="col-md-6"><div class="detail-label">Harga</div><div class="detail-value fw-semibold">Rp {{ number_format($inventory->price ?? 0, 0, ',', '.') }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Tahun / Tanggal Beli</div><div class="detail-value">{{ $inventory->purchase_year ?? '-' }} / {{ $inventory->purchase_date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Unit / Divisi</div><div class="detail-value">{{ $inventory->orgUnit ? $inventory->orgUnit->code.' — '.$inventory->orgUnit->name : '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Jenis Pengadaan</div><div class="detail-value">{{ $inventory->procurementType ? $inventory->procurementType->code.' — '.$inventory->procurementType->name : ($inventory->funding_source ?? '-') }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Sumber Dana</div><div class="detail-value">{{ $inventory->funding_source ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Supplier</div><div class="detail-value">{{ $inventory->supplier?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Vendor</div><div class="detail-value">{{ $inventory->vendor?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Penanggung Jawab</div><div class="detail-value">{{ $inventory->responsible_person ?? '-' }}</div></div>
                    <div class="col-12"><div class="detail-label">Garansi</div><div class="detail-value">{{ $inventory->warranty_info ?? '-' }} @if($inventory->warranty_expiry) <span class="text-muted">(s/d {{ $inventory->warranty_expiry->format('d/m/Y') }})</span> @endif</div></div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-heartbeat me-2 text-undira-blue"></i>Status</h5></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <span class="badge bg-info fs-6">Kondisi: {{ $inventory->condition?->label() }}</span>
                    <span class="badge bg-secondary fs-6">Status: {{ $inventory->status?->label() }}</span>
                </div>
            </div>
        </div>

        @if($inventory->photos->isNotEmpty())
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-images me-2"></i>Galeri Foto</h5></div>
            <div class="card-body photo-gallery">
                <div class="row g-2">
                    @foreach($inventory->photos as $photo)
                    <div class="col-md-3 col-6">
                        <img src="{{ asset('storage/' . $photo->path) }}" class="img-fluid rounded border" alt="Foto inventaris">
                        @can('inventory.update')
                        <form action="{{ route('inventory.photos.destroy', [$inventory, $photo]) }}" method="POST" class="mt-1" data-confirm="Hapus foto ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm w-100"><i class="fas fa-trash"></i></button>
                        </form>
                        @endcan
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @can('inventory.update')
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-upload me-2"></i>Upload Foto</h5></div>
            <div class="card-body">
                <form action="{{ route('inventory.photos.store', $inventory) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end">
                    @csrf
                    <div class="col-md-8">
                        <label class="form-label">Pilih gambar</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-undira w-100"><i class="fas fa-upload me-1"></i>Upload</button>
                    </div>
                </form>
            </div>
        </div>
        @endcan

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-history me-2"></i>Riwayat Aktivitas</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0 table-undira">
                        <thead>
                            <tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th></tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->mutations as $mutation)
                            <tr>
                                <td>{{ $mutation->created_at->format('d/m/Y H:i') }}</td>
                                <td><small>{{ $mutation->fromRoom?->full_location ?? '-' }}</small></td>
                                <td><small>{{ $mutation->toRoom?->full_location ?? '-' }}</small></td>
                                <td>{{ $mutation->reason ?? '-' }}</td>
                                <td>{{ $mutation->movedBy?->name ?? '-' }}</td>
                            </tr>
                            @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">Belum ada riwayat mutasi</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira mb-3 text-center">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code</h5></div>
            <div class="card-body">
                @if($inventory->qr_url)
                    <img src="{{ $inventory->qr_url }}" alt="QR Code" class="img-fluid mb-2 qr-preview" style="max-width:200px;">
                @else
                    <div class="empty-state py-3"><i class="fas fa-qrcode fa-3x text-muted mb-2"></i><div class="text-muted">QR belum tersedia</div></div>
                @endif
                @if($inventory->qr_url)
                <a href="{{ route('inventory.qr.download', $inventory) }}" class="btn btn-outline-success btn-sm w-100 mb-2"><i class="fas fa-download me-1"></i>Unduh QR</a>
                @endif
            </div>
        </div>

        <div class="card card-undira mb-3 text-center">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-barcode me-2"></i>Barcode</h5></div>
            <div class="card-body">
                @if($inventory->barcode_url)
                    <img src="{{ $inventory->barcode_url }}" alt="Barcode" class="img-fluid mb-2" style="max-width:100%;height:56px;object-fit:contain;">
                @endif
                @if($inventory->barcode)
                    <div class="mb-3"><code>{{ $inventory->barcode }}</code></div>
                @endif
                @if($inventory->barcode_url)
                <a href="{{ route('inventory.barcode.download', $inventory) }}" class="btn btn-outline-primary btn-sm w-100 mb-2"><i class="fas fa-download me-1"></i>Unduh Barcode</a>
                @endif
                <a href="{{ route('inventory.label.print', $inventory) }}" target="_blank" class="btn btn-secondary btn-sm w-100 mb-2"><i class="fas fa-print me-1"></i>Cetak Label</a>
                @can('inventory.update')
                <form action="{{ route('inventory.codes.regenerate', $inventory) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-undira btn-sm w-100"><i class="fas fa-sync-alt me-1"></i>Generate Ulang</button>
                </form>
                @endcan
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-server me-2"></i>Info Sistem</h5></div>
            <div class="card-body small">
                <p class="mb-1"><strong>Dibuat:</strong> {{ $inventory->created_at->format('d/m/Y H:i') }} oleh {{ $inventory->creator?->name ?? '-' }}</p>
                <p class="mb-1"><strong>Diperbarui:</strong> {{ $inventory->updated_at->format('d/m/Y H:i') }} oleh {{ $inventory->updater?->name ?? '-' }}</p>
                <p class="mb-0"><strong>Unit ke:</strong> {{ $inventory->unit_sequence ?? 1 }}</p>
            </div>
        </div>

        @if($inventory->borrowings->isNotEmpty())
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Peminjaman</h5></div>
            <ul class="list-group list-group-flush">
                @foreach($inventory->borrowings->take(5) as $borrowing)
                <li class="list-group-item small d-flex justify-content-between">
                    <span>{{ $borrowing->borrower_name }}</span>
                    <span class="badge bg-warning text-dark">{{ $borrowing->status?->label() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($inventory->maintenances->isNotEmpty())
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Maintenance</h5></div>
            <ul class="list-group list-group-flush">
                @foreach($inventory->maintenances->take(5) as $maint)
                <li class="list-group-item small d-flex justify-content-between">
                    <span>{{ $maint->service_date?->format('d/m/Y') }}</span>
                    <span class="badge bg-info text-dark">{{ $maint->status?->label() }}</span>
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
