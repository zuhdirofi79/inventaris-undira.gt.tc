@extends('layouts.app')

@section('title', 'Edit Inventaris - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('inventory.index') }}">Data Inventaris</a></li>
    <li class="breadcrumb-item"><a href="{{ route('inventory.show', $inventory) }}">{{ $inventory->inventory_number }}</a></li>
    <li class="breadcrumb-item active">Edit</li>
@endsection

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-edit me-2"></i>Edit Inventaris</h1>
    <p class="text-muted mb-0">{{ $inventory->inventory_number }} &middot; {{ $inventory->name }}</p>
</div>

<form action="{{ route('inventory.update', $inventory) }}" method="POST">
    @csrf
    @method('PUT')
    @include('inventory._form', ['inventory' => $inventory])
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-undira"><i class="fas fa-save me-1"></i>Perbarui</button>
        <a href="{{ route('inventory.show', $inventory) }}" class="btn btn-outline-secondary">Batal</a>
    </div>
</form>
@endsection

@push('scripts')
@php
    $subCategoryMap = \App\Models\SubCategory::where('is_active', true)->orderBy('name')->get(['id', 'category_id', 'name']);
@endphp
<script>
const subCategoryMap = @json($subCategoryMap->groupBy('category_id'));
$('.select2').select2({ theme: 'bootstrap-5', width: '100%' });

function loadSubCategories(catId, selected) {
    const $sub = $('#sub_category_id');
    $sub.empty().append('<option value="">Pilih Sub Kategori</option>');
    if (!catId || !subCategoryMap[catId]) return;
    subCategoryMap[catId].forEach(function(sub) {
        $sub.append(`<option value="${sub.id}" ${selected == sub.id ? 'selected' : ''}>${sub.name}</option>`);
    });
}

$('#category_id').on('change', function() { loadSubCategories($(this).val(), null); });
loadSubCategories('{{ $inventory->category_id }}', '{{ $inventory->sub_category_id }}');
</script>
@endpush
