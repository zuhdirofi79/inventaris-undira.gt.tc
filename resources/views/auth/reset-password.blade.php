@extends('layouts.auth')

@section('title', 'Reset Password - Inventaris UNDIRA')

@section('content')
<p class="text-muted mb-4">
    Buat password baru untuk akun
    <strong>{{ $email }}</strong>.
</p>

<form action="{{ route('password.update') }}" method="POST">
    @csrf

    <div class="mb-3">
        <label for="password" class="form-label">Password Baru <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autofocus>
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

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-key me-2"></i>Simpan Password Baru
    </button>

    <p class="text-center mt-4 mb-0 text-muted small">
        <a href="{{ route('login') }}" class="text-undira-blue"><i class="fas fa-arrow-left me-1"></i>Kembali ke Login</a>
    </p>
</form>
@endsection
