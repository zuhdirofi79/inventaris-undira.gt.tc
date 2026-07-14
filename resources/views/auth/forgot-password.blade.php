@extends('layouts.auth')

@section('title', 'Lupa Password - Inventaris UNDIRA')

@section('content')
<p class="text-muted mb-4">Masukkan email Anda dan kami akan mengirimkan link untuk reset password.</p>

<form action="{{ route('password.email') }}" method="POST">
    @csrf
    <div class="mb-4">
        <label for="email" class="form-label">Email</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" required autofocus>
        </div>
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-paper-plane me-2"></i>Kirim Link Reset
    </button>

    <p class="text-center mt-4 mb-0 text-muted small">
        <a href="{{ route('login') }}" class="text-undira-blue"><i class="fas fa-arrow-left me-1"></i>Kembali ke Login</a>
    </p>
</form>
@endsection
