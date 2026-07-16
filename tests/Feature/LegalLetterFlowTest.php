<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\CompanySetting;
use App\Models\LegalLetter;
use App\Models\Matter;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\LetterheadSeeder;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LegalLetterFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        CompanySetting::query()->firstOrCreate(['id' => 1], CompanySetting::defaults());
        $this->seed([LetterheadSeeder::class, LetterTemplateSeeder::class]);
    }

    public function test_staff_can_create_sign_approve_send_and_share_letter_to_client_portal(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('signatures/staff.png', 'signature');

        $staff = $this->staffUser();
        $staff->forceFill(['signature_path' => 'signatures/staff.png'])->save();

        $client = Client::create([
            'client_no' => 'CL-001',
            'client_type' => 'individual',
            'name' => 'Demo Client',
            'email' => 'client@example.test',
            'phone' => '+256700000001',
            'status' => 'active',
        ]);

        $matter = Matter::create([
            'client_id' => $client->id,
            'title' => 'Contract Review',
            'reference_no' => 'MT-001',
            'opened_by' => $staff->id,
            'opened_on' => now()->toDateString(),
            'status' => 'open',
            'privacy_status' => 'public',
        ]);

        $this->actingAs($staff)
            ->get(route('letters.create', ['matter_id' => $matter->id, 'client_id' => $client->id]))
            ->assertOk()
            ->assertSee('Create Letter / Opinion')
            ->assertSee('Live Preview')
            ->assertSee('Draft')
            ->assertSee('Review')
            ->assertSee('Received Copy');

        $this->actingAs($staff)
            ->post(route('letters.store'), [
                'client_id' => $client->id,
                'matter_id' => $matter->id,
                'letter_type' => 'opinion',
                'recipient_name' => 'Demo Client',
                'recipient_email' => 'client@example.test',
                'subject' => 'RE: Contract Review',
                'body' => "Dear {recipient_name},\n\nThis is our opinion for {matter_number}.",
                'letter_date' => now()->toDateString(),
                'signature_mode' => 'profile',
            ])
            ->assertSessionHas('status', 'Letter drafted.');

        $letter = LegalLetter::firstOrFail();

        $this->assertSame('profile', $letter->signature_mode);
        $this->assertSame('signatures/staff.png', $letter->signature_path);
        $this->assertSame($staff->id, $letter->signed_by);

        $this->actingAs($staff)
            ->get(route('letters.show', $letter))
            ->assertOk()
            ->assertSee('Branded Preview')
            ->assertSee('Workflow Actions')
            ->assertSee('Draft')
            ->assertSee('Submit for Review');

        $this->actingAs($staff)
            ->patch(route('letters.submit', $letter))
            ->assertSessionHas('status', 'Letter submitted for review.');

        $this->actingAs($staff)
            ->patch(route('letters.approve', $letter), ['approval_notes' => 'Looks good.'])
            ->assertSessionHas('status', 'Letter approved.');

        $this->actingAs($staff)
            ->patch(route('letters.mark-sent', $letter), [
                'sent_at' => now()->format('Y-m-d H:i:s'),
                'client_visible' => '1',
            ])
            ->assertSessionHas('status', 'Letter marked as sent.');

        $letter->refresh();
        $this->assertSame('sent', $letter->status);
        $this->assertTrue($letter->client_visible);

        $portalUser = User::factory()->create([
            'name' => 'Demo Client Portal',
            'email' => 'client@example.test',
            'account_type' => 'client',
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
        ]);

        ClientPortalAccount::create([
            'user_id' => $portalUser->id,
            'client_id' => $client->id,
            'registered_email' => 'client@example.test',
            'verified_at' => now(),
        ]);

        $this->flushSession();
        Sanctum::actingAs($portalUser);

        $this->actingAs($portalUser)
            ->get(route('client.matters.show', $matter))
            ->assertOk()
            ->assertSee('Letters & Opinions', false)
            ->assertSee('RE: Contract Review');
    }

    private function staffUser(): User
    {
        $role = Role::findOrCreate('Advocate', 'web');

        foreach ([
            'letters.dashboard',
            'letters.index',
            'letters.create',
            'letters.store',
            'letters.show',
            'letters.edit',
            'letters.update',
            'letters.submit',
            'letters.approve',
            'letters.mark-sent',
            'letters.client-visibility',
            'letters.pdf',
        ] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole($role);

        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }
}
