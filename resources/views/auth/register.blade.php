<x-guest-layout>
    <main class="kfms-auth-stage">
        <section class="kfms-auth-showcase kfms-register-showcase">
            <div class="kfms-auth-blue">
                <x-company-logo mark-class="kfms-auth-logo-mark" image-class="kfms-auth-logo-image" />
                <div class="kfms-auth-welcome">
                    <h1>JOIN US</h1>
                    <h3>{{ strtoupper($companySetting->short_name) }}</h3>
                    <p>Create access for staff who will manage matters, recoveries, land titles, finance, and administration.</p>
                </div>
                <span class="kfms-orb kfms-orb-large"></span>
                <span class="kfms-orb kfms-orb-small"></span>
            </div>

            <div class="kfms-auth-white">
                <div class="kfms-auth-form-card kfms-register-card">
                    <div class="kfms-auth-title">
                        <h2>Request Access</h2>
                        <p>{{ $companySetting->company_name }}</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-light border text-dark py-2">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('register') }}">
                        @csrf

                        <div class="kfms-register-grid">
                            <label class="kfms-input-group" for="name">
                                <i class="mdi mdi-account"></i>
                                <input id="name" type="text" name="name" value="{{ old('name') }}" placeholder="Full name" required autofocus autocomplete="name">
                            </label>

                            <label class="kfms-input-group" for="email">
                                <i class="mdi mdi-email"></i>
                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Email address" required autocomplete="username">
                            </label>

                            <label class="kfms-input-group" for="password">
                                <i class="mdi mdi-lock"></i>
                                <input id="password" type="password" name="password" placeholder="Password" required autocomplete="new-password">
                            </label>

                            <label class="kfms-input-group" for="password_confirmation">
                                <i class="mdi mdi-lock-check"></i>
                                <input id="password_confirmation" type="password" name="password_confirmation" placeholder="Confirm password" required autocomplete="new-password">
                            </label>
                        </div>

                        @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
                            <div class="form-check kfms-terms">
                                <input class="form-check-input" type="checkbox" name="terms" id="terms" required>
                                <label class="form-check-label" for="terms">
                                    {!! __('I agree to the :terms_of_service and :privacy_policy', [
                                        'terms_of_service' => '<a target="_blank" href="'.route('terms.show').'">'.__('Terms of Service').'</a>',
                                        'privacy_policy' => '<a target="_blank" href="'.route('policy.show').'">'.__('Privacy Policy').'</a>',
                                    ]) !!}
                                </label>
                            </div>
                        @endif

                        <button class="kfms-primary-button" type="submit">Create Access</button>
                    </form>

                    <p class="kfms-auth-switch">
                        Already have an account?
                        <a href="{{ route('login') }}">Sign in</a>
                    </p>
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
