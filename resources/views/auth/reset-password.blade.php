<x-guest-layout>
    <main class="kca-auth-stage kca-login-stage">
        <section class="kca-auth-card kca-login-card kca-login-card-centered kca-login-refined">
            <div class="kca-login-card-accent" aria-hidden="true"></div>
            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        <div class="kca-login-kicker">
                            <i class="mdi mdi-shield-key-outline"></i>
                            <span>Set new password</span>
                        </div>
                        <div class="kca-login-brandmark">
                            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
                        </div>
                        <p>Create a new password for your firm account.</p>
                    </div>

                    @if ($errors->any())
                        <div class="kca-alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <label class="kca-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" placeholder="Enter your email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <label class="kca-field" for="password">
                            <span>New Password</span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Enter new password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <label class="kca-field" for="password_confirmation">
                            <span>Confirm Password</span>
                            <div>
                                <i class="mdi mdi-lock-check-outline"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm new password" required autocomplete="new-password">
                                <button class="kca-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <button class="kca-primary-button" type="submit">
                            <i class="mdi mdi-check-circle-outline"></i>
                            Reset Password
                        </button>
                    </form>

                    <div class="kca-login-support">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>Use a strong password that is not shared with other systems.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
