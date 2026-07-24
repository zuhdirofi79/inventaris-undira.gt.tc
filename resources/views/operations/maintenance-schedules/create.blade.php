@extends('layouts.app')

@section('title', 'Tambah Jadwal Pemeliharaan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Buat Jadwal Preventif"
    subtitle="Pilih inventaris (lokasi atau cari cepat). Sistem menampilkan riwayat, menyarankan interval/pengingat/PIC, dan menghitung tanggal berikutnya."
    icon="bi bi-calendar2-plus"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jadwal Preventif', 'url' => route('maintenance-schedules.index')],
        ['label' => 'Tambah'],
    ]"
    :back-route="route('maintenance-schedules.index')"
    back-label="Jadwal Preventif"
    :list-back="true"
/>

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form action="{{ route('maintenance-schedules.store') }}" method="POST" id="scheduleForm" data-no-loading>
    @csrf
    <div class="card card-undira mb-3">
        <div class="card-header">
            <strong>1. Pilih Inventaris</strong>
            <div class="small text-muted fw-normal mt-1">Lokasi (Kampus→…→Barang) atau Quick Search (nomor / nama / QR / barcode / serial).</div>
        </div>
        <div class="card-body">
            <x-inventory-selector
                name="inventory_item_id"
                id="schedule-inventory-selector"
                :selected-id="$maintenanceContext['id'] ?? null"
                :selected-label="isset($maintenanceContext) ? ($maintenanceContext['inventory_number'].' — '.$maintenanceContext['name']) : null"
                modes="both"
                show-qr-scan
                resolve-maintenance
                help-text="Ketik nomor, nama, barcode, atau serial (min. 2 karakter), atau scan QR."
                label="Inventaris"
            />
        </div>
    </div>

    <div id="schedulePreviewCard" class="card card-undira mb-3 {{ empty($preview) ? 'd-none' : '' }}">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Ringkasan Inventaris</strong>
            <span id="dupBadge" class="badge bg-danger {{ empty($preview['duplicate']) ? 'd-none' : '' }}">Jadwal duplikat</span>
        </div>
        <div class="card-body">
            <div id="dupAlert" class="alert alert-warning {{ empty($preview['duplicate']) ? 'd-none' : '' }}">
                <div class="fw-semibold">Jadwal aktif jenis ini sudah ada.</div>
                <div class="small" id="dupDetail">
                    @if(!empty($preview['duplicate']))
                        Jadwal #{{ $preview['duplicate']['id'] }} · berikutnya {{ $preview['duplicate']['next_maintenance_date'] }} ·
                        <a href="{{ $preview['duplicate']['url'] }}">buka jadwal</a>
                    @endif
                </div>
            </div>
            <div class="row g-3 small">
                <div class="col-md-3">
                    <div class="text-muted">Last Maintenance</div>
                    <div class="fw-semibold" id="pvLastTitle">{{ $preview['last_maintenance']['maintenance_number'] ?? '—' }}</div>
                    <div class="text-muted" id="pvLastMeta">
                        @if(!empty($preview['last_maintenance']))
                            {{ $preview['last_maintenance']['service_date'] }} · {{ $preview['last_maintenance']['type_label'] }} · {{ $preview['last_maintenance']['status_label'] }}
                        @else
                            Belum ada work order
                        @endif
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Maintenance Interval</div>
                    <div class="fw-semibold" id="pvInterval">{{ $preview['suggested_interval_label'] ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">Reminder</div>
                    <div class="fw-semibold" id="pvReminder">{{ isset($preview['suggested_remind_before_days']) ? $preview['suggested_remind_before_days'].' hari sebelumnya' : '—' }}</div>
                    <div class="text-muted" id="pvReminderStart">Mulai: {{ $preview['reminder_starts_at'] ?? '—' }}</div>
                </div>
                <div class="col-md-3">
                    <div class="text-muted">PIC</div>
                    <div class="fw-semibold" id="pvPic">{{ $preview['pic'] ?? '—' }}</div>
                </div>
            </div>

            <hr class="my-3">
            <h6 class="text-undira-blue mb-2">Timeline Maintenance</h6>
            <ul class="list-unstyled mb-0" id="pvTimeline">
                @forelse(($preview['timeline'] ?? []) as $ev)
                    <li class="d-flex gap-2 border-bottom py-2 small">
                        <span class="badge bg-{{ $ev['tone'] ?? 'secondary' }} align-self-start">{{ $ev['date'] ?? '' }}</span>
                        <div>
                            <div class="fw-semibold">
                                @if(!empty($ev['url']))<a href="{{ $ev['url'] }}">{{ $ev['title'] }}</a>@else{{ $ev['title'] }}@endif
                            </div>
                            <div class="text-muted">{{ $ev['subtitle'] ?? '' }} @if(!empty($ev['meta']))· {{ $ev['meta'] }}@endif</div>
                        </div>
                    </li>
                @empty
                    <li class="text-muted small">Belum ada riwayat / jadwal.</li>
                @endforelse
            </ul>
        </div>
    </div>

    <div class="card card-undira">
        <div class="card-header"><strong>2. Detail Jadwal</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Jenis Pemeliharaan <span class="text-danger">*</span></label>
                    <select name="maintenance_type" id="maintenance_type" class="form-select" required>
                        @foreach($types as $type)
                            <option value="{{ $type->value }}" @selected(old('maintenance_type', 'preventive') === $type->value)>{{ $type->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Interval <span class="text-danger">*</span></label>
                    <input type="number" name="interval_value" id="interval_value" class="form-control" min="1" max="3650"
                           value="{{ old('interval_value', $preview['suggested_interval_value'] ?? 3) }}" required>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Satuan <span class="text-danger">*</span></label>
                    <select name="interval_unit" id="interval_unit" class="form-select" required>
                        @foreach($units as $unit)
                            <option value="{{ $unit->value }}" @selected(old('interval_unit', $preview['suggested_interval_unit'] ?? 'months') === $unit->value)>{{ $unit->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Tanggal Berikutnya <span class="text-danger">*</span></label>
                    <input type="date" name="next_maintenance_date" id="next_maintenance_date" class="form-control"
                           value="{{ old('next_maintenance_date', $preview['suggested_next_maintenance_date'] ?? '') }}" required>
                    <div class="form-text">Dihitung otomatis dari last maintenance / hari ini + interval. Bisa diubah manual.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ingatkan Sebelum <span class="text-danger">*</span></label>
                    <select name="remind_before_days" id="remind_before_days" class="form-select" required>
                        @foreach([7, 14, 30] as $d)
                            <option value="{{ $d }}" @selected((int) old('remind_before_days', $preview['suggested_remind_before_days'] ?? 7) === $d)>{{ $d }} hari</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">PIC Penanggung Jawab</label>
                    <input type="text" name="responsible_pic" id="responsible_pic" class="form-control"
                           value="{{ old('responsible_pic', $preview['pic'] ?? '') }}"
                           maxlength="255">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Catatan</label>
                    <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                </div>
                <div class="col-12">
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', true))>
                        <label class="form-check-label" for="is_active">Jadwal aktif</label>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
            <button type="submit" class="btn btn-undira" id="btnScheduleSubmit">
                <i class="fas fa-save me-1"></i>Simpan Jadwal
            </button>
            <a href="{{ route('maintenance-schedules.index') }}" class="btn btn-outline-secondary">Batal</a>
            <span class="small text-muted" id="submitHint"></span>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script src="{{ asset('js/inventory-maintenance-context.js') }}?v={{ @filemtime(public_path('js/inventory-maintenance-context.js')) ?: time() }}"></script>
<script>
(function ($) {
    var previewUrl = {!! json_encode($previewUrl, JSON_UNESCAPED_SLASHES) !!};
    var suggestUrl = {!! json_encode($suggestNextUrl, JSON_UNESCAPED_SLASHES) !!};
    var nextManual = false;
    var unitLabels = { days: 'hari', weeks: 'minggu', months: 'bulan', years: 'tahun' };

    function inventoryId() {
        return $('#schedule-inventory-selector [data-invsel-hidden]').val()
            || $('input[name="inventory_item_id"]').val()
            || '';
    }

    function renderTimeline(events) {
        var $ul = $('#pvTimeline').empty();
        if (!events || !events.length) {
            $ul.append($('<li class="text-muted small">').text('Belum ada riwayat / jadwal.'));
            return;
        }
        events.forEach(function (ev) {
            var $li = $('<li class="d-flex gap-2 border-bottom py-2 small">');
            $li.append($('<span class="badge align-self-start">').addClass('bg-' + (ev.tone || 'secondary')).text(ev.date || ''));
            var $body = $('<div>');
            var $title = $('<div class="fw-semibold">');
            if (ev.url) {
                $title.append($('<a>').attr('href', ev.url).text(ev.title || ''));
            } else {
                $title.text(ev.title || '');
            }
            $body.append($title);
            $body.append($('<div class="text-muted">').text((ev.subtitle || '') + (ev.meta ? ' · ' + ev.meta : '')));
            $li.append($body);
            $ul.append($li);
        });
    }

    function applyPreview(p) {
        if (!p) {
            $('#schedulePreviewCard').addClass('d-none');
            return;
        }
        $('#schedulePreviewCard').removeClass('d-none');

        var last = p.last_maintenance;
        $('#pvLastTitle').text(last ? last.maintenance_number : '—');
        $('#pvLastMeta').text(last
            ? ((last.service_date || '') + ' · ' + (last.type_label || '') + ' · ' + (last.status_label || ''))
            : 'Belum ada work order');

        $('#pvInterval').text(p.suggested_interval_label || '—');
        $('#pvReminder').text((p.suggested_remind_before_days || '—') + ' hari sebelumnya');
        $('#pvReminderStart').text('Mulai: ' + (p.reminder_starts_at || '—'));
        $('#pvPic').text(p.pic || '—');

        if (p.duplicate) {
            $('#dupBadge, #dupAlert').removeClass('d-none');
            $('#dupDetail').html(
                'Jadwal #' + p.duplicate.id + ' · berikutnya ' + (p.duplicate.next_maintenance_date || '—') +
                ' · <a href="' + p.duplicate.url + '">buka jadwal</a>'
            );
            $('#btnScheduleSubmit').prop('disabled', true);
            $('#submitHint').text('Tidak bisa menyimpan: jadwal duplikat untuk jenis yang sama.');
        } else {
            $('#dupBadge, #dupAlert').addClass('d-none');
            $('#btnScheduleSubmit').prop('disabled', false);
            $('#submitHint').text('');
        }

        if (!nextManual && p.suggested_next_maintenance_date) {
            $('#next_maintenance_date').val(p.suggested_next_maintenance_date);
        }
        if (!$('#responsible_pic').val() || $('#responsible_pic').data('auto') === 1) {
            $('#responsible_pic').val(p.pic || '').data('auto', 1);
        }
        if (p.suggested_interval_value) {
            $('#interval_value').val(p.suggested_interval_value);
        }
        if (p.suggested_interval_unit) {
            $('#interval_unit').val(p.suggested_interval_unit);
        }
        if (p.suggested_remind_before_days) {
            $('#remind_before_days').val(p.suggested_remind_before_days);
        }

        renderTimeline(p.timeline || []);
    }

    function loadPreview() {
        var id = inventoryId();
        if (!id) {
            applyPreview(null);
            return;
        }
        $.getJSON(previewUrl, {
            inventory_item_id: id,
            maintenance_type: $('#maintenance_type').val()
        }).done(applyPreview);
    }

    function refreshNext() {
        var id = inventoryId();
        if (!id || nextManual) return;
        $.getJSON(suggestUrl, {
            inventory_item_id: id,
            interval_value: $('#interval_value').val(),
            interval_unit: $('#interval_unit').val(),
            remind_before_days: $('#remind_before_days').val()
        }).done(function (res) {
            if (res.next_maintenance_date) {
                $('#next_maintenance_date').val(res.next_maintenance_date);
            }
            if (res.reminder_starts_at) {
                $('#pvReminderStart').text('Mulai: ' + res.reminder_starts_at);
            }
            var iv = $('#interval_value').val();
            var iu = $('#interval_unit').val();
            $('#pvInterval').text(iv + ' ' + (unitLabels[iu] || iu));
            $('#pvReminder').text(($('#remind_before_days').val() || '—') + ' hari sebelumnya');
        });
    }

    $(document).on('inventory-selector:selected inventory-maintenance-context:loaded', function () {
        nextManual = false;
        loadPreview();
    });

    $('#maintenance_type').on('change', function () {
        nextManual = false;
        loadPreview();
    });

    $('#interval_value, #interval_unit, #remind_before_days').on('change input', function () {
        refreshNext();
    });

    $('#next_maintenance_date').on('change', function () {
        nextManual = true;
    });

    $('#responsible_pic').on('input', function () {
        $(this).data('auto', 0);
    });

    @if(!empty($preview))
    applyPreview(@json($preview));
    @endif
})(window.jQuery);
</script>
@endpush
