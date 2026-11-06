<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_register_and_remove_a_phone_push_subscription(): void
    {
        config([
            'services.webpush.subject' => 'mailto:admin@example.test',
            'services.webpush.public_key' => 'test-public-key',
            'services.webpush.private_key' => 'test-private-key',
        ]);

        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/test-endpoint';

        $this->actingAs($user)
            ->postJson(route('push-subscriptions.store'), [
                'endpoint' => $endpoint,
                'keys' => [
                    'p256dh' => 'test-p256dh',
                    'auth' => 'test-auth',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Phone alerts are enabled on this device.');

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);

        $this->actingAs($user)
            ->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJsonPath('message', 'Phone alerts are disabled on this device.');

        $this->assertDatabaseMissing('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint_hash' => hash('sha256', $endpoint),
        ]);
    }

    public function test_push_subscriptions_reject_non_https_endpoints(): void
    {
        config([
            'services.webpush.subject' => 'mailto:admin@example.test',
            'services.webpush.public_key' => 'test-public-key',
            'services.webpush.private_key' => 'test-private-key',
        ]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('push-subscriptions.store'), [
                'endpoint' => 'http://example.test/push',
                'keys' => ['p256dh' => 'test-p256dh', 'auth' => 'test-auth'],
            ])
            ->assertStatus(422);
    }
}
