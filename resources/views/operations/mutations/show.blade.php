@extends('layouts.app')

@section('title', 'Detail Mutasi - Inventaris UNDIRA')

@section('content')
<x-page-header
    title="Detail Mutasi"
    subtitle="{{ $mutation->inventoryItem?->inventory_number }} · {{ $mutation->inventoryItem?->name }}"
    icon="bi bi-arrow-left-right"
    :breadcrumbs="[
        ['label' => 'Dashboard', 'url' => route('dashboard')],
        ['label' => 'Mutasi', 'url' => route('mutations.index')],
        ['label' => 'Detail'],
    ]"
    :back-route="route('mutations.index')"
    back-label="Daftar Mutasi"
/>

<div class="card card-undira">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6>Inventaris</h6>
                <p><strong>{{ $mutation->inventoryItem?->name }}</strong><br><code>{{ $mutation->inventoryItem?->inventory_number }}</code></p>
            </div>
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr><th>Dari</th><td>{{ $mutation->fromRoom?->full_location ?? '-' }}</td></tr>
                    <tr><th>Ke</th><td>{{ $mutation->toRoom?->full_location ?? '-' }}</td></tr>
                    <tr><th>Alasan</th><td>{{ $mutation->reason ?? '-' }}</td></tr>
                    <tr><th>Catatan</th><td>{{ $mutation->notes ?? '-' }}</td></tr>
                    <tr><th>Dipindahkan Oleh</th><td>{{ $mutation->movedBy?->name }}</td></tr>
                    <tr><th>Tanggal</th><td>{{ $mutation->created_at->format('d/m/Y H:i') }}</td></tr>
                </table>
            </div>
        </div>
        @if($mutation->inventoryItem)
        <a href="{{ route('inventory.show', $mutation->inventoryItem) }}" class="btn btn-outline-primary btn-sm">Lihat Inventaris</a>
        @endif
    </div>
</div>
@endsection
