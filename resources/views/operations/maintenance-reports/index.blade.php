@extends('layouts.app')

@section('title', 'Laporan Maintenance - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Laporan Pemeliharaan"
    subtitle="Riwayat, biaya, preventif, performa vendor/teknisi, aset bermasalah"
    icon="bi bi-file-earmark-bar-graph"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Pemeliharaan Aset', 'url' => route('maintenances.index')],
        ['label' => 'Laporan'],
    ]"
    :back-route="route('maintenances.index')"
    back-label="Pemeliharaan Aset"
>
    <a href="{{ route('maintenance-dashboard.index') }}" class="btn btn-outline-primary btn-sm">Dashboard Maintenance</a>
</x-page-header>

<form action="{{ route('maintenance-reports.generate') }}" method="POST" class="card card-undira">
    @csrf
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Jenis Laporan <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                    @foreach($types as $key => $label)
                        <option value="{{ $key }}" @selected(old('type', 'history') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Dari Tanggal</label>
                <input type="date" name="date_from" class="form-control" value="{{ old('date_from', now()->startOfYear()->toDateString()) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Sampai Tanggal</label>
                <input type="date" name="date_to" class="form-control" value="{{ old('date_to', now()->toDateString()) }}">
            </div>
            <div class="col-md-2">
                <label class="form-label">Kampus</label>
                <select name="campus_id" class="form-select">
                    <option value="">Semua</option>
                    @foreach($campuses as $campus)
                        <option value="{{ $campus->id }}" @selected(old('campus_id') == $campus->id)>{{ $campus->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Status WO</label>
                <select name="status" class="form-select">
                    <option value="">Semua</option>
                    @foreach($statuses as $status)
                        <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12">
                <label class="form-label d-block">Format keluaran</label>
                <div class="btn-group" role="group">
                    <input type="radio" class="btn-check" name="format" id="fmtView" value="view" checked>
                    <label class="btn btn-outline-secondary" for="fmtView">Tampil</label>
                    <input type="radio" class="btn-check" name="format" id="fmtExcel" value="excel">
                    <label class="btn btn-outline-success" for="fmtExcel">Excel</label>
                    <input type="radio" class="btn-check" name="format" id="fmtCsv" value="csv">
                    <label class="btn btn-outline-success" for="fmtCsv">CSV</label>
                    <input type="radio" class="btn-check" name="format" id="fmtPdf" value="pdf">
                    <label class="btn btn-outline-danger" for="fmtPdf">PDF</label>
                </div>
                <div class="form-text">Filter tanggal berlaku untuk laporan berbasis work order. Preventif & aset rusak memakai filter kampus.</div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <button type="submit" class="btn btn-undira"><i class="bi bi-play-fill me-1"></i>Generate</button>
    </div>
</form>
@endsection
