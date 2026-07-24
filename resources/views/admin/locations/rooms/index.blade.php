@extends('layouts.app')

@section('title', 'Kelola Ruangan - ' . $floor->name)

@section('content')
<x-page-header
    :title="'Ruangan: ' . $floor->name"
    subtitle="Kelola ruangan pada lantai ini"
    icon="bi bi-door-open"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Kelola Kampus', 'url' => route('admin.locations.campuses.index')],
        ['label' => $floor->building->campus->name, 'url' => route('admin.locations.buildings.index', $floor->building->campus)],
        ['label' => $floor->building->name, 'url' => route('admin.locations.floors.index', $floor->building)],
        ['label' => $floor->name],
    ]"
    :back-route="route('admin.locations.floors.index', $floor->building)"
    :back-label="$floor->building->name"
    :list-back="true"
>
    <button type="button" class="btn btn-undira btn-sm" data-bs-toggle="modal" data-bs-target="#modalCreate" data-no-loader="1">
        <i class="bi bi-plus-lg me-1"></i>Tambah Ruangan
    </button>
</x-page-header>

<div class="card card-undira">
    <div class="card-body p-0">
        <table class="table table-hover mb-0">
            <thead><tr><th>Kode</th><th>Nama</th><th>Tipe</th><th>Kapasitas</th><th>Inventaris</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                @forelse($rooms as $room)
                <tr>
                    <td><code>{{ $room->code }}</code></td>
                    <td><a href="{{ route('rooms.show', $room) }}">{{ $room->name }}</a></td>
                    <td>{{ $room->room_type ?? '-' }}</td>
                    <td>{{ $room->capacity ?? '-' }}</td>
                    <td>{{ $room->inventory_items_count }}</td>
                    <td><span class="badge {{ $room->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $room->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#modalEdit{{ $room->id }}" data-no-loader="1"><i class="fas fa-edit"></i></button>
                        <form action="{{ route('admin.locations.rooms.destroy', $room) }}" method="POST" class="d-inline"
                              data-confirm-title="Pindahkan data ke Trash?"
                              data-confirm="Ruangan beserta inventaris di dalamnya akan masuk Trash dan masih dapat dipulihkan."
                              data-confirm-button="Pindahkan ke Trash">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center text-muted py-4">Belum ada ruangan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rooms->hasPages())<div class="card-footer">{{ $rooms->links() }}</div>@endif
</div>
@endsection

@push('modals')
<div class="modal fade" id="modalCreate" tabindex="-1" aria-labelledby="modalCreateRoomLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <form action="{{ route('admin.locations.rooms.store') }}" method="POST" class="modal-content">
            @csrf
            <input type="hidden" name="floor_id" value="{{ $floor->id }}">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateRoomLabel">Tambah Ruangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required></div>
                <div class="mb-3"><label class="form-label">Jenis Ruangan <span class="text-danger">*</span></label><input type="text" name="room_type" class="form-control" placeholder="Lab, Kelas, Kantor..." required></div>
                <div class="mb-3"><label class="form-label">Kapasitas <span class="text-danger">*</span></label><input type="number" name="capacity" class="form-control" min="1" required></div>
                <div class="mb-3"><label class="form-label">Keterangan</label><textarea name="notes" class="form-control" rows="2"></textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="roomCreateActive" checked>
                    <label class="form-check-label" for="roomCreateActive">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>

@foreach($rooms as $room)
<div class="modal fade" id="modalEdit{{ $room->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <form action="{{ route('admin.locations.rooms.update', $room) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Ruangan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $room->code }}" required maxlength="20"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $room->name }}" required></div>
                <div class="mb-3"><label class="form-label">Jenis Ruangan <span class="text-danger">*</span></label><input type="text" name="room_type" class="form-control" value="{{ $room->room_type }}" required></div>
                <div class="mb-3"><label class="form-label">Kapasitas <span class="text-danger">*</span></label><input type="number" name="capacity" class="form-control" value="{{ $room->capacity }}" min="1" required></div>
                <div class="mb-3"><label class="form-label">Keterangan</label><textarea name="notes" class="form-control" rows="2">{{ $room->notes }}</textarea></div>
                <div class="form-check">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="roomEditActive{{ $room->id }}" {{ $room->is_active ? 'checked' : '' }}>
                    <label class="form-check-label" for="roomEditActive{{ $room->id }}">Aktif</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endpush
