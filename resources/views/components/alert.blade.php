{{-- Flash success/error/warning ditampilkan sebagai Toast di layouts/app (UX Fase 5). --}}
@php
    $inlineValidationFields = array_fill_keys((array) ($inlineValidationFields ?? []), true);
    $summaryErrors = collect($errors->getMessages())
        ->reject(fn ($messages, $field) => isset($inlineValidationFields[$field]))
        ->flatten();
@endphp
@if($summaryErrors->isNotEmpty())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle me-2"></i>
        <strong>Terjadi kesalahan:</strong>
        <ul class="mb-0 mt-2">
            @foreach($summaryErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif

@if(session('info'))
    <div class="alert alert-info alert-dismissible fade show" role="alert">
        <i class="fas fa-info-circle me-2"></i>{{ session('info') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
    </div>
@endif
