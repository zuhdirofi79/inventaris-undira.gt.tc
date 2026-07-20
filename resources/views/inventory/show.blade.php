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
            @if($inventory->group_key)
                &middot; <a href="{{ route('inventory.group-units', $inventory->group_key) }}">Lihat semua unit</a>
            @endif
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        @can('borrowing.create')
        <a href="{{ route('borrowings.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-primary btn-sm"><i class="fas fa-hand-holding me-1"></i>Pinjam</a>
        @endcan
        @can('maintenance.create')
        <a href="{{ route('maintenances.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-info btn-sm"><i class="fas fa-wrench me-1"></i>Maintenance</a>
        @endcan
        @can('mutation.create')
        <a href="{{ route('mutations.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-secondary btn-sm"><i class="fas fa-exchange-alt me-1"></i>Mutasi</a>
        @endcan
        @can('inventory.view')
        <a href="{{ route('scanner.index') }}" class="btn btn-outline-dark btn-sm"><i class="fas fa-barcode me-1"></i>Scan Lagi</a>
        @endcan
        @can('inventory.update')
        <a href="{{ route('inventory.edit', $inventory) }}" class="btn btn-warning btn-sm"><i class="fas fa-edit me-1"></i>Edit</a>
        @endcan
        @can('inventory.delete')
        <form action="{{ route('inventory.destroy', $inventory) }}" method="POST" data-confirm="Yakin ingin menghapus inventaris ini?">
            @csrf @method('DELETE')
            <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-trash me-1"></i>Hapus</button>
        </form>
        @endcan
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Detail Inventaris</h5></div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="table-responsive">
                        <table class="table table-sm">
                            <tr><th width="40%">No. Inventaris</th><td class="text-break">{{ $inventory->inventory_number }}</td></tr>
                            <tr><th>Barcode</th><td class="text-break">{{ $inventory->barcode ?? '-' }}</td></tr>
                            <tr><th>Kategori</th><td>{{ $inventory->category?->name ?? '-' }}</td></tr>
                            <tr><th>Sub Kategori</th><td>{{ $inventory->subCategory?->name ?? '-' }}</td></tr>
                            <tr><th>Kampus</th><td>{{ $inventory->room?->floor?->building?->campus?->name ?? '-' }}</td></tr>
                            <tr><th>Gedung</th><td>{{ $inventory->room?->floor?->building?->name ?? '-' }}</td></tr>
                            <tr><th>Lantai</th><td>{{ $inventory->room?->floor?->name ?? '-' }}</td></tr>
                            <tr><th>Ruangan</th><td>{{ $inventory->room?->name ?? '-' }}</td></tr>
                            <tr><th>Jumlah / Satuan</th><td>1 {{ $inventory->unit ?? 'Unit' }} (1 aset = 1 record)</td></tr>
                            <tr><th>Merek / Tipe</th><td>{{ $inventory->brand ?? '-' }} / {{ $inventory->type ?? '-' }}</td></tr>
                            <tr><th>Model</th><td>{{ $inventory->model ?? '-' }}</td></tr>
                            <tr><th>No. Seri</th><td>{{ $inventory->serial_number ?? '-' }}</td></tr>
                        </table>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="table-responsive">
                        <table class="table table-sm">
                            <tr><th width="40%">Kondisi</th><td><span class="badge bg-info">{{ $inventory->condition?->label() }}</span></td></tr>
                            <tr><th>Status</th><td><span class="badge bg-secondary">{{ $inventory->status?->label() }}</span></td></tr>
                            <tr><th>Harga</th><td>Rp {{ number_format($inventory->price ?? 0, 0, ',', '.') }}</td></tr>
                            <tr><th>Tahun Beli</th><td>{{ $inventory->purchase_year ?? '-' }}</td></tr>
                            <tr><th>Tanggal Beli</th><td>{{ $inventory->purchase_date?->format('d/m/Y') ?? '-' }}</td></tr>
                            <tr><th>Unit / Divisi</th><td>{{ $inventory->orgUnit ? $inventory->orgUnit->code.' — '.$inventory->orgUnit->name : '-' }}</td></tr>
                            <tr><th>Jenis Pengadaan</th><td>{{ $inventory->procurementType ? $inventory->procurementType->code.' — '.$inventory->procurementType->name : ($inventory->funding_source ?? '-') }}</td></tr>
                            <tr><th>Sumber Dana</th><td>{{ $inventory->funding_source ?? '-' }}</td></tr>
                            <tr><th>Supplier</th><td>{{ $inventory->supplier?->name ?? '-' }}</td></tr>
                            <tr><th>Vendor</th><td>{{ $inventory->vendor?->name ?? '-' }}</td></tr>
                            <tr><th>Penanggung Jawab</th><td>{{ $inventory->responsible_person ?? '-' }}</td></tr>
                            <tr><th>Garansi</th><td>{{ $inventory->warranty_info ?? '-' }} @if($inventory->warranty_expiry) (s/d {{ $inventory->warranty_expiry->format('d/m/Y') }}) @endif</td></tr>
                        </table>
                        </div>
                    </div>
                    @if($inventory->specification)
                    <div class="col-12">
                        <h6>Spesifikasi</h6>
                        <p class="text-muted">{{ $inventory->specification }}</p>
                    </div>
                    @endif
                    @if($inventory->notes)
                    <div class="col-12">
                        <h6>Catatan</h6>
                        <p class="text-muted">{{ $inventory->notes }}</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @if($inventory->photos->isNotEmpty())
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-images me-2"></i>Galeri Foto</h5></div>
            <div class="card-body photo-gallery">
                <div class="row g-2">
                    @foreach($inventory->photos as $photo)
                    <div class="col-md-3 col-6">
                        <img src="{{ asset('storage/' . $photo->path) }}" class="img-fluid" alt="Foto inventaris">
                        @can('inventory.update')
                        <form action="{{ route('inventory.photos.destroy', [$inventory, $photo]) }}" method="POST" class="mt-1" data-confirm="Hapus foto ini?">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-xs btn-danger btn-sm w-100"><i class="fas fa-trash"></i></button>
                        </form>
                        @endcan
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

        @can('inventory.update')
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Upload Foto</h5></div>
            <div class="card-body">
                <form action="{{ route('inventory.photos.store', $inventory) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="file" name="photo" class="form-control mb-2" accept="image/*" required>
                    <button type="submit" class="btn btn-undira btn-sm"><i class="fas fa-upload me-1"></i>Upload</button>
                </form>
            </div>
        </div>
        @endcan

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Riwayat Mutasi</h5></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th></tr></thead>
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
                        <tr><td colspan="5" class="text-center text-muted">Belum ada mutasi</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira mb-4 text-center">
            <div class="card-header"><h5 class="mb-0">QR Code & Barcode</h5></div>
            <div class="card-body">
                @if($inventory->qr_url)
                    <img src="{{ $inventory->qr_url }}" alt="QR Code" class="img-fluid mb-3" style="max-width:200px;">
                @else
                    <div class="text-muted mb-3"><i class="fas fa-qrcode fa-4x"></i><br>QR belum tersedia</div>
                @endif
                @if($inventory->barcode)
                    <div class="mb-2"><strong>Barcode:</strong> {{ $inventory->barcode }}</div>
                @endif
                <a href="{{ route('inventory.scan', $inventory->inventory_number) }}" target="_blank" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-external-link-alt me-1"></i>Lihat Halaman Scan
                </a>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Info Sistem</h5></div>
            <div class="card-body small">
                <p class="mb-1"><strong>Dibuat:</strong> {{ $inventory->created_at->format('d/m/Y H:i') }} oleh {{ $inventory->creator?->name ?? '-' }}</p>
                <p class="mb-1"><strong>Diperbarui:</strong> {{ $inventory->updated_at->format('d/m/Y H:i') }} oleh {{ $inventory->updater?->name ?? '-' }}</p>
                <p class="mb-0"><strong>Unit ke:</strong> {{ $inventory->unit_sequence ?? 1 }}</p>
            </div>
        </div>

        @if($inventory->borrowings->isNotEmpty())
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Peminjaman</h5></div>
            <ul class="list-group list-group-flush">
                @foreach($inventory->borrowings->take(5) as $borrowing)
                <li class="list-group-item small">
                    {{ $borrowing->borrower_name }} &middot; {{ $borrowing->status?->label() }}
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        @if($inventory->maintenances->isNotEmpty())
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Maintenance</h5></div>
            <ul class="list-group list-group-flush">
                @foreach($inventory->maintenances->take(5) as $maint)
                <li class="list-group-item small">
                    {{ $maint->service_date?->format('d/m/Y') }} &middot; {{ $maint->status?->label() }}
                </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
