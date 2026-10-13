@extends('layouts.app')

@section('title', 'Riwayat Peminjaman - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Riwayat Peminjaman"
    subtitle="Inventaris, ruangan, dan kendaraan operasional dalam satu daftar."
    icon="bi bi-clock-history"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Riwayat Peminjaman'],
    ]"
/>

<div class="card card-undira">
    <div class="card-body">
        <form method="GET" action="{{ route('borrowing-records.index') }}" class="row g-3 align-items-end mb-3">
            <div class="col-12 col-lg-3">
                <label class="form-label" for="record-kind">Jenis Peminjaman</label>
                <select id="record-kind" name="kind" class="form-select">
                    <option value="">Semua Jenis</option>
                    <option value="inventaris" @selected($kind === 'inventaris')>Peminjaman Inventaris</option>
                    <option value="ruangan" @selected($kind === 'ruangan')>Peminjaman Ruangan</option>
                    <option value="kendaraan" @selected($kind === 'kendaraan')>Peminjaman Kendaraan Operasional</option>
                </select>
            </div>
            <div class="col-12 col-lg-3">
                <label class="form-label" for="record-campus">Lokasi Kampus</label>
                <select id="record-campus" name="campus_id" class="form-select">
                    <option value="">Semua Kampus</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected((string) $campusId === (string) $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-4 col-lg-2">
                <label class="form-label" for="record-status">Status</label>
                <select id="record-status" name="status" class="form-select">
                    <option value="">Semua Status</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option['value'] }}" @selected($status === $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-5 col-lg-2">
                <label class="form-label" for="record-q">Pencarian</label>
                <input id="record-q" type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nomor, peminjam, barang, ruangan, atau tujuan">
            </div>
            <div class="col-12 col-md-3 col-lg-2 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-undira">Filter</button>
                <a href="{{ route('borrowing-records.index') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Jenis Peminjaman</th>
                        <th>Nomor</th>
                        <th>Barang / Ruangan / Tujuan</th>
                        <th>Lokasi Kampus</th>
                        <th>Peminjam</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        <tr>
                            <td>{{ $record['kind_label'] }}</td>
                            <td>
                                @if(!empty($record['number_url']))
                                    <a href="{{ $record['number_url'] }}">{{ $record['number'] }}</a>
                                @else
                                    {{ $record['number'] }}
                                @endif
                            </td>
                            <td>{{ $record['subject'] }}</td>
                            <td>{{ $record['campus'] }}</td>
                            <td>{{ $record['borrower'] }}</td>
                            <td>{{ $record['starts_at'] ?? '—' }}</td>
                            <td>{{ $record['ends_at'] ?? '—' }}</td>
                            <td>
                                <span class="badge bg-{{ $record['status_color'] }}">{{ $record['status_label'] }}</span>
                                @if(!empty($record['reason']))
                                    <div class="small text-muted mt-1">{{ $record['reason'] }}</div>
                                @endif
                            </td>
                            <td class="text-end"><a href="{{ $record['url'] }}" class="btn btn-sm btn-outline-secondary">Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">
                                @if($kind !== '' || $campusId !== '' || $status !== '' || $search !== '')
                                    Tidak ada peminjaman yang sesuai dengan filter.
                                @else
                                    Tidak ada peminjaman.
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3">{{ $records->links() }}</div>
    </div>
</div>
@endsection
