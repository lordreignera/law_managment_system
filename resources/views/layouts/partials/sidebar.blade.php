@php
    $items = [
        ['label' => 'Dashboard', 'icon' => 'mdi-view-dashboard', 'route' => 'dashboard', 'permission' => 'dashboard'],
        ['label' => 'Messages', 'icon' => 'mdi-chat-outline', 'route' => 'messages.index', 'active' => 'messages.*', 'permission' => 'messages.index'],
        [
            'label' => 'Client Management',
            'icon' => 'mdi-account-multiple',
            'route' => 'intakes.index',
            'active' => ['intakes.*', 'clients.*'],
            'children' => [
                ['label' => 'Client Intakes', 'route' => 'intakes.index', 'active' => 'intakes.*', 'permission' => 'intakes.index'],
                ['label' => 'Approved Clients', 'route' => 'clients.index', 'active' => 'clients.*', 'permission' => 'clients.index'],
            ],
            'permission_any' => ['intakes.index', 'clients.index'],
        ],
        [
            'label' => 'Matter Management',
            'icon' => 'mdi-briefcase',
            'route' => 'matters.index',
            'active' => 'matters.*',
            'children' => [
                ['label' => 'Matter Register', 'route' => 'matters.index', 'active' => 'matters.*'],
                ['label' => 'File Pending', 'route' => 'matters.index', 'query' => ['status' => 'file_pending']],
                ['label' => 'Open Matters', 'route' => 'matters.index', 'query' => ['status' => 'open']],
                ['label' => 'Planning', 'route' => 'matters.index', 'query' => ['status' => 'planning']],
                ['label' => 'Active Work', 'route' => 'matters.index', 'query' => ['status' => 'active']],
                ['label' => 'Billing Pending', 'route' => 'matters.index', 'query' => ['status' => 'billing_pending']],
                ['label' => 'Under Review', 'route' => 'matters.index', 'query' => ['status' => 'under_review']],
                ['label' => 'Closed / Archived', 'route' => 'matters.index', 'query' => ['status' => 'closed']],
            ],
            'permission' => 'matters.index',
        ],
        [
            'label' => 'Litigation',
            'icon' => 'mdi-gavel',
            'route' => 'litigation.dashboard',
            'active' => ['litigation.*'],
            'children' => [
                ['label' => 'Litigation Dashboard', 'route' => 'litigation.dashboard', 'active' => 'litigation.dashboard', 'permission' => 'litigation.dashboard'],
                ['label' => 'Cause List', 'route' => 'litigation.index', 'active' => 'litigation.index', 'permission' => 'litigation.index'],
                ['label' => 'Add Cause List / Court File', 'route' => 'litigation.create', 'active' => 'litigation.create', 'permission' => 'litigation.create'],
            ],
            'permission_any' => ['litigation.dashboard', 'litigation.index', 'litigation.create'],
        ],
        ['label' => 'Firm Calendar', 'icon' => 'mdi-calendar-month', 'route' => 'calendar.index', 'active' => 'calendar.*', 'permission' => 'calendar.index'],
        [
            'label' => 'Recoveries',
            'icon' => 'mdi-bank',
            'route' => 'recoveries.index',
            'active' => ['recoveries.*'],
            'children' => [
                ['label' => 'Recovery Dashboard', 'route' => 'recoveries.dashboard', 'active' => 'recoveries.dashboard', 'permission' => 'recoveries.dashboard'],
                ['label' => 'Accounts Register', 'route' => 'recoveries.index', 'active' => 'recoveries.index', 'permission' => 'recoveries.index'],
                ['label' => 'Import Portfolios', 'route' => 'recoveries.import', 'active' => ['recoveries.import', 'recoveries.batches.*'], 'permission' => 'recoveries.import'],
                ['label' => 'My Recoveries', 'route' => 'recoveries.mine', 'active' => 'recoveries.mine', 'permission' => 'recoveries.mine'],
                ['label' => 'Reports', 'route' => 'recoveries.reports', 'active' => 'recoveries.reports', 'permission' => 'recoveries.reports'],
            ],
            'permission_any' => ['recoveries.dashboard', 'recoveries.index', 'recoveries.import', 'recoveries.mine', 'recoveries.reports'],
        ],
        ['label' => 'Securities', 'icon' => 'mdi-file-document', 'route' => 'land-titles.index', 'permission' => 'land-titles.index'],
        [
            'label' => 'Finance',
            'icon' => 'mdi-cash-multiple',
            'route' => 'finance.index',
            'active' => ['finance.*', 'expenses.*', 'petty-cash.*', 'ledger.*'],
            'children' => [
                ['label' => 'Dashboard', 'route' => 'finance.dashboard', 'active' => 'finance.dashboard', 'permission' => 'finance.dashboard'],
                ['label' => 'Overview', 'route' => 'finance.index', 'active' => 'finance.index', 'permission' => 'finance.index'],
                ['label' => 'Expenses', 'route' => 'expenses.index', 'active' => 'expenses.*', 'permission' => 'expenses.index'],
                ['label' => 'Petty Cash', 'route' => 'petty-cash.index', 'active' => 'petty-cash.*', 'permission' => 'petty-cash.index'],
                ['label' => 'Ledger', 'route' => 'ledger.index', 'active' => 'ledger.*', 'permission' => 'ledger.index'],
            ],
            'permission_any' => ['finance.dashboard', 'finance.index', 'expenses.index', 'petty-cash.index', 'ledger.index'],
        ],
        [
            'label' => 'Human Resources',
            'icon' => 'mdi-account-tie',
            'route' => 'hr.dashboard',
            'active' => ['hr.*', 'staff.*', 'leave.*'],
            'children' => [
                ['label' => 'HR Dashboard', 'route' => 'hr.dashboard', 'active' => 'hr.dashboard', 'permission' => 'hr.dashboard'],
                ['label' => 'Staff Register', 'route' => 'staff.index', 'active' => 'staff.*', 'permission' => 'staff.index'],
                ['label' => 'Leave Management', 'route' => 'leave.index', 'active' => 'leave.*', 'permission' => 'leave.index'],
            ],
            'permission_any' => ['hr.dashboard', 'staff.index', 'leave.index'],
        ],
        ['label' => 'Requisitions', 'icon' => 'mdi-clipboard-text', 'route' => 'requisitions.index', 'active' => 'requisitions.*', 'permission' => 'requisitions.index'],
        [
            'label' => 'Access Control',
            'icon' => 'mdi-shield-account',
            'route' => 'access.users.index',
            'active' => 'access.*',
            'children' => [
                ['label' => 'All Users', 'route' => 'access.users.index', 'permission' => 'access.users.index'],
                ['label' => 'Approval Requests', 'route' => 'access.approvals.index', 'permission' => 'access.approvals.index'],
                ['label' => 'Roles', 'route' => 'access.roles.index', 'permission' => 'access.roles.index'],
                ['label' => 'Permissions', 'route' => 'access.permissions.index', 'permission' => 'access.permissions.index'],
            ],
            'permission_any' => ['access.users.index', 'access.approvals.index', 'access.roles.index', 'access.permissions.index'],
        ],
        ['label' => 'System Settings', 'icon' => 'mdi-tune', 'route' => 'settings.system.overview', 'active' => 'settings.system.*', 'permission' => 'settings.system.overview'],
        ['label' => 'Branches', 'icon' => 'mdi-office-building-marker', 'route' => 'branches.index', 'active' => 'branches.*', 'permission' => 'branches.index'],
        ['label' => 'Public Holidays', 'icon' => 'mdi-calendar-star', 'route' => 'holidays.index', 'active' => 'holidays.*', 'permission' => 'holidays.index'],
        ['label' => 'Company Settings', 'icon' => 'mdi-settings', 'route' => 'settings.company.edit', 'active' => 'settings.company.*', 'permission' => 'settings.company.edit'],
    ];
