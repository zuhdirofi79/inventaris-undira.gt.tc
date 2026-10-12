@extends('layouts.app')

@section('title', 'Detail Peminjaman Ruangan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Detail Peminjaman Ruangan"
    subtitle="{{ $borrowing->approval_number ?: 'Menunggu nomor approval' }}"
    icon="bi bi-door-open"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman Ruangan', 'url' => route('room-borrowings.show', $borrowing)],
        ['label' => 'Detail'],
    ]"
/>

<div class="card card-undira mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <span class="badge bg-{{ $borrowing->displayColor() }}">{{ $borrowing->displayLabel() }}</span>
            <span class="text-muted">Diajukan {{ optional($borrowing->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Peminjam</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_name }}</dd>
            <dt class="col-sm-3">Email</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_email ?: '—' }}</dd>
            <dt class="col-sm-3">Telepon</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_phone }}</dd>
            <dt class="col-sm-3">Jenis kegiatan</dt>
            <dd class="col-sm-9">{{ $borrowing->activity_type?->label() ?: '—' }}</dd>
            <dt class="col-sm-3">Tujuan kegiatan</dt>
            <dd class="col-sm-9">{{ $borrowing->purpose }}</dd>
            <dt class="col-sm-3">Kampus</dt>
            <dd class="col-sm-9">{{ $borrowing->campus?->name ?: '—' }}</dd>
            <dt class="col-sm-3">Ruangan</dt>
            <dd class="col-sm-9">{{ $borrowing->room?->full_location ?: '—' }}</dd>
            <dt class="col-sm-3">Jadwal mulai</dt>
            <dd class="col-sm-9">{{ $borrowing->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</dd>
            <dt class="col-sm-3">Jadwal selesai</dt>
            <dd class="col-sm-9">{{ $borrowing->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?: '—' }}</dd>
            <dt class="col-sm-3">Anggaran</dt>
            <dd class="col-sm-9">{{ $borrowing->uses_budget ? ($borrowing->budget_type ?: '—') : 'Tidak menggunakan anggaran' }}</dd>
            <dt class="col-sm-3">Peserta</dt>
            <dd class="col-sm-9">{{ $borrowing->participantText() }}</dd>
            <dt class="col-sm-3">Link dokumentasi</dt>
            <dd class="col-sm-9">
                @if($borrowing->documentation_url)
                    <a href="{{ $borrowing->documentation_url }}" target="_blank" rel="noopener noreferrer">{{ $borrowing->documentation_url }}</a>
                @else
                    —
                @endif
            </dd>
            <dt class="col-sm-3">Dokumen persetujuan pimpinan</dt>
            <dd class="col-sm-9">
                @php $approval = $borrowing->documentMeta('approval'); @endphp
                @if($approval)
                    <a href="{{ route('room-borrowings.documents', [$borrowing, 'approval']) }}">{{ $approval['name'] }}</a>
                @else
                    —
                @endif
            </dd>
            <dt class="col-sm-3">Layout acara</dt>
            <dd class="col-sm-9">
                @php $layout = $borrowing->documentMeta('layout'); @endphp
                @if($layout)
                    <a href="{{ route('room-borrowings.documents', [$borrowing, 'layout']) }}">{{ $layout['name'] }}</a>
                @else
                    —
                @endif
            </dd>
            <dt class="col-sm-3">Daftar peserta</dt>
            <dd class="col-sm-9">
                @php $participants = $borrowing->documentMeta('participants'); @endphp
                @if($participants)
                    <a href="{{ route('room-borrowings.documents', [$borrowing, 'participants']) }}">{{ $participants['name'] }}</a>
                @else
                    —
                @endif
            </dd>
            <dt class="col-sm-3">Fasilitas di ruangan</dt>
            <dd class="col-sm-9">
                @forelse($borrowing->room?->inventoryItems ?? [] as $item)
                    <div>{{ $item->inventory_number }} — {{ $item->name }}</div>
                @empty
                    —
                @endforelse
            </dd>
            <dt class="col-sm-3">Admin</dt>
            <dd class="col-sm-9">{{ $borrowing->approvedBy?->name ?: '—' }}</dd>
            @if($borrowing->rejection_reason)
                <dt class="col-sm-3">Alasan penolakan</dt>
                <dd class="col-sm-9">{{ $borrowing->rejection_reason }}</dd>
            @endif
        </dl>
    </div>
</div>

@can('room-borrowing.approve')
    @if($borrowing->status === \App\Enums\FacilityRequestStatus::MenungguPersetujuan)
        <div class="card card-undira">
            <div class="card-body">
                <h2 class="h6">Keputusan</h2>
                <form method="POST" action="{{ route('room-borrowings.decide', $borrowing) }}" class="row g-3">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label" for="decision">Keputusan</label>
                        <select id="decision" name="decision" class="form-select @error('decision') is-invalid @enderror" required>
                            <option value="approve" @selected(old('decision') === 'approve')>Setujui</option>
                            <option value="reject" @selected(old('decision') === 'reject')>Tolak</option>
                        </select>
                        @error('decision')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-8">
                        <label class="form-label" for="rejection_reason">Alasan penolakan</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="2" class="form-control @error('rejection_reason') is-invalid @enderror" placeholder="Wajib diisi jika pengajuan ditolak">{{ old('rejection_reason') }}</textarea>
                        @error('rejection_reason')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-undira">Simpan Keputusan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endcan
@endsection
