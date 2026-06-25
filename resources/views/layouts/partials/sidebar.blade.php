@php
    $items = [
        ['label' => 'Dashboard', 'icon' => 'mdi-view-dashboard-outline', 'route' => 'dashboard'],
        ['label' => 'Clients', 'icon' => 'mdi-account-tie-outline', 'route' => 'clients.index'],
        ['label' => 'Matters', 'icon' => 'mdi-briefcase-outline', 'route' => 'matters.index'],
        ['label' => 'Recoveries', 'icon' => 'mdi-bank-transfer-in', 'route' => 'recoveries.index'],
        ['label' => 'Land Titles', 'icon' => 'mdi-file-document-outline', 'route' => 'land-titles.index'],
        ['label' => 'Finance', 'icon' => 'mdi-cash-multiple', 'route' => 'finance.index'],
        ['label' => 'Staff', 'icon' => 'mdi-account-group-outline', 'route' => 'staff.index'],
        ['label' => 'System Settings', 'icon' => 'mdi-tune-variant', 'route' => 'settings.system.overview', 'active' => 'settings.system.*'],
        ['label' => 'Company Settings', 'icon' => 'mdi-cog-outline', 'route' => 'settings.company.edit', 'active' => 'settings.company.*'],
    ];
@endphp

<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
        <a class="sidebar-brand brand-logo kfms-brand" href="{{ route('dashboard') }}">
            <x-company-logo mark-class="kfms-brand-mark" image-class="kfms-brand-image" />
            <strong>{{ $companySetting->short_name }}</strong>
        </a>
        <a class="sidebar-brand brand-logo-mini kfms-brand-mini" href="{{ route('dashboard') }}">
            <x-company-logo mark-class="kfms-brand-mini-mark" image-class="kfms-brand-mini-image" />
        </a>
    </div>

    <ul class="nav">
        <li class="nav-item nav-category">
            <span class="nav-link">Navigation</span>
        </li>
        @foreach ($items as $item)
            <li class="nav-item menu-items {{ request()->routeIs($item['route']) || request()->routeIs($item['active'] ?? $item['route']) ? 'active' : '' }}">
                <a class="nav-link" href="{{ route($item['route']) }}">
                    <span class="menu-icon">
                        <i class="mdi {{ $item['icon'] }}"></i>
                    </span>
                    <span class="menu-title">{{ $item['label'] }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
