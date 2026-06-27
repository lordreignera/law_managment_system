<?php

namespace Tests\Feature;

use App\Models\Approval;
use App\Models\Concerns\Approvable;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\HasAttachments;
use App\Models\Requisition;
use App\Models\User;
use App\Services\ApprovalService;
use App\Support\Sms\AfricasTalkingGateway;
use App\Support\Sms\LogSmsGateway;
use App\Support\Sms\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Test-only model that layers the Phase 0 infrastructure traits onto the
 * existing requisitions table without touching the production model.
 */
class Phase0Subject extends Requisition
{
    use Approvable;
    use Auditable;
    use HasAttachments;

    protected $table = 'requisitions';
}

class Phase0InfrastructureTest extends TestCase
{
    use RefreshDatabase;

    private function makeSubject(User $user): Phase0Subject
    {
        return Phase0Subject::create([
            'requested_by' => $user->id,
            'reference_no' => 'REQ-'.uniqid(),
            'purpose' => 'Office supplies',
            'amount' => 150,
            'status' => 'submitted',
        ]);
    }

    public function test_audit_log_records_model_lifecycle(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $subject = $this->makeSubject($user);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'created',
            'auditable_id' => $subject->id,
            'user_id' => $user->id,
        ]);

        $subject->update(['purpose' => 'Updated purpose']);

        $this->assertDatabaseHas('audit_logs', [
            'event' => 'updated',
            'auditable_id' => $subject->id,
        ]);
    }

    public function test_polymorphic_attachments_store_files(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();
        $this->actingAs($user);
        $subject = $this->makeSubject($user);

        $attachment = $subject->addAttachment(
            UploadedFile::fake()->create('brief.pdf', 120, 'application/pdf'),
            ['title' => 'Brief', 'category' => 'pleadings']
        );

        $this->assertDatabaseHas('attachments', [
            'attachable_id' => $subject->id,
            'title' => 'Brief',
            'category' => 'pleadings',
            'uploaded_by' => $user->id,
        ]);
        Storage::disk('local')->assertExists($attachment->path);
        $this->assertSame(1, $subject->attachments()->count());
    }

    public function test_approval_chain_transitions_and_is_audited(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $this->actingAs($requester);
        $subject = $this->makeSubject($requester);

        $service = app(ApprovalService::class);
        $service->submit($subject, $requester, [$approver->id]);

        $this->assertSame(Approval::PENDING, $subject->approvalStatus());

        $pending = $subject->currentApproval();
        $this->assertNotNull($pending);

        $service->approve($pending, $approver, 'Looks good');

        $this->assertSame(Approval::APPROVED, $subject->fresh()->approvalStatus());
        $this->assertDatabaseHas('approvals', [
            'approvable_id' => $subject->id,
            'status' => Approval::APPROVED,
            'approver_id' => $approver->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'approval.approved',
            'auditable_id' => $subject->id,
        ]);
    }

    public function test_rejection_marks_overall_status_rejected(): void
    {
        $requester = User::factory()->create();
        $approver = User::factory()->create();
        $this->actingAs($requester);
        $subject = $this->makeSubject($requester);

        $service = app(ApprovalService::class);
        $service->submit($subject, $requester, [$approver->id]);
        $service->reject($subject->currentApproval(), $approver, 'Over budget');

        $this->assertSame(Approval::REJECTED, $subject->fresh()->approvalStatus());
    }

    public function test_default_sms_gateway_is_safe_log_driver(): void
    {
        config(['sms.default' => 'log']);
        $this->app->forgetInstance(SmsGateway::class);

        $gateway = app(SmsGateway::class);
        $this->assertInstanceOf(LogSmsGateway::class, $gateway);

        $result = $gateway->send(['+256700000000'], 'Test reminder');
        $this->assertTrue($result->successful);
        $this->assertSame(['+256700000000'], $result->recipients);
    }

    public function test_africas_talking_gateway_posts_to_api(): void
    {
        Http::fake([
            '*' => Http::response([
                'SMSMessageData' => [
                    'Recipients' => [['number' => '+256700000000', 'status' => 'Success']],
                ],
            ], 200),
        ]);

        $gateway = new AfricasTalkingGateway('sandbox', 'test-key', 'KFMS', true);
        $result = $gateway->send(['+256700000000'], 'Pay reminder');

        $this->assertTrue($result->successful);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'africastalking.com')
            && $request['message'] === 'Pay reminder');
    }
}
