{{-- Global UNDIRA browser favicon (login + dashboard + standalone pages). --}}
@php
    $faviconVersion = static function (string $relativePath): string {
        $absolute = public_path($relativePath);

        return is_file($absolute) ? (string) filemtime($absolute) : '20260723-2';
    };
@endphp
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-undira-32.png') }}?v={{ $faviconVersion('images/favicon-undira-32.png') }}">
<link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-undira.png') }}?v={{ $faviconVersion('images/favicon-undira.png') }}">
<link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-undira.png') }}?v={{ $faviconVersion('images/favicon-undira.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon-undira-180.png') }}?v={{ $faviconVersion('images/favicon-undira-180.png') }}">
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion('favicon.ico') }}" sizes="any">
