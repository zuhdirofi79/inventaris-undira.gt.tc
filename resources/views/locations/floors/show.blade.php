@extends('layouts.app')

@section('title', $floor->name . ' - Inventaris UNDIRA')

@php
    $campus = $floor->building->campus;
    $building = $floor->building;
@endphp

@push('styles')
@include('locations.partials.explore-styles')
@endpush

@section('content')
<x-page-header
    :title="$floor->name"
    icon="bi bi-layers"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Jelajahi Lokasi', 'url' => route('campuses.index')],
        ['label' => $campus->name, 'url' => route('campuses.show', $campus)],
        ['label' => $building->name, 'url' => route('buildings.show', $building)],
        ['label' => $floor->name],
    ]"
    :back-route="route('buildings.show', $building)"
    back-label="Lantai"
    :list-back="true"
>
    <x-slot:meta>
        {{ $campus->name }} &middot; {{ $building->name }} &middot; Lantai {{ $floor->floor_number }}
        &middot; <span id="roomCountLabel">{{ $floor->rooms->count() }}</span> Ruangan
    </x-slot:meta>
    <x-slot:actions>
        @can('location.manage')
        <button type="button" class="btn btn-undira btn-sm" id="btnOpenCreateRoom" data-bs-toggle="modal" data-bs-target="#modalRoomForm" data-no-loader="1">
            <i class="bi bi-plus-lg me-1"></i>Tambah Ruangan
        </button>
        @endcan
    </x-slot:actions>
</x-page-header>

<div class="lex-search-wrap">
    <label class="form-label small mb-1" for="lexRoomSearch">Cari Ruangan</label>
    <div class="input-group">
        <span class="input-group-text"><i class="bi bi-search" aria-hidden="true"></i></span>
        <input type="search" id="lexRoomSearch" class="form-control" placeholder="Nama, kode, atau fungsi ruangan…" autocomplete="off">
    </div>
</div>

<div class="lex-grid lex-grid--4" id="roomCardGrid">
    @forelse($floor->rooms as $room)
        @include('locations.floors._room-card', ['room' => $room, 'canManageRooms' => $canManageRooms, 'floor' => $floor])
    @empty
    <div class="lex-empty" id="roomEmptyState">
        <p class="fw-semibold mb-1">Belum ada ruangan di lantai ini</p>
        @can('location.manage')
        <p class="mb-2">Tambahkan ruangan agar inventaris dapat ditempatkan.</p>
        <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalRoomForm" data-no-loader="1">
            <i class="bi bi-plus-lg me-1"></i>Tambah Ruangan
        </button>
        @else
        <p class="mb-0">Belum ada data ruangan aktif.</p>
        @endcan
    </div>
    @endforelse
</div>
@endsection

