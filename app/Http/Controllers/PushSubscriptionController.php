<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function store(Request $request, PushNotificationService $pushNotifications): JsonResponse
    {
        abort_unless($pushNotifications->isConfigured(), 503, 'Phone alerts are not configured yet.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:2048'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $endpoint = $data['endpoint'];
        abort_unless($this->isSupportedPushEndpoint($endpoint), 422, 'This browser push provider is not supported.');

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $endpoint,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => 'aes128gcm',
            ],
        );

        return response()->json(['message' => 'Phone alerts are enabled on this device.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);

        $request->user()->pushSubscriptions()
            ->where('endpoint_hash', hash('sha256', $data['endpoint']))
            ->delete();

        return response()->json(['message' => 'Phone alerts are disabled on this device.']);
    }

    private function isSupportedPushEndpoint(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https' || empty($parts['host'])) {
            return false;
        }

        $host = strtolower(rtrim($parts['host'], '.'));
        $supportedHosts = [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'push.services.mozilla.com',
            'web.push.apple.com',
        ];

        return in_array($host, $supportedHosts, true)
            || str_ends_with($host, '.notify.windows.com');
    }
}
