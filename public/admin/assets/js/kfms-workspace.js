(function () {
    'use strict';

    function setupSettingsSearch() {
        var form = document.querySelector('[data-settings-search]');
        if (!form) return;
        var input = form.querySelector('input[name="search"]');
        var cards = Array.from(document.querySelectorAll('[data-live-setting]'));
        var status = document.getElementById('settings-search-status');
        var empty = document.querySelector('[data-settings-empty]');

        function filter(updateUrl) {
            var query = input.value.trim().toLocaleLowerCase();
            var count = 0;
            cards.forEach(function (card) {
                var matches = !query || (query.length === 1
                    ? card.dataset.settingTitle.startsWith(query)
                    : card.dataset.settingSearch.includes(query));
                card.hidden = !matches;
                if (matches) count++;
            });
            status.textContent = count + (count === 1 ? ' setting found' : ' settings found');
            empty.hidden = count !== 0;
            if (updateUrl) {
                var url = new URL(window.location.href);
                if (input.value.trim()) url.searchParams.set('search', input.value.trim());
                else url.searchParams.delete('search');
                try { window.history.replaceState(window.history.state, '', url.pathname + url.search + url.hash); } catch (error) { /* Filtering still works when URL updates are unavailable. */ }
            }
        }

        input.addEventListener('input', function () { filter(true); });
        form.addEventListener('submit', function (event) { event.preventDefault(); filter(true); });
        form.querySelector('[data-settings-clear]').addEventListener('click', function (event) {
            event.preventDefault();
            input.value = '';
            filter(true);
            input.focus();
        });
        filter(false);
    }

    function setupBulkApprovals() {
        var container = document.querySelector('[data-bulk-approvals]');
        if (!container) return;
        var form = container.querySelector('#bulk-approvals');
        if (!form) return;
        var options = Array.from(container.querySelectorAll('[data-bulk-user]')).filter(function (option) { return !option.disabled; });
        var all = container.querySelector('[data-bulk-all]');
        var count = container.querySelector('[data-bulk-count]');
        var submit = container.querySelector('[data-bulk-submit]');

        function refresh() {
            var selected = options.filter(function (option) { return option.checked; }).length;
            count.textContent = selected + ' selected on this page';
            submit.disabled = selected === 0;
            all.disabled = options.length === 0;
            all.checked = options.length > 0 && selected === options.length;
            all.indeterminate = selected > 0 && selected < options.length;
        }
        all.addEventListener('change', function () {
            options.forEach(function (option) { option.checked = all.checked; });
            refresh();
        });
        options.forEach(function (option) { option.addEventListener('change', refresh); });
        container.querySelectorAll('[data-bulk-first]').forEach(function (button) {
            button.disabled = options.length === 0;
            button.addEventListener('click', function () {
                options.forEach(function (option, index) { option.checked = index < Number(button.dataset.bulkFirst); });
                refresh();
            });
        });
        form.addEventListener('submit', function (event) {
            if (!options.some(function (option) { return option.checked; })) {
                event.preventDefault();
                return;
            }
            submit.disabled = true;
            submit.textContent = 'Approving...';
        });
        window.addEventListener('pageshow', function () { submit.textContent = 'Approve Selected'; refresh(); });
        refresh();
    }

    function setupBackNavigation() {
        var key = 'kfms.workspace.history.' + document.body.dataset.workspaceUser;
        var back = document.querySelector('[data-workspace-back]');
        var current = window.location.pathname + window.location.search;
        function safePath(path) {
            if (typeof path !== 'string' || !path.startsWith('/') || path.startsWith('//')) return false;
            var url = new URL(path, window.location.origin);
            return url.origin === window.location.origin && url.pathname !== '/'
                && !/^\/(login|logout|register|forgot-password|reset-password|two-factor|client-portal|access-pending)(\/|$)/.test(url.pathname);
        }
        try {
            var stored = JSON.parse(window.sessionStorage.getItem(key) || '[]');
            var stack = Array.isArray(stored) ? stored.filter(safePath) : [];
            if (stack[stack.length - 1] !== current) {
                var existing = stack.lastIndexOf(current);
                stack = existing >= 0 ? stack.slice(0, existing + 1) : stack.concat(current);
            }
            stack = stack.slice(-30);
            window.sessionStorage.setItem(key, JSON.stringify(stack));
            if (back && stack.length > 1) back.href = stack[stack.length - 2];
            window.addEventListener('pagehide', function () {
                var latest = window.location.pathname + window.location.search;
                if (safePath(latest) && window.location.pathname === current.split('?')[0]) {
                    stack[stack.length - 1] = latest;
                    window.sessionStorage.setItem(key, JSON.stringify(stack));
                }
            });
        } catch (error) { /* The server-rendered Back link remains available without browser storage. */ }
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupSettingsSearch();
        setupBulkApprovals();
        setupBackNavigation();
    });
})();
