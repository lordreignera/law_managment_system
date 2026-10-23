<?php

namespace Tests\Feature;

use App\Mail\LegalLetterMail;
use App\Models\Client;
use App\Models\ClientPortalAccount;
use App\Models\CompanySetting;
use App\Models\Department;
use App\Models\LegalLetter;
use App\Models\Matter;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\LetterheadSeeder;
use Database\Seeders\LetterTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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
        $this->withoutVite();

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
            ->assertSee('Received Copy')
            ->assertSee('Save Draft')
            ->assertSee('kfms-letter-steps', false);

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

    public function test_creator_department_supervisor_and_shared_reviewer_can_view_private_drafts(): void
    {
        $department = Department::create(['name' => 'Litigation', 'code' => 'LIT']);
        $otherDepartment = Department::create(['name' => 'Finance', 'code' => 'FIN']);
        $creator = $this->staffUser();
        $reviewer = User::factory()->create(['department_id' => $otherDepartment->id]);
        $outsider = $this->staffUser();
        $managerRole = Role::findOrCreate('Department Manager');

        foreach (['letters.dashboard', 'letters.index', 'letters.show', 'letters.approve'] as $permission) {
            $managerRole->givePermissionTo(Permission::findOrCreate($permission));
        }

        $manager = User::factory()->create(['department_id' => $department->id]);
        $manager->assignRole($managerRole);
        StaffProfile::create(['user_id' => $manager->id, 'employment_status' => 'active']);
        $creator->update(['department_id' => $department->id, 'manager_id' => $manager->id]);
        $outsider->update(['department_id' => $otherDepartment->id]);

        $letter = LegalLetter::create($this->letterData() + [
            'reference_no' => 'TEST/PRIVATE',
            'created_by' => $creator->id,
            'department_id' => $department->id,
            'status' => 'draft',
        ]);
        $letter->shares()->create(['user_id' => $reviewer->id, 'shared_by' => $creator->id]);

        $this->actingAs($manager)->get(route('letters.index'))
            ->assertOk()
            ->assertSee('Correspondence');
        $this->actingAs($creator)->get(route('letters.show', $letter))->assertOk();
        $this->actingAs($reviewer)->get(route('letters.show', $letter))->assertOk();
        $this->actingAs($outsider)->get(route('letters.index'))
            ->assertOk()
            ->assertDontSee('TEST/PRIVATE');
        $this->actingAs($outsider)->get(route('letters.show', $letter))->assertNotFound();
        $this->actingAs($outsider)->get(route('letters.pdf', $letter))->assertNotFound();
    }

    public function test_incomplete_draft_can_be_saved_but_cannot_be_submitted_for_review(): void
    {
        $staff = $this->staffUser();

        $this->actingAs($staff)->post(route('letters.store'), [
            'letter_type' => 'general',
            'signature_mode' => 'none',
        ])->assertSessionHas('status', 'Letter drafted.');

        $letter = LegalLetter::firstOrFail();
        $this->assertSame('draft', $letter->status);
        $this->assertSame('', $letter->recipient_name);
        $this->assertSame('', $letter->subject);
        $this->assertSame('', $letter->body);

        $this->actingAs($staff)->patch(route('letters.submit', $letter))
            ->assertSessionHasErrors(['recipient_name', 'subject', 'body', 'letter_date']);

        $this->assertSame('draft', $letter->fresh()->status);
    }

    public function test_letters_dashboard_uses_module_hero_design(): void
    {
        $staff = $this->staffUser();

        $response = $this->actingAs($staff)
            ->get(route('letters.dashboard'))
            ->assertOk()
            ->assertSee('Letters & opinions control room', false)
            ->assertSee('Draft, review, approve, and dispatch firm correspondence.')
            ->assertSee('Create Letter')
            ->assertSee('Document Workspace')
            ->assertSee('Open Document Workspace')
            ->assertSee('Pending Review')
            ->assertSee('Recent Letters');

        $content = $response->getContent();
        $pendingReviewPosition = strpos($content, 'data-letter-dashboard-section="pending-review"');
        $recentLettersPosition = strpos($content, 'data-letter-dashboard-section="recent"');

        $this->assertNotFalse($pendingReviewPosition);
        $this->assertNotFalse($recentLettersPosition);
        $this->assertLessThan($recentLettersPosition, $pendingReviewPosition);
    }

    public function test_unsaved_draft_preview_is_an_a4_pdf_without_creating_records_or_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs($this->staffUser())
            ->post(route('letters.draft-preview'), array_merge($this->letterData(), [
                'recipient_name' => '',
                'recipient_email' => 'still-typing@',
                'subject' => '',
                'signature_mode' => 'upload',
                'signature_upload' => UploadedFile::fake()->image('signature.png'),
            ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertDatabaseCount('legal_letters', 0);
        $this->assertEmpty(Storage::disk('public')->allFiles());
    }

    public function test_preview_includes_unsaved_changes_without_overwriting_saved_letter(): void
    {
        $staff = $this->staffUser();
        $letter = LegalLetter::create($this->letterData() + ['reference_no' => 'TEST/001', 'created_by' => $staff->id]);

        $response = $this->actingAs($staff)->post(route('letters.preview', $letter), array_merge($this->letterData(), [
            'body' => str_repeat("A long paragraph that continues on the next A4 page.\n\n", 120),
        ]))->assertOk();

        preg_match_all('/\/Type\s*\/Page\b/', $response->getContent(), $pages);
        $this->assertGreaterThan(1, count($pages[0]));
        $this->assertMatchesRegularExpression('/\/MediaBox\s*\[0\.000\s+0\.000\s+595\.\d+\s+841\.\d+\]/', $response->getContent());
        $this->assertSame($this->letterData()['body'], $letter->fresh()->body);
    }

    public function test_editor_can_save_and_update_using_json_without_creating_duplicate_letters(): void
    {
        $staff = $this->staffUser();
        $this->actingAs($staff)->postJson(route('letters.store'), $this->letterData())
            ->assertOk()->assertJsonStructure(['update_url', 'edit_url', 'preview_url', 'email_url', 'reference_no']);
        $letter = LegalLetter::firstOrFail();

        $this->putJson(route('letters.update', $letter), array_merge($this->letterData(), [
            'body' => 'The latest letter wording.',
        ]))->assertOk();

        $this->assertDatabaseCount('legal_letters', 1);
        $this->assertSame('The latest letter wording.', $letter->fresh()->body);
    }

    public function test_saved_letter_can_be_downloaded_or_opened_inline(): void
    {
        $staff = $this->staffUser();
        $letter = LegalLetter::create($this->letterData() + ['reference_no' => 'TEST/001', 'created_by' => $staff->id]);
        $this->actingAs($staff)->get(route('letters.pdf', $letter))
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=test-001.pdf');
        $this->get(route('letters.pdf', ['letter' => $letter, 'inline' => 1]))
            ->assertOk()->assertHeader('Content-Disposition', 'inline; filename=test-001.pdf');
    }

    public function test_email_attaches_pdf_uses_connected_mailer_and_preserves_review_status(): void
    {
        Mail::fake();
        $staff = $this->staffUser();
        $letter = LegalLetter::create($this->letterData() + ['reference_no' => 'TEST/001', 'created_by' => $staff->id, 'status' => 'draft']);

        $this->actingAs($staff)->postJson(route('letters.email', $letter), [
            'to' => 'recipient@example.test',
            'subject' => 'Letter for review',
            'message' => 'Please review the attached letter.',
        ])->assertOk()->assertJsonPath('message', 'Letter emailed to recipient@example.test.');

        Mail::assertSent(LegalLetterMail::class, function (LegalLetterMail $mail) use ($staff) {
            $mail->build();

            return $mail->hasTo('recipient@example.test')
                && $mail->hasReplyTo($staff->email)
                && $mail->hasAttachedData($mail->pdfContent, 'test-001.pdf', ['mime' => 'application/pdf'])
                && str_starts_with($mail->pdfContent, '%PDF-')
                && $mail->subject === 'Letter for review'
                && str_contains($mail->render(), 'Please review the attached letter.');
        });
        $this->assertSame('draft', $letter->fresh()->status);
        $this->assertNull($letter->fresh()->sent_at);
    }

    public function test_email_validation_and_permissions_prevent_delivery(): void
    {
        Mail::fake();
        $staff = $this->staffUser();
        $letter = LegalLetter::create($this->letterData() + ['reference_no' => 'TEST/001', 'created_by' => $staff->id]);
        $this->actingAs($staff)->postJson(route('letters.email', $letter), [
            'to' => 'not-an-email', 'subject' => 'Letter', 'message' => 'Please review.',
        ])->assertUnprocessable()->assertJsonValidationErrors('to');

        $staff->roles->first()->revokePermissionTo('letters.mark-sent');
        $this->postJson(route('letters.email', $letter), [
            'to' => 'recipient@example.test', 'subject' => 'Letter', 'message' => 'Please review.',
        ])->assertForbidden();
        Mail::assertNothingSent();
    }

    public function test_email_failure_keeps_saved_letter_available_to_retry(): void
    {
        $staff = $this->staffUser();
        $letter = LegalLetter::create($this->letterData() + ['reference_no' => 'TEST/001', 'created_by' => $staff->id, 'status' => 'draft']);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('Mail service unavailable'));

        $this->actingAs($staff)->postJson(route('letters.email', $letter), [
            'to' => 'recipient@example.test', 'subject' => 'Letter', 'message' => 'Please review.',
        ])->assertStatus(503)->assertJsonPath('message', 'The email could not be sent. Your letter is saved; please try again.');
        $this->assertSame('draft', $letter->fresh()->status);
        $this->assertSame($this->letterData()['body'], $letter->fresh()->body);
    }

    public function test_draft_preview_requires_letter_editor_permission(): void
    {
        $staff = $this->staffUser();
        $staff->roles->first()->revokePermissionTo('letters.create');
        $this->actingAs($staff)->postJson(route('letters.draft-preview'), $this->letterData())->assertForbidden();
    }

    public function test_saved_signature_and_signer_survive_edits_and_can_be_removed_from_preview(): void
    {
        Storage::fake('public');
        $staff = $this->staffUser();
        $this->actingAs($staff)->postJson(route('letters.store'), array_merge($this->letterData(), [
            'signature_mode' => 'upload',
            'signature_upload' => UploadedFile::fake()->image('signature.png', 120, 40),
        ]))->assertOk();
        $letter = LegalLetter::firstOrFail();
        $path = $letter->signature_path;

        $this->putJson(route('letters.update', $letter), array_merge($this->letterData(), [
            'signature_mode' => 'upload', 'body' => 'Updated signed letter.',
        ]))->assertOk();
        $this->assertSame($staff->id, $letter->fresh()->signed_by);
        $this->assertSame($path, $letter->fresh()->signature_path);

        $signed = $this->post(route('letters.preview', $letter), array_merge($this->letterData(), ['signature_mode' => 'upload']))->assertOk();
        $unsigned = $this->post(route('letters.preview', $letter), $this->letterData())->assertOk();
        preg_match_all('/\/Subtype\s*\/Image\b/', $signed->getContent(), $signedImages);
        preg_match_all('/\/Subtype\s*\/Image\b/', $unsigned->getContent(), $unsignedImages);
        $this->assertGreaterThan(count($unsignedImages[0]), count($signedImages[0]));
        $this->assertSame($path, $letter->fresh()->signature_path);
    }

    private function letterData(): array
    {
        return [
            'letter_type' => 'general',
            'recipient_name' => 'Demo Recipient',
            'recipient_email' => 'recipient@example.test',
            'subject' => 'Correspondence',
            'body' => "Dear {recipient_name},\n\nPlease find our response below.",
            'letter_date' => '2026-09-24',
            'signature_mode' => 'none',
        ];
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
