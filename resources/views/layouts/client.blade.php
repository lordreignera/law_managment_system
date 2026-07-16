<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Client Portal') - {{ $companySetting->company_name ?: config('app.name', 'KFMS') }}</title>

        @include('layouts.partials.favicon')
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendor.bundle.base.css') }}">
        <link rel="stylesheet" href="{{ asset('admin/assets/css/kfms-admin.css') }}?v={{ file_exists(public_path('admin/assets/css/kfms-admin.css')) ? filemtime(public_path('admin/assets/css/kfms-admin.css')) : time() }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="kfms-client-portal" style="--kfms-primary: {{ $companySetting->primary_color }}; --kfms-secondary: {{ $companySetting->secondary_color }};">
        @php
            $portalClient = auth()->user()?->clientPortalAccount?->client;
            $portalName = $portalClient?->display_name ?: auth()->user()?->name;
        @endphp

        <div class="kfms-client-shell">
            <aside class="kfms-client-sidebar">
                <a class="kfms-client-brand" href="{{ route('client.dashboard') }}">
                    <x-company-logo mark-class="kfms-client-logo-mark" image-class="kfms-client-logo-image" />
                </a>

                <div class="kfms-client-user">
                    <span>Client Portal</span>
                    <strong>{{ $portalName }}</strong>
                </div>

                <nav class="kfms-client-nav" aria-label="Client portal navigation">
                    <a href="{{ route('client.dashboard') }}" @class(['is-active' => request()->routeIs('client.dashboard')])>
                        <i class="mdi mdi-view-dashboard-outline"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="{{ route('client.matters.index') }}" @class(['is-active' => request()->routeIs('client.matters.*')])>
                        <i class="mdi mdi-briefcase-outline"></i>
                        <span>My Matters</span>
                    </a>
                    <a href="{{ route('client.messages.index') }}" @class(['is-active' => request()->routeIs('client.messages.*')])>
                        <i class="mdi mdi-message-text-outline"></i>
                        <span>Messages</span>
                    </a>
                </nav>

                <form class="kfms-client-logout" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <input type="hidden" name="client_portal_logout" value="1">
                    <button type="submit"><i class="mdi mdi-logout"></i> Sign Out</button>
                </form>
            </aside>

            <div class="kfms-client-workspace">
                <header class="kfms-client-header">
                    <div>
                        <span>{{ $companySetting->company_name ?: 'Kalikumutima & Co Advocates' }}</span>
                        <strong>@yield('title', 'Client Portal')</strong>
                    </div>
                    <a href="{{ route('client.messages.index') }}" @class(['kfms-client-header-action', 'is-active' => request()->routeIs('client.messages.*')])>
                        <i class="mdi mdi-message-text-outline"></i>
                        Messages
                    </a>
                </header>

                <main class="kfms-client-main">
                    @if (session('status'))
                        <div class="kfms-alert">{{ session('status') }}</div>
                    @endif

                    @yield('content')
                </main>
            </div>
        </div>
    </body>
</html>
