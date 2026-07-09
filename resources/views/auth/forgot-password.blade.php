<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined">
            <div class="kca-login-card-accent" aria-hidden="true"></div>
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-lock-reset"></i>
                            <span>Password recovery</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Enter your approved account email and we will send you a secure reset link.</p>
                    </div>

                    @if ($errors->any())
                        <div class="kca-alert">{{ $errors->first() }}</div>
                    @endif

                    @session('status')
                        <div class="kca-alert">{{ $value }}</div>
                    @endsession

                    <form method="POST" action="{{ route('password.email') }}">
                        @csrf

                        <label class="kca-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Enter your account email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <button class="kca-primary-button" type="submit">
                            <i class="mdi mdi-email-send-outline"></i>
                            Send Reset Link
                        </button>
                    </form>

                    <div class="kca-divider"><span>Remembered it?</span></div>
                    <a class="kca-outline-button" href="{{ route('login') }}">
                        <i class="mdi mdi-arrow-left"></i>
                        <strong>Back to Sign In</strong>
                    </a>

                    <div class="kca-login-support">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>Reset links are sent from {{ config('mail.from.address') }}.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
