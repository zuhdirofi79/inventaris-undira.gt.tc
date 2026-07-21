@extends('layouts.auth')

@section('title', 'Login - Inventaris UNDIRA')

@section('content')
<form action="{{ route('login') }}" method="POST" class="auth-form" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
        <div class="input-group input-group-undira">
            <span class="input-group-text" aria-hidden="true"><i class="fas fa-envelope"></i></span>
            <input type="email" name="email" id="email"
                   class="form-control @error('email') is-invalid @enderror"
                   value="{{ old('email') }}"
                   placeholder="nama@undira.ac.id"
                   autocomplete="username"
                   required autofocus>
        </div>
        @error('email')
            <div class="invalid-feedback d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</div>
        @enderror
        <div class="form-text">Gunakan email resmi UNDIRA.</div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
        <div class="input-group input-group-undira">
            <span class="input-group-text" aria-hidden="true"><i class="fas fa-lock"></i></span>
            <input type="password" name="password" id="password"
                   class="form-control @error('password') is-invalid @enderror"
                   placeholder="Masukkan password"
                   autocomplete="current-password"
                   required>
            <button type="button" class="btn btn-outline-secondary" id="togglePassword" title="Tampilkan password" aria-label="Tampilkan password">
                <i class="fas fa-eye"></i>
            </button>
        </div>
        @error('password')
            <div class="invalid-feedback d-block"><i class="fas fa-exclamation-circle me-1"></i>{{ $message }}</div>
        @enderror
    </div>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div class="form-check">
            <input type="checkbox" name="remember" id="remember" class="form-check-input" {{ old('remember') ? 'checked' : '' }}>
            <label class="form-check-label" for="remember">Ingat saya</label>
        </div>
        <a href="{{ route('password.request') }}" class="text-decoration-none text-undira-green small fw-semibold">Lupa password?</a>
    </div>

    <button type="submit" class="btn btn-undira btn-lg w-100 py-2 fw-semibold">
        <i class="fas fa-sign-in-alt me-2"></i>Masuk ke Sistem
    </button>

    <p class="text-center mt-4 mb-0 text-muted small">
        Belum punya akun?
        <a href="{{ route('register') }}" class="text-undira-blue fw-semibold text-decoration-none">Daftar sebagai Viewer</a>
    </p>
</form>
@endsection

@push('scripts')
<script>
document.getElementById('togglePassword')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    const icon = this.querySelector('i');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    icon.classList.toggle('fa-eye', !show);
    icon.classList.toggle('fa-eye-slash', show);
    this.setAttribute('title', show ? 'Sembunyikan password' : 'Tampilkan password');
    this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
});
</script>
@endpush
