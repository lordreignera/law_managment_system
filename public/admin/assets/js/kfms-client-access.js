(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var emailInput = document.querySelector('[data-client-lookup-url]');
        var message = document.querySelector('[data-client-lookup-message]');
        var phoneInput = document.getElementById('phone');
        if (!emailInput || !message || !phoneInput) return;
        var form = emailInput.closest('form');
        var timer;
        var controller;
        var pending;
        var activeEmail = '';
        var matchedEmail = '';
        var waitingForSubmit = false;
        var version = 0;

        function email() { return emailInput.value.trim().toLowerCase(); }
        function setMessage(text, state) { message.textContent = text; message.dataset.state = state; }
        function clearMatch() {
            phoneInput.value = '';
            matchedEmail = '';
        }
        function lookup() {
            var requestedEmail = email();
            if (!requestedEmail || !emailInput.checkValidity()) return Promise.resolve(false);
            if (matchedEmail === requestedEmail) return Promise.resolve(true);
            if (pending && activeEmail === requestedEmail) return pending;
            if (controller) controller.abort();
            controller = new AbortController();
            var requestVersion = ++version;
            var requestController = controller;
            activeEmail = requestedEmail;
            setMessage('Checking client record...', 'checking');
            var url = new URL(emailInput.dataset.clientLookupUrl, window.location.origin);
            url.searchParams.set('email', requestedEmail);
            pending = (async function () {
                try {
                    var response = await fetch(url, { headers: { Accept: 'application/json' }, signal: requestController.signal });
                    var payload = await response.json();
                    if (requestVersion !== version || email() !== requestedEmail) return false;
                    if (!response.ok || !payload.exists) {
                        clearMatch();
                        setMessage(payload.message || 'This email is not registered as an active client.', 'error');
                        return false;
                    }
                    phoneInput.value = payload.phone_hint || '';
                    phoneInput.readOnly = true;
                    phoneInput.placeholder = payload.phone_hint ? 'Registered phone number (partly hidden)' : 'No phone number recorded';
                    matchedEmail = requestedEmail;
                    setMessage(payload.message || 'Client record found.', payload.has_portal_account ? 'warning' : 'success');
                    return true;
                } catch (error) {
                    if (requestVersion === version && error.name !== 'AbortError') {
                        clearMatch();
                        setMessage('We could not check your email. Check your connection and try again.', 'error');
                    }
                    return false;
                } finally {
                    if (requestVersion === version) pending = null;
                }
            })();
            return pending;
        }
        emailInput.addEventListener('input', function () {
            window.clearTimeout(timer);
            version++;
            if (controller) controller.abort();
            pending = null;
            clearMatch();
            setMessage('', '');
            timer = window.setTimeout(lookup, 250);
        });
        emailInput.addEventListener('change', lookup);
        emailInput.addEventListener('blur', function () { window.clearTimeout(timer); lookup(); });
        form.addEventListener('submit', async function (event) {
            if (matchedEmail === email()) return;
            event.preventDefault();
            if (waitingForSubmit) return;
            waitingForSubmit = true;
            window.clearTimeout(timer);
            try {
                if (await lookup()) form.requestSubmit();
            } finally {
                waitingForSubmit = false;
            }
        });
        if (emailInput.value) lookup();
    });
})();
