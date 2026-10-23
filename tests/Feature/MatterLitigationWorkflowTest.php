<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Client;
use App\Models\CourtEvent;
use App\Models\CourtEventReminderLog;
use App\Models\DocumentBundle;
use App\Models\File;
use App\Models\Matter;
use App\Models\MatterAssignment;
use App\Models\PracticeArea;
use App\Models\StaffProfile;
use App\Models\User;
use App\Notifications\CourtEventDeadlineReminder;
use App\Notifications\CourtEventEscalation;
use App\Notifications\CourtEventReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MatterLitigationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions = [], string $name = 'Staff User', ?int $branchId = null): User
    {
        $role = Role::findOrCreate($name.' Role');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create([
            'name' => $name,
            'branch_id' => $branchId,
        ]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
            'phone' => '+256700000001',
        ]);

        return $user;
    }

    private function client(string $name = 'Workflow Client'): Client
    {
        return Client::create([
            'client_no' => 'CL'.str_pad((string) (Client::count() + 1), 8, '0', STR_PAD_LEFT),
            'client_type' => 'individual',
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function matter(Client $client, User $owner, array $attributes = []): Matter
    {
        return Matter::create(array_merge([
            'client_id' => $client->id,
            'opened_by' => $owner->id,
            'reference_no' => 'MT'.str_pad((string) (Matter::count() + 1), 8, '0', STR_PAD_LEFT),
            'title' => 'Workflow Matter',
            'status' => 'open',
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Internal instructions that must remain private.',
            'client_summary' => 'Approved progress summary for the client.',
        ], $attributes));
    }

    public function test_one_client_can_have_multiple_matters_with_deliberate_file_selection(): void
    {
        $user = $this->activeUser(['clients.matters.store', 'matters.update'], 'Matter Operator');
        $practiceArea = PracticeArea::create(['name' => 'Commercial', 'code' => 'COMM']);
        $client = $this->client();
        $fileA = File::create(['client_id' => $client->id, 'file_number' => 'FL00000001', 'file_name' => 'Agreement']);
        $fileB = File::create(['client_id' => $client->id, 'file_number' => 'FL00000002', 'file_name' => 'Dispute']);

        $payload = [
            'practice_area_id' => $practiceArea->id,
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Internal instructions.',
            'partner_ids' => [$user->id],
        ];

        $this->actingAs($user)->post(route('clients.matters.store', $client), $payload + [
            'title' => 'Agreement Matter',
            'file_ids' => [$fileA->id],
        ])->assertRedirect();

        $this->actingAs($user)->post(route('clients.matters.store', $client), $payload + [
            'title' => 'Dispute Matter',
            'file_ids' => [$fileB->id],
        ])->assertRedirect();

        $this->assertSame(2, $client->fresh()->matters()->count());
        $this->assertSame($client->matters()->where('title', 'Agreement Matter')->value('id'), $fileA->fresh()->matter_id);
        $this->assertSame($client->matters()->where('title', 'Dispute Matter')->value('id'), $fileB->fresh()->matter_id);

        $agreement = $client->fresh()->matters()->where('title', 'Agreement Matter')->firstOrFail();
        $this->actingAs($user)->put(route('matters.update', $agreement), [
            'title' => $agreement->title,
            'practice_area_id' => $practiceArea->id,
            'opened_on' => $agreement->opened_on->toDateString(),
            'status' => 'open',
            'privacy_status' => 'public',
            'description' => 'Updated internal instructions.',
            'file_ids' => [],
            'partner_ids' => [$user->id],
        ])->assertRedirect();
        $this->assertNull($fileA->fresh()->matter_id);
    }

    public function test_new_matter_accepts_uploaded_documents_but_not_closure_details(): void
    {
        Storage::fake('local');
        $user = $this->activeUser(['matters.create', 'matters.store'], 'Matter Creator');
        $client = $this->client('Document Upload Client');
        $practiceArea = PracticeArea::create(['name' => 'Document Practice', 'code' => 'DOCS']);

        $this->actingAs($user)
            ->get(route('matters.create'))
            ->assertOk()
            ->assertSee('Upload Documents')
            ->assertSee('name="documents[]"', false)
            ->assertDontSee('Date Closed');

        $basePayload = [
            'title' => 'New document matter',
            'client_id' => $client->id,
            'practice_area_id' => $practiceArea->id,
            'opened_on' => now()->toDateString(),
            'privacy_status' => 'public',
            'description' => 'Initial matter instructions.',
            'partner_ids' => [$user->id],
        ];

        $this->actingAs($user)
            ->post(route('matters.store'), $basePayload + [
                'status' => 'closed',
                'closed_on' => now()->toDateString(),
                'documents' => [UploadedFile::fake()->create('instruction.pdf', 10, 'application/pdf')],
            ])
            ->assertRedirect(route('matters.index'));

        $matter = Matter::firstOrFail();
        $attachment = $matter->attachments()->firstOrFail();

        $this->assertNull($matter->closed_on);
        $this->assertSame('open', $matter->status);
        $this->assertSame('instruction.pdf', $attachment->original_name);
        $this->assertSame('matter-document', $attachment->category);
        $this->assertTrue($attachment->is_current);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => $attachment->getMorphClass(),
            'auditable_id' => $attachment->id,
            'event' => 'uploaded',
        ]);
    }

    public function test_client_portal_shows_only_the_client_visible_summary(): void
    {
        Role::findOrCreate('Client', 'web');
        $owner = $this->activeUser([], 'Portal Matter Owner');
        $client = $this->client('Portal Summary Client');
        $matter = $this->matter($client, $owner, [
            'description' => 'Confidential internal litigation strategy.',
            'client_summary' => 'Your matter is awaiting the next court date.',
        ]);
        $portalUser = User::factory()->create([
            'email' => 'portal-summary@example.test',
            'account_type' => 'client',
            'email_verified_at' => now(),
        ]);
        $portalUser->assignRole('Client');
        $client->portalAccount()->create([
            'user_id' => $portalUser->id,
            'registered_email' => $portalUser->email,
        ]);

        $this->actingAs($portalUser)->get(route('client.matters.show', $matter))
            ->assertOk()
            ->assertSee('Your matter is awaiting the next court date.')
            ->assertDontSee('Confidential internal litigation strategy.');
    }

    public function test_matter_assignment_file_and_document_actions_are_audited(): void
    {
        Storage::fake('local');
        $user = $this->activeUser([], 'Audit Operator');
        $client = $this->client('Audit Client');
        $matter = $this->matter($client, $user);
        $assignment = MatterAssignment::create([
            'matter_id' => $matter->id,
            'user_id' => $user->id,
            'assignment_role' => 'partner',
        ]);
        $file = File::create([
            'client_id' => $client->id,
            'matter_id' => $matter->id,
            'file_number' => 'FL00000003',
            'file_name' => 'Audit File',
        ]);
        Storage::disk('local')->put('matter/audit.txt', 'audit content');
        $attachment = $matter->attachments()->create([
            'uploaded_by' => $user->id,
            'disk' => 'local',
            'path' => 'matter/audit.txt',
            'original_name' => 'audit.txt',
            'mime_type' => 'text/plain',
        ]);

        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $matter->getMorphClass(), 'auditable_id' => $matter->id, 'event' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $assignment->getMorphClass(), 'auditable_id' => $assignment->id, 'event' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $file->getMorphClass(), 'auditable_id' => $file->id, 'event' => 'created']);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $attachment->getMorphClass(), 'auditable_id' => $attachment->id, 'event' => 'created']);

        $this->actingAs($user)->get(route('attachments.download', $attachment))->assertOk();
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => $attachment->getMorphClass(), 'auditable_id' => $attachment->id, 'event' => 'downloaded']);
    }

    public function test_calendar_rejects_matter_outside_the_users_visibility_scope(): void
    {
        $branchA = Branch::create(['name' => 'Calendar A', 'code' => 'CALA']);
        $branchB = Branch::create(['name' => 'Calendar B', 'code' => 'CALB']);
        $user = $this->activeUser(['calendar.create', 'calendar.store'], 'Calendar User', $branchA->id);
        $owner = $this->activeUser([], 'Other Branch Owner', $branchB->id);
        $client = $this->client('Calendar Client');
        $matter = $this->matter($client, $owner, [
            'branch_id' => $branchB->id,
            'privacy_status' => 'private',
        ]);

        $this->actingAs($user)->get(route('calendar.create'))
            ->assertOk()
            ->assertDontSee($matter->reference_no);

        $this->actingAs($user)->post(route('calendar.store'), [
            'title' => 'Hidden Matter Event',
            'type' => 'meeting',
            'starts_at' => now()->addDay()->format('Y-m-d\\TH:i'),
            'matter_id' => $matter->id,
        ])->assertSessionHasErrors('matter_id');

        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_court_reminders_track_upcoming_deadline_and_escalation_once(): void
    {
        Notification::fake();
        $user = $this->activeUser([], 'Reminder Assignee');
        $client = $this->client('Reminder Client');
        $matter = $this->matter($client, $user);
        $matter->assignments()->create(['user_id' => $user->id, 'assignment_role' => 'partner']);

        $upcoming = CourtEvent::create([
            'matter_id' => $matter->id,
            'assigned_to' => $user->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(3),
            'reminder_days_before' => 3,
        ]);
        $deadline = CourtEvent::create([
            'matter_id' => $matter->id,
            'assigned_to' => $user->id,
            'event_type' => 'filing_deadline',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(5),
            'next_step' => 'File submissions',
            'next_step_due' => now()->addDays(3)->toDateString(),
            'reminder_days_before' => 3,
        ]);
        $overdue = CourtEvent::create([
            'matter_id' => $matter->id,
            'assigned_to' => $user->id,
            'event_type' => 'mention',
            'status' => 'scheduled',
            'starts_at' => now()->subDay(),
            'escalation_days_after' => 0,
        ]);

        $this->artisan('kfms:court-reminders', ['--days' => 0])->assertExitCode(0);

        $this->assertDatabaseHas('court_event_reminder_logs', ['court_event_id' => $upcoming->id, 'reminder_type' => 'upcoming', 'status' => 'sent']);
        $this->assertDatabaseHas('court_event_reminder_logs', ['court_event_id' => $deadline->id, 'reminder_type' => 'deadline', 'status' => 'sent']);
        $this->assertDatabaseHas('court_event_reminder_logs', ['court_event_id' => $overdue->id, 'reminder_type' => 'escalation', 'status' => 'sent']);
        Notification::assertSentTo($user, CourtEventReminder::class);
        Notification::assertSentTo($user, CourtEventDeadlineReminder::class);
        Notification::assertSentTo($user, CourtEventEscalation::class);

        $this->artisan('kfms:court-reminders', ['--days' => 0])->assertExitCode(0);
        $this->assertSame(1, CourtEventReminderLog::where('court_event_id', $upcoming->id)->where('reminder_type', 'upcoming')->count());
        $this->assertSame(1, CourtEventReminderLog::where('court_event_id', $overdue->id)->where('reminder_type', 'escalation')->count());
    }

    public function test_document_versions_and_ordered_bundles_are_available(): void
    {
        Storage::fake('local');
        $user = $this->activeUser([
            'matters.instructions.show',
            'matters.instructions.update',
            'matters.documents.store',
            'matters.bundles.index',
            'matters.bundles.create',
            'matters.bundles.store',
            'matters.bundles.show',
            'matters.bundles.edit',
            'matters.bundles.update',
            'matters.bundles.download',
        ], 'Bundle Operator');
        $matter = $this->matter($this->client('Bundle Client'), $user);

        $this->actingAs($user)->post(route('matters.documents.store', $matter), [
            'document' => UploadedFile::fake()->create('claim.pdf', 10, 'application/pdf'),
            'title' => 'Claim',
            'document_key' => 'claim',
            'category' => 'pleading',
            'is_client_visible' => '1',
        ])->assertRedirect();
        $first = $matter->fresh()->attachments()->first();

        $this->actingAs($user)->post(route('matters.documents.store', $matter), [
            'document' => UploadedFile::fake()->create('claim-v2.pdf', 10, 'application/pdf'),
            'title' => 'Claim revised',
            'document_key' => 'claim',
            'replace_document_id' => $first->id,
            'category' => 'pleading',
        ])->assertRedirect();
        $this->actingAs($user)->post(route('matters.documents.store', $matter), [
            'document' => UploadedFile::fake()->create('defence.pdf', 10, 'application/pdf'),
            'title' => 'Defence',
            'document_key' => 'defence',
            'category' => 'pleading',
        ])->assertRedirect();

        $currentClaim = $matter->fresh()->attachments()->where('document_key', 'claim')->where('is_current', true)->first();
        $defence = $matter->fresh()->attachments()->where('document_key', 'defence')->first();
        $this->assertSame(2, $currentClaim->version_number);
        $this->assertFalse($first->fresh()->is_current);
        $this->assertDatabaseHas('audit_logs', ['auditable_id' => $first->id, 'event' => 'superseded']);

        $this->actingAs($user)->get(route('matters.bundles.create', $matter))
            ->assertOk()
            ->assertSee('Select documents for this bundle')
            ->assertSee('Search documents')
            ->assertSee('data-check-dropdown', false);

        $this->actingAs($user)->post(route('matters.bundles.store', $matter), [
            'name' => 'Filed Pleadings',
            'bundle_type' => 'pleadings',
            'status' => 'finalized',
            'attachment_ids' => [$defence->id, $currentClaim->id],
            'sort_orders' => [$defence->id => 2, $currentClaim->id => 1],
            'labels' => [$defence->id => '01 Defence', $currentClaim->id => '02 Claim'],
        ])->assertRedirect();

        $bundle = DocumentBundle::first();
        $this->assertSame([$currentClaim->id, $defence->id], $bundle->items()->orderBy('sort_order')->pluck('attachment_id')->all());
        $this->actingAs($user)->get(route('matters.bundles.edit', [$matter, $bundle]))->assertOk();
        $this->actingAs($user)->get(route('matters.bundles.download', [$matter, $bundle]))->assertOk();
    }
}
