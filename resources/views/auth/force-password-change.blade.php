@extends('layouts.auth')

@section('title', 'Ganti Password - Inventaris UNDIRA')

@section('content')
<p class="text-muted mb-4">
    Demi keamanan akun, Anda wajib mengganti password sebelum menggunakan sistem.
</p>

<form action="{{ route('password.force.update') }}" method="POST">
    @csrf
    @method('PUT')

    <div class="mb-3">
        <label for="current_password" class="form-label">Password Saat Ini <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="current_password" id="current_password" class="form-control @error('current_password') is-invalid @enderror" required autofocus autocomplete="current-password">
        </div>
        @error('current_password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password Baru <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-key"></i></span>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
        </div>
        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-4">
        <label for="password_confirmation" class="form-label">Konfirmasi Password Baru <span class="text-danger">*</span></label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-key"></i></span>
            <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" required autocomplete="new-password">
        </div>
    </div>

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-save me-2"></i>Simpan Password Baru
    </button>
</form>
@endsection
