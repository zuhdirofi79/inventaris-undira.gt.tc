@extends('layouts.app')

@section('title', $report['title'].' - Inventaris UNDIRA')

@section('breadcrumb')
    <li class="breadcrumb-item"><a href="{{ route('maintenance-reports.index') }}">Laporan Maintenance</a></li>
    <li class="breadcrumb-item active">Hasil</li>
@endsection

@section('content')
<x-ui.page-header title="{{ $report['title'] }}" subtitle="{{ $report['meta']['count'] ?? 0 }} baris" icon="bi bi-table">
    <a href="{{ route('maintenance-reports.index') }}" class="btn btn-outline-secondary btn-sm">Filter ulang</a>
    <form action="{{ route('maintenance-reports.generate') }}" method="POST" class="d-inline">
        @csrf
        @foreach($filters as $key => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="format" value="excel">
        <button class="btn btn-outline-success btn-sm">Excel</button>
    </form>
    <form action="{{ route('maintenance-reports.generate') }}" method="POST" class="d-inline">
        @csrf
        @foreach($filters as $key => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="format" value="csv">
        <button class="btn btn-outline-success btn-sm">CSV</button>
    </form>
    <form action="{{ route('maintenance-reports.generate') }}" method="POST" class="d-inline">
        @csrf
        @foreach($filters as $key => $value)
            @if($value !== null && $value !== '')
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
        <input type="hidden" name="format" value="pdf">
        <button class="btn btn-outline-danger btn-sm">PDF</button>
    </form>
</x-ui.page-header>

@if(isset($report['meta']['total_cost']))
<div class="alert alert-light border">Total biaya aktual: <strong>Rp {{ number_format($report['meta']['total_cost'], 0, ',', '.') }}</strong></div>
@endif

<div class="card card-undira">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped table-hover mb-0">
                <thead>
                    <tr>
                        @foreach($report['headings'] as $heading)
                            <th>{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($report['rows'] as $row)
                    <tr>
                        @foreach($row as $cell)
                            <td class="small">{{ $cell === null || $cell === '' ? '—' : $cell }}</td>
                        @endforeach
                    </tr>
                    @empty
                    <tr><td colspan="{{ count($report['headings']) }}" class="text-center text-muted py-4">Tidak ada data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
