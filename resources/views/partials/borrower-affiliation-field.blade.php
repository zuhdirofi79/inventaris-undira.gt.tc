@php
    $fieldName = $fieldName ?? 'borrower_affiliation';
    $colClass = $colClass ?? 'col-md-6 mb-3';
    $feedbackClass = $feedbackClass ?? 'invalid-feedback';
    $fieldValue = old($fieldName, $value ?? '');
@endphp
<div class="{{ $colClass }}">
    <label class="form-label" for="{{ $fieldName }}">Unit <span class="text-danger">*</span></label>
    <input
        id="{{ $fieldName }}"
        type="text"
        name="{{ $fieldName }}"
        class="form-control @error($fieldName) is-invalid @enderror"
        value="{{ $fieldValue }}"
        placeholder="Contoh: PPPA, Fakultas Teknik dan Informatika, Teknik Perminyakan, BEM, atau UKM Futsal"
        maxlength="255"
        required
        autocomplete="organization"
    >
    <div class="form-text">Wajib diisi. Masukkan nama biro, fakultas, program studi, Ormawa, atau UKM peminjam.</div>
    @error($fieldName)
        <div class="{{ $feedbackClass }}">{{ $message }}</div>
    @enderror
</div>
