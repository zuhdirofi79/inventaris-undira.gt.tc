<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login - Inventaris UNDIRA')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/logo-undira.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    @stack('styles')
</head>
<body class="auth-page">
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card-header">
            <div class="auth-logo-plate">
                <img src="{{ asset('images/logo-undira.png') }}" alt="Logo Universitas Dian Nusantara">
            </div>
            <h1 class="auth-title">Inventaris UNDIRA</h1>
            <p class="auth-subtitle mb-0">Sistem Manajemen Inventaris<br>Universitas Dian Nusantara</p>
        </div>
        <div class="auth-card-body">
            @include('components.alert')
            @yield('content')
        </div>
        <div class="auth-card-footer">
            &copy; {{ date('Y') }} Universitas Dian Nusantara
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
