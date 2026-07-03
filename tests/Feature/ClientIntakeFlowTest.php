<?php

namespace Tests\Feature;

use App\Models\AdrResolution;
use App\Models\BillingType;
use App\Models\ClientIntake;
use App\Models\File;
use App\Models\Matter;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_intake_can_be_saved_reviewed_and_approved_before_engagement(): void
    {
        $role = Role::findOrCreate('Intake Manager');
        foreach ([
            'intakes.index', 'intakes.create', 'intakes.store', 'intakes.show',
            'intakes.review',
            'clients.index', 'clients.show', 'clients.adr.create', 'clients.adr.store',
            'clients.files.create', 'clients.files.store', 'clients.adr.show',
            'clients.matters.create', 'clients.matters.store',
            'matters.index', 'matters.show',
        ] as $permissionName) {
            $role->givePermissionTo(Permission::findOrCreate($permissionName));
        }
        $practiceArea = PracticeArea::create(['name' => 'Litigation', 'is_active' => true]);
        $billingType = BillingType::create(['name' => 'Retainer', 'code' => 'retainer', 'is_active' => true]);
        Storage::fake('local');

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
            'referral_source' => 'email',
            'referral_name' => 'John Referrer',
            'referral_contact' => 'john.referrer@example.test',
            'summary' => 'Client needs advice on wrongful dismissal.',
            'conflict_parties' => [
                ['name' => 'Acme Ltd', 'relationship' => 'Opponent', 'contact' => '+256 711 000000'],
            ],
        ]);

        $intake = ClientIntake::first();

        $response->assertRedirect(route('intakes.index', absolute: false));
        $this->assertMatchesRegularExpression('/^CI\d{8}$/', $intake->intake_no);
        $this->assertNotSame('SHOULD-NOT-BE-USED', $intake->intake_no);
        $this->assertSame('pending_review', $intake->status);
        $this->assertSame('pending', $intake->review_decision);
        $this->assertNull($intake->client_id);
        $this->assertSame('John Referrer', $intake->referral_name);
        $this->assertSame('john.referrer@example.test', $intake->referral_contact);
        $this->assertCount(1, $intake->conflictParties);
        $this->assertSame('+256 711 000000', $intake->conflictParties->first()->contact);

        $this->actingAs($user)
            ->patch(route('intakes.review', $intake), [
                'review_decision' => 'approved',
                'review_notes' => 'No existing client or matter conflict found.',
            ])
            ->assertRedirect(route('intakes.index', absolute: false))
            ->assertSessionHas('status', 'Client intake review saved.');

        $intake->refresh();

        $this->assertSame('approved', $intake->status);
        $this->assertSame('approved', $intake->review_decision);
        $this->assertSame('No existing client or matter conflict found.', $intake->review_notes);
        $this->assertNotNull($intake->client_id);
        $this->assertMatchesRegularExpression('/^CL\d{8}$/', $intake->client->client_no);
        $this->assertSame('Jane Client', $intake->client->name);

        $conflictParty = $intake->conflictParties()->first();

        $this->actingAs($user)
            ->post(route('clients.adr.store', $intake->client), [
                'title' => 'Employment dispute',
                'intake_conflict_party_id' => $conflictParty->id,
                'method' => 'call',
                'resolved_on' => now()->toDateString(),
                'response' => 'court_required',
                'response_notes' => 'ADR failed; court action required.',
            ])
            ->assertSessionHas('status');

        $adr = AdrResolution::first();

        $this->assertNotNull($adr);
        $this->assertMatchesRegularExpression('/^ADR\d{8}$/', $adr->adr_no);
        $this->assertSame($conflictParty->id, $adr->intake_conflict_party_id);
        $this->assertSame('Acme Ltd', $adr->conflict_party_name);
        $this->assertSame('court_required', $adr->response);
        $this->assertNull(Matter::first());

        $this->actingAs($user)
            ->post(route('clients.files.store', $intake->client), [
                'file_name' => 'Employment dispute',
                'adr_resolution_id' => $adr->id,
                'billing_type_id' => $billingType->id,
                'agreed_fee_amount' => 2500000,
                'engagement_letter_sent_on' => now()->toDateString(),
                'engagement_letter' => UploadedFile::fake()->create('engagement-letter.pdf', 100, 'application/pdf'),
                'fee_agreement_sent_on' => now()->toDateString(),
                'fee_agreement' => UploadedFile::fake()->create('fee-agreement.pdf', 100, 'application/pdf'),
                'client_accepted_on' => now()->toDateString(),
                'retainer_required' => '1',
                'retainer_amount' => 500000,
                'retainer_payment_source' => 'bank',
                'notes' => 'Client accepted the file terms.',
            ])
            ->assertRedirect(route('clients.show', $intake->client, absolute: false));

        $file = File::first();

        $this->assertNotNull($file);
        $this->assertMatchesRegularExpression('/^FL\d{8}$/', $file->file_number);
        $this->assertSame('2500000.00', $file->agreed_fee_amount);
        $this->assertSame('bank', $file->retainer_payment_source);
        $this->assertSame($adr->id, $file->adr_resolution_id);
        $this->assertCount(2, $file->attachments);
        $this->assertNull($file->matter_id);

        $this->actingAs($user)
            ->post(route('clients.matters.store', $intake->client), [
                'title' => 'Employment dispute',
                'practice_area_id' => $practiceArea->id,
                'opened_on' => now()->toDateString(),
                'privacy_status' => 'public',
                'description' => 'Prepare matter for wrongful dismissal litigation.',
                'partner_ids' => [$user->id],
            ])
            ->assertRedirect();

        $matter = Matter::first();

        $this->assertNotNull($matter);
        $this->assertMatchesRegularExpression('/^MT\d{8}$/', $matter->reference_no);
        $this->assertSame('open', $matter->fresh()->status);
        $this->assertSame($matter->id, $file->fresh()->matter_id);
    }
}
