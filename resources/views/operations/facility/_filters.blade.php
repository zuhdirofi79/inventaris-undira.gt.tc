@php
    $dateFrom = $dateFrom ?? '';
    $dateTo = $dateTo ?? '';
    $showDates = $showDates ?? false;
@endphp
<form method="GET" action="{{ $action }}" class="row g-2 align-items-end mb-3" data-filter-form>
    <div class="col-12 col-md-6 {{ $showDates ? 'col-xl-3' : 'col-xl-4' }}">
        <label class="form-label" for="filter-q">Pencarian</label>
        <input id="filter-q" type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nomor, peminjam, atau subjek">
    </div>
    <div class="col-6 col-md-3 {{ $showDates ? 'col-xl-2' : 'col-xl-3' }}">
        <label class="form-label" for="filter-campus">Lokasi Kampus</label>
        <select id="filter-campus" name="campus_id" class="form-select">
            <option value="">Semua Kampus</option>
            @foreach($campuses as $campus)
                <option value="{{ $campus->id }}" @selected((string) $campusId === (string) $campus->id)>
                    {{ $campus->code }} — {{ $campus->name }}
                </option>
            @endforeach
        </select>
    </div>
    @if(!empty($statuses))
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">Semua Status</option>
                @foreach($statuses as $option)
                    <option value="{{ $option['value'] }}" @selected($status === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endif
    @if($showDates)
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="filter-date-from">Dari Tanggal</label>
            <input id="filter-date-from" type="date" name="date_from" class="form-control" value="{{ $dateFrom }}">
        </div>
        <div class="col-6 col-md-3 col-xl-2">
            <label class="form-label" for="filter-date-to">Sampai Tanggal</label>
            <input id="filter-date-to" type="date" name="date_to" class="form-control" value="{{ $dateTo }}">
        </div>
    @endif
    <div class="col-12 col-md-auto d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-undira">{{ $showDates ? 'Filter' : 'Terapkan' }}</button>
        <a href="{{ $action }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
@if ($errors->any())
    <div class="alert alert-danger mb-3 py-2">{{ $errors->first() }}</div>
@endif
