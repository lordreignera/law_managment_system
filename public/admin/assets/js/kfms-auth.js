document.addEventListener('click', function (event) {
    const toggle = event.target.closest('[data-password-toggle]');

    if (!toggle) {
        return;
    }

    const input = document.getElementById(toggle.dataset.passwordToggle);

    if (!input) {
        return;
    }

    const passwordIsVisible = input.type === 'text';
    input.type = passwordIsVisible ? 'password' : 'text';

    const icon = toggle.querySelector('i');

    if (icon) {
        icon.classList.toggle('mdi-eye-outline', passwordIsVisible);
        icon.classList.toggle('mdi-eye-off-outline', ! passwordIsVisible);
    }
});

document.addEventListener('click', function (event) {
    const close = event.target.closest('[data-auth-modal-close]');

    if (! close) {
        return;
    }

    const modal = close.closest('[data-auth-modal]');

    if (modal) {
        modal.classList.add('is-hidden');
    }
});

document.addEventListener('click', async function (event) {
    const link = event.target.closest('[data-portal-share-link]');

    if (!link || event.defaultPrevented || event.button > 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
        return;
    }

    event.preventDefault();

    const container = link.closest('[data-portal-share]');
    const status = container.querySelector('[data-portal-share-status]');
    const urlField = container.querySelector('[data-portal-share-url]');

    try {
        await navigator.clipboard.writeText(link.href);
        urlField.hidden = true;
        status.textContent = 'Client portal link copied. You can now share it.';
    } catch {
        status.textContent = 'Copy the link below to share it.';
        urlField.hidden = false;
        urlField.focus();
        urlField.select();
    }
});
