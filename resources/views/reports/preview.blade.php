@extends('layouts.app')
@section('title', ($meta['title'] ?? 'Preview Laporan').' - Inventaris UNDIRA')
@section('content')
<link rel="stylesheet" href="{{ asset('css/print.css') }}">
<style>.rpt-preview-frame{background:#fff;border:1px solid #c8c8c8;padding:16px;} @media print{.no-print{display:none!important;}}</style>
<x-page-header class="no-print" :title="$meta['title']" :subtitle="($meta['printed_date'] ?? '').' · '.$meta['user_name']" icon="bi bi-bar-chart"
    :breadcrumbs="[['label'=>'Dashboard','url'=>route('dashboard')],['label'=>'Laporan','url'=>route('reports.index')],['label'=>$meta['title'] ?? 'Preview']]"
    :back-route="route('reports.show', $meta['type'] ?? 'lengkap')" back-label="Kembali" :list-back="true">
    <button type="button" onclick="window.print()" class="btn btn-outline-secondary btn-sm"><i class="bi bi-printer me-1"></i>Cetak</button>
</x-page-header>
<div class="rpt-preview-frame rpt-doc rpt-{{ $meta['orientation'] ?? 'portrait' }}">
    @include('reports._document-header', ['embedLogo' => false])
    @php $type = $type ?? \App\Enums\InventoryReportType::tryFrom($meta['type'] ?? '') ?? \App\Enums\InventoryReportType::Complete; @endphp
    @if($type->isHierarchy())
        @include('reports._hierarchy')
    @else
        @include('reports._flat-table', ['items' => $items])
    @endif
    @include('reports._document-footer')
</div>
@endsection
