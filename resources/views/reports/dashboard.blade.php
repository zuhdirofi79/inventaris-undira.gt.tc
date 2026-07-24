@extends('layouts.app')

@section('title', 'Dashboard Laporan - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Dashboard Laporan"
    subtitle="Pusat pelaporan inventaris gaya Enterprise Asset Management — Universitas Dian Nusantara"
    icon="bi bi-bar-chart"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Laporan'],
    ]"
    :back-route="route('dashboard')"
    back-label="Dashboard"
/>

@php $cards = $dashboard['cards'] ?? []; @endphp
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Total Inventaris</div>
            <div class="fs-3 fw-bold">{{ number_format($cards['total'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Aktif</div>
            <div class="fs-3 fw-bold text-success">{{ number_format($cards['aktif'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Dipinjam</div>
            <div class="fs-3 fw-bold text-primary">{{ number_format($cards['dipinjam'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Rusak</div>
            <div class="fs-3 fw-bold text-danger">{{ number_format($cards['rusak'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Maintenance</div>
            <div class="fs-3 fw-bold text-warning">{{ number_format($cards['maintenance'] ?? 0) }}</div>
        </div></div>
    </div>
    <div class="col-6 col-md-3 col-xl">
        <div class="card card-undira h-100"><div class="card-body py-3">
            <div class="text-muted small text-uppercase fw-bold">Non Aktif</div>
            <div class="fs-3 fw-bold">{{ number_format($cards['non_aktif'] ?? 0) }}</div>
        </div></div>
    </div>
</div>

<div class="row g-3">
    @foreach($types as $t)
        <div class="col-md-6 col-lg-4 col-xl-3">
            <a href="{{ route('reports.show', $t->value) }}" class="text-decoration-none">
                <div class="card card-undira h-100 report-type-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="rounded-3 bg-light d-inline-flex align-items-center justify-content-center" style="width:2.75rem;height:2.75rem;">
                                <i class="{{ $t->icon() }} text-undira-blue"></i>
                            </div>
                            <div>
                                <div class="fw-bold text-dark">{{ $t->label() }}</div>
                                <div class="small text-muted mt-1">{{ $t->description() }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
    @endforeach
</div>
@endsection

@push('styles')
<style>
.report-type-card { transition: transform .15s ease, box-shadow .15s ease; border: 1px solid var(--ui-border, #E6EAF2); }
.report-type-card:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(15,23,42,.08); }
</style>
@endpush
