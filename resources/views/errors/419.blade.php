@php
    $clientContext = request()->is('client*') || request()->boolean('client_portal_logout') || auth()->user()?->isClientAccount();
    $destination = auth()->check()
        ? route($clientContext ? 'client.dashboard' : 'dashboard')
        : route($clientContext ? 'client.login' : 'login');
@endphp
<x-guest-layout>
    <x-auth-dialog title="Your session needs refreshing" :kicker="$clientContext ? 'Client portal' : 'Secure access'">
        <p>Your session or form security token has expired. This can happen after inactivity or signing in or out in another tab.</p>
        <div class="kca-verification-status" data-state="info">Please open a fresh page and try again. Your last form has not been resubmitted.</div>
        @auth
            <form method="POST" action="{{ route('logout') }}" data-kfms-session-recovery-form>
                @csrf
                @if ($clientContext)
                    <input type="hidden" name="client_portal_logout" value="1">
                @endif
                <button class="kca-primary-button" type="submit">Try signing out again</button>
            </form>
        @endauth
        <a class="kca-primary-button" href="{{ $destination }}">{{ auth()->check() ? 'Return to dashboard' : 'Sign in again' }}</a>
        <p class="kca-modal-note">If you still need to verify your email, sign in with the password you created and choose Resend Verification Email.</p>
    </x-auth-dialog>
</x-guest-layout>
