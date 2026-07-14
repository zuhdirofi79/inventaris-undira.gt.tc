<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Login - Inventaris UNDIRA')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/undira.css') }}">
    @stack('styles')
</head>
<body>
<div class="auth-wrapper">
    <div class="auth-card">
        <div class="auth-card-header">
            <img src="{{ asset('images/logo-undira.png') }}" alt="UNDIRA Logo">
            <h4 class="mb-0">Inventaris UNDIRA</h4>
            <small>Sistem Manajemen Inventaris Laboratorium</small>
        </div>
        <div class="auth-card-body">
            @include('components.alert')
            @yield('content')
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@stack('scripts')
</body>
</html>
