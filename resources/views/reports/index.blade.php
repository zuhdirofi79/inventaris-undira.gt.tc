@extends('layouts.app')

@section('title', 'Laporan - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item active">Laporan</li>
@endsection

@php
    use App\Models\Campus;
    use App\Models\Category;
    $campuses = Campus::where('is_active', true)->orderBy('name')->get();
    $categories = Category::where('is_active', true)->orderBy('name')->get();
@endphp

@section('content')
<div class="page-header-undira">
    <h1><i class="fas fa-chart-bar me-2"></i>Generate Laporan</h1>
    <p class="text-muted mb-0">Filter dan generate laporan inventaris dalam berbagai format.</p>
</div>

<form action="{{ route('reports.generate') }}" method="POST" target="_blank">
    @csrf
    <div class="card card-undira">
        <div class="card-header"><h5 class="mb-0">Filter Laporan</h5></div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Kampus</label>
                    <select name="campus_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($campuses as $campus)
                            <option value="{{ $campus->id }}">{{ $campus->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kategori</label>
                    <select name="category_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Semua</option>
                        @foreach(\App\Enums\InventoryStatus::cases() as $status)
                            <option value="{{ $status->value }}">{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Kondisi</label>
                    <select name="condition" class="form-select">
                        <option value="">Semua</option>
                        @foreach(\App\Enums\InventoryCondition::cases() as $cond)
                            <option value="{{ $cond->value }}">{{ $cond->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tahun Beli</label>
                    <input type="number" name="purchase_year" class="form-control" min="1900">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Pencarian</label>
                    <input type="text" name="search" class="form-control" placeholder="Nama, no. inventaris...">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tgl Beli Dari</label>
                    <input type="date" name="purchase_date_from" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Tgl Beli Sampai</label>
                    <input type="date" name="purchase_date_to" class="form-control">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Format Output <span class="text-danger">*</span></label>
                    <select name="format" class="form-select" required>
                        <option value="view">Tampil di Browser</option>
                        <option value="excel">Excel (.xlsx)</option>
                        <option value="csv">CSV</option>
                        <option value="pdf">PDF</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-footer">
            <button type="submit" class="btn btn-undira"><i class="fas fa-file-alt me-1"></i>Generate Laporan</button>
        </div>
    </div>
</form>
@endsection
