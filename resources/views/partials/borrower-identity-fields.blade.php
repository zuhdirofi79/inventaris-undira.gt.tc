@php
    /** @var \App\Models\User|null $authUser */
    $authUser = auth()->user();
    $canProxyBorrower = $authUser?->hasAnyRole(['super-admin', 'admin']) ?? false;
    $lockedName = trim((string) ($authUser?->name ?? ''));
    $lockedEmail = strtolower(trim((string) ($authUser?->email ?? '')));
    $nameValue = $canProxyBorrower ? old('borrower_name') : $lockedName;
    $emailValue = $canProxyBorrower ? old('borrower_email') : $lockedEmail;
    $nameCol = $nameCol ?? 'col-md-6 mb-3';
    $emailCol = $emailCol ?? 'col-md-6 mb-3';
    $feedbackClass = $feedbackClass ?? 'invalid-feedback';
@endphp
<div class="{{ $nameCol }}">
    <label class="form-label" for="borrower_name">Nama Peminjam <span class="text-danger">*</span></label>
    <input
        id="borrower_name"
        type="text"
        name="borrower_name"
        class="form-control @error('borrower_name') is-invalid @enderror"
        value="{{ $nameValue }}"
        required
        autocomplete="name"
        @if(! $canProxyBorrower) readonly @endif
    >
    @unless($canProxyBorrower)
        <div class="form-text">Nama diambil otomatis dari akun yang sedang login.</div>
    @endunless
    @error('borrower_name')
        <div class="{{ $feedbackClass }}">{{ $message }}</div>
    @enderror
</div>
<div class="{{ $emailCol }}">
    <label class="form-label" for="borrower_email">Email <span class="text-danger">*</span></label>
    <input
        id="borrower_email"
        type="email"
        name="borrower_email"
        class="form-control @error('borrower_email') is-invalid @enderror"
        value="{{ $emailValue }}"
        required
        autocomplete="email"
        @if(! $canProxyBorrower) readonly @endif
    >
    @unless($canProxyBorrower)
        <div class="form-text">Email diambil otomatis dari akun yang sedang login.</div>
    @else
        <div class="form-text">Admin/Super Admin dapat mengisi email peminjam atas nama orang lain.</div>
    @endunless
    @error('borrower_email')
        <div class="{{ $feedbackClass }}">{{ $message }}</div>
    @enderror
</div>
