@props([
    'context' => null,
    'id' => 'inventory-maintenance-context',
])

@php
    $ctx = $context ?? [];
    $has = ! empty($ctx['id']);
@endphp

<div
    id="{{ $id }}"
    class="card card-undira mb-3 {{ $has ? '' : 'd-none' }}"
    data-inventory-maintenance-context
    data-context-url="{{ url('/inventory/selector/items') }}"
>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Ringkasan Inventaris</h5>
        <span class="badge bg-secondary" data-mc-status>{{ $ctx['status_label'] ?? '' }}</span>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-2 text-center">
                <img data-mc-photo src="{{ $ctx['photo_url'] ?? '' }}" alt=""
                     class="img-fluid rounded border {{ empty($ctx['photo_url']) ? 'd-none' : '' }}"
                     style="max-height:120px;">
                <div data-mc-photo-empty class="text-muted small py-4 {{ empty($ctx['photo_url']) ? '' : 'd-none' }}">Tanpa foto</div>
            </div>
            <div class="col-md-10">
                <div class="row g-2 small">
                    <div class="col-sm-6">
                        <div class="text-muted">No. Inventaris</div>
                        <div class="fw-semibold"><code data-mc-number>{{ $ctx['inventory_number'] ?? '—' }}</code></div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted">Nama</div>
                        <div class="fw-semibold" data-mc-name>{{ $ctx['name'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted">Kondisi saat ini</div>
                        <div class="fw-semibold" data-mc-condition>{{ $ctx['condition_label'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted">PIC</div>
                        <div class="fw-semibold" data-mc-pic>{{ $ctx['pic'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted">Garansi</div>
                        <div class="fw-semibold" data-mc-warranty>{{ $ctx['warranty_label'] ?? '—' }}</div>
                    </div>
                    <div class="col-sm-6">
                        <div class="text-muted">Lokasi</div>
                        <div class="fw-semibold" data-mc-location>{{ $ctx['location'] ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-3">

        <div class="row g-3">
            <div class="col-lg-4">
                <h6 class="text-undira-blue mb-2">Work Order Terbaru</h6>
                <div data-mc-latest-wo class="small border rounded p-2 bg-light">
                    @if(!empty($ctx['latest_work_order']))
                        <div class="fw-semibold">{{ $ctx['latest_work_order']['maintenance_number'] }}</div>
                        <div>{{ $ctx['latest_work_order']['type_label'] }} · {{ $ctx['latest_work_order']['status_label'] }}</div>
                        <div class="text-muted">{{ $ctx['latest_work_order']['service_date'] ?? '—' }} · {{ $ctx['latest_work_order']['technician'] ?? '—' }}</div>
                        <a href="{{ $ctx['latest_work_order']['url'] }}" class="stretched-link" data-mc-latest-wo-link>Lihat</a>
                    @else
                        <span class="text-muted">Belum ada work order.</span>
                    @endif
                </div>
            </div>
            <div class="col-lg-4">
                <h6 class="text-undira-blue mb-2">Jadwal Pemeliharaan</h6>
                <ul class="list-unstyled small mb-0" data-mc-schedules>
                    @forelse(($ctx['maintenance_schedules'] ?? []) as $sch)
                        <li class="border-bottom py-1">
                            <a href="{{ $sch['url'] }}">{{ $sch['type_label'] }}</a>
                            <div class="text-muted">Berikutnya: {{ $sch['next_maintenance_date'] ?? '—' }} · PIC {{ $sch['responsible_pic'] }}</div>
                        </li>
                    @empty
                        <li class="text-muted">Tidak ada jadwal aktif.</li>
                    @endforelse
                </ul>
            </div>
            <div class="col-lg-4">
                <h6 class="text-undira-blue mb-2">Riwayat Maintenance</h6>
                <ul class="list-unstyled small mb-0" data-mc-history>
                    @forelse(($ctx['maintenance_history'] ?? []) as $row)
                        <li class="border-bottom py-1">
                            <a href="{{ $row['url'] }}">{{ $row['maintenance_number'] }}</a>
                            <div class="text-muted">{{ $row['service_date'] }} · {{ $row['type_label'] }} · {{ $row['status_label'] }}</div>
                        </li>
                    @empty
                        <li class="text-muted">Belum ada riwayat.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
