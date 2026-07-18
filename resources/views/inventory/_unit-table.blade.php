{{-- Individual unit rows for a room's inventory groups --}}
<div class="table-responsive d-none d-md-block">
    <table class="table table-sm table-hover mb-0 align-middle">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Unit</th>
                <th>No. Inventaris</th>
                <th>Serial</th>
                <th>Status</th>
                <th>Kondisi</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @foreach($groups as $group)
                @foreach($group['items'] as $item)
                <tr>
                    <td>{{ $item->name }}</td>
                    <td><code>{{ str_pad((string) ($item->unit_sequence ?? 1), 3, '0', STR_PAD_LEFT) }}</code></td>
                    <td><code class="text-break">{{ $item->inventory_number }}</code></td>
                    <td class="text-break">{{ $item->serial_number ?? '-' }}</td>
                    <td><span class="badge bg-secondary">{{ $item->status?->label() }}</span></td>
                    <td><span class="badge bg-info">{{ $item->condition?->label() }}</span></td>
                    <td class="text-end text-nowrap">
                        <a href="{{ route('inventory.show', $item) }}" class="btn btn-sm btn-outline-primary" title="Detail"><i class="fas fa-eye"></i></a>
                        @if($group['group_key'] && count($group['items']) > 1)
                        <a href="{{ route('inventory.group-units', $group['group_key']) }}" class="btn btn-sm btn-outline-secondary" title="Semua unit"><i class="fas fa-layer-group"></i></a>
                        @endif
                    </td>
                </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>
</div>
<div class="d-md-none p-2">
    @foreach($groups as $group)
        @foreach($group['items'] as $item)
        <a href="{{ route('inventory.show', $item) }}" class="text-decoration-none">
            <div class="inventory-item-card mb-2">
                <div class="d-flex justify-content-between gap-2">
                    <strong class="text-dark text-break">{{ $item->name }} <code>#{{ str_pad((string) ($item->unit_sequence ?? 1), 3, '0', STR_PAD_LEFT) }}</code></strong>
                    <span class="badge bg-secondary flex-shrink-0">{{ $item->status?->label() }}</span>
                </div>
                <div class="small text-muted mt-1 text-break">
                    {{ $item->inventory_number }}
                    @if($item->serial_number) · SN {{ $item->serial_number }} @endif
                    · {{ $item->condition?->label() }}
                </div>
            </div>
        </a>
        @endforeach
    @endforeach
</div>
