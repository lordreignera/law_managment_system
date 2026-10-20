@php
    $verificationUser = auth()->user();
    $isClient = $verificationUser?->isClientAccount();
    $isVerified = $verificationUser?->hasVerifiedEmail();
    $verificationExpired = $verificationExpired ?? false;
    $remaining = app(\App\Services\EmailVerificationService::class)->remaining($verificationUser);
    $deliveryFailed = session('status') === 'verification-delivery-failed';
    $sent = session('status') === 'verification-link-sent';
    $destination = route($isClient ? 'client.dashboard' : 'dashboard');
@endphp
<x-guest-layout>
    <x-auth-dialog :title="$isVerified ? 'Your email is verified' : ($verificationExpired ? 'Request a new verification link' : 'Verify your email address')" :kicker="$isClient ? 'Client portal' : 'Account verification'">
        @if ($isVerified)
            <p>Your email has already been verified. You can continue to your dashboard.</p>
            <a class="kca-primary-button" href="{{ $destination }}">Continue to dashboard</a>
        @else
            <p>{{ $verificationExpired ? 'This verification link has expired or is no longer valid. Request a fresh link below.' : 'Verify your registered email before entering your account.' }}</p>
            <div class="kca-verification-email">{{ $verificationUser?->email }}</div>
            <div class="kca-verification-status" data-verification-status data-state="{{ $deliveryFailed ? 'error' : ($sent ? 'success' : 'info') }}" role="status" aria-live="polite">
                @if ($deliveryFailed)
                    Your account is saved, but we could not send the email. Please try Resend Verification Email or contact the firm.
                @elseif ($sent)
                    A verification link has been sent. Check your inbox and spam folder.
                @elseif (session('status') === 'verification-wait')
                    Please wait a moment before requesting another email.
                @else
                    Haven't verified yet? You can request another email here.
                @endif
            </div>
            <form method="POST" action="{{ route('verification.send') }}" data-verification-resend data-retry-after="{{ $remaining }}">
                @csrf
                <button class="kca-primary-button" type="submit" data-resend-button>Resend Verification Email</button>
            </form>
            <a class="kca-outline-button" href="{{ $destination }}">I have verified my email</a>
            <a class="kca-modal-link" href="{{ route('verification.notice') }}" data-verification-reload hidden>Reload verification page</a>
            <p class="kca-modal-note">Links expire after {{ (int) config('auth.verification.expire', 60) }} minutes. If you return later, sign in with your password to see this prompt again.</p>
        @endif
        <div class="kca-modal-footer">
            <span>Need to change this email? Contact the firm.</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                @if ($isClient)
                    <input type="hidden" name="client_portal_logout" value="1">
                @endif
                <button type="submit" class="kca-modal-link">Sign Out</button>
            </form>
        </div>
    </x-auth-dialog>
    <script src="{{ asset('admin/assets/js/kfms-verification.js') }}?v={{ filemtime(public_path('admin/assets/js/kfms-verification.js')) }}"></script>
</x-guest-layout>
