<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'KFMS') }}</title>

        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <link rel="stylesheet" href="{{ asset('admin/assets/css/kfms-auth.css') }}">
        @livewireStyles
    </head>
    <body class="kfms-auth-body" style="--kfms-primary: {{ $companySetting->primary_color }}; --kfms-secondary: {{ $companySetting->secondary_color }};">
        <div>
            {{ $slot }}
        </div>

        @livewireScripts
        <script src="{{ asset('admin/assets/js/kfms-auth.js') }}"></script>
    </body>
</html>
