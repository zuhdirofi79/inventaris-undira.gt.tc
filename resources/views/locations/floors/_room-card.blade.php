@php
    $filterText = strtolower(trim(($room->name ?? '').' '.($room->code ?? '').' '.($room->room_type ?? '')));
@endphp
<div
    class="lex-filter-card room-card-col"
    data-room-id="{{ $room->id }}"
    data-filter-text="{{ $filterText }}"
>
    <div class="lex-card position-relative" style="--lex-accent: #0d9488; cursor: default;">
        <a href="{{ route('rooms.show', $room) }}" class="text-decoration-none text-reset stretched-link" style="z-index: 1;" aria-label="Buka {{ $room->name }}"></a>
        <div class="lex-card__top" style="position: relative; z-index: 2; pointer-events: none;">
            <span class="lex-card__icon" aria-hidden="true"><i class="bi bi-door-open"></i></span>
            <div class="min-w-0 flex-grow-1">
                <h2 class="lex-card__title">{{ $room->name }}</h2>
                <div class="lex-card__meta">
                    @if($room->code)
                        <span class="lex-badge lex-badge--code">{{ $room->code }}</span>
                    @endif
                    @if($room->room_type)
                        <span class="lex-badge lex-badge--soft">{{ $room->room_type }}</span>
                    @endif
                    @if(! $room->is_active)
                        <span class="badge bg-secondary">Nonaktif</span>
                    @endif
                </div>
            </div>
            @if($canManageRooms)
            <div class="dropdown room-explore-actions" style="pointer-events: auto; position: relative; z-index: 3;">
                <button class="btn btn-sm btn-light border" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Aksi" data-no-loader="1">
                    <i class="bi bi-three-dots-vertical"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <li>
                        <a class="dropdown-item" href="{{ route('rooms.show', $room) }}">
                            <i class="bi bi-eye me-2 text-primary"></i>Detail
                        </a>
                    </li>
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
                            <i class="bi bi-pencil me-2 text-warning"></i>Edit
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item btn-dup-room"
                            data-url="{{ route('admin.locations.rooms.duplicate', $room) }}">
                            <i class="bi bi-copy me-2 text-secondary"></i>Duplikasi Ruangan
                        </button>
                    </li>
                    <li>
                        <button type="button" class="dropdown-item text-danger btn-del-room"
                            data-id="{{ $room->id }}"
                            data-name="{{ $room->name }}"
                            data-url="{{ route('admin.locations.rooms.destroy', $room) }}"
                            data-count="{{ $room->inventory_items_count }}">
                            <i class="bi bi-trash me-2"></i>Hapus
                        </button>
                    </li>
                </ul>
            </div>
            @endif
        </div>
        <div class="lex-stats" style="position: relative; z-index: 2; pointer-events: none;">
            <div class="lex-stat">
                <span class="lex-stat__icon"><i class="bi bi-info-circle"></i></span>
                <span>
                    <span class="lex-stat__label">Fungsi</span>
                    <span class="lex-stat__value" style="font-size:.8rem">{{ $room->room_type ?: '—' }}</span>
                </span>
            </div>
            <div class="lex-stat">
                <span class="lex-stat__icon"><i class="bi bi-box-seam"></i></span>
                <span>
                    <span class="lex-stat__label">Inventaris</span>
                    <span class="lex-stat__value">{{ number_format($room->inventory_items_count) }}</span>
                </span>
            </div>
        </div>
        <span class="lex-cta" style="position: relative; z-index: 2; pointer-events: none;">
            Lihat Isi Ruangan <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </span>
    </div>
</div>
