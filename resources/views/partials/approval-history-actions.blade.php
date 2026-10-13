<div class="d-inline-flex flex-wrap gap-1">
    <a href="{{ $detailUrl }}" class="btn btn-sm btn-outline-primary" title="Lihat Detail" aria-label="Lihat Detail">
        <i class="fas fa-eye" aria-hidden="true"></i>
    </a>
    @if(!empty($documentUrl))
        <a href="{{ $documentUrl }}" class="btn btn-sm btn-outline-secondary" title="Dokumen Pendukung" aria-label="Dokumen Pendukung" target="_blank" rel="noopener noreferrer" data-no-loader="1">
            <i class="fas fa-paperclip" aria-hidden="true"></i>
        </a>
    @endif
    @if(!empty($receiptPdfUrl))
        <a href="{{ $receiptPdfUrl }}" class="btn btn-sm btn-outline-danger" title="Receipt PDF" aria-label="Receipt PDF" data-no-loader="1">
            <i class="fas fa-file-pdf" aria-hidden="true"></i>
        </a>
    @endif
</div>
