@extends('layouts.app')

@section('title', $inventory->inventory_number . ' - Inventaris UNDIRA')

@section('content')
@php
    $showBreadcrumbs = [
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Data Inventaris', 'url' => route('inventory.items')],
    ];
    if ($inventory->category) {
        $showBreadcrumbs[] = ['label' => $inventory->category->name, 'url' => route('inventory.category', $inventory->category)];
    }
    $showBreadcrumbs[] = ['label' => $inventory->inventory_number];
@endphp
<x-page-header
    :title="$inventory->name"
    icon="bi bi-box-seam"
    :breadcrumbs="$showBreadcrumbs"
    :back-route="route('inventory.items')"
    back-label="Data Inventaris"
>
    <x-slot:meta>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <code class="text-break">{{ $inventory->inventory_number }}</code>
            <x-inventory.condition-badge :condition="$inventory->condition" />
            <x-inventory.status-badge :status="$inventory->status" />
            @if($inventory->group_key)
                <span>&middot; <a href="{{ route('inventory.group-units', $inventory->group_key) }}">Lihat semua unit</a></span>
            @endif
        </div>
    </x-slot:meta>
    @can('borrowing.create')
    <a href="{{ route('borrowings.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-primary btn-sm" title="Buat peminjaman"><i class="bi bi-hand-index me-1"></i>Pinjam</a>
    @endcan
    @can('maintenance.create')
    <a href="{{ route('maintenances.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-info btn-sm" title="Jadwalkan maintenance"><i class="bi bi-wrench-adjustable me-1"></i>Maintenance</a>
    @endcan
    @can('mutation.create')
    <a href="{{ route('mutations.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-outline-secondary btn-sm" title="Pindahkan lokasi"><i class="bi bi-arrow-left-right me-1"></i>Mutasi</a>
    @endcan
    @can('inventory.update')
    <a href="{{ route('inventory.edit', $inventory) }}" class="btn btn-warning btn-sm" title="Ubah data"><i class="bi bi-pencil-square me-1"></i>Edit</a>
    @endcan
    @can('inventory.delete')
    <form action="{{ route('inventory.destroy', $inventory) }}" method="POST"
          data-confirm-title="Pindahkan data ke Trash?"
          data-confirm="Data masih dapat dipulihkan dari menu Trash."
          data-confirm-button="Pindahkan ke Trash">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-danger btn-sm" title="Hapus"><i class="bi bi-trash me-1"></i>Hapus</button>
    </form>
    @endcan
    @cannot('inventory.update')
        @cannot('maintenance.create')
            @cannot('mutation.create')
            <span class="badge text-bg-secondary align-self-center">Read Only</span>
            @endcannot
        @endcannot
    @endcannot
