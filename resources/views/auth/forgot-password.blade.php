<x-guest-layout>
    <main class="jf-auth-stage jf-login-stage">
        <section class="jf-auth-card jf-login-card jf-login-card-centered jf-login-refined">
            <div class="jf-login-card-accent" aria-hidden="true"></div>
            <section class="jf-form-panel">
                <div class="jf-form-wrap">
                    <div class="jf-form-title">
                        <div class="jf-login-kicker">
                            <i class="mdi mdi-lock-reset"></i>
                            <span>Password recovery</span>
                        </div>
                        <div class="jf-login-brandmark">
                            <x-company-logo mark-class="jf-login-logo-mark" image-class="jf-login-logo-image" />
                        </div>
                        <p>Enter your approved account email and we will send you a secure reset link.</p>
                    </div>

                    @if ($errors->any())
                        <div class="jf-alert">{{ $errors->first() }}</div>
                    @endif

                    @session('status')
                        <div class="jf-alert">{{ $value }}</div>
                    @endsession

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <label class="jf-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your account email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <button class="jf-primary-button" type="submit">
                            <i class="mdi mdi-email-send-outline"></i>
                            Send Reset Link
                        </button>
                    </form>

                    <div class="jf-divider"><span>Remembered it?</span></div>
                    <a class="jf-outline-button" href="{{ route('login') }}">
                        <i class="mdi mdi-arrow-left"></i>
                        <strong>Back to Sign In</strong>
                    </a>

                    <div class="jf-login-support">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>Reset links are sent from {{ config('mail.from.address') }}.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
