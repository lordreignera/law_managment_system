<x-guest-layout>
    <main class="jf-auth-stage jf-login-stage">
        <section class="jf-auth-card jf-login-card jf-login-card-centered jf-login-refined">
            <div class="jf-login-card-accent" aria-hidden="true"></div>
            <section class="jf-form-panel">
                <div class="jf-form-wrap">
                    <div class="jf-form-title">
                        <div class="jf-login-kicker">
                            <i class="mdi mdi-shield-lock-outline"></i>
                            <span>Secure legal workspace</span>
                        </div>
                        <div class="jf-login-brandmark">
                            <x-company-logo mark-class="jf-login-logo-mark" image-class="jf-login-logo-image" />
                        </div>
                        <p>Sign in to continue to your workspace.</p>
                    </div>

                    @if ($errors->any())
                        <div class="jf-alert">{{ $errors->first() }}</div>
                    @endif

                    @session('status')
                        <div class="jf-alert">{{ $value }}</div>
                    @endsession

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <label class="jf-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <label class="jf-field" for="password">
                            <span>Password</span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Enter your password" required autocomplete="current-password">
                                <button class="jf-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <div class="jf-auth-row">
                            <label for="remember_me">
                                <input id="remember_me" type="checkbox" name="remember">
                                <span>Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Forgot Password?</a>
                            @endif
                        </div>

                        <button class="jf-primary-button" type="submit">
                            <i class="mdi mdi-login"></i>
                            Sign In
                        </button>
                    </form>

                    @if (Route::has('register'))
                        <div class="jf-divider"><span>New here?</span></div>
                        <a class="jf-outline-button" href="{{ route('register') }}">
                            <i class="mdi mdi-account-plus-outline"></i>
                            <strong>Request Access</strong>
                        </a>
                    @endif

                    <div class="jf-login-support">
                        <i class="mdi mdi-scale-balance"></i>
                        <span>Private access for approved users only.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
