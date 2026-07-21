@php $p = $prefix ?? 'create'; @endphp
<div class="row g-3 config-form" data-prefix="{{ $p }}">
    <div class="col-12"><h6 class="text-uppercase text-muted border-bottom pb-1">A. Lokasi</h6></div>

    <div class="col-md-4">
        <label class="form-label">Kampus <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="campus_id" id="{{ $p }}_campus_id" class="form-select cfg-campus" required>
                <option value="">Pilih Kampus</option>
                @foreach(($lookups['campuses'] ?? []) as $c)
                    <option value="{{ $c->id }}" data-code="{{ $c->code }}" @selected(old('campus_id') == $c->id)>{{ $c->name }} ({{ $c->code }})</option>
                @endforeach
            </select>
            @can('location.manage')
            <a href="{{ route('admin.locations.campuses.index') }}" class="btn btn-outline-secondary" title="Kelola Kampus" aria-label="Kelola pilihan Kampus" target="_blank" rel="noopener"><i class="fas fa-cog" aria-hidden="true"></i></a>
            @endcan
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Gedung <span class="text-danger">*</span></label>
        <select name="building_id" id="{{ $p }}_building_id" class="form-select cfg-building" required disabled>
            <option value="">Pilih Kampus dulu</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Lantai <span class="text-danger">*</span></label>
        <select name="floor_id" id="{{ $p }}_floor_id" class="form-select cfg-floor" required disabled>
            <option value="">Pilih Gedung dulu</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Unit / Divisi <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="org_unit_id" id="{{ $p }}_org_unit_id" class="form-select cfg-org-unit" required>
                <option value="">Pilih Unit</option>
                @foreach(($lookups['orgUnits'] ?? []) as $u)
                    <option value="{{ $u->id }}" data-code="{{ $u->code }}" @selected(old('org_unit_id') == $u->id)>{{ $u->name }} ({{ $u->code }})</option>
                @endforeach
            </select>
            @can('unit.manage')
            <a href="{{ route('categories.index', ['tab' => 'units']) }}" class="btn btn-outline-secondary" title="Kelola Unit" aria-label="Kelola Unit / Divisi" target="_blank" rel="noopener"><i class="fas fa-cog" aria-hidden="true"></i></a>
            @endcan
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Fungsi Ruang</label>
        <div class="input-group">
            <select name="room_function_id" id="{{ $p }}_room_function_id" class="form-select cfg-room-function">
                <option value="">Opsional</option>
                @foreach(($lookups['roomFunctions'] ?? []) as $rf)
                    <option value="{{ $rf->id }}" @selected(old('room_function_id') == $rf->id)>{{ $rf->name }} ({{ $rf->code }})</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-manage-option" data-type="room_function" data-target="{{ $p }}_room_function_id" title="Tambah Fungsi Ruang" aria-label="Tambah Fungsi Ruang"><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kode Ruangan <span class="text-danger">*</span></label>
        <select name="room_id" id="{{ $p }}_room_id" class="form-select cfg-room" required disabled>
            <option value="">Pilih Lantai dulu</option>
        </select>
        <div class="form-text">Kode ruangan (contoh A-101), bukan kode lokasi otomatis.</div>
    </div>

    <div class="col-12"><h6 class="text-uppercase text-muted border-bottom pb-1 mt-2">B. Informasi Pengadaan</h6></div>
    <div class="col-md-6">
        <label class="form-label">Jenis Pengadaan <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="procurement_type_id" id="{{ $p }}_procurement_type_id" class="form-select" required>
                <option value="">Pilih</option>
                @foreach(($lookups['procurementTypes'] ?? []) as $pt)
                    <option value="{{ $pt->id }}" @selected(old('procurement_type_id') == $pt->id)>{{ $pt->name }} ({{ $pt->code }})</option>
                @endforeach
            </select>
            @can('procurement.manage')
            <a href="{{ route('categories.index', ['tab' => 'pengadaan']) }}" class="btn btn-outline-secondary" title="Kelola Jenis Pengadaan" aria-label="Kelola Jenis Pengadaan" target="_blank" rel="noopener"><i class="fas fa-cog" aria-hidden="true"></i></a>
            @endcan
        </div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Tahun Pembelian <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="purchase_year_id" id="{{ $p }}_purchase_year_id" class="form-select cfg-purchase-year" required>
                <option value="">Pilih Tahun</option>
                @foreach(($lookups['purchaseYears'] ?? []) as $y)
                    <option value="{{ $y->id }}" @selected(old('purchase_year_id') == $y->id)>{{ $y->year }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-manage-option" data-type="purchase_year" data-target="{{ $p }}_purchase_year_id" title="Tambah Tahun Pembelian" aria-label="Tambah Tahun Pembelian"><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>
    </div>

    <div class="col-12"><h6 class="text-uppercase text-muted border-bottom pb-1 mt-2">C. Klasifikasi Inventaris</h6></div>
    <div class="col-md-4">
        <label class="form-label">Jenis Klasifikasi <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="category_id" id="{{ $p }}_category_id" class="form-select cfg-category" required>
                <option value="">Pilih</option>
                @foreach(($lookups['categories'] ?? []) as $cat)
                    <option value="{{ $cat->id }}" @selected(old('category_id') == $cat->id)>{{ $cat->name }} ({{ $cat->code }})</option>
                @endforeach
            </select>
            <a href="{{ route('categories.index', ['tab' => 'klasifikasi']) }}" class="btn btn-outline-secondary" title="Kelola Klasifikasi" aria-label="Kelola Klasifikasi" target="_blank" rel="noopener"><i class="fas fa-cog" aria-hidden="true"></i></a>
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">Subklasifikasi <span class="text-danger">*</span></label>
        <select name="sub_category_id" id="{{ $p }}_sub_category_id" class="form-select cfg-sub-category" required disabled>
            <option value="">Pilih Klasifikasi dulu</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Kondisi <span class="text-danger">*</span></label>
        <div class="input-group">
            <select name="inventory_condition_id" id="{{ $p }}_inventory_condition_id" class="form-select cfg-condition" required>
                <option value="">Pilih</option>
                @foreach(($lookups['conditions'] ?? []) as $cond)
                    <option value="{{ $cond->id }}" @selected(old('inventory_condition_id') == $cond->id)>{{ $cond->name }}</option>
                @endforeach
            </select>
            <button type="button" class="btn btn-outline-secondary btn-manage-option" data-type="inventory_condition" data-target="{{ $p }}_inventory_condition_id" title="Tambah Kondisi" aria-label="Tambah Kondisi Inventaris"><i class="fas fa-plus" aria-hidden="true"></i></button>
        </div>
    </div>

    @if(($p ?? 'create') !== 'edit')
    <div class="col-12"><h6 class="text-uppercase text-muted border-bottom pb-1 mt-2">D. Data Inventaris Fisik</h6></div>
    <div class="col-md-6">
        <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
        <input type="text" name="asset_name" id="{{ $p }}_asset_name" class="form-control" value="{{ old('asset_name') }}" required placeholder="Contoh: Kursi Kuliah">
    </div>
    <div class="col-md-3">
        <label class="form-label">Jumlah Unit <span class="text-danger">*</span></label>
        <input type="number" name="quantity" id="{{ $p }}_quantity" class="form-control" value="{{ old('quantity', 1) }}" min="1" max="1000" required>
        <div class="form-text">Tiap unit = 1 record + nomor/QR/barcode sendiri.</div>
    </div>
    <div class="col-md-3">
        <label class="form-label">Satuan</label>
        <input type="text" name="unit" id="{{ $p }}_unit" class="form-control" value="{{ old('unit', 'Unit') }}" placeholder="Unit">
    </div>
    <div class="col-md-4">
        <label class="form-label">Merek</label>
        <input type="text" name="brand" id="{{ $p }}_brand" class="form-control" value="{{ old('brand') }}">
    </div>
    <div class="col-md-8">
        <label class="form-label">Catatan</label>
        <input type="text" name="notes" id="{{ $p }}_notes" class="form-control" value="{{ old('notes') }}">
    </div>
    <input type="hidden" name="create_assets" value="1">
    @endif

    <div class="col-12"><h6 class="text-uppercase text-muted border-bottom pb-1 mt-2">{{ ($p ?? 'create') !== 'edit' ? 'E' : 'D' }}. Hasil Otomatis</h6></div>
    <div class="col-md-6">
        <label class="form-label">Kode Lokasi Otomatis</label>
        <input type="text" class="form-control cfg-location-preview" id="{{ $p }}_location_preview" readonly placeholder="CBB-PPPA-A-101" tabindex="-1">
        <div class="form-text cfg-location-error text-danger"></div>
    </div>
    <div class="col-md-6">
        <label class="form-label">Nomor Urut Berikutnya (preview)</label>
        <input type="text" class="form-control cfg-sequence-preview" id="{{ $p }}_sequence_preview" readonly placeholder="000001" tabindex="-1">
        <div class="form-text">Sequence dikonsumsi saat inventaris disimpan. QR Code &amp; Barcode digenerate otomatis.</div>
    </div>

    <div class="col-12">
        <div class="form-check">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="{{ $p }}_is_active" @checked(old('is_active', true))>
            <label class="form-check-label" for="{{ $p }}_is_active">Status Aktif</label>
        </div>
    </div>
</div>
