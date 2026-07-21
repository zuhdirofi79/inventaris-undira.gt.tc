@extends('layouts.auth')

@section('title', 'Verifikasi Email - Inventaris UNDIRA')

@section('content')
<p class="text-muted mb-3">
    Masukkan kode OTP 4 digit yang dikirim ke
    <strong>{{ $email }}</strong>.
</p>

<form action="{{ route('verification.verify') }}" method="POST" class="mb-3">
    @csrf
    <div class="mb-3">
        <label for="otp" class="form-label">Kode OTP <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-shield-alt"></i></span>
            <input type="text" name="otp" id="otp"
                   class="form-control @error('otp') is-invalid @enderror"
                   value="{{ old('otp') }}"
                   inputmode="numeric"
                   autocomplete="one-time-code"
                   maxlength="4"
                   pattern="[0-9]{4}"
                   placeholder="••••"
                   required autofocus>
        </div>
        @error('otp')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text">Kode berlaku 10 menit.</div>
    </div>

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-check me-2"></i>Verifikasi
    </button>
</form>

<form action="{{ route('verification.resend') }}" method="POST">
    @csrf
    <button type="submit" class="btn btn-outline-secondary w-100 btn-sm">
        <i class="fas fa-paper-plane me-1"></i>Kirim ulang OTP
    </button>
</form>

<p class="text-center mt-4 mb-0 text-muted small">
    <a href="{{ route('login') }}" class="text-undira-blue"><i class="fas fa-arrow-left me-1"></i>Kembali ke Login</a>
</p>
@endsection
