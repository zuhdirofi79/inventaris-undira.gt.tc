@extends('layouts.app')

@section('title', 'Tambah Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item active">Tambah</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-plus me-2"></i>Tambah Inventaris</h1>
</div>

<form action="{{ route('inventory.store') }}" method="POST">
    @csrf
    @include('inventory._form')
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Simpan</button>
        <a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@php
    $subCategoryMap = \App\Models\SubCategory::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'name']);
@endphp

@push('scripts')
<script>
const subCategoryMap = @json($subCategoryMap->groupBy('category_id'));

$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

function loadSubCategories(catId, selected) {
    const $sub = $('#sub_category_id');
    $sub.empty().append('<option value="">Pilih Sub Kategori</option>');
    if (!catId || !subCategoryMap[catId]) return;
    subCategoryMap[catId].forEach(function(sub) {
        const sel = selected == sub.id ? 'selected' : '';
        $sub.append(`<option value="${sub.id}" ${sel}>${sub.name}</option>`);
    });
}

$('#category_id').on('change', function() {
    loadSubCategories($(this).val(), null);
});

@if(old('category_id'))
loadSubCategories('{{ old('category_id') }}', '{{ old('sub_category_id') }}');
@endif
</script>
@endpush
