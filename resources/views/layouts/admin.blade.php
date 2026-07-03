<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', 'Dashboard') - {{ config('app.name', 'KFMS') }}</title>

        @include('layouts.partials.css')
        @livewireStyles
    </head>
    <body class="kfms-template" style="--kfms-primary: {{ $companySetting->primary_color }}; --kfms-secondary: {{ $companySetting->secondary_color }};">
        <x-banner />

        <div class="container-scroller">
            @include('layouts.partials.sidebar')

            <div class="container-fluid page-body-wrapper">
                @include('layouts.partials.navbar')

                <div class="main-panel">
                    @include('layouts.partials.body')

                    <footer class="footer">
                        <div class="d-sm-flex justify-content-between">
                            <span>{{ $companySetting->company_name }} {{ $companySetting->tagline }}</span>
                            <span>{{ $companySetting->short_name }}</span>
                        </div>
                    </footer>
                </div>
            </div>
        </div>

        @stack('modals')
        @livewireScripts
        <script src="{{ asset('admin/assets/vendors/js/vendor.bundle.base.js') }}"></script>
        <script src="{{ asset('admin/assets/js/off-canvas.js') }}"></script>
        <script src="{{ asset('admin/assets/js/kfms-sidebar.js') }}?v={{ file_exists(public_path('admin/assets/js/kfms-sidebar.js')) ? filemtime(public_path('admin/assets/js/kfms-sidebar.js')) : time() }}"></script>
        <script src="{{ asset('admin/assets/js/kfms-forms.js') }}?v={{ file_exists(public_path('admin/assets/js/kfms-forms.js')) ? filemtime(public_path('admin/assets/js/kfms-forms.js')) : time() }}"></script>
        @stack('scripts')
    </body>
</html>
