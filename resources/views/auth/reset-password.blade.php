<x-guest-layout>
    <main class="jf-auth-stage jf-login-stage">
        <section class="jf-auth-card jf-login-card jf-login-card-centered jf-login-refined">
            <div class="jf-login-card-accent" aria-hidden="true"></div>
            <section class="jf-form-panel">
                <div class="jf-form-wrap">
                    <div class="jf-form-title">
                        <div class="jf-login-kicker">
                            <i class="mdi mdi-shield-key-outline"></i>
                            <span>Set new password</span>
                        </div>
                        <div class="jf-login-brandmark">
                            <x-company-logo mark-class="jf-login-logo-mark" image-class="jf-login-logo-image" />
                        </div>
                        <p>Create a new password for your JurisFlow account.</p>
                    </div>

                    @if ($errors->any())
                        <div class="jf-alert">{{ $errors->first() }}</div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <label class="jf-field" for="email">
                            <span>Email Address</span>
                            <div>
                                <i class="mdi mdi-email-outline"></i>
                                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" placeholder="Enter your email" required autofocus autocomplete="username">
                            </div>
                        </label>

                        <label class="jf-field" for="password">
                            <span>New Password</span>
                            <div>
                                <i class="mdi mdi-lock-outline"></i>
                                <input id="password" type="password" name="password" placeholder="Enter new password" required autocomplete="new-password">
                                <button class="jf-password-toggle" type="button" data-password-toggle="password" aria-label="Show password">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <label class="jf-field" for="password_confirmation">
                            <span>Confirm Password</span>
                            <div>
                                <i class="mdi mdi-lock-check-outline"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm new password" required autocomplete="new-password">
                                <button class="jf-password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password confirmation">
                                    <i class="mdi mdi-eye-outline"></i>
                                </button>
                            </div>
                        </label>

                        <button class="jf-primary-button" type="submit">
                            <i class="mdi mdi-check-circle-outline"></i>
                            Reset Password
                        </button>
                    </form>

                    <div class="jf-login-support">
                        <i class="mdi mdi-shield-check-outline"></i>
                        <span>Use a strong password that is not shared with other systems.</span>
                    </div>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
