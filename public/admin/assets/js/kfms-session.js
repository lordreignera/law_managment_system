(function () {
    'use strict';

    var unsafeMethods = { POST: true, PUT: true, PATCH: true, DELETE: true };
    var refreshPromise = null;
    var statusUrl = (document.querySelector('meta[name="kfms-session-status"]') || {}).content || '/session/status';

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