@can('location.manage')
@push('modals')
<div class="modal fade" id="modalRoomForm" tabindex="-1" aria-labelledby="modalRoomFormTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form id="roomForm" class="modal-content" novalidate>
            @csrf
            <input type="hidden" name="floor_id" id="room_floor_id" value="{{ $floor->id }}">
            <input type="hidden" name="_method" id="room_form_method" value="POST">
            <div class="modal-header">
                <h5 class="modal-title" id="modalRoomFormTitle">Tambah Ruangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border small mb-3">
                    <div><strong>Kampus:</strong> {{ $campus->name }}</div>
                    <div><strong>Gedung:</strong> {{ $building->name }}</div>
                    <div><strong>Lantai:</strong> {{ $floor->name }}</div>
                </div>

                <div id="roomFormErrors" class="alert alert-danger d-none small"></div>

                <div class="mb-3">
                    <label for="room_code" class="form-label">Kode Ruangan <span class="text-danger">*</span></label>
                    <input type="text" name="code" id="room_code" class="form-control" maxlength="20" required placeholder="Contoh: A-301">
                </div>
                <div class="mb-3">
                    <label for="room_name" class="form-label">Nama Ruangan <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="room_name" class="form-control" maxlength="255" required placeholder="Contoh: Lab Komputer 1">
                </div>
                <div class="mb-3">
                    <label for="room_type" class="form-label">Jenis Ruangan <span class="text-danger">*</span></label>
                    <select name="room_type" id="room_type" class="form-select" required>
                        <option value="">Pilih jenis</option>
                        @foreach($roomTypes as $type)
                            <option value="{{ $type }}">{{ $type }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label for="room_capacity" class="form-label">Kapasitas <span class="text-danger">*</span></label>
                    <input type="number" name="capacity" id="room_capacity" class="form-control" min="1" required placeholder="Minimal 1">
                    <div class="form-text">Jumlah kapasitas orang / unit ruangan.</div>
                </div>
                <div class="mb-3">
                    <label class="form-label d-block">Status</label>
                    <input type="hidden" name="is_active" value="0">
                    <div class="form-check form-switch">
                        <input type="checkbox" name="is_active" id="room_is_active" class="form-check-input" value="1" checked>
                        <label class="form-check-label" for="room_is_active">Aktif</label>
                    </div>
                </div>
                <div class="mb-0">
                    <label for="room_notes" class="form-label">Keterangan</label>
                    <textarea name="notes" id="room_notes" class="form-control" rows="2" maxlength="1000" placeholder="Opsional"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira" id="btnSaveRoom">
                    <i class="fas fa-save me-1"></i>Simpan
                </button>
            </div>
        </form>
    </div>
</div>
@endpush
@endcan

@push('styles')
{{-- Room explore card styles live in public/css/undira.css (theme tokens + dark mode). --}}
@endpush

@push('scripts')
@can('location.manage')
@php
    $inventoryCreateUrl = (auth()->user()->can('inventory.create') || auth()->user()->can('category.manage'))
        ? route('categories.index', [
            'tab' => 'konfigurasi',
            'create' => 1,
            'floor_id' => $floor->id,
            'building_id' => $floor->building_id,
            'campus_id' => $floor->building->campus_id,
        ])
        : null;
@endphp
<script>
(function () {
    const canManage = true;
    const floorId = @json($floor->id);
    const campusId = @json($floor->building->campus_id);
    const buildingId = @json($floor->building_id);
    const storeUrl = @json(route('admin.locations.rooms.store'));
    const csrf = @json(csrf_token());
    const inventoryCreateUrl = @json($inventoryCreateUrl);

    const typeIcons = {
        'Kelas': 'bi-easel',
        'Lab': 'bi-flask',
        'Kantor': 'bi-briefcase',
        'Aula': 'bi-people',
        'Perpustakaan': 'bi-book',
        'Meeting Room': 'bi-people',
        'Gudang': 'bi-archive',
        'Lainnya': 'bi-door-open'
    };

    function roomIcon(type) {
        return typeIcons[type] || 'bi-door-open';
    }

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, s => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[s]);
    }

    function updateRoomCount() {
        const n = $('#roomCardGrid .room-card-col').length;
        $('#roomCountLabel').text(n);
        if (n === 0) {
            if (!$('#roomEmptyState').length) {
                $('#roomCardGrid').html(`
                    <div class="lex-empty" id="roomEmptyState">
                        <p class="fw-semibold mb-1">Belum ada ruangan di lantai ini</p>
                    </div>`);
            }
        } else {
            $('#roomEmptyState').remove();
        }
    }

    function buildCard(room) {
        const filterText = escapeHtml(String(room.name || '') + ' ' + String(room.code || '') + ' ' + String(room.room_type || '')).toLowerCase();
        const typeBadge = room.room_type
            ? `<span class="lex-badge lex-badge--soft">${escapeHtml(room.room_type)}</span>`
            : '';
        const inactive = room.is_active ? '' : '<span class="badge bg-secondary">Nonaktif</span>';
        const manage = `
            <div class="dropdown room-explore-actions" style="pointer-events: auto; position: relative; z-index: 3;">
                <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi" data-no-loader="1">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li><a class="dropdown-item" href="${room.urls.show}"><i class="bi bi-eye me-2 text-primary"></i>Detail</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><button type="button" class="dropdown-item btn-edit-room"
                        data-id="${room.id}"
                        data-code="${escapeHtml(room.code)}"
                        data-name="${escapeHtml(room.name)}"
                        data-type="${escapeHtml(room.room_type || '')}"
                        data-capacity="${room.capacity ?? ''}"
                        data-active="${room.is_active ? 1 : 0}"
                        data-notes="${escapeHtml(room.notes || '')}"
                        data-update="${room.urls.update}">
                        <i class="bi bi-pencil me-2 text-warning"></i>Edit</button></li>
                    <li><button type="button" class="dropdown-item btn-dup-room" data-url="${room.urls.duplicate}">
                        <i class="bi bi-copy me-2 text-secondary"></i>Duplikasi Ruangan</button></li>
                    <li><button type="button" class="dropdown-item text-danger btn-del-room"
                        data-id="${room.id}" data-name="${escapeHtml(room.name)}" data-url="${room.urls.destroy}"
                        data-count="${room.inventory_items_count || 0}">
                        <i class="bi bi-trash me-2"></i>Hapus</button></li>
                </ul>
            </div>`;

        return `
        <div class="lex-filter-card room-card-col" data-room-id="${room.id}" data-filter-text="${filterText}">
            <div class="lex-card position-relative" style="--lex-accent: #0d9488; cursor: default;">
                <a href="${room.urls.show}" class="text-decoration-none text-reset stretched-link" style="z-index: 1;" aria-label="Buka ${escapeHtml(room.name)}"></a>
                <div class="lex-card__top" style="position: relative; z-index: 2; pointer-events: none;">
                    <span class="lex-card__icon" aria-hidden="true"><i class="bi ${roomIcon(room.room_type)}"></i></span>
                    <div class="min-w-0 flex-grow-1">
                        <h2 class="lex-card__title">${escapeHtml(room.name)}</h2>
                        <div class="lex-card__meta">
                            ${room.code ? `<span class="lex-badge lex-badge--code">${escapeHtml(room.code)}</span>` : ''}
                            ${typeBadge}${inactive}
                        </div>
                    </div>
                    ${manage}
                </div>
                <div class="lex-stats" style="position: relative; z-index: 2; pointer-events: none;">
                    <div class="lex-stat">
                        <span class="lex-stat__icon"><i class="bi bi-info-circle"></i></span>
                        <span>
                            <span class="lex-stat__label">Fungsi</span>
                            <span class="lex-stat__value" style="font-size:.8rem">${escapeHtml(room.room_type || '—')}</span>
                        </span>
                    </div>
                    <div class="lex-stat">
                        <span class="lex-stat__icon"><i class="bi bi-box-seam"></i></span>
                        <span>
                            <span class="lex-stat__label">Inventaris</span>
                            <span class="lex-stat__value">${room.inventory_items_count || 0}</span>
                        </span>
                    </div>
                </div>
                <span class="lex-cta" style="position: relative; z-index: 2; pointer-events: none;">
                    Lihat Isi Ruangan <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </span>
            </div>
        </div>`;
    }

    function upsertCard(room) {
        const html = buildCard(room);
        const $existing = $(`.room-card-col[data-room-id="${room.id}"]`);
        if ($existing.length) $existing.replaceWith(html);
        else {
            $('#roomEmptyState').remove();
            $('#roomCardGrid').append(html);
        }
        updateRoomCount();
    }

    function resetForm() {
        $('#roomForm')[0]?.reset();
        $('#room_floor_id').val(floorId);
        $('#room_form_method').val('POST');
        $('#roomForm').data('mode', 'create').data('update-url', '');
        $('#modalRoomFormTitle').text('Tambah Ruangan');
        $('#room_is_active').prop('checked', true);
        $('#roomFormErrors').addClass('d-none').empty();
        if (typeof setButtonLoading === 'function') setButtonLoading($('#btnSaveRoom')[0], false);
        else $('#btnSaveRoom').prop('disabled', false);
    }

    $('#btnOpenCreateRoom').on('click', resetForm);
    $('#modalRoomForm').on('show.bs.modal', function (e) {
        const related = e.relatedTarget;
        if (related && $(related).is('[data-bs-target="#modalRoomForm"]') && !$(related).hasClass('btn-edit-room')) {
            resetForm();
        }
    });

    $(document).on('click', '.btn-edit-room', function (e) {
        e.preventDefault();
        e.stopPropagation();
        const $btn = $(this);
        resetForm();
        $('#roomForm').data('mode', 'edit').data('update-url', $btn.data('update'));
        $('#room_form_method').val('PUT');
        $('#modalRoomFormTitle').text('Edit Ruangan');
        $('#room_code').val($btn.data('code'));
        $('#room_name').val($btn.data('name'));
        const type = $btn.data('type');
        if (type && !$('#room_type option[value="' + type + '"]').length) {
            $('#room_type').append(`<option value="${escapeHtml(type)}">${escapeHtml(type)}</option>`);
        }
        $('#room_type').val(type);
        $('#room_capacity').val($btn.data('capacity'));
        $('#room_is_active').prop('checked', String($btn.data('active')) === '1');
        $('#room_notes').val($btn.data('notes') || '');
        bootstrap.Modal.getOrCreateInstance(document.getElementById('modalRoomForm')).show();
    });

    $('#roomForm').on('submit', function (e) {
        e.preventDefault();
        const mode = $(this).data('mode') || 'create';
        const url = mode === 'edit' ? $(this).data('update-url') : storeUrl;
        const payload = {
            floor_id: floorId,
            code: $('#room_code').val().trim(),
            name: $('#room_name').val().trim(),
            room_type: $('#room_type').val(),
            capacity: $('#room_capacity').val(),
            is_active: $('#room_is_active').is(':checked') ? 1 : 0,
            notes: $('#room_notes').val(),
            _token: csrf,
        };
        if (mode === 'edit') payload._method = 'PUT';

        const $save = $('#btnSaveRoom');
        if (typeof setButtonLoading === 'function') setButtonLoading($save[0], true, 'Menyimpan...');
        else $save.prop('disabled', true);
        $('#roomFormErrors').addClass('d-none').empty();

        $.ajax({
            url,
            method: 'POST',
            data: payload,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            undiraTarget: '#modalRoomForm .modal-body',
            success(res) {
                if (typeof setButtonLoading === 'function') setButtonLoading($save[0], false);
                bootstrap.Modal.getInstance(document.getElementById('modalRoomForm'))?.hide();
                upsertCard(res.data);
                if (typeof showToast === 'function') showToast(res.message || 'Berhasil', 'success');
            },
            error(xhr) {
                if (typeof setButtonLoading === 'function') setButtonLoading($save[0], false);
                else $save.prop('disabled', false);
                const errors = xhr.responseJSON?.errors;
                const msg = xhr.responseJSON?.message || 'Gagal menyimpan ruangan.';
                if (errors) {
                    $('#roomFormErrors').removeClass('d-none').html(Object.values(errors).flat().map(escapeHtml).join('<br>'));
                } else {
                    $('#roomFormErrors').removeClass('d-none').text(msg);
                }
            }
        });
    });

    $(document).on('click', '.btn-del-room', function () {
        const url = $(this).data('url');
        const name = $(this).data('name');
        const count = Number($(this).data('count') || 0);
        const id = $(this).data('id');

        if (count > 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Tidak dapat dihapus',
                text: 'Ruangan masih memiliki inventaris. Pindahkan atau hapus inventaris terlebih dahulu.',
                confirmButtonColor: '#2E7D32',
            });
            return;
        }

        Swal.fire({
            title: 'Pindahkan data ke Trash?',
            html: `Ruangan <strong>${escapeHtml(name)}</strong> beserta inventaris di dalamnya masih dapat dipulihkan dari menu Trash.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#2E7D32',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Pindahkan ke Trash',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url,
                method: 'POST',
                data: { _token: csrf, _method: 'DELETE' },
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success(res) {
                    $(`.room-card-col[data-room-id="${id}"]`).fadeOut(200, function () {
                        $(this).remove();
                        updateRoomCount();
                    });
                    if (typeof showToast === 'function') showToast(res.message || 'Ruangan berhasil dihapus.', 'success');
                },
                error(xhr) {
                    Swal.fire('Gagal', xhr.responseJSON?.message || 'Gagal menghapus ruangan.', 'error');
                }
            });
        });
    });

    $(document).on('click', '.btn-dup-room', function () {
        const url = $(this).data('url');
        Swal.fire({
            title: 'Duplikasi ruangan?',
            text: 'Salinan ruangan akan dibuat di lantai yang sama (tanpa inventaris).',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2E7D32',
            confirmButtonText: 'Duplikasi',
            cancelButtonText: 'Batal',
        }).then((result) => {
            if (!result.isConfirmed) return;
            $.ajax({
                url,
                method: 'POST',
                data: { _token: csrf },
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                success(res) {
                    upsertCard(res.data);
                    if (typeof showToast === 'function') showToast(res.message || 'Ruangan berhasil diduplikasi.', 'success');
                },
                error(xhr) {
                    Swal.fire('Gagal', xhr.responseJSON?.message || 'Gagal menduplikasi.', 'error');
                }
            });
        });
    });
})();
</script>
@endcan
<script>
(function () {
    const input = document.getElementById('lexRoomSearch');
    if (!input) return;
    input.addEventListener('input', function () {
        const q = (input.value || '').trim().toLowerCase();
        document.querySelectorAll('#roomCardGrid .lex-filter-card').forEach(function (card) {
            const hay = card.getAttribute('data-filter-text') || '';
            card.classList.toggle('lex-hidden', q !== '' && hay.indexOf(q) === -1);
        });
    });
})();
</script>
@endpush
