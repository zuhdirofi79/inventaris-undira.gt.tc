<form method="GET" action="{{ $action }}" class="row g-2 align-items-end mb-3" data-filter-form>
    <div class="col-md-4">
        <label class="form-label" for="filter-q">Pencarian</label>
        <input id="filter-q" type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Nomor, peminjam, atau subjek">
    </div>
    <div class="col-md-3">
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
        <div class="col-md-3">
            <label class="form-label" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="form-select">
                <option value="">Semua Status</option>
                @foreach($statuses as $option)
                    <option value="{{ $option['value'] }}" @selected($status === $option['value'])>{{ $option['label'] }}</option>
                @endforeach
            </select>
        </div>
    @endif
    <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-undira">Terapkan</button>
        <a href="{{ $action }}" class="btn btn-outline-secondary">Reset</a>
    </div>
</form>
