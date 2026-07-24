@extends('layouts.app')

@section('title', 'Hasil Laporan - Inventaris UNDIRA')

@section('content')
<link rel="stylesheet" href="{{ asset('css/print.css') }}">
<style>
    /* Preview inside dashboard: keep print.css look, soft frame only on screen */
    .rpt-preview-frame {
        background: #fff;
        border: 1px solid #c8c8c8;
        padding: 16px;
    }
    @media print {
        .content-wrapper, .main-footer, .main-header, .main-sidebar { display: none !important; }
        .rpt-preview-frame { border: none; padding: 0; }
    }
</style>

<x-page-header
    class="no-print"
    :title="$meta['title']"
    :subtitle="($meta['printed_date'] ?? $meta['printed_at']).' · '.$meta['user_name']"
    icon="bi bi-bar-chart"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Laporan', 'url' => route('reports.index')],
        ['label' => 'Hasil'],
    ]"
    :back-route="route('reports.index')"
    back-label="Laporan"
    :list-back="true"
>
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Cetak</button>
</x-page-header>

<div class="rpt-preview-frame rpt-doc rpt-{{ $meta['orientation'] ?? 'portrait' }}">
    @include('reports._document-header', ['embedLogo' => false])
    @include('reports._hierarchy')
    @include('reports._document-footer')
</div>
@endsection
