{{-- Global UNDIRA browser favicon (login + dashboard + standalone pages). --}}
@php
    use App\Support\AppSettings;

    $customFavicon = null;
    try {
        $customFavicon = AppSettings::faviconUrl();
    } catch (\Throwable $e) {
        $customFavicon = null;
    }

    $faviconVersion = static function (string $relativePath): string {
        $absolute = public_path($relativePath);

        return is_file($absolute) ? (string) filemtime($absolute) : '20260723-2';
    };
@endphp
@if($customFavicon)
    <link rel="icon" href="{{ $customFavicon }}" sizes="any">
    <link rel="shortcut icon" href="{{ $customFavicon }}">
    <link rel="apple-touch-icon" href="{{ $customFavicon }}">
@else
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/favicon-undira-32.png') }}?v={{ $faviconVersion('images/favicon-undira-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('images/favicon-undira.png') }}?v={{ $faviconVersion('images/favicon-undira.png') }}">
    <link rel="shortcut icon" type="image/png" href="{{ asset('images/favicon-undira.png') }}?v={{ $faviconVersion('images/favicon-undira.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/favicon-undira-180.png') }}?v={{ $faviconVersion('images/favicon-undira-180.png') }}">
    <link rel="icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion('favicon.ico') }}" sizes="any">
@endif
