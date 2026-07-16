<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\CompanySetting;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserGuideAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::query()->firstOrCreate(['id' => 1], CompanySetting::defaults());
    }

    public function test_staff_user_can_access_user_guide_without_module_permission(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('help.user-guide'))
            ->assertOk()
            ->assertSee('System User Guide')
            ->assertSee('Letters & Opinions', false);
    }

    public function test_client_user_can_access_client_portal_guide(): void
    {
        $client = Client::create([
            'client_no' => 'CL-HELP',
            'client_type' => 'individual',
            'name' => 'Guide Client',
            'email' => 'guide.client@example.test',
            'status' => 'active',
        ]);

        $user = User::factory()->create([
            'email' => 'guide.client@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);

        ClientPortalAccount::create([
            'client_id' => $client->id,
            'user_id' => $user->id,
            'registered_email' => $client->email,
            'verified_at' => now(),
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get(route('client.help'))
            ->assertOk()
            ->assertSee('Client Portal Guide')
            ->assertSee('Messages');
    }
}
