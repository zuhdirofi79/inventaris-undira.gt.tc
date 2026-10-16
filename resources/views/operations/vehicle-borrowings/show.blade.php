@extends('layouts.app')

@section('title', 'Detail Peminjaman Kendaraan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Detail Peminjaman Kendaraan"
    subtitle="{{ $borrowing->approval_number ?: 'Menunggu nomor approval' }}"
    icon="bi bi-truck"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Detail'],
    ]"
/>

<div class="card card-undira mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
            <span class="badge bg-{{ $borrowing->displayColor() }}">{{ $borrowing->displayLabel() }}</span>
            <span class="d-inline-flex flex-wrap gap-2 align-items-center">
                @if($borrowing->approval_number)
                    <a href="{{ route('vehicle-borrowings.receipt', $borrowing) }}" class="btn btn-outline-primary btn-sm">Lihat Receipt</a>
                    <a href="{{ route('vehicle-borrowings.receipt.pdf', $borrowing) }}" class="btn btn-primary btn-sm" data-no-loader="1">Download PDF</a>
                @endif
                <span class="text-muted">Diajukan {{ optional($borrowing->created_at)->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span>
            </span>
        </div>
        <dl class="row mb-0">
            <dt class="col-sm-3">Peminjam</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_name }}</dd>
            <dt class="col-sm-3">Email</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_email }}</dd>
            <dt class="col-sm-3">Telepon</dt>
            <dd class="col-sm-9">{{ $borrowing->borrower_phone }}</dd>
            <dt class="col-sm-3">Lokasi peminjaman</dt>
            <dd class="col-sm-9">{{ $borrowing->campus?->name ?: '—' }}</dd>
            <dt class="col-sm-3">Rute</dt>
            <dd class="col-sm-9">{{ $borrowing->origin ?: ($borrowing->route?->name ?: '—') }}</dd>
            <dt class="col-sm-3">Tujuan</dt>
            <dd class="col-sm-9">{{ $borrowing->destination ?: '—' }}</dd>
            <dt class="col-sm-3">Link Google Maps Tujuan</dt>
            <dd class="col-sm-9">
                @if($borrowing->destinationMapsUrl())
                    <a
                        href="{{ $borrowing->destinationMapsUrl() }}"
                        class="btn btn-outline-primary btn-sm"
                        target="_blank"
                        rel="noopener noreferrer"
                    >Lihat Lokasi di Google Maps</a>
                @else
                    <span class="text-muted">Tidak dicantumkan</span>
                @endif
            </dd>
            <dt class="col-sm-3">Keperluan</dt>
            <dd class="col-sm-9">{{ $borrowing->purpose }}</dd>
            <dt class="col-sm-3">Jenis pengantaran</dt>
            <dd class="col-sm-9">{{ $borrowing->service_type?->label() ?: '—' }}</dd>
            <dt class="col-sm-3">Jumlah penumpang</dt>
            <dd class="col-sm-9">{{ $borrowing->passenger_count ?: '—' }}</dd>
            <dt class="col-sm-3">Jadwal mulai</dt>
            <dd class="col-sm-9">{{ $borrowing->starts_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
            <dt class="col-sm-3">Jadwal selesai</dt>
            <dd class="col-sm-9">{{ $borrowing->ends_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</dd>
            <dt class="col-sm-3">Daftar penumpang</dt>
            <dd class="col-sm-9">
                @forelse($borrowing->passengerRows() as $passenger)
                    <div>{{ $passenger['name'] }}@if($passenger['unit'] !== '') — {{ $passenger['unit'] }}@endif @if($passenger['title'] !== '') — {{ $passenger['title'] }}@endif</div>
                @empty
                    —
                @endforelse
            </dd>
            <dt class="col-sm-3">Penugasan</dt>
            <dd class="col-sm-9">
                @if($borrowing->assigned_service === \App\Enums\VehicleServiceType::GoCar)
                    GoCar
                @elseif($borrowing->vehicle || $borrowing->driver)
                    {{ $borrowing->vehicle?->label() ?: '—' }}
                    @if($borrowing->driver)
                        <div>Pengemudi: {{ $borrowing->driver->label() }}</div>
                    @endif
                @else
                    Belum ditetapkan
                @endif
            </dd>
            <dt class="col-sm-3">Dokumen Pendukung</dt>
            <dd class="col-sm-9">
                @include('partials.supporting-document', [
                    'document' => \App\Support\PrivateDocumentResponse::present(
                        $borrowing->supporting_document_path,
                        $borrowing->supporting_document_original_name,
                        $borrowing->supporting_document_size,
                        'vehicle-borrowings/'.$borrowing->id,
                    ),
                    'viewUrl' => route('vehicle-borrowings.document.preview', $borrowing),
                    'downloadUrl' => route('vehicle-borrowings.document', $borrowing),
                ])
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

@can('vehicle-borrowing.approve')
    @if($borrowing->status === \App\Enums\FacilityRequestStatus::MenungguPersetujuan)
        <div class="card card-undira">
            <div class="card-body">
                <h2 class="h6">Keputusan dan penugasan</h2>
                <form method="POST" action="{{ route('vehicle-borrowings.decide', $borrowing) }}" class="row g-3" id="vehicle-decision-form">
                    @csrf
                    <div class="col-md-4">
                        <label class="form-label" for="decision">Keputusan</label>
                        <select id="decision" name="decision" class="form-select" required>
                            <option value="approve" @selected(old('decision', 'approve') === 'approve')>Setujui</option>
                            <option value="reject" @selected(old('decision') === 'reject')>Tolak</option>
                        </select>
                    </div>
                    <div class="col-md-4" data-approve>
                        <label class="form-label" for="assignment">Penugasan</label>
                        <select id="assignment" name="assignment" class="form-select @error('assignment') is-invalid @enderror">
                            <option value="fleet" @selected(old('assignment', 'fleet') === 'fleet')>Kendaraan operasional</option>
                            <option value="gocar" @selected(old('assignment') === 'gocar')>GoCar</option>
                        </select>
                        @error('assignment')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4" data-approve data-fleet>
                        <label class="form-label" for="vehicle_id">Kendaraan</label>
                        <select id="vehicle_id" name="vehicle_id" class="form-select @error('vehicle_id') is-invalid @enderror"
                                data-passenger-count="{{ (int) ($borrowing->passenger_count ?? 0) }}">
                            <option value="">Pilih kendaraan</option>
                            @foreach($vehicles as $vehicle)
                                @php
                                    $vehicleConflictList = $assignmentConflicts['vehicles'][$vehicle->id] ?? [];
                                @endphp
                                <option
                                    value="{{ $vehicle->id }}"
                                    @selected((string) old('vehicle_id') === (string) $vehicle->id)
                                    data-capacity="{{ $vehicle->passenger_capacity !== null ? (int) $vehicle->passenger_capacity : '' }}"
                                    data-conflicts="{{ e(json_encode(array_values($vehicleConflictList))) }}"
                                >{{ $vehicle->label() }}</option>
                            @endforeach
                        </select>
                        @error('vehicle_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-4" data-approve data-fleet>
                        <label class="form-label" for="driver_id">Pengemudi</label>
                        <select id="driver_id" name="driver_id" class="form-select @error('driver_id') is-invalid @enderror">
                            <option value="">Pilih pengemudi</option>
                            @foreach($drivers as $driver)
                                @php
                                    $driverConflictList = $assignmentConflicts['drivers'][$driver->id] ?? [];
                                @endphp
                                <option
                                    value="{{ $driver->id }}"
                                    @selected((string) old('driver_id') === (string) $driver->id)
                                    data-conflicts="{{ e(json_encode(array_values($driverConflictList))) }}"
                                >{{ $driver->label() }}</option>
                            @endforeach
                        </select>
                        @error('driver_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12" data-approve data-fleet id="assignment-warning" hidden>
                        <div class="alert alert-warning mb-0 small" role="status">
                            <div class="fw-semibold mb-1">Peringatan penugasan</div>
                            <div id="assignment-warning-body"></div>
                            <div class="form-check mt-2 mb-0">
                                <input type="checkbox" class="form-check-input @error('acknowledge_conflict') is-invalid @enderror" id="acknowledge_conflict" name="acknowledge_conflict" value="1" @checked(old('acknowledge_conflict'))>
                                <label class="form-check-label" for="acknowledge_conflict">Saya memahami risikonya dan melanjutkan penugasan pada pengajuan ini.</label>
                                @error('acknowledge_conflict')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="rejection_reason">Alasan penolakan</label>
                        <textarea id="rejection_reason" name="rejection_reason" rows="2" class="form-control @error('rejection_reason') is-invalid @enderror">{{ old('rejection_reason') }}</textarea>
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

@push('scripts')
<script>
(function () {
    const decision = document.getElementById('decision');
    const assignment = document.getElementById('assignment');
    if (!decision || !assignment) return;
    const approveBlocks = document.querySelectorAll('[data-approve]');
    const fleetBlocks = document.querySelectorAll('[data-fleet]');
    const vehicleSelect = document.getElementById('vehicle_id');
    const driverSelect = document.getElementById('driver_id');
    const warningBox = document.getElementById('assignment-warning');
    const warningBody = document.getElementById('assignment-warning-body');
    const acknowledge = document.getElementById('acknowledge_conflict');
    const rejectionReason = document.getElementById('rejection_reason');

    function setDisabled(root, disabled) {
        root.querySelectorAll('input, select, textarea').forEach(function (el) {
            el.disabled = disabled;
        });
    }

    function parseConflicts(option) {
        if (!option) return [];
        try {
            const raw = option.getAttribute('data-conflicts') || '[]';
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (e) {
            return [];
        }
    }

    function syncWarning() {
        if (!warningBox || !warningBody || !vehicleSelect || !driverSelect) return;
        const showFleet = decision.value === 'approve' && assignment.value === 'fleet' && !assignment.disabled;
        if (!showFleet) {
            warningBox.hidden = true;
            setDisabled(warningBox, true);
            return;
        }

        const vehicleOpt = vehicleSelect.options[vehicleSelect.selectedIndex];
        const driverOpt = driverSelect.options[driverSelect.selectedIndex];
        const lines = [];
        const vehicleConflicts = parseConflicts(vehicleOpt);
        const driverConflicts = parseConflicts(driverOpt);
        const passengers = parseInt(vehicleSelect.getAttribute('data-passenger-count') || '0', 10) || 0;
        const capacityRaw = vehicleOpt ? vehicleOpt.getAttribute('data-capacity') : '';
        const capacity = capacityRaw === '' || capacityRaw === null ? null : parseInt(capacityRaw, 10);

        if (vehicleConflicts.length) {
            lines.push('<div class="mb-1"><strong>Kendaraan</strong> juga ditugaskan pada: ' + vehicleConflicts.map(function (c) {
                return '<div>• ' + c + '</div>';
            }).join('') + '</div>');
        }
        if (driverConflicts.length) {
            lines.push('<div class="mb-1"><strong>Pengemudi</strong> juga ditugaskan pada: ' + driverConflicts.map(function (c) {
                return '<div>• ' + c + '</div>';
            }).join('') + '</div>');
        }
        if (capacity !== null && !Number.isNaN(capacity) && passengers > capacity) {
            lines.push('<div class="mb-1">Jumlah penumpang pengajuan ini (' + passengers + ') melebihi kapasitas kendaraan (' + capacity + ').</div>');
        }

        if (!lines.length) {
            warningBox.hidden = true;
            setDisabled(warningBox, true);
            if (acknowledge && !acknowledge.defaultChecked) acknowledge.checked = false;
            return;
        }

        warningBody.innerHTML = lines.join('') + '<div class="text-muted mt-1">Admin dapat tetap menyimpan jika perjalanan ini memang dilayani bersama atau override diperlukan.</div>';
        warningBox.hidden = false;
        setDisabled(warningBox, false);
    }

    function sync() {
        const approving = decision.value === 'approve';
        const showFleet = approving && assignment.value === 'fleet';

        approveBlocks.forEach(function (block) {
            const isFleetOnly = block.hasAttribute('data-fleet');
            const visible = approving && (!isFleetOnly || showFleet);
            if (block.id === 'assignment-warning') {
                return;
            }
            block.hidden = !visible;
            setDisabled(block, !visible);
        });

        if (rejectionReason) {
            rejectionReason.required = !approving;
        }

        syncWarning();
    }

    decision.addEventListener('change', sync);
    assignment.addEventListener('change', sync);
    if (vehicleSelect) vehicleSelect.addEventListener('change', syncWarning);
    if (driverSelect) driverSelect.addEventListener('change', syncWarning);
    sync();
})();
</script>
@endpush
