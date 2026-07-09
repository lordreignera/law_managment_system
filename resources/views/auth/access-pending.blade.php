<x-guest-layout>
    <main class="kca-auth-stage">
        <section class="kca-auth-card kca-login-card">
            <aside class="kca-brand-panel">
                <div class="kca-product-lockup">
                    <x-company-logo mark-class="kca-product-mark" image-class="kca-product-logo" />
                </div>

                <div class="kca-brand-copy">
                    <h1>{{ $companySetting->company_name }}</h1>
                    <h2>Access Review</h2>
                    <p>Your firm administrator controls workspace access for {{ $companySetting->short_name }}.</p>
                </div>

                <div class="kca-feature-row">
                    <div>
                        <i class="mdi mdi-shield-check-outline"></i>
                        <strong>Reviewed</strong>
                        <span>Staff access</span>
                    </div>
                    <div>
                        <i class="mdi mdi-account-clock-outline"></i>
                        <strong>Pending</strong>
                        <span>Approval</span>
                    </div>
                    <div>
                        <i class="mdi mdi-lock-outline"></i>
                        <strong>Protected</strong>
                        <span>Client data</span>
                    </div>
                </div>
            </aside>

            <section class="kca-form-panel">
                <div class="kca-form-wrap">
                    <div class="kca-form-title">
                        @if ($companySetting->logo_url)
                            <img src="{{ $companySetting->logo_url }}" alt="{{ $companySetting->company_name }}">
                        @endif
                        <h2>Access Pending</h2>
                        <p>Your account is waiting for administrator approval before you can enter the workspace.</p>
                    </div>

                    <div class="kca-alert">
                        Please contact your firm administrator if this takes longer than expected.
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="kca-primary-button" type="submit">
                            <i class="mdi mdi-logout"></i>
                            Sign out
                        </button>
                    </form>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
