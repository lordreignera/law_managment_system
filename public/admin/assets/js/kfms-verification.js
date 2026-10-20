(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var title = document.getElementById('auth-dialog-title');
        if (title) title.focus();
        var form = document.querySelector('[data-verification-resend]');
        if (!form) return;
        var button = form.querySelector('[data-resend-button]');
        var status = document.querySelector('[data-verification-status]');
        var reload = document.querySelector('[data-verification-reload]');
        var busy = false;
        var cooldownUntil = 0;
        var interval;

        function message(text, state) {
            status.textContent = text;
            status.dataset.state = state;
        }
        function countdown(seconds) {
            window.clearInterval(interval);
            cooldownUntil = Date.now() + Math.max(0, seconds) * 1000;
            function tick() {
                var remaining = Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000));
                button.disabled = busy || remaining > 0;
                button.textContent = remaining > 0 ? 'Resend in ' + remaining + 's' : 'Resend Verification Email';
                if (!remaining) window.clearInterval(interval);
            }
            tick();
            if (seconds > 0) interval = window.setInterval(tick, 1000);
        }
        countdown(Number(form.dataset.retryAfter) || 0);
        form.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (busy || Date.now() < cooldownUntil) return;
            busy = true;
            button.disabled = true;
            button.textContent = 'Sending verification email...';
            message('Sending your verification link...', 'info');
            reload.hidden = true;
            var retryAfter = 0;
            try {
                var response = await fetch(form.action, {
                    method: 'POST', credentials: 'same-origin', body: new FormData(form),
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (response.status === 419 || response.status === 401 || response.redirected) {
                    message('Your session has expired. Reload this page and sign in again to request a new link.', 'error');
                    reload.hidden = false;
                    return;
                }
                var payload = await response.json();
                if (payload.verified && payload.redirect) {
                    window.location.assign(payload.redirect);
                    return;
                }
                retryAfter = Number(payload.retry_after || response.headers.get('Retry-After')) || 0;
                message(payload.message || 'We could not send the email. Please try again.', response.ok ? 'success' : 'error');
            } catch (error) {
                message('We could not reach the server. Check your connection and try again.', 'error');
            } finally {
                busy = false;
                countdown(retryAfter);
            }
        });
    });
})();
