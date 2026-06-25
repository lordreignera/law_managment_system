<x-guest-layout>
    <main class="kfms-auth-stage">
        <section class="kfms-auth-showcase">
            <div class="kfms-auth-blue">
                <x-company-logo mark-class="kfms-auth-logo-mark" image-class="kfms-auth-logo-image" />
                <div class="kfms-auth-welcome">
                    <h1>WELCOME</h1>
                    <h3>{{ strtoupper($companySetting->short_name) }}</h3>
                    <p>{{ $companySetting->login_heading }}</p>
                    <p>{{ $companySetting->login_subheading }}</p>
                </div>
                <span class="kfms-orb kfms-orb-large"></span>
                <span class="kfms-orb kfms-orb-small"></span>
            </div>

            <div class="kfms-auth-white">
                <div class="kfms-auth-form-card">
                    <div class="kfms-auth-title">
                        <h2>Staff Portal</h2>
                        <p>{{ $companySetting->company_name }}</p>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-light border text-dark py-2">
                            {{ $errors->first() }}
                        </div>
                    @endif

                    @session('status')
                        <div class="alert alert-light border text-dark py-2">
                            {{ $value }}
                        </div>
                    @endsession

                    <form method="POST" action="{{ route('login') }}">
                        @csrf

                        <label class="kfms-input-group" for="email">
                            <i class="mdi mdi-account"></i>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder="Email address" required autofocus autocomplete="username">
                        </label>

                        <label class="kfms-input-group" for="password">
                            <i class="mdi mdi-lock"></i>
                            <input id="password" type="password" name="password" placeholder="Password" required autocomplete="current-password">
                            <button class="kfms-show-password" type="button" data-password-toggle="password">SHOW</button>
                        </label>

                        <div class="kfms-auth-row">
                            <label class="form-check mb-0" for="remember_me">
                                <input id="remember_me" class="form-check-input" type="checkbox" name="remember">
                                <span class="form-check-label">Remember me</span>
                            </label>

                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}">Forgot Password?</a>
                            @endif
                        </div>

                        <button class="kfms-primary-button" type="submit">Access Dashboard</button>
                    </form>

                    @if (Route::has('register'))
                        <div class="kfms-divider"><span>Or</span></div>
                        <a class="kfms-outline-button" href="{{ route('register') }}">Request Access</a>
                    @endif
                </div>
            </div>
        </section>
    </main>
</x-guest-layout>