</x-page-header>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-info-circle me-2 text-undira-green"></i>Informasi Umum</h5></div>
            <div class="card-body">
                <div class="row g-3 detail-grid">
                    <div class="col-md-6"><div class="detail-label">No. Inventaris</div><div class="detail-value text-break"><code>{{ $inventory->inventory_number }}</code></div></div>
                    <div class="col-md-6"><div class="detail-label">Unit Kerja</div><div class="detail-value">{{ $inventory->orgUnit?->name ?? '-' }}</div></div>
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
                    <div class="col-md-6"><div class="detail-label">Tahun / Tanggal Beli</div><div class="detail-value">{{ $inventory->purchase_year ?? '-' }} / {{ $inventory->purchase_date?->format('d/m/Y') ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Unit / Divisi</div><div class="detail-value">{{ $inventory->orgUnit ? $inventory->orgUnit->code.' — '.$inventory->orgUnit->name : '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Jenis Pengadaan</div><div class="detail-value">{{ $inventory->procurementType ? $inventory->procurementType->code.' — '.$inventory->procurementType->name : ($inventory->funding_source ?? '-') }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Sumber Dana</div><div class="detail-value">{{ $inventory->funding_source ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Supplier</div><div class="detail-value">{{ $inventory->supplier?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">Vendor</div><div class="detail-value">{{ $inventory->vendor?->name ?? '-' }}</div></div>
                    <div class="col-md-6"><div class="detail-label">PIC</div><div class="detail-value">{{ $inventory->picDisplayName() ?? 'Belum ditentukan' }}</div></div>
                    <div class="col-12"><div class="detail-label">Garansi</div><div class="detail-value">{{ $inventory->warranty_info ?? '-' }} @if($inventory->warranty_expiry) <span class="text-muted">(s/d {{ $inventory->warranty_expiry->format('d/m/Y') }})</span> @endif</div></div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-heartbeat me-2 text-undira-blue"></i>Status Barang</h5></div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <x-inventory.condition-badge :condition="$inventory->condition" class="fs-6" />
                    <x-inventory.status-badge :status="$inventory->status" class="fs-6" />
                    @can('inventory.update')
                    <a href="{{ route('inventory.edit', $inventory) }}" class="btn btn-sm btn-outline-warning ms-auto">Ubah status / kondisi</a>
                    @endcan
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-images me-2"></i>Galeri Foto</h5>
                <span class="badge bg-secondary">{{ $inventory->photos->count() }} foto</span>
            </div>
            <div class="card-body">
                @if($inventory->photos->isNotEmpty())
                <div class="row g-2 photo-gallery mb-3">
                    @foreach($inventory->photos as $photo)
                    <div class="col-md-3 col-6">
                        <div class="position-relative border rounded overflow-hidden bg-light">
                            <img src="{{ asset('storage/' . $photo->path) }}" class="img-fluid w-100" alt="Foto inventaris" style="aspect-ratio:1;object-fit:cover;">
                            @if($photo->is_primary)
                                <span class="badge bg-success position-absolute top-0 start-0 m-1">Utama</span>
                            @endif
                        </div>
                        @can('inventory.update')
                        <div class="d-flex gap-1 mt-1">
                            @unless($photo->is_primary)
                            <form action="{{ route('inventory.photos.primary', [$inventory, $photo]) }}" method="POST" class="flex-grow-1">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm w-100" title="Jadikan utama">Utama</button>
                            </form>
                            @endunless
                            <form action="{{ route('inventory.photos.destroy', [$inventory, $photo]) }}" method="POST" class="flex-grow-1" data-confirm="Hapus foto ini?">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-outline-danger btn-sm w-100" title="Hapus"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                        @endcan
                    </div>
                    @endforeach
                </div>
                @else
                <x-empty-state icon="fa-images" title="Belum ada foto" message="Unggah foto barang untuk dokumentasi visual inventaris." />
                @endif

                @can('inventory.update')
                <form action="{{ route('inventory.photos.store', $inventory) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end border-top pt-3">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label" for="inventoryPhotos">Pilih gambar (bisa lebih dari satu)</label>
                        <input type="file" name="photos[]" id="inventoryPhotos" class="form-control" accept="image/*" multiple required>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check mt-4">
                            <input type="hidden" name="is_primary" value="0">
                            <input type="checkbox" name="is_primary" value="1" class="form-check-input" id="photoIsPrimary">
                            <label class="form-check-label" for="photoIsPrimary">Jadikan foto pertama sebagai utama</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-undira w-100" data-loading-text="Mengunggah..."><i class="fas fa-upload me-1"></i>Unggah Foto</button>
                    </div>
                </form>
                @endcan
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-paperclip me-2"></i>Lampiran</h5>
                <span class="badge bg-secondary">{{ $inventory->attachments->count() }} berkas</span>
            </div>
            <div class="card-body">
                @if($inventory->attachments->isNotEmpty())
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr><th>Nama berkas</th><th>Jenis</th><th>Diunggah</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach($inventory->attachments as $attachment)
                            <tr>
                                <td class="text-break">
                                    <a href="{{ $attachment->url }}" target="_blank" rel="noopener" data-no-loader="1">
                                        <i class="fas fa-file me-1"></i>{{ $attachment->original_name }}
                                    </a>
                                </td>
                                <td><span class="badge bg-light text-dark border">{{ $attachment->typeLabel() }}</span></td>
                                <td>{{ $attachment->created_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-end">
                                    @can('inventory.update')
                                    <form action="{{ route('inventory.attachments.destroy', [$inventory, $attachment]) }}" method="POST" class="d-inline" data-confirm="Hapus lampiran ini?">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Hapus"><i class="fas fa-trash"></i></button>
                                    </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <x-empty-state icon="fa-paperclip" title="Belum ada lampiran" message="Unggah invoice atau dokumen pendukung inventaris." />
                @endif

                @can('inventory.update')
                <form action="{{ route('inventory.attachments.store', $inventory) }}" method="POST" enctype="multipart/form-data" class="row g-2 align-items-end border-top pt-3">
                    @csrf
                    <div class="col-md-5">
                        <label class="form-label" for="attachmentFile">Berkas</label>
                        <input type="file" name="file" id="attachmentFile" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="attachmentType">Jenis</label>
                        <select name="type" id="attachmentType" class="form-select" required>
                            <option value="invoice">Invoice</option>
                            <option value="manual_book">Manual Book</option>
                            <option value="warranty">Garansi</option>
                            <option value="document">Dokumen Pendukung</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-undira w-100" data-loading-text="Mengunggah..."><i class="fas fa-upload me-1"></i>Unggah</button>
                    </div>
                </form>
                @endcan
            </div>
        </div>

        <div class="card card-undira mb-3" id="timeline">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Timeline Inventaris</h5></div>
            <div class="card-body">
                @forelse($timeline ?? [] as $event)
                <div class="d-flex gap-3 mb-3 pb-3 border-bottom">
                    <div class="text-center" style="min-width:5.5rem;">
                        <div class="fw-semibold small">{{ $event['date_label'] }}</div>
                        <div class="text-muted" style="font-size:.75rem;">{{ $event['time_label'] }}</div>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <i class="bi {{ $event['icon'] }} text-{{ $event['tone'] }}"></i>
                            <strong>{{ $event['title'] }}</strong>
                        </div>
                        @if(!empty($event['detail']))
                            <div class="small text-muted">{{ $event['detail'] }}</div>
                        @endif
                        @if(!empty($event['actor']))
                            <div class="small mt-1">oleh <strong>{{ $event['actor'] }}</strong></div>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-muted mb-0">Belum ada riwayat aktivitas.</p>
                @endforelse
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-exchange-alt me-2"></i>Riwayat Mutasi</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0 table-undira">
                        <thead>
                            <tr><th>Tanggal</th><th>Dari</th><th>Ke</th><th>Alasan</th><th>Oleh</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->mutations as $mutation)
                            <tr>
                                <td>{{ $mutation->created_at->format('d/m/Y H:i') }}</td>
                                <td><small>{{ $mutation->fromRoom?->full_location ?? '-' }}</small></td>
                                <td><small>{{ $mutation->toRoom?->full_location ?? '-' }}</small></td>
                                <td>{{ $mutation->reason ?? '-' }}</td>
                                <td>{{ $mutation->movedBy?->name ?? '-' }}</td>
                                <td class="text-end">
                                    @can('mutation.view')
                                    <a href="{{ route('mutations.show', $mutation) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">Belum ada riwayat mutasi.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Riwayat Peminjaman</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0 table-undira">
                        <thead>
                            <tr><th>Peminjam</th><th>Pinjam</th><th>Kembali</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->borrowings as $borrowing)
                            <tr>
                                <td>{{ $borrowing->borrower_name }}</td>
                                <td>{{ $borrowing->borrow_date?->format('d/m/Y') ?? '-' }}</td>
                                <td>{{ $borrowing->expected_return_date?->format('d/m/Y') ?? '-' }}</td>
                                <td><span class="badge bg-warning text-dark">{{ $borrowing->status?->label() }}</span></td>
                                <td class="text-end">
                                    @can('borrowing.view')
                                    <a href="{{ route('borrowings.show', $borrowing) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">Belum ada riwayat peminjaman.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3" id="maintenance-schedules">
            <div class="card-header card-header-undira d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bi bi-calendar2-check me-2"></i>Jadwal Preventif</h5>
                @can('maintenance.create')
                <a href="{{ route('maintenance-schedules.create', ['inventory_item_id' => $inventory->id]) }}" class="btn btn-sm btn-outline-primary">Tambah Jadwal</a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0 table-undira">
                        <thead>
                            <tr><th>Jenis</th><th>Interval</th><th>Berikutnya</th><th>PIC</th><th>Status</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->maintenanceSchedules as $sched)
                            <tr>
                                <td>{{ $sched->maintenance_type?->label() }}</td>
                                <td>{{ $sched->intervalLabel() }}</td>
                                <td>{{ $sched->next_maintenance_date?->format('d/m/Y') }}</td>
                                <td>{{ $sched->responsible_pic ?: '—' }}</td>
                                <td>
                                    @if(! $sched->is_active)
                                        Nonaktif
                                    @elseif($sched->isOverdue())
                                        <span class="text-danger">Terlambat</span>
                                    @elseif($sched->isDueToday())
                                        <span class="text-warning">Hari ini</span>
                                    @else
                                        Aktif
                                    @endif
                                </td>
                                <td class="text-end">
                                    @can('maintenance.view')
                                    <a href="{{ route('maintenance-schedules.show', $sched) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">Belum ada jadwal preventif.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-3" id="maintenance-history">
            <div class="card-header card-header-undira d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-wrench me-2"></i>Maintenance History</h5>
                @can('maintenance.create')
                <a href="{{ route('maintenances.scan') }}" class="btn btn-sm btn-outline-info">Scan QR</a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover table-striped mb-0 table-undira">
                        <thead>
                            <tr>
                                <th>No. WO</th>
                                <th>Tanggal</th>
                                <th>Teknisi</th>
                                <th>Vendor</th>
                                <th>Jenis</th>
                                <th>Prioritas</th>
                                <th>Biaya</th>
                                <th>Status</th>
                                <th>Hasil</th>
                                <th>Foto</th>
                                <th>Selesai</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($inventory->maintenances as $maint)
                            <tr>
                                <td><code class="small">{{ $maint->maintenance_number ?? '—' }}</code></td>
                                <td>{{ $maint->service_date?->format('d/m/Y') ?? '—' }}</td>
                                <td>{{ $maint->technician ?: '—' }}</td>
                                <td>{{ $maint->vendor?->name ?? '—' }}</td>
                                <td>{{ $maint->maintenance_type?->label() ?? '—' }}</td>
                                <td>{{ $maint->priority?->label() ?? '—' }}</td>
                                <td>{{ $maint->cost ? 'Rp '.number_format($maint->cost, 0, ',', '.') : '—' }}</td>
                                <td><span class="badge bg-info text-dark">{{ $maint->status?->label() }}</span></td>
                                <td>{{ $maint->result?->label() ?? '—' }}</td>
                                <td class="small">
                                    B{{ $maint->photos->where('type','before')->count() }}
                                    /S{{ $maint->photos->where('type','during')->count() }}
                                    /A{{ $maint->photos->where('type','after')->count() }}
                                </td>
                                <td>{{ $maint->completed_at?->timezone(config('app.timezone'))->format('d/m/Y') ?? '—' }}</td>
                                <td class="text-end">
                                    @can('maintenance.view')
                                    <a href="{{ route('maintenances.show', $maint) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                                    @endcan
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">Belum ada riwayat pemeliharaan.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira mb-3 text-center" id="qr-section">
            <div class="card-header card-header-undira"><h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code & Label</h5></div>
            <div class="card-body">
                @if($inventory->qr_url)
                    <img src="{{ $inventory->qr_url }}" alt="QR Code" class="img-fluid mb-2 qr-preview" style="max-width:200px;">
                    <a href="{{ route('inventory.qr.download', $inventory) }}" class="btn btn-outline-success btn-sm w-100 mb-2" data-no-loader="1"><i class="fas fa-download me-1"></i>Unduh QR Code</a>
                @else
                    <div class="empty-state py-3"><i class="fas fa-qrcode fa-3x text-muted mb-2"></i><div class="text-muted">QR belum tersedia</div></div>
                @endif
                <a href="{{ route('inventory.label.print', $inventory) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm w-100 mb-2" data-no-loader="1"><i class="fas fa-print me-1"></i>Cetak Label</a>
                @can('inventory.update')
                <form action="{{ route('inventory.codes.regenerate', $inventory) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-undira btn-sm w-100" data-loading-text="Memproses..."><i class="fas fa-sync-alt me-1"></i>Generate Ulang QR</button>
                </form>
                @endcan
                <p class="text-muted small mt-2 mb-0">QR berisi URL halaman detail inventaris.</p>
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
    </div>
</div>
@endsection
