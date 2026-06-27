<?php

namespace Tests\Feature;

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

    public function test_approved_client_can_be_viewed_updated_and_given_new_engagement(): void
    {
        $role = Role::findOrCreate('Client Manager');
        $role->givePermissionTo(Permission::findOrCreate('manage clients'));
        $role->givePermissionTo(Permission::findOrCreate('manage matters'));

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
            ->get(route('clients.show', $client))
            ->assertOk()
            ->assertSee('Jane Client');

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
            ->post(route('clients.engagements.store', $client), [
                'title' => 'Shareholder agreement',
                'practice_area_id' => $practiceArea->id,
                'opened_on' => now()->toDateString(),
                'privacy_status' => 'public',
                'description' => 'Prepare and review shareholder agreement.',
                'partner_ids' => [$user->id],
            ])
            ->assertRedirect();

        $matter = $client->fresh()->matters()->first();

        $this->assertNotNull($matter);
        $this->assertSame('engagement_pending', $matter->status);
        $this->assertSame('Shareholder agreement', $matter->title);
        $this->assertNotNull($matter->engagement);
        $this->assertMatchesRegularExpression('/^EG\d{8}$/', $matter->engagement->engagement_no);
    }
}
