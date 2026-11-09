@php
    $faviconVersion = static function (string $path): int {
        $fullPath = public_path($path);

        return file_exists($fullPath) ? filemtime($fullPath) : time();
    };
@endphp

<link rel="icon" type="image/x-icon" href="{{ asset('pwa/favicon.ico') }}?v={{ $faviconVersion('pwa/favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('pwa/favicon-32.png') }}?v={{ $faviconVersion('pwa/favicon-32.png') }}">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('pwa/apple-touch-icon.png') }}?v={{ $faviconVersion('pwa/apple-touch-icon.png') }}">
