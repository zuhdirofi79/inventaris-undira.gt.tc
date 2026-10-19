@extends('layouts.app')

@section('title', 'Pinjam Kendaraan - Inventaris UNDIRA')

@section('content')
@php
    $oldPassengers = old('passengers');
    if (! is_array($oldPassengers) || $oldPassengers === []) {
        $oldPassengers = [['name' => '', 'unit' => '', 'title' => '']];
    }
@endphp
<x-page-header
    title="Pinjam Kendaraan"
    subtitle="Ajukan kebutuhan perjalanan. Kendaraan, pengemudi, atau GoCar ditetapkan saat approval."
    icon="bi bi-truck"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Peminjaman Kendaraan'],
    ]"
/>

<div class="card card-undira">
    <div class="card-body">
        <form method="POST" action="{{ route('vehicle-borrowings.store') }}" enctype="multipart/form-data" id="vehicle-borrow-form" data-ajax-submit data-no-loading>
            @csrf
            <div class="row g-3">
                @include('partials.borrower-identity-fields', [
                    'nameCol' => 'col-md-6',
                    'emailCol' => 'col-md-6',
                ])
                <div class="col-md-6">
                    <label class="form-label" for="borrower_phone">Nomor Telepon</label>
                    <input id="borrower_phone" name="borrower_phone" value="{{ old('borrower_phone') }}" class="form-control @error('borrower_phone') is-invalid @enderror" placeholder="0812XXXX" required autocomplete="tel">
                    @error('borrower_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                @include('partials.borrower-affiliation-field', [
                    'fieldName' => 'unit',
                    'colClass' => 'col-md-6',
                ])
                <div class="col-lg-4">
                    <label class="form-label" for="route_id">Rute</label>
                    <select id="route_id" name="route_id" class="form-select @error('route_id') is-invalid @enderror" required>
                        <option value="">Pilih Rute</option>
                        @foreach($routes as $routeOption)
                            <option value="{{ $routeOption->id }}" @selected((string) old('route_id') === (string) $routeOption->id)>{{ $routeOption->name }}</option>
                        @endforeach
                    </select>
                    @error('route_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @if($routes->isEmpty())
                        <div class="form-text">Rute belum tersedia. Hubungi admin untuk menambahkan master rute.</div>
                    @endif
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="destination">Tujuan</label>
                    <input id="destination" name="destination" value="{{ old('destination') }}" class="form-control @error('destination') is-invalid @enderror" placeholder="Masukkan Tujuan, contoh: Grogol, Jakarta Barat" required>
                    @error('destination')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="purpose">Keperluan</label>
                    <textarea id="purpose" name="purpose" rows="2" class="form-control @error('purpose') is-invalid @enderror" placeholder="Masukkan keperluan peminjaman kendaraan" required>{{ old('purpose') }}</textarea>
                    @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="destination_maps_url">Link Google Maps Tujuan (Opsional)</label>
                    <input
                        id="destination_maps_url"
                        type="url"
                        name="destination_maps_url"
                        value="{{ old('destination_maps_url') }}"
                        class="form-control @error('destination_maps_url') is-invalid @enderror"
                        placeholder="Tempelkan link Google Maps lokasi tujuan"
                        inputmode="url"
                        autocomplete="off"
                    >
                    <div class="form-text">Opsional. Tempelkan tautan Google Maps agar Admin dapat melihat lokasi tujuan perjalanan dengan lebih akurat.</div>
                    @error('destination_maps_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="service_type">Jenis Pengantaran</label>
                    <select id="service_type" name="service_type" class="form-select @error('service_type') is-invalid @enderror" required>
                        <option value="">Pilih Pengantaran</option>
                        @foreach(\App\Enums\VehicleServiceType::cases() as $type)
                            @continue($type === \App\Enums\VehicleServiceType::GoCar)
                            <option value="{{ $type->value }}" @selected(old('service_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('service_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="passenger_count">Jumlah Penumpang</label>
                    <input id="passenger_count" name="passenger_count" type="number" min="1" max="50" value="{{ old('passenger_count') }}" class="form-control @error('passenger_count') is-invalid @enderror" placeholder="Masukkan Jumlah Penumpang" required>
                    @error('passenger_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="campus_id">Lokasi Peminjaman</label>
                    <select id="campus_id" name="campus_id" class="form-select @error('campus_id') is-invalid @enderror" required>
                        <option value="">Masukkan Lokasi Peminjaman</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}" @selected((string) old('campus_id') === (string) $campus->id)>{{ $campus->code }} — {{ $campus->name }}</option>
                        @endforeach
                    </select>
                    @error('campus_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="starts_at">Jadwal Mulai</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control @error('starts_at') is-invalid @enderror" required>
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="ends_at">Jadwal Selesai</label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control @error('ends_at') is-invalid @enderror" required>
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="supporting_document">Dokumen Pendukung (Maksimal 5 MB)</label>
                    <input id="supporting_document" type="file" name="supporting_document" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" class="form-control @error('supporting_document') is-invalid @enderror">
                    <div class="form-text" id="file-name">PDF, JPG, atau JPEG. Opsional.</div>
                    @error('supporting_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label">Daftar Penumpang</label>
                    <p class="text-muted small mb-2">Nama penumpang, divisi atau unit, dan jabatan. Jumlah nama harus sama dengan jumlah penumpang.</p>
                    <div data-error-bag="passengers">
                        @error('passengers')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @error('passengers.*.name')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    </div>
                    <div id="passenger-list" class="d-flex flex-column gap-2">
                        @foreach($oldPassengers as $index => $passenger)
                            <div class="row g-2 passenger-row">
                                <div class="col-md-4">
                                    <input name="passengers[{{ $index }}][name]" value="{{ $passenger['name'] ?? '' }}" class="form-control" placeholder="Nama Penumpang" required>
                                </div>
                                <div class="col-md-3">
                                    <input name="passengers[{{ $index }}][unit]" value="{{ $passenger['unit'] ?? '' }}" class="form-control" placeholder="Divisi/Unit">
                                </div>
                                <div class="col-md-3">
                                    <input name="passengers[{{ $index }}][title]" value="{{ $passenger['title'] ?? '' }}" class="form-control" placeholder="Jabatan">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-secondary w-100" data-remove-passenger>Hapus</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-outline-secondary mt-2" id="add-passenger">Tambah Penumpang</button>
                </div>
            </div>
            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-undira">Ajukan Peminjaman</button>
                <a href="{{ route('borrowing-records.index', ['kind' => 'kendaraan']) }}" class="btn btn-outline-secondary">Batal</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const list = document.getElementById('passenger-list');
    const add = document.getElementById('add-passenger');
    const file = document.getElementById('supporting_document');
    const fileName = document.getElementById('file-name');

    function reindex() {
        list.querySelectorAll('.passenger-row').forEach(function (row, index) {
            row.querySelectorAll('input').forEach(function (input) {
                input.name = input.name.replace(/passengers\[\d+\]/, 'passengers[' + index + ']');
            });
        });
    }

    add.addEventListener('click', function () {
        if (list.children.length >= 50) return;
        const row = list.querySelector('.passenger-row').cloneNode(true);
        row.querySelectorAll('input').forEach(function (input) { input.value = ''; });
        list.appendChild(row);
        reindex();
    });

    list.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-passenger]');
        if (!button || list.children.length < 2) return;
        button.closest('.passenger-row').remove();
        reindex();
    });

    file.addEventListener('change', function () {
        fileName.textContent = file.files[0] ? file.files[0].name : 'PDF, JPG, atau JPEG. Opsional.';
    });
})();
</script>
@endpush
