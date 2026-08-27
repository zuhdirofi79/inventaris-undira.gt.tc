<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login - Inventaris UNDIRA')</title>
    @include('partials.favicon')
    <link rel="preload" as="image" href="{{ asset('images/kampus-undira.jpg') }}" fetchpriority="high">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    <link rel="stylesheet" href="{{ asset('css/undira-ui.css') }}">
    <link rel="stylesheet" href="{{ asset('css/auth-glass.css') }}">
    @stack('styles')
</head>
<body class="auth-page auth-page--glass">
<div class="auth-bg" aria-hidden="true">
    <div class="auth-bg__image" id="authBgParallax"></div>
    <div class="auth-bg__overlay"></div>
    <div class="auth-bg__vignette"></div>
</div>

<div class="auth-wrapper">
    <div class="auth-card auth-card--glass">
        <div class="auth-card-header">
            <div class="auth-logo-plate">
                <img src="{{ asset('images/logo-undira.png') }}"
                     alt="Logo Universitas Dian Nusantara"
                     decoding="async">
            </div>
            @hasSection('auth-branding')
                @yield('auth-branding')
            @else
                <h1 class="auth-title">Inventaris UNDIRA</h1>
                <p class="auth-subtitle mb-0">
                    Sistem Manajemen Inventaris<br>
                    Universitas Dian Nusantara
                </p>
            @endif
        </div>
        <div class="auth-card-body">
            @include('components.auth-alert')
            @yield('content')
        </div>
        <div class="auth-card-footer">
            <strong>&copy; {{ date('Y') }} Inventaris UNDIRA</strong>
            Universitas Dian Nusantara
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    var layer = document.getElementById('authBgParallax');
    if (!layer || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia('(pointer: coarse)').matches) return;

    var max = 10;
    var raf = 0;
    var targetX = 0;
    var targetY = 0;

    function onMove(e) {
        var cx = window.innerWidth / 2;
        var cy = window.innerHeight / 2;
        targetX = ((e.clientX - cx) / cx) * max;
        targetY = ((e.clientY - cy) / cy) * max;
        if (!raf) raf = requestAnimationFrame(paint);
    }

    function paint() {
        raf = 0;
        layer.style.transform = 'translate3d(' + (targetX * -1).toFixed(2) + 'px,' + (targetY * -1).toFixed(2) + 'px,0)';
    }

    window.addEventListener('mousemove', onMove, { passive: true });
})();
</script>
@stack('scripts')
</body>
</html>
