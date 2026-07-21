@can('category.manage')
{{-- Create Fungsi Ruang --}}
<div class="modal fade" id="modalCreateRoomFunction" tabindex="-1" aria-labelledby="modalCreateRoomFunctionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('room-functions.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateRoomFunctionLabel">Tambah Fungsi Ruang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="rfCreateCode">Kode <span class="text-danger">*</span></label><input type="text" name="code" id="rfCreateCode" class="form-control" required maxlength="30" placeholder="KELAS"></div>
                <div class="mb-3"><label class="form-label" for="rfCreateName">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="rfCreateName" class="form-control" required placeholder="Ruang Kelas"></div>
                <div class="mb-3"><label class="form-label" for="rfCreateSort">Urutan</label><input type="number" name="sort_order" id="rfCreateSort" class="form-control" min="0" value="0"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="rfCreateActive" checked><label class="form-check-label" for="rfCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach(($roomFunctions ?? []) as $fn)
<div class="modal fade" id="modalEditRoomFunction{{ $fn->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('room-functions.update', $fn) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Fungsi Ruang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $fn->code }}" required maxlength="30"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $fn->name }}" required></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" min="0" value="{{ $fn->sort_order }}"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="rfEditActive{{ $fn->id }}" {{ $fn->is_active ? 'checked' : '' }}><label class="form-check-label" for="rfEditActive{{ $fn->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- Create Tahun --}}
<div class="modal fade" id="modalCreatePurchaseYear" tabindex="-1" aria-labelledby="modalCreatePurchaseYearLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('purchase-years.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreatePurchaseYearLabel">Tambah Tahun Pembelian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="pyCreateYear">Tahun <span class="text-danger">*</span></label><input type="number" name="year" id="pyCreateYear" class="form-control" required min="1990" max="2100" placeholder="{{ date('Y') }}"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="pyCreateActive" checked><label class="form-check-label" for="pyCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach(($purchaseYears ?? []) as $year)
<div class="modal fade" id="modalEditPurchaseYear{{ $year->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('purchase-years.update', $year) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Tahun Pembelian</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Tahun <span class="text-danger">*</span></label><input type="number" name="year" class="form-control" value="{{ $year->year }}" required min="1990" max="2100"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="pyEditActive{{ $year->id }}" {{ $year->is_active ? 'checked' : '' }}><label class="form-check-label" for="pyEditActive{{ $year->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach

{{-- Create Kondisi --}}
<div class="modal fade" id="modalCreateCondition" tabindex="-1" aria-labelledby="modalCreateConditionLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('inventory-conditions.store') }}" method="POST" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title" id="modalCreateConditionLabel">Tambah Kondisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label" for="condCreateCode">Kode <span class="text-danger">*</span></label><input type="text" name="code" id="condCreateCode" class="form-control" required maxlength="30" placeholder="BAIK"></div>
                <div class="mb-3"><label class="form-label" for="condCreateName">Nama <span class="text-danger">*</span></label><input type="text" name="name" id="condCreateName" class="form-control" required placeholder="Baik"></div>
                <div class="mb-3"><label class="form-label" for="condCreateSort">Urutan</label><input type="number" name="sort_order" id="condCreateSort" class="form-control" min="0" value="0"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="condCreateActive" checked><label class="form-check-label" for="condCreateActive">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Simpan</button>
            </div>
        </form>
    </div>
</div>
@foreach(($inventoryConditions ?? []) as $cond)
<div class="modal fade" id="modalEditCondition{{ $cond->id }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form action="{{ route('inventory-conditions.update', $cond) }}" method="POST" class="modal-content">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title">Edit Kondisi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3"><label class="form-label">Kode <span class="text-danger">*</span></label><input type="text" name="code" class="form-control" value="{{ $cond->code }}" required maxlength="30"></div>
                <div class="mb-3"><label class="form-label">Nama <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" value="{{ $cond->name }}" required></div>
                <div class="mb-3"><label class="form-label">Urutan</label><input type="number" name="sort_order" class="form-control" min="0" value="{{ $cond->sort_order }}"></div>
                <div class="form-check"><input type="checkbox" name="is_active" value="1" class="form-check-input" id="condEditActive{{ $cond->id }}" {{ $cond->is_active ? 'checked' : '' }}><label class="form-check-label" for="condEditActive{{ $cond->id }}">Aktif</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-undira">Perbarui</button>
            </div>
        </form>
    </div>
</div>
@endforeach
@endcan
