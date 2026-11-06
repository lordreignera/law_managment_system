<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsureSessionIsFresh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SessionRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_session_status_returns_a_fresh_token_without_caching(): void
    {
        $response = $this->getJson(route('session.status'));

        $response->assertOk()
            ->assertJsonPath('authenticated', false)
            ->assertJsonPath('user_id', null)
            ->assertJsonStructure(['csrf_token']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_session_status_identifies_only_the_current_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('session.status'))
            ->assertOk()
            ->assertJsonPath('authenticated', true)
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonStructure(['csrf_token']);
    }

    public function test_idle_session_is_invalidated_and_returns_structured_recovery(): void
    {
        config(['session.idle_timeout' => 1]);
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession([
                EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(2)->timestamp,
            ])
            ->getJson(route('session.status'));

        $response->assertStatus(401)
            ->assertJsonPath('authenticated', null)
            ->assertJsonPath('session_expired', true)
            ->assertJsonPath('reason', 'idle');
        $this->assertGuest();
    }

    public function test_active_session_activity_timestamp_is_refreshed(): void
    {
        config(['session.idle_timeout' => 30]);
        $user = User::factory()->create();
        $oldActivity = now()->subMinute()->timestamp;

        $this->actingAs($user)
            ->withSession([EnsureSessionIsFresh::LAST_ACTIVITY_KEY => $oldActivity])
            ->getJson(route('session.status'))
            ->assertOk();

        $this->assertGreaterThan(
            $oldActivity,
            (int) $this->app['session.store']->get(EnsureSessionIsFresh::LAST_ACTIVITY_KEY)
        );
        $this->assertAuthenticatedAs($user);
    }

    public function test_expired_form_returns_structured_json_recovery_instead_of_html(): void
    {
        Route::middleware('web')->post('/client-portal/test-expired-json', function () {
            throw new TokenMismatchException;
        });

        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->post('/client-portal/test-expired-json');

        // The route is registered by the test so this assertion exercises the
        // application's 419 exception renderer rather than a controller.
        $this->assertSame(419, $response->status());
        $response->assertJsonPath('session_expired', true)
            ->assertJsonPath('login_url', route('client.login'));
    }
}
