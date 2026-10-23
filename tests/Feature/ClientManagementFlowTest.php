<?php

namespace Tests\Feature;

use App\Models\BillingType;
use App\Models\Client;
use App\Models\ContactPosition;
use App\Models\Country;
use App\Models\PracticeArea;
use App\Models\RelationshipType;
use App\Models\Salutation;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientManagementFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_client_creation_routes_direct_users_through_intake(): void
    {
        $role = Role::findOrCreate('Client Intake Redirect Test');
        foreach (['clients.create', 'clients.store', 'intakes.create'] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.create'))
            ->assertRedirect(route('intakes.create', absolute: false));
        $this->get(route('intakes.create'))
            ->assertOk()
            ->assertSee('Start with a client intake so conflicts are checked before a client record is approved.');

        $this->actingAs($user)
            ->post(route('clients.store'), ['name' => 'Unscreened Client'])
            ->assertRedirect(route('intakes.create', absolute: false));
        $this->get(route('intakes.create'))
            ->assertSee('Direct client creation is closed. Record the enquiry as an intake, complete conflict screening, and approve it to create the client record.');

        $this->assertDatabaseCount('clients', 0);
    }

    public function test_approved_client_can_be_viewed_updated_and_given_new_engagement(): void
    {
        $role = Role::findOrCreate('Client Manager');
        foreach ([
            'clients.index', 'clients.show', 'clients.details.edit', 'clients.details.update',
            'clients.adr.create', 'clients.adr.store', 'clients.adr.show',
            'clients.files.create', 'clients.files.store',
            'clients.matters.create', 'clients.matters.store',
            'matters.index', 'matters.show',
        ] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $position = ContactPosition::create(['name' => 'Director']);
        $country = Country::create(['name' => 'Uganda']);
        $salutation = Salutation::create(['name' => 'Ms']);
        $relationship = RelationshipType::create(['name' => 'Sibling']);
        $practiceArea = PracticeArea::create(['name' => 'Corporate']);
        $billingType = BillingType::create(['name' => 'Fixed Fee', 'code' => 'fixed_fee', 'is_active' => true]);

        $client = Client::create([
            'client_no' => 'CL26060001',
            'client_type' => 'individual',
            'name' => 'Jane Client',
            'first_name' => 'Jane',
            'last_name' => 'Client',
            'email' => 'jane@example.test',
            'phone' => '+256 700 000000',
            'address' => 'Kampala',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('kfms-client-action-menu', false)
            ->assertSee('client-actions-'.$client->id, false)
            ->assertSee('View Client Details')
            ->assertDontSee('Delete Client');

        $this->actingAs($user)
            ->get(route('clients.show', ['client' => $client, 'modal' => 1]))
            ->assertOk()
            ->assertSee('Additional client information')
            ->assertSee('View full profile')
            ->assertDontSee('Other contacts')
            ->assertDontSee('data-client-delete-open', false);

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(Role::findOrCreate('Super Admin', 'web'));
        StaffProfile::create([
            'user_id' => $superAdmin->id,
            'employment_status' => 'active',
        ]);

        $this->actingAs($superAdmin)
            ->get(route('clients.index'))
            ->assertOk()
            ->assertSee('Delete Client');

        $this->actingAs($superAdmin)
            ->get(route('clients.show', ['client' => $client, 'modal' => 1]))
            ->assertOk()
            ->assertSee('Delete client')
            ->assertSee('data-client-delete-open', false);

        $this->actingAs($user)
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Jane Client')
            ->assertSee('kfms-client-details', false)
            ->assertDontSee('kfms-detail-grid', false)
            ->assertSee('Add ADR')
            ->assertSee('Add File')
            ->assertSee('Add Matter')
            ->assertSee('id="clientAdrModal"', false)
            ->assertSee('id="clientFileModal"', false)
            ->assertSee('id="clientMatterModal"', false);

        $this->actingAs($user)
            ->put(route('clients.details.update', $client), [
                'client_type' => 'individual',
                'salutation_id' => $salutation->id,
                'first_name' => 'Jane',
                'middle_name' => 'A.',
                'last_name' => 'Client',
                'gender' => 'female',
                'position_id' => $position->id,
                'country_id' => $country->id,
                'client_in_charge_id' => $user->id,
                'nin_passport_no' => 'CM123456',
                'date_of_birth' => '1990-05-15',
                'email' => 'jane.updated@example.test',
                'phone' => '+256 701 000000',
                'address' => 'Kampala Road',
                'occupation' => 'Director',
                'tin' => '100200300',
                'status' => 'active',
                'add_next_of_kin' => '1',
                'next_of_kin' => [
                    'relationship_type_id' => $relationship->id,
                    'salutation_id' => $salutation->id,
                    'country_id' => $country->id,
                    'first_name' => 'Mary',
                    'last_name' => 'Relative',
                    'gender' => 'female',
                    'phone' => '+256 702 000000',
                    'email' => 'mary@example.test',
                    'nin_passport_no' => 'NK123456',
                    'date_of_birth' => '1988-02-10',
                    'address' => 'Ntinda',
                ],
            ])
            ->assertRedirect(route('clients.show', $client, absolute: false));

        $client = $client->fresh(['nextOfKin']);

        $this->assertSame('jane.updated@example.test', $client->email);
        $this->assertSame('Jane A. Client', $client->name);
        $this->assertSame('CM123456', $client->nin_passport_no);
        $this->assertNotNull($client->nextOfKin);
        $this->assertSame('Mary Relative', $client->nextOfKin->name);

        $this->actingAs($user)
            ->post(route('clients.adr.store', $client), [
                'title' => 'Shareholder agreement',
                'conflict_party_name' => 'Acme Holdings',
                'conflict_party_phone' => '+256 700 123456',
                'conflict_party_email' => 'legal@acme.example',
                'method' => 'email',
                'resolved_on' => now()->toDateString(),
                'response' => 'accepted_negotiation',
                'response_notes' => 'Conflict party agreed to resolve before filing.',
                '_client_profile_modal' => 'adr',
            ])
            ->assertRedirect(route('clients.show', $client, absolute: false));

        $adr = $client->fresh()->adrResolutions()->first();

        $this->assertNotNull($adr);
        $this->assertMatchesRegularExpression('/^ADR\d{8}$/', $adr->adr_no);
        $this->assertSame('Acme Holdings', $adr->conflict_party_name);
        $this->assertSame('+256 700 123456', $adr->conflict_party_phone);
        $this->assertSame('legal@acme.example', $adr->conflict_party_email);
        $this->assertSame('accepted_negotiation', $adr->response);
        $this->assertNull($client->fresh()->matters()->first());

        $this->actingAs($user)
            ->post(route('clients.files.store', $client), [
                'file_name' => 'Shareholder agreement',
                'adr_resolution_id' => $adr->id,
                'billing_type_id' => $billingType->id,
                'agreed_fee_amount' => 1500000,
                'client_accepted_on' => now()->toDateString(),
                'notes' => 'Client accepted the file terms.',
                '_client_profile_modal' => 'file',
            ])
            ->assertRedirect(route('clients.show', $client, absolute: false));

        $file = $client->fresh()->files()->first();

        $this->assertNotNull($file);
        $this->assertMatchesRegularExpression('/^FL\d{8}$/', $file->file_number);
        $this->assertSame('1500000.00', $file->agreed_fee_amount);
        $this->assertSame($adr->id, $file->adr_resolution_id);
        $this->assertNull($file->matter_id);

        $this->actingAs($user)
            ->post(route('clients.matters.store', $client), [
                'title' => 'Shareholder agreement',
                'practice_area_id' => $practiceArea->id,
                'opened_on' => now()->toDateString(),
                'privacy_status' => 'public',
                'description' => 'Prepare and review shareholder agreement.',
                'file_ids' => [$file->id],
                'partner_ids' => [$user->id],
                '_client_profile_modal' => 'matter',
            ])
            ->assertRedirect(route('clients.show', $client, absolute: false));

        $matter = $client->fresh()->matters()->first();

        $this->assertNotNull($matter);
        $this->assertSame('open', $matter->status);
        $this->assertSame('Shareholder agreement', $matter->title);
        $this->assertSame($matter->id, $file->fresh()->matter_id);
        $this->assertTrue($matter->files->contains($file));

        $this->actingAs($user)
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();

        $this->actingAs($superAdmin)
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index', absolute: false));

        $this->assertSoftDeleted('clients', ['id' => $client->id]);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $client->getMorphClass(),
            'auditable_id' => $client->id,
            'event' => 'deleted',
        ]);
    }
}
