<?php

namespace App\Services;

use App\Models\PushSubscription as StoredPushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class PushNotificationService
{
    public function isConfigured(): bool
    {
        return filled(config('services.webpush.public_key'))
            && filled(config('services.webpush.private_key'))
            && filled(config('services.webpush.subject'));
    }

    public function sendTo(User $user, array $payload): void
    {
        if (! $this->isConfigured()) {
            return;
        }

        $subscriptions = $user->pushSubscriptions()->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => config('services.webpush.subject'),
                    'publicKey' => config('services.webpush.public_key'),
                    'privateKey' => config('services.webpush.private_key'),
                ],
            ], [
                'TTL' => 60,
                'urgency' => 'high',
            ]);

            $body = json_encode($payload, JSON_THROW_ON_ERROR);

            foreach ($subscriptions as $storedSubscription) {
                $subscription = Subscription::create([
                    'endpoint' => $storedSubscription->endpoint,
                    'publicKey' => $storedSubscription->public_key,
                    'authToken' => $storedSubscription->auth_token,
                    'contentEncoding' => $storedSubscription->content_encoding,
                ]);

                $report = $webPush->sendOneNotification($subscription, $body);

                if ($report->isSubscriptionExpired()) {
                    $storedSubscription->delete();
                } elseif (! $report->isSuccess()) {
                    Log::warning('KFMS push notification was not accepted.', [
                        'subscription_id' => $storedSubscription->id,
                        'reason' => $report->getReason(),
                    ]);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
