@extends('layouts.auth')

@section('title', 'Login - Inventaris UNDIRA')

@section('content')
<form action="{{ route('login') }}" method="POST">
    @csrf
    <div class="mb-3">
        <label for="email" class="form-label">Email</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
            <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}" placeholder="nama@undira.ac.id" required autofocus>
        </div>
        @error('email')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password</label>
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror"
                   placeholder="Masukkan password" required>
        </div>
        @error('password')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div class="form-check">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Ingat saya</label>
        </div>
        <a href="{{ route('password.request') }}" class="text-decoration-none text-undira-green small">Lupa password?</a>
    </div>

    <button type="submit" class="btn btn-undira w-100 py-2">
        <i class="fas fa-sign-in-alt me-2"></i>Masuk
    </button>

    <p class="text-center mt-4 mb-0 text-muted small">
        Belum punya akun? <a href="{{ route('register') }}" class="text-undira-blue">Daftar sebagai Viewer</a>
    </p>
</form>
@endsection
