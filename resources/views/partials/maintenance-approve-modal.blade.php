{{--
  Shared Approve Maintenance confirmation modal.
  Place via @push('modals') so it sits outside .content-wrapper.
  Expected IDs (defaults):
    modal: #approveMaintenanceModal
    form:  #approveMaintenanceForm
    submit:#approveMaintenanceSubmit
--}}
@php
    $modalId = $modalId ?? 'approveMaintenanceModal';
    $formId = $formId ?? 'approveMaintenanceForm';
    $submitId = $submitId ?? 'approveMaintenanceSubmit';
    $action = $action ?? '#';
    $woNumber = $woNumber ?? '—';
    $itemName = $itemName ?? '—';
    $itemNumber = $itemNumber ?? '—';
    $typeLabel = $typeLabel ?? '—';
    $priorityLabel = $priorityLabel ?? '—';
@endphp
<div class="modal fade undira-approve-modal" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form
            method="POST"
            action="{{ $action }}"
            id="{{ $formId }}"
            class="modal-content"
            data-no-loading
        >
            @csrf
            <div class="modal-body undira-approve-modal__body">
                <div class="undira-approve-modal__icon" aria-hidden="true">
                    <i class="bi bi-check2-circle"></i>
                </div>
                <h5 class="undira-approve-modal__title" id="{{ $modalId }}Label">Konfirmasi Approval</h5>
                <p class="undira-approve-modal__desc">
                    Apakah Anda yakin ingin menyetujui perbaikan maintenance ini?
                </p>
                <div class="undira-approve-modal__details" aria-label="Detail Work Order">
                    <div class="undira-approve-modal__details-label">Detail Work Order</div>
                    <div class="undira-approve-modal__wo" data-approve-wo>{{ $woNumber }}</div>
                    <dl class="undira-approve-modal__meta mb-0">
                        <div>
                            <dt>Inventaris</dt>
                            <dd data-approve-item-name>{{ $itemName }}</dd>
                        </div>
                        <div>
                            <dt>No. Inventaris</dt>
                            <dd data-approve-item-number>{{ $itemNumber }}</dd>
                        </div>
                        <div>
                            <dt>Jenis</dt>
                            <dd data-approve-type>{{ $typeLabel }}</dd>
                        </div>
                        <div>
                            <dt>Prioritas</dt>
                            <dd data-approve-priority>{{ $priorityLabel }}</dd>
                        </div>
                    </dl>
                </div>
                <p class="undira-approve-modal__hint small mb-0">
                    Setelah disetujui, status inventaris akan menjadi <strong>Aktif</strong>.
                </p>
                <div class="alert alert-danger py-2 small d-none mt-3 mb-0" data-approve-error role="alert"></div>
            </div>
            <div class="modal-footer undira-approve-modal__footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-success" id="{{ $submitId }}">
                    <i class="bi bi-check-lg me-1" aria-hidden="true"></i>Approve Perbaikan
                </button>
            </div>
        </form>
    </div>
</div>
