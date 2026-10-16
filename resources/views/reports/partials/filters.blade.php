@php
    /** @var \App\Enums\InventoryReportType $type */
    /** @var array $options */
    $family = $type->family();
@endphp
<div class="card card-undira mb-3" id="reportFilterCard">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filter Laporan</h5>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnResetReportFilters"><i class="fas fa-undo me-1"></i>Reset</button>
    </div>
    <div class="card-body">
        <form id="reportFilterForm" class="row g-2">
            @if($family === 'room-borrowing')
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="campus_id">Kampus</label>
                    <select name="campus_id" id="campus_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['campuses'] as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="building_id">Gedung</label>
                    <select name="building_id" id="building_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="floor_id">Lantai</label>
                    <select name="floor_id" id="floor_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="room_id">Ruangan</label>
                    <select name="room_id" id="room_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="status">Status Peminjaman</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['facilityStatuses'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="approval_status">Status Approval</label>
                    <select name="approval_status" id="approval_status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['facilityStatuses'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="borrower_name">Peminjam</label>
                    <input type="search" name="borrower_name" id="borrower_name" class="form-control form-control-sm" placeholder="Nama peminjam" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="approval_number">Nomor Pengajuan / Approval</label>
                    <input type="search" name="approval_number" id="approval_number" class="form-control form-control-sm" placeholder="Nomor…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="purpose">Keperluan</label>
                    <input type="search" name="purpose" id="purpose" class="form-control form-control-sm" placeholder="Keperluan…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="tanggal_awal">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="tanggal_akhir">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="keyword">Keyword</label>
                    <input type="search" name="keyword" id="keyword" class="form-control form-control-sm" placeholder="Cari…" autocomplete="off">
                </div>
            @elseif($family === 'vehicle-borrowing')
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="campus_id">Kampus</label>
                    <select name="campus_id" id="campus_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['campuses'] as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="status">Status Peminjaman</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['facilityStatuses'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="approval_status">Status Approval</label>
                    <select name="approval_status" id="approval_status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['facilityStatuses'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="service_type">Jenis Layanan</label>
                    <select name="service_type" id="service_type" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['vehicleServiceTypes'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="destination">Rute / Tujuan</label>
                    <input type="search" name="destination" id="destination" class="form-control form-control-sm" placeholder="Tujuan…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="borrower_name">Peminjam</label>
                    <input type="search" name="borrower_name" id="borrower_name" class="form-control form-control-sm" placeholder="Nama peminjam" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="borrower_phone">Nomor Telepon</label>
                    <input type="search" name="borrower_phone" id="borrower_phone" class="form-control form-control-sm" placeholder="08…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="assigned_vehicle_id">Kendaraan</label>
                    <select name="assigned_vehicle_id" id="assigned_vehicle_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['vehicles'] as $v)
                            <option value="{{ $v->id }}">{{ $v->name }} · {{ $v->plate_number }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="assigned_driver_id">Driver</label>
                    <select name="assigned_driver_id" id="assigned_driver_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['drivers'] as $d)
                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="approval_number">Nomor Pengajuan / Approval</label>
                    <input type="search" name="approval_number" id="approval_number" class="form-control form-control-sm" placeholder="Nomor…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="tanggal_awal">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="tanggal_akhir">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="keyword">Keyword</label>
                    <input type="search" name="keyword" id="keyword" class="form-control form-control-sm" placeholder="Cari…" autocomplete="off">
                </div>
            @else
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="campus_id">Kampus</label>
                    <select name="campus_id" id="campus_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['campuses'] as $c)
                            <option value="{{ $c->id }}">{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="building_id">Gedung</label>
                    <select name="building_id" id="building_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="floor_id">Lantai</label>
                    <select name="floor_id" id="floor_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="room_id">Ruangan</label>
                    <select name="room_id" id="room_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-0" for="category_id">Kategori</label>
                    <select name="category_id" id="category_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['categories'] as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="sub_category_id">Subkategori</label>
                    <select name="sub_category_id" id="sub_category_id" class="form-select form-select-sm" disabled><option value="">Semua</option></select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="pic_id">PIC</label>
                    <select name="pic_id" id="pic_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['pics'] as $pic)
                            <option value="{{ $pic->id }}">{{ $pic->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="status">Status</label>
                    <select name="status" id="status" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['statuses'] as $st)
                            <option value="{{ $st->value }}">{{ $st->label() }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-0" for="condition">Kondisi</label>
                    <select name="condition" id="condition" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['conditions'] as $cond)
                            <option value="{{ $cond->value }}">{{ $cond->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="procurement_type_id">Jenis Pengadaan</label>
                    <select name="procurement_type_id" id="procurement_type_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['procurementTypes'] as $pt)
                            <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="purchase_year">Tahun Pengadaan</label>
                    <input type="number" name="purchase_year" id="purchase_year" class="form-control form-control-sm" min="1990" max="2100" placeholder="YYYY">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="tanggal_awal">Tanggal Awal</label>
                    <input type="date" name="tanggal_awal" id="tanggal_awal" class="form-control form-control-sm">
                </div>
                <div class="col-md-2">
                    <label class="form-label small mb-0" for="tanggal_akhir">Tanggal Akhir</label>
                    <input type="date" name="tanggal_akhir" id="tanggal_akhir" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-0" for="keyword">Keyword</label>
                    <input type="search" name="keyword" id="keyword" class="form-control form-control-sm" placeholder="Cari…" autocomplete="off">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="inventory_number">Nomor Inventaris</label>
                    <input type="text" name="inventory_number" id="inventory_number" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="item_name">Nama Barang</label>
                    <input type="text" name="name" id="item_name" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="serial_number">Serial Number</label>
                    <input type="text" name="serial_number" id="serial_number" class="form-control form-control-sm">
                </div>

                <div class="col-md-3">
                    <label class="form-label small mb-0" for="merk">Merk</label>
                    <input type="text" name="merk" id="merk" class="form-control form-control-sm">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="vendor_id">Vendor</label>
                    <select name="vendor_id" id="vendor_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['vendors'] as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-0" for="supplier_id">Supplier</label>
                    <select name="supplier_id" id="supplier_id" class="form-select form-select-sm">
                        <option value="">Semua</option>
                        @foreach($options['suppliers'] as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="include_trash" value="1" id="include_trash">
                        <label class="form-check-label small" for="include_trash">Sertakan trash</label>
                    </div>
                </div>
            @endif
        </form>
    </div>
    <div class="card-footer d-flex flex-wrap gap-2 align-items-center">
        <button type="button" class="btn btn-undira btn-sm" id="btnPreview"><i class="fas fa-eye me-1"></i>Preview</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrint"><i class="fas fa-print me-1"></i>Print</button>
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-danger" id="btnPdfPortrait"><i class="fas fa-file-pdf me-1"></i>PDF Portrait</button>
            <button type="button" class="btn btn-outline-danger" id="btnPdfLandscape">PDF Landscape</button>
        </div>
        <button type="button" class="btn btn-outline-success btn-sm" id="btnExcel"><i class="fas fa-file-excel me-1"></i>Excel</button>
        <button type="button" class="btn btn-outline-dark btn-sm" id="btnCsv"><i class="fas fa-file-csv me-1"></i>CSV</button>
        <span class="ms-auto small text-muted" id="reportLoading" style="display:none;"><i class="fas fa-spinner fa-spin me-1"></i>Memuat data…</span>
    </div>
</div>
