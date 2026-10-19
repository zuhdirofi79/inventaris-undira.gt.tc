@php
    use App\Support\ApprovalCancellationWindow;

    $canCancel = !empty($canCancel);
    $cancelUrl = $cancelUrl ?? null;
    $kindLabel = $kindLabel ?? 'Peminjaman';
    $number = $number ?? '—';
    $borrower = $borrower ?? '—';
    $approvedAt = $approvedAt ?? null;
    $modalId = $modalId ?? ('cancelApprovalModal'.uniqid());
    $deadlineLabel = ApprovalCancellationWindow::remainingLabel($approvedAt);
    $approvedLabel = ApprovalCancellationWindow::format($approvedAt);
@endphp

@if($canCancel && $cancelUrl)
    <button type="button"
            class="btn btn-sm btn-outline-warning"
            title="Batalkan Persetujuan"
            aria-label="Batalkan Persetujuan"
            data-bs-toggle="modal"
            data-bs-target="#{{ $modalId }}">
        <i class="fas fa-undo" aria-hidden="true"></i>
    </button>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ $cancelUrl }}">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title" id="{{ $modalId }}Label">Batalkan Persetujuan</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-3">Persetujuan peminjaman ini akan dibatalkan. Masukkan alasan pembatalan sebelum melanjutkan.</p>
                        <dl class="row mb-3 small">
                            <dt class="col-sm-4">Nomor</dt>
                            <dd class="col-sm-8">{{ $number }}</dd>
                            <dt class="col-sm-4">Jenis</dt>
                            <dd class="col-sm-8">{{ $kindLabel }}</dd>
                            <dt class="col-sm-4">Peminjam</dt>
                            <dd class="col-sm-8">{{ $borrower }}</dd>
                            <dt class="col-sm-4">Waktu Persetujuan</dt>
                            <dd class="col-sm-8">{{ $approvedLabel }}</dd>
                            <dt class="col-sm-4">Batas Pembatalan</dt>
                            <dd class="col-sm-8">{{ $deadlineLabel }}</dd>
                        </dl>
                        <div class="mb-0">
                            <label class="form-label" for="{{ $modalId }}_reason">Alasan pembatalan <span class="text-danger">*</span></label>
                            <textarea id="{{ $modalId }}_reason"
                                      name="cancellation_reason"
                                      class="form-control"
                                      rows="3"
                                      required
                                      minlength="5"
                                      maxlength="1000"
                                      placeholder="Contoh: jadwal berubah / penugasan salah"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-warning">Konfirmasi Pembatalan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@elseif(!empty($showExpiredHint) && !empty($expiredHint))
    <span class="small text-muted d-inline-block" title="{{ $expiredHint }}">
        <i class="fas fa-clock" aria-hidden="true"></i>
    </span>
@endif
