<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPortalVerificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private function client(): Client
    {
        return Client::create([
            'client_no' => 'CL-VERIFY', 'client_type' => 'individual',
            'name' => 'Verification Client', 'email' => 'registered.client@example.test',
            'phone' => '+256700111222', 'status' => 'active',
        ]);
    }

    private function payload(Client $client): array
    {
        return ['email' => $client->email,
            'password' => 'secure-password-123', 'password_confirmation' => 'secure-password-123'];
    }

    private function portalUser(bool $verified = false): User
    {
        $client = $this->client();
        $user = User::factory()->create([
            'email' => $client->email, 'account_type' => 'client',
            'email_verified_at' => $verified ? now() : null,
        ]);
        ClientPortalAccount::create([
            'client_id' => $client->id, 'user_id' => $user->id, 'registered_email' => $client->email,
        ]);

        return $user;
    }

    public function test_registration_sends_the_first_verification_email_and_blocks_portal_until_verified(): void
    {
        Notification::fake();
        $client = $this->client();
        $this->post(route('client.register.store'), $this->payload($client))
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-link-sent');
        $user = User::where('email', $client->email)->firstOrFail();
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->assertDatabaseHas('client_portal_accounts', ['client_id' => $client->id, 'user_id' => $user->id]);
        $this->get(route('client.dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('role="dialog"', false)
            ->assertSee($client->email)->assertSee('Resend Verification Email');
    }

    public function test_initial_mail_failure_preserves_account_and_offers_resend(): void
    {
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated mail outage'));
        $client = $this->client();
        $this->post(route('client.register.store'), $this->payload($client))
            ->assertRedirect(route('verification.notice'))->assertSessionHas('status', 'verification-delivery-failed');
        $this->assertDatabaseHas('client_portal_accounts', ['client_id' => $client->id]);
        $this->get(route('verification.notice'))->assertOk()->assertSee('Your account is saved, but we could not send the email.');
        Notification::fake();
        $this->postJson(route('verification.send'))->assertOk();
        Notification::assertSentTo(User::where('email', $client->email)->firstOrFail(), VerifyEmail::class);
    }

    public function test_failed_portal_insert_rolls_back_user_and_can_be_retried(): void
    {
        Notification::fake();
        $client = $this->client();
        $event = 'eloquent.creating: '.ClientPortalAccount::class;
        Event::listen($event, function () {
            throw new \RuntimeException('Simulated portal insert failure');
        });
        $this->withoutExceptionHandling();
        try {
            $this->post(route('client.register.store'), $this->payload($client));
            $this->fail('Expected insertion failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated portal insert failure', $exception->getMessage());
        } finally {
            Event::forget($event);
        }
        $this->assertDatabaseMissing('users', ['email' => $client->email]);
        $this->assertDatabaseMissing('client_portal_accounts', ['client_id' => $client->id]);
        Notification::assertNothingSent();
        $this->post(route('client.register.store'), $this->payload($client))->assertRedirect(route('verification.notice'));
    }

    public function test_unverified_client_cannot_change_email_to_another_mailbox(): void
    {
        Notification::fake();
        $user = $this->portalUser();
        $this->actingAs($user);
        Livewire::test(UpdateProfileInformationForm::class)
            ->set('state.email', 'another.mailbox@example.test')
            ->call('updateProfileInformation')->assertHasErrors('email');
        $this->assertSame('registered.client@example.test', $user->fresh()->email);
        $this->put(route('user-profile-information.update'), ['name' => $user->name, 'email' => 'another.mailbox@example.test'])
            ->assertSessionHasErrors('email', null, 'updateProfileInformation');
        Notification::assertNothingSent();
    }

    public function test_portal_rejects_account_with_mismatched_registered_email(): void
    {
        $user = $this->portalUser(true);
        $user->update(['email' => 'different.mailbox@example.test']);
        $this->actingAs($user)->get(route('client.dashboard'))->assertForbidden();
    }

    public function test_original_email_link_verifies_client_and_opens_own_dashboard(): void
    {
        Notification::fake();
        $user = $this->portalUser();
        Matter::create(['client_id' => $user->clientPortalAccount->client_id, 'reference_no' => 'MT-VERIFY',
            'title' => 'Verified client matter', 'status' => 'open']);
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->actingAs($user)->get($url)->assertRedirect();
        $this->actingAs($user->fresh())->get(route('client.dashboard'))->assertOk()->assertSee('Verified client matter')
            ->assertSee('data-client-menu-toggle', false)->assertSee('kfms-client-header-logout', false)->assertSee('Sign Out');
    }

    public function test_returning_unverified_client_is_prompted_to_resend_after_sign_in(): void
    {
        $user = $this->portalUser();
        $this->post(route('client.login.store'), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee($user->email)->assertSee('Resend Verification Email');
    }

    public function test_verification_link_is_preserved_when_client_has_to_sign_in_first(): void
    {
        $user = $this->portalUser();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $this->get($url)->assertRedirect(route('login'));
        $this->post(route('client.login.store'), ['email' => $user->email, 'password' => 'password'])->assertRedirect($url);
        $this->get($url)->assertRedirect();
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
    }

    public function test_expired_signed_link_shows_resend_modal_without_verifying_user(): void
    {
        $user = $this->portalUser();
        $url = URL::temporarySignedRoute('verification.verify', now()->subMinute(), ['id' => $user->id, 'hash' => sha1($user->email)]);
        $response = $this->actingAs($user)->get($url)->assertForbidden()->assertSee('Request a new verification link')
            ->assertSee('Resend Verification Email');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_resend_sends_once_and_returns_cooldown_for_repeated_clicks(): void
    {
        $this->freezeTime();
        Notification::fake();
        $user = $this->portalUser();
        $this->actingAs($user)->postJson(route('verification.send'))->assertOk()->assertJsonPath('retry_after', 60);
        $this->postJson(route('verification.send'))->assertStatus(429)->assertHeader('Retry-After', '60');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->travel(61)->seconds();
        $this->postJson(route('verification.send'))->assertOk();
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_resend_reports_mail_failure_and_can_retry(): void
    {
        $user = $this->portalUser();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated resend outage'));
        $this->actingAs($user)->postJson(route('verification.send'))->assertStatus(503)->assertJsonFragment([
            'message' => 'We could not send the verification email. Please try again shortly or contact the firm.',
        ]);
        Notification::fake();
        $this->postJson(route('verification.send'))->assertOk();
        Notification::assertSentTo($user, VerifyEmail::class);
    }

    public function test_resend_requires_authentication(): void
    {
        $this->postJson(route('verification.send'))->assertUnauthorized();
    }

    public function test_expired_form_has_clear_client_sign_in_recovery(): void
    {
        Route::middleware('web')->post('/client-portal/test-expired-form', function () {
            throw new TokenMismatchException;
        });
        $this->post('/client-portal/test-expired-form')->assertStatus(419)
            ->assertSee('Your session needs refreshing')->assertSee('Sign in again')
            ->assertSee('href="'.e(route('client.login')).'"', false);
    }
}
