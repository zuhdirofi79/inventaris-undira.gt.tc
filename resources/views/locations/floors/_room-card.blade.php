@php
    $typeIcons = [
        'Kelas' => 'fa-chalkboard-teacher',
        'Lab' => 'fa-flask',
        'Kantor' => 'fa-briefcase',
        'Aula' => 'fa-users',
        'Perpustakaan' => 'fa-book',
        'Meeting Room' => 'fa-handshake',
        'Gudang' => 'fa-warehouse',
    ];
    $icon = $typeIcons[$room->room_type] ?? 'fa-door-open';
@endphp
<div class="col-12 col-sm-6 col-md-6 col-lg-4 col-xl-3 room-card-col" data-room-id="{{ $room->id }}">
    <div class="card room-explore-card card-undira">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="room-explore-icon"><i class="fas {{ $icon }}"></i></span>
                <div class="d-flex align-items-center gap-1">
                    @if($room->room_type)
                        <span class="badge bg-undira-blue">{{ $room->room_type }}</span>
                    @endif
                    <div class="dropdown room-explore-actions">
                        <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow">
                            <li>
                                <a class="dropdown-item" href="{{ route('rooms.show', $room) }}">
                                    <i class="fas fa-eye me-2 text-primary"></i>Detail
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ route('rooms.show', $room) }}">
                                    <i class="fas fa-boxes me-2 text-undira-green"></i>Kelola Inventaris
                                </a>
                            </li>
                            @if($canManageRooms)
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item btn-edit-room"
                                    data-id="{{ $room->id }}"
                                    data-code="{{ $room->code }}"
                                    data-name="{{ $room->name }}"
                                    data-type="{{ $room->room_type }}"
                                    data-capacity="{{ $room->capacity }}"
                                    data-active="{{ $room->is_active ? 1 : 0 }}"
                                    data-notes="{{ $room->notes }}"
                                    data-update="{{ route('admin.locations.rooms.update', $room) }}">
                                    <i class="fas fa-edit me-2 text-warning"></i>Edit
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item btn-dup-room"
                                    data-url="{{ route('admin.locations.rooms.duplicate', $room) }}">
                                    <i class="fas fa-copy me-2 text-secondary"></i>Duplikasi Ruangan
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item text-danger btn-del-room"
                                    data-id="{{ $room->id }}"
                                    data-name="{{ $room->name }}"
                                    data-url="{{ route('admin.locations.rooms.destroy', $room) }}"
                                    data-count="{{ $room->inventory_items_count }}">
                                    <i class="fas fa-trash me-2"></i>Hapus
                                </button>
                            </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>
            <h6 class="text-undira-blue mb-1 text-break">{{ $room->name }}</h6>
            <p class="text-muted small mb-2"><code>{{ $room->code }}</code></p>
            <div class="d-flex flex-wrap gap-1 mb-2">
                <span class="badge bg-secondary"><i class="fas fa-box me-1"></i>{{ $room->inventory_items_count }} Item</span>
                <span class="badge bg-light text-dark"><i class="fas fa-user-friends me-1"></i>Kap. {{ $room->capacity ?? '-' }}</span>
                <span class="badge {{ $room->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $room->is_active ? 'Aktif' : 'Nonaktif' }}</span>
            </div>
            <div class="d-flex flex-wrap gap-1">
                <a href="{{ route('rooms.show', $room) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                @if($canManageRooms)
                    @canany(['inventory.create', 'category.manage'])
                    <a href="{{ route('categories.index', [
                            'tab' => 'konfigurasi',
                            'create' => 1,
                            'room_id' => $room->id,
                            'floor_id' => $floor->id ?? $room->floor_id,
                            'building_id' => $floor->building_id ?? null,
                            'campus_id' => $floor->building->campus_id ?? null,
                        ]) }}" class="btn btn-sm btn-outline-success" title="Tambah inventaris via Konfigurasi"><i class="fas fa-plus"></i></a>
                    @endcanany
                    <button type="button" class="btn btn-sm btn-outline-warning btn-edit-room"
                        data-id="{{ $room->id }}"
                        data-code="{{ $room->code }}"
                        data-name="{{ $room->name }}"
                        data-type="{{ $room->room_type }}"
                        data-capacity="{{ $room->capacity }}"
                        data-active="{{ $room->is_active ? 1 : 0 }}"
                        data-notes="{{ $room->notes }}"
                        data-update="{{ route('admin.locations.rooms.update', $room) }}"
                        title="Edit"><i class="fas fa-edit"></i></button>
                @endif
            </div>
        </div>
    </div>
</div>
