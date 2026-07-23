@extends('layouts.app')

@section('title', 'Trash - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Trash</li>
@endsection

@section('content')
<x-ui.page-header
    title="Trash"
    subtitle="Recycle Bin — akses berdasarkan izin Trash. Soft-deleted data dapat dipulihkan atau dihapus permanen."
    icon="bi bi-trash"
>
    <span class="badge bg-secondary align-self-center">Recycle Bin</span>
</x-ui.page-header>

<ul class="nav nav-tabs mb-3 flex-wrap">
    @foreach($tabLabels as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}"
               href="{{ route('trash.index', array_merge(request()->except('page'), ['tab' => $key])) }}">
                {{ $label }}
                <span class="badge bg-secondary">{{ $counts[$key] ?? 0 }}</span>
            </a>
        </li>
    @endforeach
</ul>

<div class="card card-undira mb-3 border-secondary">
    <div class="card-body bg-light">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div class="col-md-3">
                <label class="form-label">Pencarian</label>
                <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Nama / kode...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Dihapus oleh</label>
                <select name="deleted_by" class="form-select">
                    <option value="">Semua</option>
                    @foreach($deleters as $user)
                        <option value="{{ $user->id }}" @selected(request('deleted_by') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dari tanggal</label>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai tanggal</label>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-search me-1"></i>Filter</button>
                <a href="{{ route('trash.index', ['tab' => $tab]) }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card card-undira border-secondary">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-secondary">
                    <tr>
                        <th>Nama</th>
                        <th>Lokasi / Info</th>
                        <th>Tanggal dihapus</th>
                        <th>Dihapus oleh</th>
                        <th>Status</th>
                        <th style="width:180px">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        @php
                            $name = match($tab) {
                                'konfigurasi' => $item->generated_location_code ?: ('Config #'.$item->id),
                                'inventaris' => $item->name,
                                default => $item->name ?? ('#'.$item->id),
                            };
                            $info = match($tab) {
                                'inventaris' => trim(($item->inventory_number ?? '').' · '.($item->category?->name ?? '-').' · '.($item->full_location ?? '-')),
                                'konfigurasi' => collect([$item->campus?->name, $item->building?->name, $item->floor?->name, $item->room?->name, $item->category?->name])->filter()->implode(' / '),
                                'gedung' => $item->campus?->name ?? '-',
                                'lantai' => trim(($item->building?->campus?->name ?? '').' / '.($item->building?->name ?? '')),
                                'ruangan' => $item->full_location ?? '-',
                                'sub_kategori' => $item->category?->name ?? '-',
                                'supplier', 'vendor' => $item->email ?? ($item->phone ?? '-'),
                                default => $item->code ?? '-',
                            };
                            $deletedHuman = $item->deleted_at
                                ? 'Deleted '.$item->deleted_at->diffForHumans(null, true).' lalu'
                                : '-';
                        @endphp
                        <tr class="text-secondary">
                            <td>
                                <strong>{{ $name }}</strong>
                                @if(!empty($item->code) && !in_array($tab, ['inventaris', 'konfigurasi'], true))
                                    <div class="small">{{ $item->code }}</div>
                                @endif
                            </td>
                            <td><small>{{ $info }}</small></td>
                            <td>
                                <div>{{ $item->deleted_at?->format('d/m/Y H:i') }}</div>
                                <span class="badge bg-secondary">{{ $deletedHuman }}</span>
                            </td>
                            <td>{{ $item->deletedByUser?->name ?? '-' }}</td>
                            <td><span class="badge bg-danger">Terhapus</span></td>
                            <td class="text-nowrap">
                                @can('trash.restore')
                                <form action="{{ route('trash.restore', [$tab, $item->id]) }}" method="POST" class="d-inline"
                                      data-confirm-title="Pulihkan data ini?"
                                      data-confirm="Data dan relasi turunannya akan kembali ke sistem."
                                      data-confirm-button="Pulihkan"
                                      data-confirm-color="#2E7D32">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success" title="Restore">
                                        <i class="fas fa-recycle"></i>
                                    </button>
                                </form>
                                @endcan
                                @can('trash.force-delete')
                                <form action="{{ route('trash.force-delete', [$tab, $item->id]) }}" method="POST" class="d-inline"
                                      data-confirm-title="Data akan dihapus permanen."
                                      data-confirm="Data TIDAK dapat dikembalikan. Lanjutkan?"
                                      data-confirm-button="Hapus Permanen"
                                      data-confirm-color="#c62828">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete Permanently">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">Trash kosong untuk tab ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($items->hasPages())
        <div class="card-footer">{{ $items->links() }}</div>
    @endif
</div>
@endsection
