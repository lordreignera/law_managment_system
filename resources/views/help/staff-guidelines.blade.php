<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined kca-guidelines-card">
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-book-open-page-variant"></i>
                            <span>Staff Guidelines</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Use this quick guide before signing in to the firm workspace.</p>
                    </div>

                    <div class="kca-guidelines-list">
                        <article>
                            <i class="mdi mdi-account-plus-outline"></i>
                            <div>
                                <strong>Request Access</strong>
                                <span>New staff members request access from the staff sign-in page using their official email and work details.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-account-check-outline"></i>
                            <div>
                                <strong>Approval</strong>
                                <span>An authorised administrator reviews the request, approves the account, and assigns the right role and permissions.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-lock-reset"></i>
                            <div>
                                <strong>Password Help</strong>
                                <span>Use Forgot Password on the sign-in page to receive a reset link through your registered email address.</span>
                            </div>
                        </article>
                        <article>
                            <i class="mdi mdi-view-dashboard-outline"></i>
                            <div>
                                <strong>After Login</strong>
                                <span>Your dashboard and sidebar will show only the modules available to your role.</span>
                            </div>
                        </article>
                    </div>

                    <div class="kca-guidelines-actions">
                        <a class="kca-primary-button" href="{{ route('login') }}">
                            <i class="mdi mdi-login"></i>
                            Staff Sign In
                        </a>
                        <a class="kca-outline-button" href="{{ route('register') }}">
                            <i class="mdi mdi-account-plus-outline"></i>
                            <strong>Request Access</strong>
                        </a>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
