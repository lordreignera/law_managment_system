/**
 * KFMS sidebar controller
 *
 * Replaces the Star Admin misc.js / hoverable-collapse.js scripts that
 * were never loaded by admin.blade.php. Responsibilities:
 *
 *  - Wire the navbar [data-toggle="minimize"] button to toggle
 *    .sidebar-icon-only on the body (desktop) or .active on
 *    #sidebar (mobile, mirroring the off-canvas behaviour).
 *  - Persist the collapsed state per-browser via localStorage so it
 *    survives navigation.
 *  - In icon-only mode, expose .hover-open on a nav-item while the
 *    mouse is over it so the CSS fly-out label shows.
 *
 * No jQuery. No external dependencies.
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'kfms.sidebar.collapsed';
    var DESKTOP_MQ = window.matchMedia('(min-width: 992px)');
    var body = document.body;
    var backdrop = null;

    function isDesktop() {
        return DESKTOP_MQ.matches;
    }

    function ensureBackdrop() {
        if (backdrop) {
            return backdrop;
        }
        backdrop = document.createElement('div');
        backdrop.className = 'kfms-sidebar-backdrop';
        backdrop.setAttribute('aria-hidden', 'true');
        backdrop.addEventListener('click', closeOffcanvasSidebar);
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function syncBackdrop() {
        var sidebar = document.getElementById('sidebar');
        var open = !isDesktop() && sidebar && sidebar.classList.contains('active');
        if (open) {
            ensureBackdrop().classList.add('is-visible');
            document.body.classList.add('kfms-no-scroll');
        } else if (backdrop) {
            backdrop.classList.remove('is-visible');
            document.body.classList.remove('kfms-no-scroll');
        }
    }

    function closeOffcanvasSidebar() {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('active')) {
            sidebar.classList.remove('active');
            syncBackdrop();
        }
    }

    function applyPersistedState() {
        if (!isDesktop()) {
            // The .sidebar-icon-only class only makes sense on desktop;
            // mobile uses the off-canvas pattern.
            body.classList.remove('sidebar-icon-only');
            return;
        }
        if (localStorage.getItem(STORAGE_KEY) === '1') {
            body.classList.add('sidebar-icon-only');
        } else {
            body.classList.remove('sidebar-icon-only');
        }
    }

    function handleMinimizeClick(event) {
        var trigger = event.target.closest('[data-toggle="minimize"]');
        if (!trigger) {
            return;
        }
        event.preventDefault();

        if (!isDesktop()) {
            var sidebar = document.getElementById('sidebar');
            if (sidebar) {
                sidebar.classList.toggle('active');
                syncBackdrop();
            }
            return;
        }

        body.classList.toggle('sidebar-icon-only');
        try {
            localStorage.setItem(
                STORAGE_KEY,
                body.classList.contains('sidebar-icon-only') ? '1' : '0'
            );
        } catch (e) {
            // localStorage may be disabled (private mode); state still toggles for the session.
        }
    }

    function handleOffcanvasToggle(event) {
        var trigger = event.target.closest('[data-toggle="offcanvas"]');
        if (!trigger) {
            return;
        }
        // off-canvas.js already toggles the .active class; we only sync the backdrop here.
        // Run after the existing handler by deferring to the next tick.
        window.setTimeout(syncBackdrop, 0);
    }

    function handleEscape(event) {
        if (event.key === 'Escape') {
            closeOffcanvasSidebar();
        }
    }

    function handleSidebarMouseOver(event) {
        if (!isDesktop() || !body.classList.contains('sidebar-icon-only')) {
            return;
        }
        var item = event.target.closest('.sidebar .nav-item');
        if (!item || item.classList.contains('nav-category')) {
            return;
        }
        item.classList.add('hover-open');
    }

    function handleSidebarMouseOut(event) {
        if (!body.classList.contains('sidebar-icon-only')) {
            return;
        }
        var item = event.target.closest('.sidebar .nav-item');
        if (!item) {
            return;
        }
        // Only clear when the pointer actually leaves the item subtree.
        if (event.relatedTarget && item.contains(event.relatedTarget)) {
            return;
        }
        item.classList.remove('hover-open');
    }

    function clearHoverOpenAll() {
        var open = document.querySelectorAll('.sidebar .nav-item.hover-open');
        for (var i = 0; i < open.length; i++) {
            open[i].classList.remove('hover-open');
        }
    }

    function init() {
        applyPersistedState();

        document.addEventListener('click', handleMinimizeClick);
        document.addEventListener('click', handleOffcanvasToggle);
        document.addEventListener('keydown', handleEscape);
        document.addEventListener('mouseover', handleSidebarMouseOver);
        document.addEventListener('mouseout', handleSidebarMouseOut);

        // When crossing the desktop/mobile breakpoint, re-apply the right state
        // so we never end up with icon-only mode active on a phone.
        var onChange = function () {
            clearHoverOpenAll();
            closeOffcanvasSidebar();
            applyPersistedState();
        };
        if (typeof DESKTOP_MQ.addEventListener === 'function') {
            DESKTOP_MQ.addEventListener('change', onChange);
        } else if (typeof DESKTOP_MQ.addListener === 'function') {
            DESKTOP_MQ.addListener(onChange);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
