<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage kca-client-register-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined kca-client-register-card">
            <div class="kca-login-card-accent" aria-hidden="true"></div>
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-account-lock-outline"></i>
                            <span>Client portal</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Use the email already registered with the firm to sign in to your portal.</p>
                    </div>

                    @if ($errors->any())
                        <div class="kca-alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('client.register.store') }}">
                        @csrf

                        <label class="kca-field" for="email">
                            <span>Registered Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter the email we have on record" required autofocus autocomplete="email" data-client-lookup-url="{{ route('client.lookup') }}">
                            </div>
                        </label>
                        <p class="kca-lookup-message" data-client-lookup-message aria-live="polite"></p>

                        <label class="kca-field" for="phone">
                            <span>Registered Phone Number</span>
                            <div>
                                <i class="mdi mdi-phone-outline"></i>
                                <input id="phone" type="text" name="phone" value="{{ old('phone') }}" placeholder="Filled after email is confirmed" autocomplete="tel" readonly>
                            </div>
                        </label>
                        <input id="client_no" type="hidden" name="client_no" value="">

                        <label class="kca-field" for="password">
                            <span>Create Password</span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Create a secure password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <label class="kca-field" for="password_confirmation">
                            <span>Confirm Password</span>
                            <div>
                                <i class="mdi mdi-lock-check-outline"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Repeat password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <button class="kca-primary-button" type="submit">
                            <i class="mdi mdi-account-key-outline"></i>
                            <strong>Access Your Portal</strong>
                        </button>
                    </form>

                    <div class="kca-divider"><span>Already activated?</span></div>
                    <a class="kca-outline-button" href="{{ route('login', ['portal' => 'client']) }}">
                        <i class="mdi mdi-login"></i>
                        <strong>Sign In</strong>
                    </a>

                    <div class="kca-login-support">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>
                            Only emails already registered as firm clients can create portal access.
                            <a href="{{ route('help.client-guidelines') }}">User Guidelines</a>
                        </span>
                    </div>
                </div>
            </section>
        </section>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const emailInput = document.querySelector('[data-client-lookup-url]');
            const message = document.querySelector('[data-client-lookup-message]');
            const phoneInput = document.getElementById('phone');
            const clientNoInput = document.getElementById('client_no');

            if (! emailInput || ! message || ! phoneInput) {
                return;
            }

            let lookupTimer;
            let activeLookup;

            const setMessage = function (text, state) {
                message.textContent = text || '';
                message.dataset.state = state || '';
            };

            const lookupClient = async function () {
                const email = emailInput.value.trim();

                phoneInput.value = '';
                if (clientNoInput) {
                    clientNoInput.value = '';
                }

                if (! email || ! emailInput.checkValidity()) {
                    setMessage('', '');
                    return;
                }

                if (activeLookup) {
                    activeLookup.abort();
                }

                activeLookup = new AbortController();
                setMessage('Checking client record...', 'checking');

                try {
                    const url = new URL(emailInput.dataset.clientLookupUrl, window.location.origin);
                    url.searchParams.set('email', email);

                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                        },
                        signal: activeLookup.signal,
                    });
                    const payload = await response.json();

                    if (! response.ok || ! payload.exists) {
                        setMessage(payload.message || 'This email is not registered as an active client.', 'error');
                        return;
                    }

                    phoneInput.value = payload.phone || '';
                    phoneInput.readOnly = true;
                    if (clientNoInput) {
                        clientNoInput.value = payload.client_no || '';
                    }

                    setMessage(payload.message || 'Client record found.', payload.has_portal_account ? 'warning' : 'success');
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        setMessage('We could not check this email right now. You can still submit the form.', 'warning');
                    }
                }
            };

            emailInput.addEventListener('input', function () {
                window.clearTimeout(lookupTimer);
                lookupTimer = window.setTimeout(lookupClient, 500);
            });

            emailInput.addEventListener('blur', lookupClient);

            if (emailInput.value) {
                lookupClient();
            }
        });
    </script>
</x-guest-layout>
