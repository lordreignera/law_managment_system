<nav class="navbar p-0 fixed-top d-flex flex-row">
    <div class="navbar-brand-wrapper d-flex d-lg-none align-items-center justify-content-center">
        <a class="navbar-brand brand-logo-mini kfms-brand-mini" href="{{ route('dashboard') }}">
            <x-company-logo mark-class="kfms-brand-mini-mark" image-class="kfms-brand-mini-image" />
        </a>
    </div>

    <div class="navbar-menu-wrapper flex-grow d-flex align-items-stretch">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
            <span class="mdi mdi-menu"></span>
        </button>

        <ul class="navbar-nav w-100">
            <li class="nav-item w-100">
                <div class="nav-link mt-2 mt-md-0 d-none d-lg-block">
                    <span class="kfms-eyebrow">{{ $companySetting->company_name }}</span>
                    <h1>@yield('page-title', 'Dashboard')</h1>
                </div>
            </li>
        </ul>

        <ul class="navbar-nav navbar-nav-right kfms-navbar-actions">
            <li class="nav-item dropdown d-flex">
                <button class="kfms-quick-action" type="button" id="quickActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="mdi mdi-plus-circle-outline"></i>
                    <span>Quick Actions</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end navbar-dropdown kfms-action-dropdown" aria-labelledby="quickActionsDropdown">
                    @can('intakes.create')
                        <a class="dropdown-item" href="{{ route('intakes.create') }}">
                            <i class="mdi mdi-clipboard-plus-outline"></i>
                            New Intake
                        </a>
                    @endcan
                    @can('settings.system.overview')
                        <a class="dropdown-item" href="{{ route('settings.system.overview') }}">
                            <i class="mdi mdi-tune-variant"></i>
                            System Settings
                        </a>
                    @endcan
                    @can('settings.company.edit')
                        <a class="dropdown-item" href="{{ route('settings.company.edit') }}">
                            <i class="mdi mdi-cog-outline"></i>
                            Company Branding
                        </a>
                    @endcan
                </div>
            </li>

            <li class="nav-item dropdown">
                <button class="kfms-nav-icon" type="button" id="chatDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Messages">
                    <i class="mdi mdi-chat-outline"></i>
                    @if (($unreadMessageCount ?? 0) > 0)
                        <span class="kfms-nav-badge">{{ $unreadMessageCount > 9 ? '9+' : $unreadMessageCount }}</span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end navbar-dropdown kfms-notice-dropdown" aria-labelledby="chatDropdown">
                    <h6>Messages</h6>
                    @forelse (($recentMessageConversations ?? collect()) as $conversation)
                        <a class="dropdown-item" href="{{ route('messages.show', $conversation) }}">
                            <strong>{{ $conversation->title }}</strong>
                            <span>{{ $conversation->latestMessage?->sender?->name ?: 'System' }} &middot; {{ $conversation->last_message_at?->diffForHumans() }}</span>
                        </a>
                    @empty
                        <p>No messages yet.</p>
                    @endforelse
                    @can('messages.index')
                        <a class="dropdown-item kfms-dropdown-cta" href="{{ route('messages.index') }}">
                            <i class="mdi mdi-open-in-new"></i>
                            Open Messages
                        </a>
                    @endcan
                </div>
            </li>

            <li class="nav-item dropdown">
                <button class="kfms-nav-icon" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="mdi mdi-bell-outline"></i>
                    <span class="kfms-nav-badge">3</span>
                </button>
                <div class="dropdown-menu dropdown-menu-end navbar-dropdown kfms-notice-dropdown" aria-labelledby="notificationDropdown">
                    <h6>Notifications</h6>
                    @can('matters.index')
                        <a class="dropdown-item" href="{{ route('matters.index') }}">Review open matters</a>
                    @endcan
                    @can('intakes.index')
                        <a class="dropdown-item" href="{{ route('intakes.index') }}">Review intake conflicts</a>
                    @endcan
                    @can('recoveries.index')
                        <a class="dropdown-item" href="{{ route('recoveries.index') }}">Check recovery assignments</a>
                    @endcan
                    @can('settings.company.edit')
                        <a class="dropdown-item" href="{{ route('settings.company.edit') }}">Confirm company branding</a>
                    @endcan
                </div>
            </li>

            <li class="nav-item dropdown">
                <a class="nav-link" id="profileDropdown" href="#" data-bs-toggle="dropdown">
                    <div class="navbar-profile">
                        <img class="kfms-profile-initial small" src="{{ auth()->user()->profile_photo_url }}" alt="{{ auth()->user()->name }}">
                        <p class="mb-0 d-none d-sm-block navbar-profile-name">{{ auth()->user()->name }}</p>
                        <i class="mdi mdi-menu-down d-none d-sm-block"></i>
                    </div>
                </a>
                <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list" aria-labelledby="profileDropdown">
                    <a class="dropdown-item preview-item" href="{{ route('profile.show') }}">
                        <div class="preview-thumbnail">
                            <div class="preview-icon">
                                <i class="mdi mdi-account-outline"></i>
                            </div>
                        </div>
                        <div class="preview-item-content">
                            <p class="preview-subject mb-1">Profile</p>
                        </div>
                    </a>
                    <div class="dropdown-divider"></div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item preview-item">
                            <div class="preview-thumbnail">
                                <div class="preview-icon">
                                    <i class="mdi mdi-logout"></i>
                                </div>
                            </div>
                            <div class="preview-item-content">
                                <p class="preview-subject mb-1">Logout</p>
                            </div>
                        </button>
                    </form>
                </div>
            </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
            <span class="mdi mdi-format-line-spacing"></span>
        </button>
    </div>
</nav>