@endphp

<nav class="sidebar sidebar-offcanvas" id="sidebar">
    <div class="sidebar-brand-wrapper d-none d-lg-flex align-items-center justify-content-center fixed-top">
        <a class="sidebar-brand brand-logo kfms-brand" href="{{ route('dashboard') }}">
            <x-company-logo mark-class="kfms-brand-mark" image-class="kfms-brand-image" />
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
            @continue(array_key_exists('visible', $item) && ! $item['visible'])
            @continue(isset($item['permission']) && ! auth()->user()?->can($item['permission']))
            @continue(isset($item['permission_any']) && ! collect($item['permission_any'])->contains(fn ($permission) => auth()->user()?->can($permission)))
            @php
                $activePatterns = (array) ($item['active'] ?? $item['route']);
                $isActive = request()->routeIs($item['route']) || collect($activePatterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                $hasChildren = ! empty($item['children']);
            @endphp
            <li class="nav-item menu-items {{ $isActive ? 'active' : '' }} {{ $hasChildren ? 'kfms-has-submenu' : '' }}">
                @if ($hasChildren)
                    <details class="kfms-sidebar-group" name="kfms-sidebar-sections" @if ($isActive) open @endif>
                        <summary class="nav-link" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
                            <span class="menu-icon">
                                <i class="mdi {{ $item['icon'] }}"></i>
                            </span>
                            <span class="menu-title">{{ $item['label'] }}</span>
                            <i class="mdi mdi-chevron-right kfms-submenu-arrow"></i>
                        </summary>
                        <div class="kfms-sidebar-submenu" id="sidebar-{{ \Illuminate\Support\Str::slug($item['label']) }}">
                            <ul class="nav flex-column sub-menu kfms-sub-menu">
                                @foreach ($item['children'] as $child)
                                    @continue(isset($child['permission']) && ! auth()->user()?->can($child['permission']))
                                    @php
                                        $childUrl = route($child['route'], $child['query'] ?? []);
                                        $childPatterns = (array) ($child['active'] ?? $child['route']);
                                        $childRouteActive = collect($childPatterns)->contains(fn ($pattern) => request()->routeIs($pattern));
                                        $childActive = isset($child['query'])
                                            ? ($childRouteActive && collect($child['query'])->every(fn ($value, $key) => request($key) === $value))
                                            : ($childRouteActive && ! request()->has('status'));
                                    @endphp
                                    <li class="nav-item {{ $childActive ? 'active' : '' }}">
                                        <a class="nav-link" href="{{ $childUrl }}">{{ $child['label'] }}</a>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </details>
                @else
                    <a class="nav-link" href="{{ route($item['route']) }}">
                        <span class="menu-icon">
                            <i class="mdi {{ $item['icon'] }}"></i>
                        </span>
                        <span class="menu-title">{{ $item['label'] }}</span>
                    </a>
                @endif
            </li>
        @endforeach
    </ul>
</nav>
