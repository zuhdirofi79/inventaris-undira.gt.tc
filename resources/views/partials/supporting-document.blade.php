@if(($document['state'] ?? 'empty') === 'ready')
    <div class="supporting-document" style="max-width:100%">
        <div class="d-flex align-items-start gap-2 mb-2">
            <span class="flex-shrink-0 d-inline-flex align-items-center justify-content-center" style="width:72px;height:72px;border-radius:6px;border:1px solid var(--bs-border-color,#dee2e6)">
                @if($document['isImage'])
                    <i class="fas fa-file-image fa-2x text-primary" aria-hidden="true"></i>
                @else
                    <i class="fas fa-file-pdf fa-2x text-danger" aria-hidden="true"></i>
                @endif
            </span>
            <div class="min-w-0">
                <div class="fw-semibold text-break" style="overflow-wrap:anywhere">{{ $document['name'] }}</div>
                @if(($document['size'] ?? '') !== '')
                    <div class="small text-muted">{{ $document['size'] }}</div>
                @endif
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ $viewUrl }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" data-no-loader="1">
                <i class="fas fa-eye me-1" aria-hidden="true"></i>Lihat Dokumen
            </a>
            <a href="{{ $downloadUrl }}" class="btn btn-sm btn-outline-secondary" data-no-loader="1">
                <i class="fas fa-download me-1" aria-hidden="true"></i>Download
            </a>
        </div>
    </div>
@elseif(($document['state'] ?? '') === 'missing')
    <span class="text-muted">Dokumen pendukung tidak ditemukan.</span>
@else
    <span class="text-muted">Dokumen pendukung tidak tersedia.</span>
@endif
