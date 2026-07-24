@extends('layouts.app')

@section('title', 'Buat Stock Opname - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Buat Stock Opname"
    subtitle="Buat sesi pencatatan stok untuk kampus yang dipilih"
    icon="bi bi-plus-lg"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Stock Opname', 'url' => route('stock-opnames.index')],
        ['label' => 'Buat'],
    ]"
    :back-route="route('stock-opnames.index')"
    back-label="Daftar Stock Opname"
    :list-back="true"
/>

<form action="{{ route('stock-opnames.store') }}" method="POST">
    @csrf
    <div class="card card-undira">
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Judul <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" value="{{ old('title') }}" required placeholder="Stock Opname Lab A - Januari 2026">
            </div>
            <div class="mb-3">
                <label class="form-label">Kampus <span class="text-danger">*</span></label>
                <select name="campus_id" class="form-select" required>
                    <option value="">Pilih Kampus</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" {{ old('campus_id') == $campus->id ? 'selected' : '' }}>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Catatan</label>
                <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
            <a href="{{ route('stock-opnames.index') }}" class="btn btn-outline-secondary">Batal</a>
        </div>
    </div>
</form>
@endsection
