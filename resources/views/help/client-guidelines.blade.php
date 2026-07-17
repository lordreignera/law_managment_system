<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined kca-guidelines-card">
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-book-open-page-variant"></i>
                            <span>Client Portal Guidelines</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Use this quick guide to access your private client portal.</p>
                    </div>

                    <div class="kca-guidelines-list">
                        <article>
                            <i class="mdi mdi-email-check-outline"></i>
                            <div>
                                <strong>Registered Email</strong>
                                <span>Use the email address already saved in the firm's client records. If the email is not found, contact the firm.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-account-key-outline"></i>
                            <div>
                                <strong>Access Your Portal</strong>
                                <span>Enter your registered email, confirm the phone number shown, create a password, and continue to your portal.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-folder-lock-outline"></i>
                            <div>
                                <strong>Your Matters Only</strong>
                                <span>You will only see your own matters, documents, letters, and updates shared with you by the firm.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-message-text-outline"></i>
                            <div>
                                <strong>Messages</strong>
                                <span>Use portal messages to communicate with the advocate or team assigned to your matter.</span>
                            </div>
                        </article>
                    </div>

                    <div class="kca-guidelines-actions">
                        <a class="kca-primary-button" href="{{ route('login', ['portal' => 'client']) }}">
                            <i class="mdi mdi-login"></i>
                            Client Sign In
                        </a>
                        <a class="kca-outline-button" href="{{ route('client.register') }}">
                            <i class="mdi mdi-account-key-outline"></i>
                            <strong>Access Your Portal</strong>
                        </a>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
