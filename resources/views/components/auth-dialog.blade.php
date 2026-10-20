@props(['title', 'kicker' => 'Secure access'])

<main class="kca-modal-stage">
    <section class="kca-verification-dialog" role="dialog" aria-modal="true" aria-labelledby="auth-dialog-title">
        <div class="kca-modal-logo">
            <x-company-logo mark-class="kca-login-logo-mark" image-class="kca-login-logo-image" />
        </div>
        <span class="kca-modal-kicker">{{ $kicker }}</span>
        <h1 id="auth-dialog-title" tabindex="-1">{{ $title }}</h1>
        {{ $slot }}
    </section>
</main>
