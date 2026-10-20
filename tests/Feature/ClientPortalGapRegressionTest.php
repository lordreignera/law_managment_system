<?php

namespace Tests\Feature;

use App\Livewire\Profile\UpdateProfileInformationForm;
use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\EmailVerificationService;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

class ClientPortalGapRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
    }

    private function clientUser(): User
    {
        $client = Client::create([
            'client_no' => 'CL-PRIVATE', 'name' => 'Private Client',
            'email' => 'private.client@example.test', 'phone' => '+256700123456', 'status' => 'active',
        ]);
        $user = User::factory()->unverified()->create(['email' => $client->email, 'account_type' => 'client']);
        ClientPortalAccount::create(['client_id' => $client->id, 'user_id' => $user->id, 'registered_email' => $client->email]);

        return $user;
    }

    private function link(User $user, bool $expired = false): string
    {
        return URL::temporarySignedRoute('verification.verify', $expired ? now()->subMinute() : now()->addHour(),
            ['id' => $user->id, 'hash' => sha1($user->email)]);
    }

    public function test_lookup_and_registration_page_do_not_expose_full_phone_or_client_reference(): void
    {
        $user = $this->clientUser();
        $this->getJson(route('client.lookup', ['email' => $user->email]))->assertOk()
            ->assertJsonPath('phone_hint', '•••• 3456')->assertJsonMissingPath('phone')->assertJsonMissingPath('client_no')
            ->assertDontSee('+256700123456')->assertDontSee('CL-PRIVATE');
        $this->withSession(['_old_input' => ['phone' => '+256700123456']])->get(route('client.register'))
            ->assertOk()->assertDontSee('+256700123456')->assertDontSee('name="phone"', false)->assertDontSee('name="client_no"', false);
        $this->assertGuest();
    }

    public function test_short_phone_numbers_are_fully_hidden_and_missing_numbers_are_not_invented(): void
    {
        $user = $this->clientUser();
        $client = $user->clientPortalAccount->client;
        $client->update(['phone' => '1234']);
        $this->getJson(route('client.lookup', ['email' => $user->email]))->assertOk()
            ->assertJsonPath('phone_hint', '••••')->assertDontSee('1234');
        $client->update(['phone' => null]);
        $this->getJson(route('client.lookup', ['email' => $user->email]))->assertOk()->assertJsonPath('phone_hint', null);
    }

    public function test_other_account_can_deliberately_switch_and_resume_the_original_verification_link(): void
    {
        $clientUser = $this->clientUser();
        $staff = User::factory()->create(['email_verified_at' => now()]);
        StaffProfile::create(['user_id' => $staff->id, 'employment_status' => 'active']);
        $attendance = app(AttendanceService::class)->checkIn($staff, Request::create('/login', 'POST'));
        $url = $this->link($clientUser);
        $this->actingAs($staff)->get($url)->assertForbidden()->assertSee('Switch accounts to verify')
            ->assertSee('Sign out and switch accounts')->assertDontSee('Your email is verified')
            ->assertDontSee($clientUser->email);
        $this->assertAuthenticatedAs($staff);
        $this->post(route('verification.switch-account'), ['redirect' => 'https://untrusted.example/'])
            ->assertRedirect(route('client.login'));
        $this->assertGuest();
        $this->assertNotNull($attendance->fresh()->checked_out_at);
        $intended = session('url.intended');
        $this->assertStringStartsWith('/email/verify/'.$clientUser->id.'/', $intended);
        $this->assertStringNotContainsString('untrusted.example', $intended);
        $this->get(route('client.login'))->assertOk()->assertSee('Sign in with the account that received the verification email');
        $this->post(route('client.login.store'), ['email' => $clientUser->email, 'password' => 'password'])->assertRedirect($intended);
        $this->get($intended)->assertRedirect();
        $this->assertTrue($clientUser->fresh()->hasVerifiedEmail());
        $this->actingAs($clientUser->fresh())->get(route('client.dashboard'))->assertOk()->assertSee('+256700123456');
    }

    public function test_expired_link_under_another_account_prompts_switch_then_resend_for_the_correct_user(): void
    {
        $clientUser = $this->clientUser();
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($staff)->get($this->link($clientUser, true))->assertForbidden()
            ->assertSee('Switch accounts to verify')->assertDontSee('Your email is verified');
        $this->post(route('verification.switch-account'))->assertRedirect(route('client.login'));
        $intended = session('url.intended');
        $this->post(route('client.login.store'), ['email' => $clientUser->email, 'password' => 'password'])->assertRedirect($intended);
        $this->get($intended)->assertForbidden()->assertSee('Request a new verification link')->assertSee('Resend Verification Email');
        $this->assertFalse($clientUser->fresh()->hasVerifiedEmail());
    }

    public function test_invalid_signature_cannot_create_an_account_switch_destination(): void
    {
        $clientUser = $this->clientUser();
        $staff = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($staff)->get($this->link($clientUser).'&tampered=1')->assertForbidden()
            ->assertSee('This link is invalid.')->assertDontSee('Sign out and switch accounts');
        $this->assertNull(session('verification.pending'));
        $this->post(route('verification.switch-account'))->assertRedirect(route('verification.notice'));
        $this->assertAuthenticatedAs($staff);
        $this->assertFalse($clientUser->fresh()->hasVerifiedEmail());
    }

    public function test_switching_accounts_requires_authentication(): void
    {
        $this->postJson(route('verification.switch-account'))->assertUnauthorized();
    }

    public function test_validly_signed_link_with_stale_email_hash_offers_resend_without_verifying(): void
    {
        $user = $this->clientUser();
        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1('old-email@example.test')]);
        $this->actingAs($user)->get($url)->assertForbidden()->assertSee('Request a new verification link')->assertSee('Resend Verification Email');
        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }

    public function test_popup_and_profile_enforce_the_same_cooldown_in_both_directions(): void
    {
        Notification::fake();
        $user = $this->clientUser();
        $this->actingAs($user)->postJson(route('verification.send'))->assertOk();
        $profile = Livewire::test(UpdateProfileInformationForm::class)
            ->call('sendEmailVerification')->assertSet('verificationLinkSent', false)->assertSet('verificationRetryAfter', 60)
            ->call('sendEmailVerification');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->travel(61)->seconds();
        $profile->call('sendEmailVerification')->assertSet('verificationLinkSent', true);
        $this->postJson(route('verification.send'))->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 2);
    }

    public function test_resend_cooldown_is_preserved_after_starting_a_new_session(): void
    {
        Notification::fake();
        $user = $this->clientUser();
        $this->actingAs($user)->postJson(route('verification.send'))->assertOk();
        $this->flushSession();
        $this->actingAs($user)->postJson(route('verification.send'))->assertStatus(429);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_profile_reports_delivery_failure_and_allows_a_successful_retry(): void
    {
        $user = $this->clientUser();
        Notification::shouldReceive('send')->once()->andThrow(new \RuntimeException('Simulated delivery failure'));
        $this->actingAs($user);
        $profile = Livewire::test(UpdateProfileInformationForm::class)->call('sendEmailVerification')
            ->assertSet('verificationLinkSent', false)->assertSet('verificationState', 'error')->assertSet('verificationRetryAfter', 0)
            ->assertSee('We could not send the verification email.');
        Notification::fake();
        $profile->call('sendEmailVerification')->assertSet('verificationLinkSent', true)->assertSet('verificationState', 'success');
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->postJson(route('verification.send'))->assertStatus(429);
    }

    public function test_in_flight_send_prevents_a_second_send_through_the_profile(): void
    {
        Notification::fake();
        $user = $this->clientUser();
        $this->actingAs($user);
        $result = app(EmailVerificationService::class)->send($user, function () use ($user) {
            Livewire::test(UpdateProfileInformationForm::class)->call('sendEmailVerification')
                ->assertSet('verificationLinkSent', false)->assertSet('verificationRetryAfter', 1);
            $user->sendEmailVerificationNotification();
        });
        $this->assertSame('sent', $result['status']);
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
    }

    public function test_verified_users_do_not_receive_more_verification_emails(): void
    {
        Notification::fake();
        $user = $this->clientUser();
        $user->markEmailAsVerified();
        $this->actingAs($user)->postJson(route('verification.send'))->assertOk()->assertJsonPath('verified', true);
        Livewire::test(UpdateProfileInformationForm::class)->call('sendEmailVerification')->assertSet('verificationLinkSent', false);
        Notification::assertNothingSent();
    }

    public function test_switching_to_a_staff_account_also_resumes_the_verification_link(): void
    {
        $clientUser = $this->clientUser();
        $clientUser->markEmailAsVerified();
        $staff = User::factory()->unverified()->create();
        $this->actingAs($clientUser)->get($this->link($staff))->assertForbidden()->assertSee('Switch accounts to verify');
        $this->post(route('verification.switch-account'))->assertRedirect(route('login'));
        $intended = session('url.intended');
        $this->post(route('login'), ['email' => $staff->email, 'password' => 'password'])->assertRedirect($intended);
        $this->get($intended)->assertRedirect();
        $this->assertTrue($staff->fresh()->hasVerifiedEmail());
    }

    public function test_profile_email_change_uses_the_shared_delivery_feedback_and_cooldown(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user);
        Livewire::test(UpdateProfileInformationForm::class)->set('state.email', 'updated.staff@example.test')
            ->call('updateProfileInformation')->assertHasNoErrors()->assertSet('verificationLinkSent', true);
        $user->refresh();
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentToTimes($user, VerifyEmail::class, 1);
        $this->actingAs($user)->postJson(route('verification.send'))->assertStatus(429);
    }
}
