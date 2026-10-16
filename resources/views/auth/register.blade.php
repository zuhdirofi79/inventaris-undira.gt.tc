@extends('layouts.auth')

@section('title', 'Daftar - Inventaris UNDIRA')

@section('content')
<form action="{{ route('register') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="name" class="form-label">Nama Lengkap <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-user"></i></span>
            <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror"
                   value="{{ old('name') }}" required autofocus>
        </div>
        @error('name')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label for="identity_number" class="form-label">NIK / NIDN / NIM <small class="text-muted">(opsional)</small></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-id-card"></i></span>
            <input type="text" name="identity_number" id="identity_number"
                   class="form-control @error('identity_number') is-invalid @enderror"
                   value="{{ old('identity_number') }}" placeholder="Sesuai identitas Anda">
        </div>
        @error('identity_number')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label for="email" class="form-label">Email UNDIRA <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" placeholder="nama@undira.ac.id" required>
        </div>
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        <div class="form-text">Wajib menggunakan email resmi @undira.ac.id.</div>
    </div>

    <div class="mb-3">
        <label for="phone" class="form-label">No. Telepon <small class="text-muted">(opsional)</small></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-phone"></i></span>
            <input type="text" name="phone" id="phone" class="form-control" value="{{ old('phone') }}">
        </div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required>
        </div>
        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Konfirmasi Password <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required>
        </div>
    </div>

    <div class="alert alert-info small">
        <i class="fas fa-info-circle me-1"></i>
        Setelah daftar, kode OTP 4 digit akan dikirim ke email Anda. Role awal: <strong>viewer</strong>.
    </div>

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-user-plus me-2"></i>Daftar
    </button>

    <p class="text-center mt-4 mb-0 text-muted small auth-signup">
        Sudah punya akun?
        <a href="{{ route('login') }}" class="auth-link auth-link--blue">Masuk menggunakan akun Anda</a>
    </p>
</form>
@endsection
