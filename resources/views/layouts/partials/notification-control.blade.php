@auth
    @php
        $navbarNotifications = auth()->user()->unreadNotifications()->latest()->limit(5)->get();
        $navbarUnreadCount = auth()->user()->unreadNotifications()->count();
    @endphp

    <div
        class="dropdown kfms-notification-center"
        data-kfms-notification-center
        data-notification-feed-url="{{ route('notifications.unread', absolute: false) }}">
        <button
            class="kfms-nav-icon"
            type="button"
            id="notificationDropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false"
            aria-label="Notifications"
            title="Notifications">
            <i class="mdi mdi-bell-outline" aria-hidden="true"></i>
            <span
                class="kfms-nav-badge"
                data-kfms-notification-badge
                @if ($navbarUnreadCount === 0) hidden @endif>{{ $navbarUnreadCount > 9 ? '9+' : $navbarUnreadCount }}</span>
        </button>

        <div class="dropdown-menu dropdown-menu-end navbar-dropdown kfms-notice-dropdown" aria-labelledby="notificationDropdown">
            <h6>Notifications</h6>
            <div data-kfms-notification-items>
                @forelse ($navbarNotifications as $notification)
                    <a
                        class="dropdown-item kfms-notification-item"
                        data-notification-id="{{ $notification->id }}"
                        href="{{ $notification->data['url'] ?? route('notifications.index') }}">
                        <strong>{{ $notification->data['subject'] ?? 'Notification' }}</strong>
                        @if (filled($notification->data['body'] ?? null))
                            <span>{{ str($notification->data['body'])->limit(100) }}</span>
                        @endif
                    </a>
                @empty
                    <span class="dropdown-item-text" data-kfms-notification-empty>No unread notifications</span>
                @endforelse
            </div>
            <a class="dropdown-item kfms-dropdown-cta" href="{{ route('notifications.index') }}">
                <i class="mdi mdi-open-in-new"></i>
                View all notifications
            </a>
        </div>
    </div>
@endauth
