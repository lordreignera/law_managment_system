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
    toggle.textContent = passwordIsVisible ? 'SHOW' : 'HIDE';
});
