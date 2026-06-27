@php
    $adminAssetVersion = static function (string $path): int {
        $fullPath = public_path($path);

        return file_exists($fullPath) ? filemtime($fullPath) : time();
    };
@endphp

<link rel="stylesheet" href="{{ asset('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}?v={{ $adminAssetVersion('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
<link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}?v={{ $adminAssetVersion('admin/assets/vendors/css/vendor.bundle.base.css') }}">
<link rel="stylesheet" href="{{ asset('admin/assets/css/style.css') }}?v={{ $adminAssetVersion('admin/assets/css/style.css') }}">
@vite(['resources/css/app.css', 'resources/js/app.js'])
<link rel="stylesheet" href="{{ asset('admin/assets/css/kfms-admin.css') }}?v={{ $adminAssetVersion('admin/assets/css/kfms-admin.css') }}">
