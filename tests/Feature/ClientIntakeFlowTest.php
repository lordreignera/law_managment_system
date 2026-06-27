<?php

namespace Tests\Feature;

use App\Models\ClientIntake;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ClientIntakeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_intake_can_be_reviewed_and_converted_to_matter(): void
    {
        $role = Role::findOrCreate('Intake Manager');
        $role->givePermissionTo(Permission::findOrCreate('manage intakes'));
        $role->givePermissionTo(Permission::findOrCreate('manage matters'));
        $practiceArea = PracticeArea::create(['name' => 'Litigation', 'is_active' => true]);

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('intakes.store'), [
            'intake_no' => 'SHOULD-NOT-BE-USED',
            'client_type' => 'individual',
            'client_name' => 'Jane Client',
            'email' => 'jane@example.test',
            'phone' => '+256 700 000000',
            'legal_issue' => 'Employment dispute',
            'practice_area_id' => $practiceArea->id,
            'urgency' => 'urgent',
            'summary' => 'Client needs advice on wrongful dismissal.',
            'conflict_parties' => [
                ['name' => 'Acme Ltd', 'relationship' => 'Opponent'],
            ],
        ]);

        $intake = ClientIntake::first();

        $response->assertRedirect(route('intakes.show', $intake, absolute: false));
        $this->assertMatchesRegularExpression('/^CI\d{8}$/', $intake->intake_no);
        $this->assertNotSame('SHOULD-NOT-BE-USED', $intake->intake_no);
        $this->assertSame('pending', $intake->conflict_status);
        $this->assertCount(1, $intake->conflictParties);

        $this->actingAs($user)
            ->patch(route('intakes.conflict-review', $intake), [
                'conflict_status' => 'cleared',
                'conflict_notes' => 'No existing client or matter conflict found.',
            ])
            ->assertSessionHas('status', 'Conflict review saved.');

        $this->actingAs($user)
            ->post(route('intakes.convert-matter', $intake))
            ->assertRedirect(route('matters.index', absolute: false));

        $intake->refresh();

        $this->assertSame('engagement_pending', $intake->status);
        $this->assertNotNull($intake->client_id);
        $this->assertNotNull($intake->converted_matter_id);
        $this->assertMatchesRegularExpression('/^CL\d{8}$/', $intake->client->client_no);
        $this->assertMatchesRegularExpression('/^MT\d{8}$/', $intake->convertedMatter->reference_no);
        $this->assertMatchesRegularExpression('/^EG\d{8}$/', $intake->convertedMatter->engagement->engagement_no);
        $this->assertSame('engagement_pending', $intake->convertedMatter->status);
        $this->assertSame('Employment dispute', $intake->convertedMatter->title);

        $this->actingAs($user)
            ->patch(route('matters.engagement.update', $intake->convertedMatter), [
                'engagement_letter_sent_on' => now()->toDateString(),
                'fee_agreement_sent_on' => now()->toDateString(),
                'client_accepted_on' => now()->toDateString(),
                'retainer_required' => '1',
                'retainer_amount' => 500000,
                'engagement_notes' => 'Client accepted the engagement terms.',
            ])
            ->assertRedirect(route('matters.show', $intake->convertedMatter, absolute: false));

        $this->assertSame('open', $intake->convertedMatter->fresh()->status);
        $this->assertSame('accepted', $intake->convertedMatter->fresh()->engagement->status);
    }
}
