@php
    $isEdit = isset($inventory);
    $item = $inventory ?? null;
    $selectedRoom = $item?->room;
    $selectedCampusId = old('campus_id', $selectedRoom?->floor?->building?->campus_id ?? request('campus_id'));
    $selectedBuildingId = old('building_id', $selectedRoom?->floor?->building_id ?? request('building_id'));
    $selectedFloorId = old('floor_id', $selectedRoom?->floor_id ?? request('floor_id'));
    $selectedRoomId = old('room_id', $item?->room_id ?? request('room_id'));
    $selectedCategoryId = old('category_id', $item?->category_id ?? request('category_id'));
@endphp

<div class="row">
    <div class="col-lg-8">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Informasi Dasar</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Nama Barang <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $item?->name) }}" required>
                    </div>
                    @if(!$isEdit)
                    <div class="col-6 col-md-2">
                        <label class="form-label">Jumlah</label>
                        <input type="number" name="quantity" class="form-control" value="{{ old('quantity', 1) }}" min="1" max="1000">
                        <div class="form-text">Tiap unit = 1 record + nomor/barcode/QR sendiri.</div>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit', 'Unit') }}">
                    </div>
                    @else
                    <div class="col-12 col-md-4">
                        <label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-control" value="{{ old('unit', $item?->unit ?? 'Unit') }}">
                    </div>
                    @endif

                    <div class="col-12 col-md-6">
                        <label class="form-label">Kategori <span class="text-danger">*</span></label>
                        <select name="category_id" id="category_id" class="form-select" required>
                            <option value="">Pilih Kategori</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($selectedCategoryId == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Sub Kategori</label>
                        <select name="sub_category_id" id="sub_category_id" class="form-select">
                            <option value="">Pilih Sub Kategori</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Lokasi</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label">Kampus <span class="text-danger">*</span></label>
                        <select id="campus_id" class="form-select" required>
                            <option value="">Pilih Kampus</option>
                            @foreach($locationData as $campus)
                                <option value="{{ $campus->id }}" @selected($selectedCampusId == $campus->id)>{{ $campus->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Gedung <span class="text-danger">*</span></label>
                        <select id="building_id" class="form-select" required>
                            <option value="">Pilih Gedung</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Lantai <span class="text-danger">*</span></label>
                        <select id="floor_id" class="form-select" required>
                            <option value="">Pilih Lantai</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Ruangan <span class="text-danger">*</span></label>
                        <select name="room_id" id="room_id" class="form-select" required>
                            <option value="">Pilih Ruangan</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Spesifikasi</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <label class="form-label">Merek</label>
                        <input type="text" name="brand" class="form-control" value="{{ old('brand', $item?->brand) }}">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Tipe</label>
                        <input type="text" name="type" class="form-control" value="{{ old('type', $item?->type) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" value="{{ old('model', $item?->model) }}">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">No. Seri</label>
                        <input type="text" name="serial_number" class="form-control" value="{{ old('serial_number', $item?->serial_number) }}">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Unit / Divisi</label>
                        <select name="org_unit_id" class="form-select">
                            <option value="">Pilih Unit/Divisi</option>
                            @foreach($orgUnits ?? [] as $unit)
                                <option value="{{ $unit->id }}" @selected(old('org_unit_id', $item?->org_unit_id) == $unit->id)>{{ $unit->code }} — {{ $unit->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Penanggung Jawab</label>
                        <input type="text" name="responsible_person" class="form-control" value="{{ old('responsible_person', $item?->responsible_person) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Deskripsi / Spesifikasi</label>
                        <textarea name="specification" class="form-control" rows="3">{{ old('specification', $item?->specification) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Pembelian & Nilai</h5></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6 col-md-4">
                        <label class="form-label">Tahun Beli</label>
                        <input type="number" name="purchase_year" class="form-control" value="{{ old('purchase_year', $item?->purchase_year) }}" min="1900">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Tanggal Beli</label>
                        <input type="date" name="purchase_date" class="form-control" value="{{ old('purchase_date', $item?->purchase_date?->format('Y-m-d')) }}">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Jenis Pengadaan</label>
                        <select name="procurement_type_id" class="form-select">
                            <option value="">Pilih Jenis Pengadaan</option>
                            @foreach($procurementTypes ?? [] as $type)
                                <option value="{{ $type->id }}" @selected(old('procurement_type_id', $item?->procurement_type_id) == $type->id)>{{ $type->code }} — {{ $type->name }}</option>
                            @endforeach
                        </select>
                        <input type="hidden" name="funding_source" value="{{ old('funding_source', $item?->funding_source) }}">
                    </div>
                    <div class="col-6 col-md-6">
                        <label class="form-label">Harga (Rp)</label>
                        <input type="number" name="price" class="form-control" value="{{ old('price', $item?->price) }}" min="0" step="0.01">
                    </div>
                    <div class="col-6 col-md-6">
                        <label class="form-label">Nilai Depresiasi</label>
                        <input type="number" name="depreciation_value" class="form-control" value="{{ old('depreciation_value', $item?->depreciation_value) }}" min="0" step="0.01">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Status & Kondisi</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Kondisi <span class="text-danger">*</span></label>
                    <select name="condition" class="form-select" required>
                        @foreach(\App\Enums\InventoryCondition::cases() as $cond)
                            <option value="{{ $cond->value }}" @selected(old('condition', $item?->condition?->value ?? 'bagus') == $cond->value)>{{ $cond->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Status <span class="text-danger">*</span></label>
                    <select name="status" class="form-select" required>
                        @foreach(\App\Enums\InventoryStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $item?->status?->value ?? 'aktif') == $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Supplier & Vendor</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-select">
                        <option value="">Pilih Supplier</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected(old('supplier_id', $item?->supplier_id) == $supplier->id)>{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Vendor</label>
                    <select name="vendor_id" class="form-select">
                        <option value="">Pilih Vendor</option>
                        @foreach($vendors as $vendor)
                            <option value="{{ $vendor->id }}" @selected(old('vendor_id', $item?->vendor_id) == $vendor->id)>{{ $vendor->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card card-undira mb-4">
            <div class="card-header"><h5 class="mb-0">Garansi & Catatan</h5></div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Info Garansi</label>
                    <textarea name="warranty_info" class="form-control" rows="2">{{ old('warranty_info', $item?->warranty_info) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tanggal Habis Garansi</label>
                    <input type="date" name="warranty_expiry" class="form-control" value="{{ old('warranty_expiry', $item?->warranty_expiry?->format('Y-m-d')) }}">
                </div>
                <div class="mb-0">
                    <label class="form-label">Catatan</label>
                    <textarea name="notes" class="form-control" rows="2">{{ old('notes', $item?->notes) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>
