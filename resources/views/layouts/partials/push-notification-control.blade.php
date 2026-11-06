@auth
    <button
        type="button"
        class="kfms-push-control"
        data-kfms-push-notifications
        data-vapid-public-key="{{ config('services.webpush.public_key') }}"
        data-subscribed="{{ auth()->user()->pushSubscriptions()->exists() ? 'true' : 'false' }}"
        data-subscribe-url="{{ route('push-subscriptions.store') }}"
        data-unsubscribe-url="{{ route('push-subscriptions.destroy') }}"
        @disabled(! filled(config('services.webpush.public_key')))
        aria-label="{{ filled(config('services.webpush.public_key') ) ? 'Enable phone notifications' : 'Phone notifications need VAPID setup' }}"
        title="{{ filled(config('services.webpush.public_key')) ? 'Enable phone notifications' : 'Phone notifications need VAPID setup' }}">
        <i class="mdi mdi-bell-ring-outline" aria-hidden="true"></i>
        <span data-kfms-push-label>{{ filled(config('services.webpush.public_key')) ? (auth()->user()->pushSubscriptions()->exists() ? 'Phone alerts on' : 'Enable phone alerts') : 'Alerts setup needed' }}</span>
    </button>
    <span class="kfms-push-status" data-kfms-push-status aria-live="polite"></span>
@endauth
