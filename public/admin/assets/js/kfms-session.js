(function () {
    'use strict';

    var unsafeMethods = { POST: true, PUT: true, PATCH: true, DELETE: true };
    var refreshPromise = null;
    var statusUrl = (document.querySelector('meta[name="kfms-session-status"]') || {}).content || '/session/status';
    var heartbeatUrl = (document.querySelector('meta[name="kfms-session-heartbeat"]') || {}).content || '/session/heartbeat';

    function csrfMeta() {
        return document.querySelector('meta[name="csrf-token"]');
    }

    function currentToken() {
        var meta = csrfMeta();
        return meta ? meta.content : '';
    }

    function setToken(token) {
        if (!token) return;

        var meta = csrfMeta();
        if (meta) meta.content = token;

        document.querySelectorAll('input[name="_token"]').forEach(function (input) {
            input.value = token;
        });

        if (window.axios && window.axios.defaults) {
            window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
        }
    }

    function refreshSession() {
        if (refreshPromise) return refreshPromise;

        refreshPromise = fetch(statusUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        }).then(function (response) {
            if (!response.ok) throw new Error('Session status was not available.');
            return response.json();
        }).then(function (payload) {
            setToken(payload.csrf_token);
            return payload;
        }).finally(function () {
            refreshPromise = null;
        });

        return refreshPromise;
    }

    function pageUserId() {
        var body = document.body;
        return body && (body.dataset.kfmsSessionUser || body.dataset.workspaceUser || '');
    }

    function isLogout(form) {
        try {
            return new URL(form.action || window.location.href, window.location.origin).pathname.replace(/\/$/, '') === '/logout';
        } catch (error) {
            return false;
        }
    }

    function isClientContext(form) {
        return Boolean(
            form && form.querySelector('input[name="client_portal_logout"]')
        ) || window.location.pathname.indexOf('/client') === 0 || document.body.classList.contains('kfms-client-portal');
    }

    function destination(clientContext) {
        return clientContext ? '/client-portal/login' : '/login';
    }

    function showRecovery(clientContext, message) {
        var notice = document.querySelector('[data-kfms-session-recovery]');
        if (!notice) {
            notice = document.createElement('div');
            notice.setAttribute('data-kfms-session-recovery', '');
            notice.setAttribute('role', 'alert');
            notice.style.cssText = 'position:fixed;z-index:2147483647;inset:auto 1rem 1rem;max-width:32rem;margin:auto;padding:1rem 1.25rem;background:#fff;border:1px solid #d7b04a;border-radius:.5rem;box-shadow:0 10px 30px rgba(0,0,0,.18);font:500 15px/1.45 system-ui,sans-serif;color:#071d3a;';
            document.body.appendChild(notice);
        }
        notice.innerHTML = '';
        var text = document.createElement('span');
        text.textContent = message || 'Your session could not be refreshed. Please sign in again.';
        notice.appendChild(text);
        notice.appendChild(document.createTextNode(' '));
        var link = document.createElement('a');
        link.href = destination(clientContext);
        link.textContent = 'Sign in again';
        link.style.cssText = 'color:#0b4770;text-decoration:underline;font-weight:700;';
        notice.appendChild(link);
    }

    function startIdleProtection() {
        var body = document.body;
        var userId = pageUserId();
        var timeout = Number(body && body.dataset ? body.dataset.kfmsIdleTimeout : 0);

        // Guest pages and test/minimal documents do not have an authenticated
        // idle session to protect.
        if (!body || !userId || !timeout || timeout < 1000 || typeof window.setInterval !== 'function') return;

        var warningWindow = Math.min(60000, Math.max(10000, Math.floor(timeout / 10)));
        var activityKey = 'kfms:last-activity:' + userId;
        var logoutKey = 'kfms:idle-logout:' + userId;
        var storage = null;
        var notice = null;
        var logoutStarted = false;
        var lastLocalWrite = 0;
        var lastUserActivityAt = Date.now();
        var lastHeartbeatAttemptAt = 0;
        var heartbeatInFlight = false;
        var heartbeatFailed = false;
        var heartbeatEveryMs = 4 * 60 * 1000;
        var activeWindowMs = 10 * 60 * 1000;

        try {
            storage = window.localStorage;
        } catch (error) {
            storage = null;
        }

        function readActivity() {
            if (!storage) return lastUserActivityAt;
            try {
                var value = Number(storage.getItem(activityKey));
                if (value > lastUserActivityAt && value <= Date.now()) lastUserActivityAt = value;
                return lastUserActivityAt;
            } catch (error) {
                return lastUserActivityAt;
            }
        }

        function writeActivity(timestamp) {
            if (!storage) return;
            try {
                storage.setItem(activityKey, String(timestamp));
            } catch (error) {
                // Private browsing modes can deny localStorage access.
            }
        }

        function createNotice() {
            if (notice) return notice;

            notice = document.createElement('div');
            notice.setAttribute('data-kfms-idle-warning', '');
            notice.setAttribute('role', 'alertdialog');
            notice.setAttribute('aria-live', 'assertive');
            notice.style.cssText = 'position:fixed;z-index:2147483646;right:1rem;bottom:1rem;max-width:28rem;padding:1rem 1.15rem;background:#fff;border:1px solid #e3a008;border-radius:.55rem;box-shadow:0 10px 30px rgba(0,0,0,.2);font:500 14px/1.45 system-ui,sans-serif;color:#071d3a;';

            var message = document.createElement('span');
            message.setAttribute('data-kfms-idle-message', '');
            notice.appendChild(message);
            notice.appendChild(document.createTextNode(' '));

            var stay = document.createElement('button');
            stay.type = 'button';
            stay.textContent = 'Stay signed in';
            stay.style.cssText = 'margin-top:.65rem;padding:.45rem .7rem;border:0;border-radius:.35rem;background:#071d3a;color:#fff;font-weight:700;cursor:pointer;';
            stay.addEventListener('click', function () {
                touchActivity(true);
            });
            notice.appendChild(stay);
            notice.hidden = true;
            document.body.appendChild(notice);

            return notice;
        }

        function touchActivity(force) {
            var timestamp = Date.now();
            if (!force && timestamp - lastLocalWrite < 5000) return;
            lastUserActivityAt = timestamp;
            lastLocalWrite = timestamp;
            writeActivity(timestamp);
            if (notice) notice.hidden = true;
            refreshHeartbeat(force);
        }

        function refreshHeartbeat(force) {
            if (heartbeatFailed || heartbeatInFlight || document.hidden
                || timestampSinceActivity() > activeWindowMs
                || (!force && Date.now() - lastHeartbeatAttemptAt < heartbeatEveryMs)) {
                return;
            }

            var token = currentToken();
            if (!token) return;

            heartbeatInFlight = true;
            lastHeartbeatAttemptAt = Date.now();

            fetch(heartbeatUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ activity_age_ms: Math.max(0, timestampSinceActivity()) }),
            }).then(function (response) {
                if (response.status === 401 || response.status === 419) {
                    heartbeatFailed = true;
                    showRecovery(document.body.classList.contains('kfms-client-portal'), 'Your secure session expired. Please sign in again.');
                    return null;
                }
                if (!response.ok) throw new Error('Session heartbeat was not available.');
                return response.json();
            }).then(function (payload) {
                if (payload && payload.csrf_token) setToken(payload.csrf_token);
            }).catch(function () {
                // A temporary network interruption should not sign the user out;
                // the next visible heartbeat will retry while the session is valid.
            }).finally(function () {
                heartbeatInFlight = false;
            });
        }

        function timestampSinceActivity() {
            readActivity();
            return Math.max(0, Date.now() - lastUserActivityAt);
        }

        function finishIdleLogout(broadcast) {
            if (logoutStarted) return;
            logoutStarted = true;

            if (broadcast && storage) {
                try {
                    storage.setItem(logoutKey, String(Date.now()));
                } catch (error) {
                    // The current tab still logs out if storage is unavailable.
                }
            }

            function postLogout() {
                return fetch('/logout', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-CSRF-TOKEN': currentToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: new URLSearchParams({ _token: currentToken() }),
                });
            }

            // Get the token belonging to the session that is still active in
            // this tab before posting logout. If the server has already
            // expired it, the recovery redirect below is still correct.
            refreshSession().catch(function () {}).finally(function () {
                postLogout().finally(function () {
                    window.location.replace(destination(document.body.classList.contains('kfms-client-portal')) + '?session=expired');
                });
            });
        }

        function checkIdle() {
            var remaining = timeout - (Date.now() - readActivity());
            if (remaining <= 0) {
                finishIdleLogout(true);
                return;
            }

            if (remaining <= warningWindow) {
                var idleNotice = createNotice();
                idleNotice.querySelector('[data-kfms-idle-message]').textContent = 'You will be signed out in ' + Math.ceil(remaining / 1000) + ' seconds because of inactivity.';
                idleNotice.hidden = false;
            } else if (notice) {
                notice.hidden = true;
            }
        }

        lastUserActivityAt = Date.now();
        writeActivity(lastUserActivityAt);
        ['pointerdown', 'keydown', 'scroll', 'touchstart', 'mousemove'].forEach(function (eventName) {
            document.addEventListener(eventName, function () {
                touchActivity(false);
            }, { passive: true });
        });
        document.addEventListener('visibilitychange', function () {
            checkIdle();
            if (!document.hidden) refreshHeartbeat(true);
        });
        if (typeof window.addEventListener === 'function') {
            window.addEventListener('storage', function (event) {
                if (event.key === logoutKey && event.newValue) finishIdleLogout(false);
                if (event.key === activityKey) checkIdle();
            });
        }
        window.setInterval(checkIdle, 1000);
        window.setInterval(function () {
            refreshHeartbeat(false);
        }, heartbeatEveryMs);
    }

    function submitAfterRefresh(form, submitter) {
        return refreshSession().then(function (payload) {
            var clientContext = isClientContext(form);
            var logout = isLogout(form);
            var originalUser = pageUserId();
            var currentUser = payload.user_id == null ? '' : String(payload.user_id);

            // A session may have expired or another tab may have signed in as
            // a different account. Never send the old action in either case.
            if ((originalUser && (!payload.authenticated || currentUser !== String(originalUser))) || (logout && !payload.authenticated)) {
                if (logout && !payload.authenticated) {
                    window.location.replace(clientContext ? '/client-portal' : '/login');
                } else {
                    showRecovery(clientContext, currentUser && originalUser !== currentUser
                        ? 'This tab belongs to a different signed-in account. Sign in again before continuing.'
                        : 'Your session expired before the form was submitted.');
                }
                return;
            }

            form.dataset.kfmsSessionPrepared = '1';
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit(submitter || undefined);
            } else {
                form.submit();
            }
        });
    }

    // Refresh the token immediately before every same-origin state-changing
    // HTML form. The original action is submitted at most once and only after
    // the page is confirmed to belong to the same session/account.
    document.addEventListener('submit', function (event) {
        var form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (form.dataset.kfmsSkipSessionGuard === '1' || form.dataset.kfmsSessionPrepared === '1') {
            if (form.dataset.kfmsSessionPrepared === '1') delete form.dataset.kfmsSessionPrepared;
            return;
        }

        var method = (form.method || 'GET').toUpperCase();
        if (!unsafeMethods[method]) return;

        var action;
        try {
            action = new URL(form.action || window.location.href, window.location.origin);
        } catch (error) {
            return;
        }
        if (action.origin !== window.location.origin || form.dataset.kfmsSessionPreparing === '1') return;

        event.preventDefault();
        form.dataset.kfmsSessionPreparing = '1';
        submitAfterRefresh(form, event.submitter).catch(function () {
            showRecovery(isClientContext(form));
        }).finally(function () {
            delete form.dataset.kfmsSessionPreparing;
        });
    }, true);

    // Expose a small API for fetch-based modules (letters, verification, and
    // future modules) without automatically replaying a failed mutation.
    window.KfmsSession = {
        refresh: refreshSession,
        token: currentToken,
        setToken: setToken,
        updateFormToken: function (form) {
            var token = currentToken();
            var input = form && form.querySelector('input[name="_token"]');
            if (input) input.value = token;
            return token;
        },
    };

    startIdleProtection();

    // Livewire reads the meta token for each request. If its request still
    // reaches the server after the session has expired, replace Livewire's
    // generic browser alert with the same safe recovery message and never
    // retry the component action automatically.
    if (window.Livewire && typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('request', function (hook) {
            if (typeof hook.fail !== 'function') return;
            hook.fail(function (failure) {
                if (failure.status !== 419) return;
                failure.preventDefault();
                showRecovery(document.body.classList.contains('kfms-client-portal'));
            });
        });
    }
})();
