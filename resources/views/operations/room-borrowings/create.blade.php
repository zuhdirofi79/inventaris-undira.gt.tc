@extends('layouts.app')

@section('title', 'Pinjam Ruangan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Pinjam Ruangan"
    subtitle="Isi kegiatan, pilih ruangan dari kampus yang terdaftar, lalu unggah dokumen persetujuan pimpinan."
    icon="bi bi-door-open"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pinjam Ruangan'],
    ]"
/>

<div class="card card-undira">
    <div class="card-body">
        <form method="POST" action="{{ route('room-borrowings.store') }}" id="room-borrow-form" enctype="multipart/form-data">
            @csrf

            <h2 class="h6 text-body-secondary border-bottom pb-2 mb-3">Informasi Kegiatan</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label" for="borrower_name">Nama Peminjam</label>
                    <input id="borrower_name" name="borrower_name" value="{{ old('borrower_name') }}" class="form-control @error('borrower_name') is-invalid @enderror" required>
                    @error('borrower_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="borrower_email">Email</label>
                    <input id="borrower_email" type="email" name="borrower_email" value="{{ old('borrower_email') }}" class="form-control @error('borrower_email') is-invalid @enderror">
                    @error('borrower_email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="activity_type">Jenis Kegiatan</label>
                    <select id="activity_type" name="activity_type" class="form-select @error('activity_type') is-invalid @enderror" required>
                        <option value="">Masukkan Jenis Kegiatan</option>
                        @foreach($activityTypes as $type)
                            <option value="{{ $type->value }}" @selected(old('activity_type') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                    @error('activity_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label" for="purpose">Tujuan Kegiatan</label>
                    <textarea id="purpose" name="purpose" rows="3" class="form-control @error('purpose') is-invalid @enderror" placeholder="Masukkan Tujuan Kegiatan" required maxlength="2000">{{ old('purpose') }}</textarea>
                    @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <h2 class="h6 text-body-secondary border-bottom pb-2 mb-3 mt-4">Lokasi dan Jadwal</h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label" for="campus_id">Kampus</label>
                    <select id="campus_id" class="form-select" data-old="{{ old('campus_id') }}">
                        <option value="">Masukkan Kampus</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="building_id">Gedung</label>
                    <select id="building_id" class="form-select" data-old="{{ old('building_id') }}" disabled>
                        <option value="">Pilih gedung</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="floor_id">Lantai</label>
                    <select id="floor_id" class="form-select" data-old="{{ old('floor_id') }}" disabled>
                        <option value="">Pilih lantai</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="room_id">Ruangan</label>
                    <select id="room_id" name="room_id" class="form-select @error('room_id') is-invalid @enderror" data-old="{{ old('room_id') }}" required disabled>
                        <option value="">Pilih Ruangan</option>
                    </select>
                    @error('room_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="starts_at">Jadwal Mulai</label>
                    <input id="starts_at" type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control @error('starts_at') is-invalid @enderror" required>
                    @error('starts_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="ends_at">Jadwal Selesai</label>
                    <input id="ends_at" type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control @error('ends_at') is-invalid @enderror" required>
                    @error('ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="borrower_phone">Nomor Telepon</label>
                    <input id="borrower_phone" name="borrower_phone" value="{{ old('borrower_phone') }}" class="form-control @error('borrower_phone') is-invalid @enderror" placeholder="0812XXXX" required>
                    @error('borrower_phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-8">
                    <label class="form-label" for="documentation_url">Link Dokumentasi</label>
                    <input id="documentation_url" type="url" name="documentation_url" value="{{ old('documentation_url') }}" class="form-control @error('documentation_url') is-invalid @enderror" placeholder="https://drive.google.com/...">
                    @error('documentation_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>

            <h2 class="h6 text-body-secondary border-bottom pb-2 mb-3 mt-4">Anggaran dan Peserta</h2>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label d-block">Anggaran</label>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="uses_budget" name="uses_budget" value="1" @checked(old('uses_budget'))>
                        <label class="form-check-label" for="uses_budget">Gunakan Anggaran</label>
                    </div>
                </div>
                <div class="col-md-8" id="budget-type-wrap" @if(! old('uses_budget')) hidden @endif>
                    <label class="form-label" for="budget_type">Jenis Anggaran</label>
                    <input id="budget_type" name="budget_type" value="{{ old('budget_type') }}" class="form-control @error('budget_type') is-invalid @enderror" placeholder="Masukkan Jenis Anggaran" maxlength="255" @disabled(! old('uses_budget'))>
                    @error('budget_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-12">
                    <label class="form-label d-block">Peserta</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="participant_internal" name="participant_internal" value="1" @checked(old('participant_internal'))>
                            <label class="form-check-label" for="participant_internal">Internal</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="participant_external" name="participant_external" value="1" @checked(old('participant_external'))>
                            <label class="form-check-label" for="participant_external">External</label>
                        </div>
                    </div>
                    @error('participants')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                </div>
            </div>

            <h2 class="h6 text-body-secondary border-bottom pb-2 mb-3 mt-4">Dokumen Pendukung</h2>
            <div class="row g-3">
                <div class="col-lg-4">
                    <label class="form-label" for="approval_document">Dokumen Persetujuan Pimpinan (Maksimal 5 MB)</label>
                    <input id="approval_document" type="file" name="approval_document" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" class="form-control @error('approval_document') is-invalid @enderror" required>
                    <div class="form-text" id="approval-document-name">Format PDF, JPG, atau JPEG. Wajib diunggah.</div>
                    @error('approval_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @include('operations.room-borrowings._template', ['templates' => $templates, 'kind' => 'approval'])
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="layout_document">Layout Acara (Maksimal 5 MB)</label>
                    <input id="layout_document" type="file" name="layout_document" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" class="form-control @error('layout_document') is-invalid @enderror">
                    <div class="form-text" id="layout-document-name">Opsional. Format PDF, JPG, atau JPEG.</div>
                    @error('layout_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @include('operations.room-borrowings._template', ['templates' => $templates, 'kind' => 'layout'])
                </div>
                <div class="col-lg-4">
                    <label class="form-label" for="participant_document">Daftar Peserta (Maksimal 5 MB)</label>
                    <input id="participant_document" type="file" name="participant_document" accept=".pdf,.jpg,.jpeg,application/pdf,image/jpeg" class="form-control @error('participant_document') is-invalid @enderror">
                    <div class="form-text" id="participant-document-name">Opsional. Format PDF, JPG, atau JPEG.</div>
                    @error('participant_document')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    @include('operations.room-borrowings._template', ['templates' => $templates, 'kind' => 'participants'])
                </div>
            </div>

            <div class="mt-4 d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-undira" data-loading-text="Menyimpan...">Simpan Pengajuan</button>
                <a href="{{ route('borrowing-records.index', ['kind' => 'ruangan']) }}" class="btn btn-outline-secondary">Tutup</a>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const urls = {
        campuses: @json(route('inventory.selector.campuses')),
        buildings: @json(route('inventory.selector.buildings')),
        floors: @json(route('inventory.selector.floors')),
        rooms: @json(route('inventory.selector.rooms')),
    };
    const campus = document.getElementById('campus_id');
    const building = document.getElementById('building_id');
    const floor = document.getElementById('floor_id');
    const room = document.getElementById('room_id');
    const budget = document.getElementById('uses_budget');
    const budgetWrap = document.getElementById('budget-type-wrap');
    const budgetType = document.getElementById('budget_type');
    const form = document.getElementById('room-borrow-form');

    function fill(select, rows, placeholder) {
        const current = select.getAttribute('data-old') || '';
        select.innerHTML = '';
        const empty = document.createElement('option');
        empty.value = '';
        empty.textContent = placeholder;
        select.appendChild(empty);
        (rows || []).forEach(function (row) {
            const option = document.createElement('option');
            option.value = String(row.id);
            option.textContent = row.text;
            if (String(row.id) === String(current)) {
                option.selected = true;
            }
            select.appendChild(option);
        });
        select.disabled = !rows || rows.length === 0;
        select.removeAttribute('data-old');
    }

    async function load(url, params) {
        const response = await fetch(url + '?' + new URLSearchParams(params), {
            headers: { 'Accept': 'application/json' },
        });
        if (!response.ok) {
            throw new Error('location');
        }
        const payload = await response.json();
        return Array.isArray(payload.results) ? payload.results : [];
    }

    function syncBudget() {
        const on = budget.checked;
        budgetWrap.hidden = !on;
        budgetType.disabled = !on;
        budgetType.required = on;
    }

    campus.addEventListener('change', async function () {
        fill(building, [], 'Pilih gedung');
        fill(floor, [], 'Pilih lantai');
        fill(room, [], 'Pilih Ruangan');
        if (!campus.value) return;
        fill(building, await load(urls.buildings, { campus_id: campus.value }), 'Pilih gedung');
    });
    building.addEventListener('change', async function () {
        fill(floor, [], 'Pilih lantai');
        fill(room, [], 'Pilih Ruangan');
        if (!building.value) return;
        fill(floor, await load(urls.floors, { building_id: building.value }), 'Pilih lantai');
    });
    floor.addEventListener('change', async function () {
        fill(room, [], 'Pilih Ruangan');
        if (!floor.value) return;
        fill(room, await load(urls.rooms, { floor_id: floor.value }), 'Pilih Ruangan');
    });

    budget.addEventListener('change', syncBudget);
    syncBudget();

    ['approval_document', 'layout_document', 'participant_document'].forEach(function (id) {
        const input = document.getElementById(id);
        const hint = document.getElementById(id.replaceAll('_', '-') + '-name');
        if (!input || !hint) return;
        const initial = hint.textContent;
        input.addEventListener('change', function () {
            hint.textContent = input.files && input.files[0] ? input.files[0].name : initial;
        });
    });

    form.addEventListener('submit', function (event) {
        const start = document.getElementById('starts_at');
        const end = document.getElementById('ends_at');
        if (start.value && end.value && end.value <= start.value) {
            end.setCustomValidity('Jadwal selesai harus setelah jadwal mulai.');
            end.reportValidity();
            event.preventDefault();
            return;
        }
        end.setCustomValidity('');
    });

    load(urls.campuses, {}).then(async function (rows) {
        fill(campus, rows, 'Masukkan Kampus');
        if (campus.value) {
            fill(building, await load(urls.buildings, { campus_id: campus.value }), 'Pilih gedung');
        }
        if (building.value) {
            fill(floor, await load(urls.floors, { building_id: building.value }), 'Pilih lantai');
        }
        if (floor.value) {
            fill(room, await load(urls.rooms, { floor_id: floor.value }), 'Pilih Ruangan');
        }
    }).catch(function () {
        campus.options[0].textContent = 'Lokasi tidak dapat dimuat';
    });
})();
</script>
@endpush
