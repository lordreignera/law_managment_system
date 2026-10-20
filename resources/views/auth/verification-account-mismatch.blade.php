<x-guest-layout>
    <x-auth-dialog :title="$canResumeVerification ? 'Switch accounts to verify' : 'Request a fresh verification link'" kicker="Account verification">
        <p>You are signed in as <strong>{{ auth()->user()->email }}</strong>. This link cannot verify the account currently signed in.</p>
        @if ($canResumeVerification)
            <div class="kca-verification-status" data-state="info">Sign out and sign in with the account that received this email. We will keep the verification link so you can continue.</div>
            <form method="POST" action="{{ route('verification.switch-account') }}">
                @csrf
                <button class="kca-primary-button" type="submit">Sign out and switch accounts</button>
            </form>
        @else
            <div class="kca-verification-status" data-state="error">This link is invalid. Sign in to the intended account and request a new verification email.</div>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                @if (auth()->user()->isClientAccount())
                    <input type="hidden" name="client_portal_logout" value="1">
                @endif
                <button class="kca-primary-button" type="submit">Sign Out</button>
            </form>
        @endif
        <a class="kca-outline-button" href="{{ route(auth()->user()->isClientAccount() ? 'client.dashboard' : 'dashboard') }}">Return to my account</a>
    </x-auth-dialog>
    <script src="{{ asset('admin/assets/js/kfms-verification.js') }}?v={{ filemtime(public_path('admin/assets/js/kfms-verification.js')) }}"></script>
</x-guest-layout>
