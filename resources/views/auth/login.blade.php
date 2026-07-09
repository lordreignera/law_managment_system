<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined">
            <div class="kca-login-card-accent" aria-hidden="true"></div>
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-shield-lock-outline"></i>
                            <span>Secure legal workspace</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Sign in to continue to your workspace.</p>
                    </div>

                    @if ($errors->any())
                        <div class="kca-alert">{{ $errors->first() }}</div>
                    @endif

                    @session('status')
                        <div class="kca-alert">{{ $value }}</div>
                    @endsession

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <label class="kca-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <label class="kca-field" for="password">
                            <span>Password</span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <div class="kca-auth-row">
                            <label for="remember_me">
                                <input id="remember_me" type="checkbox" name="remember">
                                <span>Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Forgot Password?</a>
                            @endif
                        </div>

                        <button class="kca-primary-button" type="submit">
                            <i class="mdi mdi-login"></i>
                            Sign In
                        </button>
                    </form>

                    @if (Route::has('register'))
                        <div class="kca-divider"><span>New here?</span></div>
                        <a class="kca-outline-button" href="{{ route('register') }}">
                            <i class="mdi mdi-account-plus-outline"></i>
                            <strong>Request Access</strong>
                        </a>
                    @endif

                    <div class="kca-login-support">
                        <i class="mdi mdi-scale-balance"></i>
                        <span>Private access for approved users only.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
