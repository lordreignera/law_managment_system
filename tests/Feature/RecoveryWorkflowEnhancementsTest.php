<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\RecoveryPromiseReminderLog;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Sms\SmsGateway;
use App\Support\Sms\SmsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecoveryWorkflowEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_due_promise_sms_is_sent_once_and_logged(): void
    {
        $this->app->instance(SmsGateway::class, new class implements SmsGateway
        {
            public function send(array $recipients, string $message): SmsResult
            {
                return SmsResult::success($recipients, ['test' => true]);
            }
        });

        $branch = Branch::create(['name' => 'Recovery Branch', 'code' => 'REC']);
        $manager = $this->activeUser(['recoveries.reminders.send'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Reminder Bank', 'code' => 'REM-1']);
        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Promise Debtor',
            'phone' => '0700000000',
            'outstanding_amount' => 500000,
            'status' => 'active',
            'branch_id' => $branch->id,
            'sms_opt_in' => true,
        ]);
        $activity = RecoveryActivity::create([
            'recovery_account_id' => $account->id,
            'user_id' => $manager->id,
            'activity_type' => 'call',
            'response_outcome' => 'promised',
            'activity_at' => now()->subDay(),
            'promised_amount' => 100000,
            'promised_on' => now()->toDateString(),
            'notes' => 'Payment promise recorded.',
        ]);

        $this->actingAs($manager)->post(route('recoveries.reminders.send'))->assertRedirect();

        $this->assertDatabaseHas('recovery_promise_reminder_logs', [
            'recovery_activity_id' => $activity->id,
            'status' => 'sent',
            'recipient_phone' => '0700000000',
        ]);

        $this->actingAs($manager)->post(route('recoveries.reminders.send'))->assertRedirect();
        $this->assertSame(1, RecoveryPromiseReminderLog::where('recovery_activity_id', $activity->id)->count());
    }

    public function test_manager_can_record_skip_trace_result_and_identifiers(): void
    {
        $branch = Branch::create(['name' => 'Recovery Branch', 'code' => 'REC']);
        $manager = $this->activeUser(['recoveries.show', 'recoveries.skip-trace.store'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Trace Bank', 'code' => 'TRC-1']);
        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Trace Debtor',
            'status' => 'active',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)->post(route('recoveries.skip-trace.store', $account), [
            'provider' => 'nin',
            'status' => 'found',
            'nin' => 'CM123456789',
            'whatsapp_number' => '0700111222',
            'notes' => 'NIN search returned a verified contact.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $account->refresh();
        $this->assertSame('found', $account->skip_trace_status);
        $this->assertSame('CM123456789', $account->nin);
        $this->assertDatabaseHas('recovery_skip_trace_checks', [
            'recovery_account_id' => $account->id,
            'provider' => 'nin',
            'status' => 'found',
            'nin' => 'CM123456789',
            'whatsapp_number' => '0700111222',
        ]);
    }

    public function test_pipeline_report_can_filter_and_export_pipeline_rows(): void
    {
        $branch = Branch::create(['name' => 'Recovery Branch', 'code' => 'REC']);
        $manager = $this->activeUser(['recoveries.reports', 'recoveries.export'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Pipeline Bank', 'code' => 'PIP-1']);
        RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Pipeline Debtor',
            'outstanding_amount' => 900000,
            'status' => 'active',
            'pipeline_stage' => 'field_visit',
            'priority' => 'urgent',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)->get(route('recoveries.reports', ['pipeline_stage' => 'field_visit']))
            ->assertOk()
            ->assertSee('Pipeline Report')
            ->assertSee('Field Visit')
            ->assertSee('900,000.00');

        $this->actingAs($manager)->get(route('recoveries.export', ['type' => 'pipeline', 'format' => 'xlsx', 'pipeline_stage' => 'field_visit']))
            ->assertOk();
    }

    public function test_recovery_officer_can_only_log_activity_for_assigned_account(): void
    {
        $branch = Branch::create(['name' => 'Recovery Branch', 'code' => 'REC']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $other = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Access Bank', 'code' => 'ACC-1']);
        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Assigned Elsewhere',
            'status' => 'active',
            'assigned_to' => $other->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'no_response',
            'activity_at' => now()->format('Y-m-d\\TH:i'),
            'notes' => 'Not allowed.',
        ])->assertForbidden();
    }

    private function activeUser(array $permissions, string $roleName, ?int $branchId = null): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($role);
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);

        return $user;
    }
}
