<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="kfms-session-status" content="{{ route('session.status', absolute: false) }}">
        <meta name="kfms-session-heartbeat" content="{{ route('session.heartbeat', absolute: false) }}">
        @include('layouts.partials.session-history')

        <title>{{ $companySetting->company_name ?: config('app.name', 'KFMS') }}</title>

        @include('layouts.partials.favicon')
        @include('layouts.partials.pwa-meta')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <!-- Styles -->
        @livewireStyles
    </head>
    <body class="font-sans antialiased" data-kfms-session-user="{{ auth()->id() }}" data-kfms-idle-timeout="{{ (int) config('session.idle_timeout', config('session.lifetime', 120)) * 60 * 1000 }}">
        <x-banner />

        <div class="min-h-screen bg-gray-100">
            @livewire('navigation-menu')

            <!-- Page Heading -->
            @if (isset($header))
                <header class="bg-white shadow">
                    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endif

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>
        </div>

        @stack('modals')
        @include('layouts.partials.pwa-install-prompt')

        @livewireScripts
        <script src="{{ asset('admin/assets/js/kfms-session.js') }}?v={{ file_exists(public_path('admin/assets/js/kfms-session.js')) ? filemtime(public_path('admin/assets/js/kfms-session.js')) : time() }}"></script>
        <script src="{{ asset('admin/assets/js/kfms-pwa.js') }}?v={{ file_exists(public_path('admin/assets/js/kfms-pwa.js')) ? filemtime(public_path('admin/assets/js/kfms-pwa.js')) : time() }}"></script>
    </body>
</html>
