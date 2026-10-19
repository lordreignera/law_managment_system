<main class="content-wrapper kfms-content">
    <div @class(['kfms-content-container', 'kfms-form-page' => request()->routeIs('*.create', '*.edit', 'access.approvals.show')])>
        <x-workspace-navigation />
        @yield('content')
    </div>
</main>
