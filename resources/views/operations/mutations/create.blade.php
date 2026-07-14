@extends('layouts.app')

@section('title', 'Tambah Mutasi - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('mutations.index') }}">Mutasi</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="page-header-undira"><h1><i class="fas fa-plus me-2"></i>Tambah Mutasi</h1></div>

<form action="{{ route('mutations.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Inventaris <span class="text-danger">*</span></label>
                <select name="inventory_item_id" id="inventory_item_id" class="form-select select2" required>
                    <option value="">Pilih Inventaris</option>
                    @foreach($items as $item)
                        <option value="{{ $item->id }}" data-room="{{ $item->room_id }}">{{ $item->inventory_number }} - {{ $item->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Pindah ke Ruangan <span class="text-danger">*</span></label>
                <select name="to_room_id" class="form-select select2" required>
                    <option value="">Pilih Ruangan Tujuan</option>
                    @foreach($rooms as $room)
                        <option value="{{ $room->id }}">{{ $room->full_location }}</option>
                    @endforeach
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
<script>$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });</script>
@endpush
