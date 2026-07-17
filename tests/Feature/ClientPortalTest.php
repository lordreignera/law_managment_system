<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\Conversation;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\MonthlyReferenceNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_portal_welcome_page_can_be_rendered(): void
    {
        $this->get(route('client.portal'))
            ->assertOk()
            ->assertSee('Client Portal')
            ->assertSee('Create Portal Account')
            ->assertSee('Sign In');
    }

    public function test_client_portal_login_context_hides_staff_access_request(): void
    {
        $this->get(route('login', ['portal' => 'client']))
            ->assertOk()
            ->assertSee('Private client portal')
            ->assertSee('Access Your Portal')
            ->assertDontSee('Request Access');
    }

    public function test_authenticated_client_opening_portal_goes_to_dashboard(): void
    {
        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Portal Redirect Client',
            'email' => 'portal.redirect@example.test',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'email' => 'portal.redirect@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);
        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $user->email,
        ]);

        $this->actingAs($user)
            ->get(route('client.portal'))
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_only_existing_client_email_can_register_for_portal(): void
    {
        $this->post(route('client.register.store'), [
            'email' => 'unknown@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');

        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Portal Client',
            'email' => 'client@example.test',
            'phone' => '+256 700 111222',
            'status' => 'active',
        ]);

        $this->post(route('client.register.store'), [
            'email' => 'client@example.test',
            'phone' => '+256 700 111222',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::where('email', 'client@example.test')->first();

        $this->assertNotNull($user);
        $this->assertSame('client', $user->account_type);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertDatabaseHas('client_portal_accounts', [
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_phone' => '+256 700 111222',
        ]);
    }

    public function test_client_portal_account_cannot_be_duplicated(): void
    {
        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Existing Portal Client',
            'email' => 'existing@example.test',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'email' => 'existing@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);
        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $user->email,
        ]);

        $this->post(route('client.register.store'), [
            'email' => 'existing@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('email');
    }

    public function test_client_lookup_returns_registered_phone_for_active_client(): void
    {
        Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Lookup Client',
            'email' => 'lookup@example.test',
            'phone' => '+256 700 999111',
            'status' => 'active',
        ]);

        $this->getJson(route('client.lookup', ['email' => 'lookup@example.test']))
            ->assertOk()
            ->assertJson([
                'exists' => true,
                'phone' => '+256 700 999111',
                'has_portal_account' => false,
            ]);
    }

    public function test_client_lookup_rejects_unknown_email(): void
    {
        $this->getJson(route('client.lookup', ['email' => 'unknown@example.test']))
            ->assertNotFound()
            ->assertJson([
                'exists' => false,
            ]);
    }

    public function test_matched_client_can_access_dashboard_after_portal_registration(): void
    {
        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Portal Registration Client',
            'email' => 'portal.registration@example.test',
            'status' => 'active',
        ]);

        $this->post(route('client.register.store'), [
            'email' => 'portal.registration@example.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('client.dashboard'));

        $user = User::where('email', 'portal.registration@example.test')->first();
        $portalAccount = ClientPortalAccount::where('client_id', $client->id)->first();

        $this->assertNotNull($user);
        $this->assertNotNull($portalAccount);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($portalAccount->verified_at);
    }

    public function test_existing_client_account_is_verified_and_sent_to_dashboard_on_login(): void
    {
        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Login Client',
            'email' => 'login.client@example.test',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'email' => 'login.client@example.test',
            'account_type' => 'client',
            'email_verified_at' => null,
        ]);
        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $user->email,
        ]);

        $this->post('/login', [
            'email' => 'login.client@example.test',
            'password' => 'password',
        ])->assertRedirect(route('client.dashboard'));

        $user->refresh();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotNull($user->clientPortalAccount->fresh()->verified_at);
    }

    public function test_client_portal_logout_returns_to_client_welcome_page(): void
    {
        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Logout Client',
            'email' => 'logout.client@example.test',
            'status' => 'active',
        ]);
        $user = User::factory()->create([
            'email' => 'logout.client@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);
        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $user->email,
        ]);

        $this->actingAs($user)->post(route('logout'), [
            'client_portal_logout' => '1',
        ])->assertRedirect(route('client.portal'));

        $this->assertGuest();
    }

    public function test_client_only_sees_own_matters_and_can_message_assigned_advocate(): void
    {
        Role::firstOrCreate(['name' => 'Client', 'guard_name' => 'web']);

        $client = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Matter Client',
            'email' => 'matter.client@example.test',
            'status' => 'active',
        ]);
        $otherClient = Client::create([
            'client_no' => MonthlyReferenceNumber::make(Client::class, 'client_no', 'CL'),
            'client_type' => 'individual',
            'name' => 'Other Client',
            'email' => 'other.client@example.test',
            'status' => 'active',
        ]);
        $portalUser = User::factory()->create([
            'email' => 'matter.client@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);
        $portalUser->assignRole('Client');
        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $portalUser->id,
            'registered_email' => $portalUser->email,
        ]);

        $advocate = User::factory()->create(['email_verified_at' => now()]);
        StaffProfile::create([
            'user_id' => $advocate->id,
            'employment_status' => 'active',
            'job_title' => 'Advocate',
        ]);

        $practiceArea = PracticeArea::create(['name' => 'Litigation', 'code' => 'PA-001']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'practice_area_id' => $practiceArea->id,
            'reference_no' => 'MT-001',
            'title' => 'Client Visible Matter',
            'status' => 'open',
            'description' => 'Public matter summary.',
        ]);
        $matter->assignments()->create([
            'user_id' => $advocate->id,
            'assignment_role' => 'advocate',
            'is_lead' => true,
        ]);
        $otherMatter = Matter::create([
            'client_id' => $otherClient->id,
            'reference_no' => 'MT-002',
            'title' => 'Other Client Matter',
            'status' => 'open',
        ]);

        $this->actingAs($portalUser)->get(route('client.dashboard'))
            ->assertOk()
            ->assertSee('Client Visible Matter')
            ->assertDontSee('Other Client Matter');

        $this->actingAs($portalUser)->get(route('client.matters.show', $otherMatter))
            ->assertForbidden();

        $this->actingAs($portalUser)->post(route('client.matters.messages.store', $matter), [
            'body' => 'Hello counsel, please update me.',
        ])->assertRedirect(route('client.matters.show', $matter));

        $conversation = Conversation::where('matter_id', $matter->id)->first();

        $this->assertNotNull($conversation);
        $this->assertSame('client_matter', $conversation->audience_type);
        $this->assertTrue($conversation->participants()->where('user_id', $portalUser->id)->exists());
        $this->assertTrue($conversation->participants()->where('user_id', $advocate->id)->exists());
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'sender_id' => $portalUser->id,
            'body' => 'Hello counsel, please update me.',
        ]);

        $this->actingAs($portalUser)->get(route('client.messages.index'))
            ->assertOk()
            ->assertSee('Client Visible Matter')
            ->assertSee('Hello counsel, please update me.')
            ->assertDontSee('Other Client Matter');

        $this->actingAs($portalUser)->get(route('client.messages.show', $conversation))
            ->assertOk()
            ->assertSee('Message')
            ->assertSee('Client Visible Matter')
            ->assertSee('Lead Advocate');
    }
}
