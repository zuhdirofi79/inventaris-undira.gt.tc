@extends('layouts.app')

@section('title', $floor->name . ' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('campuses.index') }}">Jelajahi Lokasi</a></li>
    <li class="breadcrumb-item"><a href="{{ route('campuses.show', $floor->building->campus) }}">{{ $floor->building->campus->name }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('buildings.show', $floor->building) }}">{{ $floor->building->name }}</a></li>
    <li class="breadcrumb-item active">{{ $floor->name }}</li>
@endsection

@php
    $campus = $floor->building->campus;
    $building = $floor->building;
@endphp

@section('content')
<div class="page-header-undira d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
    <div>
        <h1 class="mb-1"><i class="fas fa-layer-group me-2"></i>{{ $floor->name }}</h1>
        <p class="text-muted mb-0 small">
            {{ $campus->name }} &middot; {{ $building->name }} &middot; Lantai {{ $floor->floor_number }}
            &middot; <span id="roomCountLabel">{{ $floor->rooms->count() }}</span> Ruangan
        </p>
    </div>
    @can('location.manage')
    <button type="button" class="btn btn-undira btn-sm" id="btnOpenCreateRoom" data-bs-toggle="modal" data-bs-target="#modalRoomForm" data-no-loader="1">
        <i class="fas fa-plus me-1"></i>Tambah Ruangan
    </button>
    @endcan
</div>

<div class="row g-3" id="roomCardGrid">
    @forelse($floor->rooms as $room)
        @include('locations.floors._room-card', ['room' => $room, 'canManageRooms' => $canManageRooms, 'floor' => $floor])
    @empty
    <div class="col-12" id="roomEmptyState">
        <div class="empty-state card card-undira">
            <div class="card-body">
                <i class="fas fa-door-open"></i>
                <p class="fw-semibold mb-1">Belum ada ruangan di lantai ini</p>
                @can('location.manage')
                <p class="mb-2">Tambahkan ruangan agar inventaris dapat ditempatkan.</p>
                <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalRoomForm" data-no-loader="1">
                    <i class="fas fa-plus me-1"></i>Tambah Ruangan
                </button>
                @else
                <p class="mb-0">Belum ada data ruangan aktif.</p>
                @endcan
            </div>
        </div>
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
        'Kelas': 'fa-chalkboard-teacher',
        'Lab': 'fa-flask',
        'Kantor': 'fa-briefcase',
        'Aula': 'fa-users',
        'Perpustakaan': 'fa-book',
        'Meeting Room': 'fa-handshake',
        'Gudang': 'fa-warehouse',
        'Lainnya': 'fa-door-open'
    };

    function roomIcon(type) {
        return typeIcons[type] || 'fa-door-open';
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
                    <div class="col-12" id="roomEmptyState">
                        <div class="empty-state card card-undira"><div class="card-body">
                            <i class="fas fa-door-open"></i>
                            <p class="fw-semibold mb-1">Belum ada ruangan di lantai ini</p>
                        </div></div>
                    </div>`);
            }
        } else {
            $('#roomEmptyState').remove();
        }
    }

    function buildCard(room) {
        const icon = roomIcon(room.room_type);
        const statusBadge = room.is_active
            ? '<span class="badge bg-success">Aktif</span>'
            : '<span class="badge bg-secondary">Nonaktif</span>';
        const typeBadge = room.room_type
            ? `<span class="badge bg-undira-blue">${escapeHtml(room.room_type)}</span>`
            : '';
        const inventBtn = inventoryCreateUrl
            ? `<a href="${inventoryCreateUrl}&room_id=${room.id}" class="btn btn-sm btn-outline-success" title="Tambah inventaris via Konfigurasi"><i class="fas fa-plus"></i></a>`
            : '';

        const actions = `
            <li><a class="dropdown-item" href="${room.urls.show}"><i class="fas fa-eye me-2 text-primary"></i>Detail</a></li>
            <li><a class="dropdown-item" href="${room.urls.show}"><i class="fas fa-boxes me-2 text-undira-green"></i>Kelola Inventaris</a></li>
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
                <i class="fas fa-edit me-2 text-warning"></i>Edit</button></li>
            <li><button type="button" class="dropdown-item btn-dup-room" data-url="${room.urls.duplicate}">
                <i class="fas fa-copy me-2 text-secondary"></i>Duplikasi Ruangan</button></li>
            <li><button type="button" class="dropdown-item text-danger btn-del-room"
                data-id="${room.id}" data-name="${escapeHtml(room.name)}" data-url="${room.urls.destroy}"
                data-count="${room.inventory_items_count || 0}">
                <i class="fas fa-trash me-2"></i>Hapus</button></li>`;

        return `
        <div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3 room-card-col" data-room-id="${room.id}">
            <div class="card room-explore-card card-undira">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="room-explore-icon"><i class="fas ${icon}"></i></span>
                        <div class="d-flex align-items-center gap-1">
                            ${typeBadge}
                            <div class="dropdown room-explore-actions">
                                <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end shadow">${actions}</ul>
                            </div>
                        </div>
                    </div>
                    <h6 class="room-card-title text-break">${escapeHtml(room.name)}</h6>
                    <p class="room-card-code small"><code>${escapeHtml(room.code)}</code></p>
                    <div class="d-flex flex-wrap gap-1 mb-2">
                        <span class="badge bg-secondary"><i class="fas fa-box me-1"></i>${room.inventory_items_count || 0} Item</span>
                        <span class="badge bg-light text-dark"><i class="fas fa-user-friends me-1"></i>Kap. ${room.capacity ?? '-'}</span>
                        ${statusBadge}
                    </div>
                    <div class="d-flex flex-wrap gap-1">
                        <a href="${room.urls.show}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                        ${inventBtn}
                        <button type="button" class="btn btn-sm btn-outline-warning btn-edit-room"
                            data-id="${room.id}" data-code="${escapeHtml(room.code)}" data-name="${escapeHtml(room.name)}"
                            data-type="${escapeHtml(room.room_type || '')}" data-capacity="${room.capacity ?? ''}"
                            data-active="${room.is_active ? 1 : 0}" data-notes="${escapeHtml(room.notes || '')}"
                            data-update="${room.urls.update}" title="Edit"><i class="fas fa-edit"></i></button>
                    </div>
                </div>
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
@endpush
