@extends('layouts.auth')

@section('title', 'Login - Inventaris UNDIRA')

@section('auth-branding')
    <h1 class="auth-title auth-title--loadout">LOADOUT</h1>
    <p class="auth-subtitle auth-tagline mb-0">
        <em>(List of Operational Assets, Data, Organization,<br>Utilization &amp; Tracking)</em>
    </p>
@endsection

@section('content')
<form action="{{ route('login') }}" method="POST" class="auth-form" id="loginForm" novalidate>
    @csrf

    <div class="mb-3">
        <label for="email" class="form-label">Email <span class="text-danger" aria-hidden="true">*</span></label>
        <div class="auth-field @error('email') is-invalid @enderror" data-field="email">
            <span class="auth-field__icon" aria-hidden="true"><i class="fas fa-envelope"></i></span>
            <input type="email"
                   name="email"
                   id="email"
                   class="form-control auth-field__input"
                   value="{{ old('email') }}"
                   placeholder="nama@undira.ac.id"
                   autocomplete="username"
                   inputmode="email"
                   required
                   autofocus
                   aria-required="true"
                   aria-describedby="emailHelp">
        </div>
        <div class="auth-field-hint" id="emailHelp">Gunakan email resmi UNDIRA.</div>
    </div>

    <div class="mb-3">
        <label for="password" class="form-label">Password <span class="text-danger" aria-hidden="true">*</span></label>
        <div class="auth-field @error('password') is-invalid @enderror" data-field="password">
            <span class="auth-field__icon" aria-hidden="true"><i class="fas fa-lock"></i></span>
            <input type="password"
                   name="password"
                   id="password"
                   class="form-control auth-field__input"
                   placeholder="Masukkan password"
                   autocomplete="current-password"
                   required
                   aria-required="true">
            <button type="button"
                    class="auth-field__toggle"
                    id="togglePassword"
                    title="Tampilkan password"
                    aria-label="Tampilkan password"
                    aria-controls="password"
                    aria-pressed="false">
                <i class="fas fa-eye" aria-hidden="true"></i>
            </button>
        </div>
    </div>

    <div class="auth-row">
        <label class="auth-remember" for="remember">
            <input type="checkbox"
                   name="remember"
                   id="remember"
                   value="1"
                   {{ old('remember') ? 'checked' : '' }}>
            <span class="auth-remember__box" aria-hidden="true"><i class="fas fa-check"></i></span>
            <span>Ingat saya</span>
        </label>
        <a href="{{ route('password.request') }}" class="auth-link">Lupa password?</a>
    </div>

    <button type="submit" id="btnLogin" class="btn btn-auth-login">
        <i class="fas fa-sign-in-alt" aria-hidden="true"></i>
        <span class="btn-auth-login__label">Masuk ke Sistem</span>
    </button>
    <p class="auth-login-hint" id="loginHint" aria-live="polite"></p>

    <p class="auth-signup">
        Belum punya akun?
        <a href="{{ route('register') }}" class="auth-link auth-link--blue">Daftar sebagai Viewer</a>
    </p>
</form>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/undira-loading.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/undira-loading.js') }}"></script>
<script>
(function () {
    var form = document.getElementById('loginForm');
    var email = document.getElementById('email');
    var password = document.getElementById('password');
    var toggle = document.getElementById('togglePassword');
    var btn = document.getElementById('btnLogin');
    var hint = document.getElementById('loginHint');

    function wrapOf(el) {
        return el ? el.closest('.auth-field') : null;
    }

    function setValidity(input, ok) {
        var wrap = wrapOf(input);
        if (!wrap || !input.value) {
            if (wrap) {
                wrap.classList.remove('is-valid', 'is-invalid');
            }
            return;
        }
        wrap.classList.toggle('is-valid', ok);
        wrap.classList.toggle('is-invalid', !ok);
    }

    function validateEmail() {
        if (!email) return true;
        var ok = email.checkValidity();
        setValidity(email, ok);
        return ok;
    }

    function validatePassword() {
        if (!password) return true;
        var ok = password.value.length > 0;
        setValidity(password, ok);
        return ok;
    }

    email?.addEventListener('input', validateEmail);
    email?.addEventListener('blur', validateEmail);
    password?.addEventListener('input', validatePassword);
    password?.addEventListener('blur', validatePassword);

    toggle?.addEventListener('click', function () {
        var show = password.type === 'password';
        password.type = show ? 'text' : 'password';
        var icon = this.querySelector('i');
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
        this.setAttribute('aria-pressed', show ? 'true' : 'false');
        this.setAttribute('title', show ? 'Sembunyikan password' : 'Tampilkan password');
        this.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
    });

    form?.addEventListener('submit', function (e) {
        var emailOk = validateEmail();
        var passOk = validatePassword();
        if (!emailOk || !passOk) {
            e.preventDefault();
            if (!emailOk) email.focus();
            else password.focus();
            return;
        }

        if (btn) {
            btn.disabled = true;
            var label = btn.querySelector('.btn-auth-login__label');
            if (label) label.textContent = 'Memverifikasi...';
            else btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memverifikasi...';

            if (!btn.querySelector('.spinner-border')) {
                var spin = document.createElement('span');
                spin.className = 'spinner-border spinner-border-sm';
                spin.setAttribute('role', 'status');
                spin.setAttribute('aria-hidden', 'true');
                var icon = btn.querySelector('.fa-sign-in-alt');
                if (icon) icon.replaceWith(spin);
                else btn.prepend(spin);
            }

            if (typeof setButtonLoading === 'function') {
                /* keep disabled state consistent with shared helper */
            }
        }
        if (hint) hint.textContent = 'Mohon tunggu sebentar';
    });
})();
</script>
@endpush
