<?php

namespace Tests\Feature;

use App\Models\Bank;
use App\Models\Branch;
use App\Models\CentenaryBranch;
use App\Models\CentenaryRegion;
use App\Models\Client;
use App\Models\Court;
use App\Models\CourtEvent;
use App\Models\Department;
use App\Models\Invoice;
use App\Models\JobTitle;
use App\Models\Matter;
use App\Models\NotificationTemplate;
use App\Models\RecoveryClient;
use App\Models\StaffProfile;
use App\Models\TaxSetting;
use App\Models\User;
use App\Notifications\CourtEventReminder;
use Database\Seeders\CurrencyTypeSeeder;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PhaseOneMasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CurrencyTypeSeeder::class);
    }

    private function staff(bool $admin = true): User
    {
        $user = User::factory()->create();
        StaffProfile::create(['user_id' => $user->id, 'employment_status' => 'active']);
        if ($admin) {
            $user->assignRole(Role::findOrCreate('Super Admin'));
        }

        return $user;
    }

    private function upload(string $setting, string $contents)
    {
        return $this->post(route('settings.master-data.store'), [
            'setting' => $setting,
            'file' => UploadedFile::fake()->createWithContent('master-data.csv', $contents),
        ]);
    }

    public function test_all_seeded_reference_screens_support_search_and_templates(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::role('Super Admin')->firstOrFail());
        foreach (config('system_settings') as $slug => $definition) {
            $this->get(route('settings.system.index', ['setting' => $slug, 'search' => 'test']))->assertOk();
            $this->get(route('settings.master-data.template', $slug))->assertOk();
        }
        foreach (['branches', 'users', 'roles'] as $slug) {
            $this->get(route('settings.master-data.template', $slug))->assertOk();
        }
        foreach (['banks', 'bank_branches', 'judicial_officers', 'opposing_counsels', 'centenary_regions', 'centenary_branches', 'recovery_clients', 'clients', 'matters'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_previously_mismatched_settings_can_save_and_edit_descriptions(): void
    {
        $this->actingAs($this->staff());
        $court = Court::create(['name' => 'Test Court']);
        $region = CentenaryRegion::create(['name' => 'Existing Region']);
        $cases = [
            'judicial-officers' => ['name' => 'Test Judicial Officer', 'court_id' => $court->id],
            'opposing-counsels' => ['name' => 'Test Counsel', 'firm_name' => 'Test Firm'],
            'centenary-regions' => ['name' => 'New Region'],
            'centenary-branches' => ['name' => 'New Branch', 'centenary_region_id' => $region->id],
        ];
        foreach ($cases as $slug => $input) {
            $this->post(route('settings.system.store', $slug), $input + ['description' => 'Approved reference data', 'is_active' => 1])->assertSessionHasNoErrors();
            $record = config('system_settings.'.$slug.'.model')::where('name', $input['name'])->firstOrFail();
            $this->assertSame('Approved reference data', $record->description);
            $this->get(route('settings.system.edit', [$slug, $record]))->assertOk();
            $this->put(route('settings.system.update', [$slug, $record]), $input + ['description' => 'Updated reference data', 'is_active' => 1])->assertSessionHasNoErrors();
            $this->get(route('settings.system.index', ['setting' => $slug, 'search' => 'Updated reference data']))->assertOk()->assertSee($input['name']);
        }
    }

    public function test_import_resolves_parents_and_rolls_back_a_file_with_an_invalid_row(): void
    {
        $this->actingAs($this->staff());
        $bankA = Bank::create(['name' => 'Approved Bank A']);
        $bankB = Bank::create(['name' => 'Approved Bank B']);
        $this->upload('bank-branches', "name,bank_name,is_active\nCentral,Approved Bank A,1\nOther,Missing Bank,1\n")
            ->assertSessionHasErrors('file');
        $this->assertDatabaseCount('bank_branches', 0);
        $this->upload('bank-branches', "name,bank_name,is_active\nCentral,Approved Bank A,1\nCentral,Approved Bank B,1\n")
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('bank_branches', ['bank_id' => $bankA->id, 'name' => 'Central']);
        $this->assertDatabaseHas('bank_branches', ['bank_id' => $bankB->id, 'name' => 'Central']);
    }

    public function test_user_and_role_imports_validate_access_and_create_controlled_staff(): void
    {
        $admin = $this->staff();
        $limited = $this->staff(false);
        $limited->givePermissionTo(Permission::findOrCreate('settings.master-data.store'));
        $limited->givePermissionTo(Permission::findOrCreate('settings.system.store'));
        $this->actingAs($limited)->upload('users', "name,email\nUnauthorised,blocked@example.test\n")->assertForbidden();
        $this->actingAs($admin);
        Permission::findOrCreate('matters.index');
        $this->upload('roles', "name,permissions\nImported Advocate,matters.index\n")->assertSessionHasNoErrors();
        $branch = Branch::create(['name' => 'Approved Office']);
        Department::create(['name' => 'Approved Department', 'branch_id' => $branch->id]);
        JobTitle::create(['name' => 'Approved Advocate']);
        $this->upload('users', "name,email,password,phone,job_title,branch_name,department_name,role\nImported Staff,staff@example.test,temporary123,0700000000,Approved Advocate,Approved Office,Approved Department,Imported Advocate\n")
            ->assertSessionHasNoErrors();
        $user = User::where('email', 'staff@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('temporary123', $user->password));
        $this->assertSame('Approved Advocate', $user->staffProfile->job_title);
        $this->assertSame('active', $user->staffProfile->employment_status);
        $this->assertTrue($user->can('matters.index'));
        $this->upload('roles', "name,permissions\nSuper Admin,matters.index\n")->assertSessionHasErrors('file');
    }

    public function test_staff_creation_rejects_a_job_title_outside_the_active_list(): void
    {
        Notification::fake();
        $this->actingAs($this->staff());
        $branch = Branch::create(['name' => 'Office']);
        $department = Department::create(['name' => 'Department', 'branch_id' => $branch->id]);
        Role::findOrCreate('Advocate');
        JobTitle::create(['name' => 'Configured Advocate']);
        $payload = ['name' => 'New Staff', 'email' => 'newstaff@example.test', 'password' => 'temporary123', 'password_confirmation' => 'temporary123', 'phone' => '0700000000', 'job_title' => 'Unconfigured Title', 'branch_id' => $branch->id, 'department_id' => $department->id, 'role' => 'Advocate'];
        $this->post(route('staff.store'), $payload)->assertSessionHasErrors('job_title');
        $this->assertDatabaseMissing('users', ['email' => $payload['email']]);
        $payload['job_title'] = 'Configured Advocate';
        $this->post(route('staff.store'), $payload)->assertSessionHasNoErrors();
        JobTitle::where('name', 'Configured Advocate')->firstOrFail()->update(['name' => 'Renamed Advocate']);
        $this->assertSame('Renamed Advocate', User::where('email', $payload['email'])->firstOrFail()->staffProfile->job_title);
    }

    public function test_invoice_tax_treatments_are_calculated_and_snapshotted(): void
    {
        $this->actingAs($this->staff());
        $client = Client::create(['client_no' => 'CL-TAX', 'name' => 'Tax Client']);
        foreach ([
            ['vat', false, 100, 100, 18, 0, 118],
            ['vat', true, 118, 100, 18, 0, 118],
            ['withholding', false, 100, 100, 0, 6, 94],
        ] as $index => [$type, $inclusive, $amount, $base, $tax, $withheld, $total]) {
            $setting = TaxSetting::create(['name' => 'Test Tax '.$index, 'tax_type' => $type, 'rate' => $type === 'vat' ? 18 : 6, 'is_inclusive' => $inclusive]);
            $this->post(route('finance.invoices.store'), ['client_id' => $client->id, 'invoice_date' => today()->toDateString(), 'subtotal' => $amount, 'currency' => 'UGX', 'tax_setting_id' => $setting->id, 'status' => 'draft'])->assertSessionHasNoErrors();
            $invoice = Invoice::latest('id')->firstOrFail();
            $this->assertEquals($base, $invoice->subtotal);
            $this->assertEquals($tax, $invoice->tax);
            $this->assertEquals($withheld, $invoice->withholding_tax);
            $this->assertEquals($total, $invoice->total);
            $setting->update(['rate' => 1]);
            $this->assertEquals($total, $invoice->fresh()->total);
            $this->assertNotEquals(1, (float) $invoice->fresh()->tax_rate);
        }
        $this->post(route('finance.invoices.store'), ['client_id' => $client->id, 'invoice_date' => today()->toDateString(), 'subtotal' => 100, 'currency' => 'UGX', 'tax' => 999, 'status' => 'draft'])->assertSessionHasErrors('tax');
        $this->post(route('finance.invoices.store'), ['client_id' => $client->id, 'invoice_date' => today()->toDateString(), 'subtotal' => 100, 'currency' => 'USD', 'status' => 'draft'])->assertSessionHasErrors('currency');
    }

    public function test_recovery_references_enforce_client_portfolio_currency_and_region_branch_pair(): void
    {
        $this->actingAs($this->staff());
        $client = RecoveryClient::create(['name' => 'Centenary Bank', 'portfolio_types' => ['NPL']]);
        $central = CentenaryRegion::create(['name' => 'Central']);
        $western = CentenaryRegion::create(['name' => 'Western']);
        CentenaryBranch::create(['name' => 'Western Branch', 'centenary_region_id' => $western->id]);
        CentenaryBranch::create(['name' => 'Central Branch', 'centenary_region_id' => $central->id]);
        $payload = ['recovery_client_id' => $client->id, 'debtor_name' => 'Debtor', 'status' => 'active', 'portfolio_type' => 'Unknown', 'currency' => 'FAKE', 'region' => 'Central', 'branch_name' => 'Western Branch'];
        $this->post(route('recoveries.store'), $payload)->assertSessionHasErrors(['portfolio_type', 'currency', 'branch_name']);
        $payload = array_replace($payload, ['portfolio_type' => 'NPL', 'currency' => 'UGX', 'branch_name' => 'Central Branch']);
        $this->post(route('recoveries.store'), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recovery_accounts', ['portfolio_type' => 'NPL', 'currency' => 'UGX', 'region' => 'Central', 'branch_name' => 'Central Branch']);
    }

    public function test_notification_templates_drive_delivery_and_users_can_only_mark_their_own_read(): void
    {
        $user = $this->staff();
        $other = $this->staff(false);
        $client = Client::create(['client_no' => 'CL-NOTICE', 'name' => 'Notice Client']);
        $matter = Matter::create(['client_id' => $client->id, 'reference_no' => 'MT-NOTICE', 'title' => 'Notice Matter']);
        $event = CourtEvent::create(['matter_id' => $matter->id, 'starts_at' => now()->addDay(), 'event_type' => 'hearing', 'court_name' => 'Test Court']);
        $template = NotificationTemplate::create(['name' => 'Custom Court Notice', 'event_key' => 'litigation.court_event.reminder', 'channel' => 'database', 'subject' => 'Court {{ matter_reference }}', 'body' => '{{ recipient_name }}: {{ event_type }} at {{ court_name }}']);
        $user->notify(new CourtEventReminder($event));
        $notice = $user->notifications()->firstOrFail();
        $this->assertSame('Court MT-NOTICE', $notice->data['subject']);
        $this->assertSame($user->name.': Hearing at Test Court', $notice->data['body']);
        $this->actingAs($other)->post(route('notifications.read', $notice->id))->assertNotFound();
        $this->actingAs($user)->get(route('notifications.index'))->assertOk()->assertSee('Court MT-NOTICE');
        $this->post(route('notifications.read', $notice->id))->assertRedirect();
        $this->assertNotNull($notice->fresh()->read_at);
        $template->update(['is_active' => false]);
        $user->notify(new CourtEventReminder($event));
        $this->assertSame(1, $user->notifications()->count());
        $template->update(['is_active' => true, 'channel' => 'email']);
        $notification = new CourtEventReminder($event);
        $this->assertSame(['mail'], $notification->via($user));
        $this->assertSame('Court MT-NOTICE', $notification->toMail($user)->subject);
    }

    public function test_notification_settings_reject_unsupported_events_and_variables(): void
    {
        $this->actingAs($this->staff());
        $input = ['name' => 'Invalid template', 'event_key' => 'unknown.event', 'channel' => 'database', 'body' => 'Test body', 'is_active' => 1];
        $this->post(route('settings.system.store', 'notification-templates'), $input)->assertSessionHasErrors('event_key');
        $input['event_key'] = 'litigation.court_event.reminder';
        $input['body'] = 'Unsupported {{ private.password }}';
        $this->post(route('settings.system.store', 'notification-templates'), $input)->assertSessionHasErrors('body');
        $this->assertDatabaseCount('notification_templates', 0);
    }

    public function test_recovery_import_reports_unconfigured_currency_instead_of_saving_it(): void
    {
        $this->actingAs($this->staff());
        Role::findOrCreate('Recovery Officer');
        $client = RecoveryClient::create(['name' => 'Approved Recovery Client', 'portfolio_types' => ['NPL']]);
        $this->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'NPL',
            'file' => UploadedFile::fake()->createWithContent('accounts.csv', "acct_name,foracid,acct_crncy_code\nTest Debtor,ACC-001,ZZZ\n"),
        ])->assertRedirect()->assertSessionHas('import_errors');
        $this->assertDatabaseCount('recovery_accounts', 0);
        $this->assertDatabaseHas('recovery_import_batches', ['imported_rows' => 0, 'skipped_rows' => 1]);
    }
}
