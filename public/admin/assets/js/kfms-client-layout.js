(function () {
    'use strict';
    document.addEventListener('DOMContentLoaded', function () {
        var toggle = document.querySelector('[data-client-menu-toggle]');
        var sidebar = document.getElementById('client-navigation');
        var backdrop = document.querySelector('.kfms-client-backdrop');
        var workspace = document.querySelector('.kfms-client-workspace');
        if (!toggle || !sidebar || !backdrop || !workspace) return;
        var mobile = window.matchMedia('(max-width: 991px)');
        var isOpen = false;
        function setOpen(open, restoreFocus) {
            isOpen = open && mobile.matches;
            document.body.classList.toggle('kfms-client-menu-open', isOpen);
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            backdrop.hidden = !isOpen;
            sidebar.inert = mobile.matches && !isOpen;
            workspace.inert = isOpen;
            if (isOpen) { sidebar.setAttribute('role', 'dialog'); sidebar.setAttribute('aria-modal', 'true'); }
            else { sidebar.removeAttribute('role'); sidebar.removeAttribute('aria-modal'); }
            if (isOpen) sidebar.querySelector('[data-client-menu-close]').focus();
            else if (restoreFocus) toggle.focus();
        }
        toggle.addEventListener('click', function () { setOpen(!isOpen, true); });
        document.querySelectorAll('[data-client-menu-close]').forEach(function (button) {
            button.addEventListener('click', function () { setOpen(false, true); });
        });
        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false, false); });
        });
        document.addEventListener('keydown', function (event) {
            if (!isOpen) return;
            if (event.key === 'Escape') { event.preventDefault(); setOpen(false, true); }
            if (event.key === 'Tab') {
                var links = Array.from(sidebar.querySelectorAll('a, button:not([disabled])'));
                var first = links[0]; var last = links[links.length - 1];
                if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
                else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
            }
        });
        mobile.addEventListener('change', function () { setOpen(false, false); });
        window.addEventListener('pageshow', function () { setOpen(false, false); });
        setOpen(false, false);
    });
})();
