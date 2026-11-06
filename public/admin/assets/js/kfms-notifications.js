(function () {
    'use strict';

    var pollInterval = 20000;
    var centers = [];
    var knownIds = new Set();
    var initialized = false;

    var escapeText = function (value) {
        return String(value || '').trim();
    };

    var formatTime = function (value) {
        if (!value) {
            return '';
        }

        try {
            return new Intl.DateTimeFormat(undefined, {
                dateStyle: 'medium',
                timeStyle: 'short',
            }).format(new Date(value));
        } catch (error) {
            return '';
        }
    };

    var updateBadge = function (center, count) {
        var badge = center.querySelector('[data-kfms-notification-badge]');
        if (!badge) {
            return;
        }

        badge.textContent = count > 9 ? '9+' : String(count);
        badge.hidden = count < 1;
    };

    var renderItems = function (center, notifications) {
        var container = center.querySelector('[data-kfms-notification-items]');
        if (!container) {
            return;
        }

        container.replaceChildren();

        if (!notifications.length) {
            var empty = document.createElement('span');
            empty.className = 'dropdown-item-text';
            empty.dataset.kfmsNotificationEmpty = '1';
            empty.textContent = 'No unread notifications';
            container.appendChild(empty);
            return;
        }

        notifications.forEach(function (notification) {
            var link = document.createElement('a');
            link.className = 'dropdown-item kfms-notification-item';
            link.dataset.notificationId = notification.id;
            link.href = notification.url || '/notifications';

            var subject = document.createElement('strong');
            subject.textContent = escapeText(notification.subject) || 'Notification';
            link.appendChild(subject);

            var body = escapeText(notification.body);
            if (body) {
                var summary = document.createElement('span');
                summary.textContent = body.length > 100 ? body.slice(0, 97) + '...' : body;
                link.appendChild(summary);
            }

            var time = formatTime(notification.created_at);
            if (time) {
                var timestamp = document.createElement('small');
                timestamp.textContent = time;
                link.appendChild(timestamp);
            }

            container.appendChild(link);
        });
    };

    var showToast = function (notification) {
        var stack = document.querySelector('[data-kfms-notification-toasts]');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'kfms-notification-toasts';
            stack.dataset.kfmsNotificationToasts = '1';
            stack.setAttribute('aria-live', 'polite');
            stack.setAttribute('aria-atomic', 'false');
            document.body.appendChild(stack);
        }

        var toast = document.createElement('div');
        toast.className = 'kfms-notification-toast';
        toast.dataset.notificationId = notification.id;

        var icon = document.createElement('i');
        icon.className = 'mdi mdi-bell-ring-outline';
        icon.setAttribute('aria-hidden', 'true');
        toast.appendChild(icon);

        var content = document.createElement('div');
        var subject = document.createElement('strong');
        subject.textContent = escapeText(notification.subject) || 'New notification';
        content.appendChild(subject);

        var body = escapeText(notification.body);
        if (body) {
            var message = document.createElement('span');
            message.textContent = body.length > 160 ? body.slice(0, 157) + '...' : body;
            content.appendChild(message);
        }

        toast.appendChild(content);

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'kfms-notification-toast-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.innerHTML = '&times;';
        close.addEventListener('click', function () { toast.remove(); });
        toast.appendChild(close);

        if (notification.url) {
            toast.addEventListener('click', function (event) {
                if (event.target !== close) {
                    window.location.href = notification.url;
                }
            });
            toast.classList.add('is-clickable');
        }

        stack.appendChild(toast);
        window.setTimeout(function () {
            toast.remove();
        }, 8000);
    };

    var sync = function () {
        if (!centers.length) {
            return;
        }

        fetch(centers[0].dataset.notificationFeedUrl, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
            cache: 'no-store',
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Notification feed unavailable');
                }

                return response.json();
            })
            .then(function (payload) {
                var notifications = Array.isArray(payload.notifications) ? payload.notifications : [];
                var freshNotifications = initialized
                    ? notifications.filter(function (notification) { return !knownIds.has(notification.id); })
                    : [];

                notifications.forEach(function (notification) { knownIds.add(notification.id); });
                initialized = true;

                centers.forEach(function (center) {
                    updateBadge(center, Number(payload.unread_count || 0));
                    renderItems(center, notifications.slice(0, 5));
                });

                freshNotifications.slice(0, 3).reverse().forEach(showToast);
            })
            .catch(function () {
                // A temporary network or session failure should not disrupt the page.
            });
    };

    document.addEventListener('DOMContentLoaded', function () {
        centers = Array.from(document.querySelectorAll('[data-kfms-notification-center][data-notification-feed-url]'));
        if (!centers.length) {
            return;
        }

        centers.forEach(function (center) {
            center.querySelectorAll('[data-notification-id]').forEach(function (item) {
                knownIds.add(item.dataset.notificationId);
            });
        });

        window.setTimeout(sync, 1000);
        window.setInterval(sync, pollInterval);
    });
})();
