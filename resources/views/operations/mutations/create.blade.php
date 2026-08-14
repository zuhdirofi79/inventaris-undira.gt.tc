@extends('layouts.app')

@section('title', 'Tambah Mutasi - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Tambah Mutasi"
    subtitle="Pilih inventaris dan ruangan tujuan untuk memindahkan aset"
    icon="bi bi-plus-lg"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Mutasi', 'url' => route('mutations.index')],
        ['label' => 'Tambah'],
    ]"
    :back-route="route('mutations.index')"
    back-label="Daftar Mutasi"
    :list-back="true"
/>

<form action="{{ route('mutations.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="mb-3">
                <x-inventory-selector
                    name="inventory_item_id"
                    id="mutation-inventory-selector"
                    :selected-id="$selectedItem?->id"
                    :selected-label="$selectedItem ? ($selectedItem->inventory_number.' - '.$selectedItem->name) : null"
                    modes="both"
                />
            </div>
            <div class="mb-3">
                <label class="form-label">Pindah ke Ruangan <span class="text-danger">*</span></label>
                <select name="to_room_id" id="to_room_id" class="form-select select2-room" required
                        data-lookup-url="{{ route('inventory.lookup-rooms') }}"
                        data-placeholder="Ketik kode / nama ruangan…">
                    <option value="">Pilih Ruangan Tujuan</option>
                    @if($selectedRoom)
                        <option value="{{ $selectedRoom->id }}" selected>{{ $selectedRoom->full_location }}</option>
                    @endif
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Alasan <span class="text-danger">*</span></label>
                <input type="text" name="reason" class="form-control" value="{{ old('reason') }}" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
            <a href="{{ route('mutations.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
(function () {
    if (!$.fn.select2) return;
    function bindAjaxSelect($el) {
        $el.select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: $el.data('placeholder'),
            minimumInputLength: 1,
            ajax: {
                url: $el.data('lookup-url'),
                dataType: 'json',
                delay: 250,
                data: function (params) { return { q: params.term }; },
                processResults: function (data) { return data; },
            },
        });
    }
    bindAjaxSelect($('.select2-room'));
})();
</script>
@endpush
