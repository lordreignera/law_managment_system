<x-guest-layout>
    <main class="jf-auth-stage">
        <section class="jf-auth-card jf-login-card">
            <aside class="jf-brand-panel">
                <div class="jf-product-lockup">
                    <x-company-logo mark-class="jf-product-mark" image-class="jf-product-logo" />
                </div>

                <div class="jf-brand-copy">
                    <h1>{{ $companySetting->company_name }}</h1>
                    <h2>Access Review</h2>
                    <p>Your firm administrator controls workspace access for {{ $companySetting->short_name }}.</p>
                </div>

                <div class="jf-feature-row">
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

            <section class="jf-form-panel">
                <div class="jf-form-wrap">
                    <div class="jf-form-title">
                        @if ($companySetting->logo_url)
                            <img src="{{ $companySetting->logo_url }}" alt="{{ $companySetting->company_name }}">
                        @endif
                        <h2>Access Pending</h2>
                        <p>Your account is waiting for administrator approval before you can enter the workspace.</p>
                    </div>

                    <div class="jf-alert">
                        Please contact your firm administrator if this takes longer than expected.
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="jf-primary-button" type="submit">
                            <i class="mdi mdi-logout"></i>
                            Sign out
                        </button>
                    </form>
                </div>
            </section>
        </section>
    </main>
</x-guest-layout>
