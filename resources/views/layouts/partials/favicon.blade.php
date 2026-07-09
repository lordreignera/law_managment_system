@php
    $faviconVersion = static function (string $path): int {
        $fullPath = public_path($path);

        return file_exists($fullPath) ? filemtime($fullPath) : time();
    };
@endphp

<link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ $faviconVersion('favicon.ico') }}">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon.png') }}?v={{ $faviconVersion('favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ $faviconVersion('apple-touch-icon.png') }}">
