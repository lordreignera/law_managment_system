<?php

namespace Tests\Feature;

use App\Models\Attachment;
use App\Models\Bank;
use App\Models\BankBranch;
use App\Models\Branch;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\RecoveryImportBatch;
use App\Models\RecoveryImportTemplate;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\Recoveries\RecoveryPortfolioMapper;
use Database\Seeders\CurrencyTypeSeeder;
use Database\Seeders\RecoveryClientSeeder;
use Database\Seeders\RecoveryOfficerPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecoveryFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->seed(CurrencyTypeSeeder::class);
        $bank = Bank::create(['name' => 'Stanbic Bank']);
        BankBranch::create(['bank_id' => $bank->id, 'name' => 'Kampala']);
    }

    private function activeUser(array $permissions, string $roleName, ?int $branchId = null): User
    {
        $role = Role::findOrCreate($roleName);
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create(['branch_id' => $branchId]);
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_manager_can_create_and_assign_a_recovery(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser(['recoveries.index', 'recoveries.create', 'recoveries.store', 'recoveries.show', 'recoveries.assignment.edit'], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Stanbic Bank', 'code' => 'RC-1']);

        $response = $this->actingAs($manager)->post(route('recoveries.store'), [
            'recovery_client_id' => $client->id,
            'debtor_name' => 'John Debtor',
            'phone' => '0700000000',
            'outstanding_amount' => 5000000,
            'currency' => 'UGX',
            'status' => 'active',
            'assigned_to' => $officer->id,
        ]);

        $account = RecoveryAccount::first();

        $this->assertNotNull($account);
        $this->assertSame('John Debtor', $account->debtor_name);
        $this->assertSame($officer->id, $account->assigned_to);
        $this->assertSame($manager->id, $account->assigned_by);
        $this->assertNotNull($account->assigned_at);
        // Branch defaults from the assigned officer's branch.
        $this->assertSame($branch->id, $account->branch_id);
        $response->assertRedirect(route('recoveries.show', $account));

        $this->actingAs($manager)->get(route('recoveries.index'))
            ->assertOk()
            ->assertSee('Phone')
            ->assertSee('0700000000')
            ->assertSee('Change');
    }

    public function test_recovery_manager_lands_on_recovery_dashboard(): void
    {
        $manager = $this->activeUser(['dashboard', 'recoveries.dashboard'], 'Recoveries Manager');

        $this->actingAs($manager)->get(route('dashboard'))
            ->assertRedirect(route('recoveries.dashboard'));
    }

    public function test_recovery_officer_lands_on_my_recoveries_dashboard(): void
    {
        $officer = $this->activeUser(['dashboard', 'recoveries.mine'], 'Recovery Officer');

        $this->actingAs($officer)->get(route('dashboard'))
            ->assertRedirect(route('recoveries.mine'));
    }

    public function test_recovery_officer_seed_removes_manager_actions_and_preserves_assigned_work(): void
    {
        $officer = $this->activeUser([
            'dashboard',
            'recoveries.index',
            'recoveries.import',
            'recoveries.import.store',
            'recoveries.assignment.edit',
            'recoveries.assignment.update',
            'recoveries.destroy',
            'recoveries.templates.store',
            'recoveries.templates.download',
            'recoveries.clients.portfolios.store',
        ], 'Recovery Officer');

        $this->seed(RecoveryOfficerPermissionSeeder::class);
        $this->seed(RecoveryOfficerPermissionSeeder::class);

        foreach (['recoveries.mine', 'recoveries.mine.export', 'recoveries.show', 'recoveries.activities.store', 'recoveries.skip-trace.store'] as $permission) {
            $this->assertTrue($officer->can($permission), $permission);
        }

        foreach (['recoveries.index', 'recoveries.import', 'recoveries.import.store', 'recoveries.assignment.edit', 'recoveries.assignment.update', 'recoveries.destroy', 'recoveries.templates.store', 'recoveries.templates.download', 'recoveries.clients.portfolios.store'] as $permission) {
            $this->assertFalse($officer->can($permission), $permission);
        }

        $this->assertTrue($officer->can('dashboard'));
        $this->actingAs($officer)->get(route('recoveries.import'))->assertForbidden();
        $this->actingAs($officer)->post(route('recoveries.import.store'))->assertForbidden();
    }

    public function test_recovery_officer_cannot_open_or_skip_trace_another_officers_direct_account_link(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.mine', 'recoveries.show', 'recoveries.skip-trace.store', 'recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $otherOfficer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $manager = $this->activeUser(['recoveries.show', 'recoveries.assignment.edit'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Portfolio Bank', 'code' => 'RC-ACCESS']);

        $mine = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Assigned Debtor',
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);
        $someoneElses = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Other Debtor',
            'status' => 'active',
            'assigned_to' => $otherOfficer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->get(route('recoveries.show', $mine))
            ->assertOk()
            ->assertSee('Assigned Debtor');
        $this->actingAs($officer)->get(route('recoveries.show', $someoneElses))->assertForbidden();
        $this->actingAs($officer)->post(route('recoveries.skip-trace.store', $someoneElses), [
            'provider' => 'manual',
            'status' => 'pending',
        ])->assertForbidden();
        $this->actingAs($officer)->get(route('recoveries.assignment.edit', $mine))->assertForbidden();

        $this->actingAs($manager)->get(route('recoveries.show', $someoneElses))->assertOk();
    }

    public function test_manager_can_add_recovery_client_with_portfolio_types(): void
    {
        $manager = $this->activeUser(['recoveries.dashboard', 'recoveries.clients.store'], 'Recoveries Manager');
        Role::findOrCreate('Recovery Officer');

        $this->actingAs($manager)->get(route('recoveries.dashboard'))
            ->assertOk()
            ->assertSee('Add Bank / Portfolio')
            ->assertSee('recovery-client-modal', false)
            ->assertSee('Bank / Institution Name')
            ->assertSee('Portfolio Types');

        $this->actingAs($manager)->post(route('recoveries.clients.store'), [
            'name' => 'New Recovery Bank',
            'contact_person' => 'Credit Manager',
            'portfolio_types' => "NPL\nWrite Off",
        ])->assertSessionHasNoErrors();

        $client = RecoveryClient::where('name', 'New Recovery Bank')->first();

        $this->assertNotNull($client);
        $this->assertSame(['NPL', 'Write Off'], $client->portfolio_types);
    }

    public function test_manager_can_configure_a_new_client_portfolio_template_and_import_its_columns(): void
    {
        $manager = $this->activeUser([
            'recoveries.import', 'recoveries.import.store', 'recoveries.batches.show',
            'recoveries.clients.store', 'recoveries.clients.portfolios.store',
            'recoveries.templates.store', 'recoveries.templates.download', 'recoveries.templates.destroy',
        ], 'Recoveries Manager');

        $this->actingAs($manager)->post(route('recoveries.clients.store'), [
            'name' => 'New Lending Institution',
            'portfolio_types' => "Retail NPL",
            'return_to' => 'import',
        ])->assertSessionHasNoErrors();
        $client = RecoveryClient::where('name', 'New Lending Institution')->firstOrFail();

        $this->actingAs($manager)->post(route('recoveries.clients.portfolios.store', $client), [
            'portfolio_type' => 'SME Write Off',
        ])->assertRedirect(route('recoveries.import', [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
        ]));
        $this->assertSame(['Retail NPL', 'SME Write Off'], $client->fresh()->portfolio_types);

        $mapping = [
            'debtor_name' => 'Borrower Full Name',
            'outstanding_amount' => 'Balance Due',
            'account_number' => 'Loan ID',
            'source_recovered_amount' => 'Collected Before Handover',
        ];
        $this->actingAs($manager)->post(route('recoveries.templates.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
            'sheet_name' => 'SME Accounts',
            'column_map' => $mapping,
        ])->assertSessionHasNoErrors();

        $template = RecoveryImportTemplate::firstOrFail();
        $this->assertSame($mapping, $template->column_map);
        $this->actingAs($manager)->get(route('recoveries.import', [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
        ]))->assertOk()->assertSee('Create / Edit Template')->assertSee('SME Write Off');
        $download = $this->actingAs($manager)->get(route('recoveries.templates.download', $template))->assertOk();
        $this->assertSame("\"Borrower Full Name\",\"Balance Due\",\"Loan ID\",\"Collected Before Handover\"\n", $download->streamedContent());

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('Unrelated')->fromArray([
            ['Borrower Full Name', 'Balance Due'], ['Wrong Borrower', 80000],
        ]);
        $spreadsheet->createSheet()->setTitle('SME Accounts')->fromArray([
            ['Borrower Full Name', 'Balance Due', 'Loan ID', 'Collected Before Handover'],
            ['Right Borrower', 250000, 'L-100', 30000],
        ]);
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
            'file' => $this->uploadedRecoveryWorkbook($spreadsheet),
        ])->assertSessionHasNoErrors();

        $batch = RecoveryImportBatch::firstOrFail();
        $account = RecoveryAccount::firstOrFail();
        $this->assertSame('SME Accounts', $batch->sheet_name);
        $this->assertDatabaseCount('recovery_accounts', 1);
        $this->assertSame('Right Borrower', $account->debtor_name);
        $this->assertSame('L-100', $account->account_number);
        $this->assertEquals(250000, $account->net_outstanding_balance);
        $this->assertEquals(30000, $account->sourceReportedRecovery());

        $csvPath = tempnam(sys_get_temp_dir(), 'recovery-standard-').'.csv';
        file_put_contents($csvPath, "debtor_name,account_number,outstanding_amount,currency\nStandard Borrower,L-200,45000,UGX\n");
        $standardCsv = new UploadedFile($csvPath, 'standard.csv', 'text/csv', null, true);
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
            'file' => $standardCsv,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('recovery_accounts', ['debtor_name' => 'Standard Borrower', 'account_number' => 'L-200']);

        $wrong = new Spreadsheet;
        $wrong->getActiveSheet()->fromArray([['Other Name', 'Other Balance'], ['Not Imported', 50000]]);
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
            'file' => $this->uploadedRecoveryWorkbook($wrong),
        ])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('recovery_import_batches', 2);
        $this->assertDatabaseCount('recovery_accounts', 2);

        $this->actingAs($manager)->post(route('recoveries.templates.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'SME Write Off',
            'column_map' => ['debtor_name' => 'Same Column', 'outstanding_amount' => 'Same Column'],
        ])->assertSessionHasErrors('column_map');
        $this->assertSame($mapping, $template->fresh()->column_map);
    }

    public function test_recovery_lenders_and_portfolios_are_seeded_without_borrower_records(): void
    {
        $this->seed(RecoveryClientSeeder::class);
        $stanbic = RecoveryClient::where('name', 'Stanbic Bank Uganda')->firstOrFail();
        $stanbic->update(['portfolio_types' => ['Custom Watchlist']]);
        $this->seed(RecoveryClientSeeder::class);

        $this->assertDatabaseCount('recovery_clients', 4);
        $this->assertDatabaseCount('recovery_accounts', 0);
        $this->assertSame(['Stanbic Write Off', 'Stanbic NPL', 'Custom Watchlist'], $stanbic->fresh()->portfolio_types);
        $this->assertSame(['DFCU NPL', 'DFCU Write Off'], RecoveryClient::where('name', 'dfcu Bank')->firstOrFail()->portfolio_types);
        $this->assertSame(['UDB'], RecoveryClient::where('name', 'Uganda Development Bank')->firstOrFail()->portfolio_types);
        $this->assertSame(['BOA'], RecoveryClient::where('name', 'Bank of Africa Uganda')->firstOrFail()->portfolio_types);
    }

    public function test_multi_sheet_import_uses_only_selected_portfolio_and_preserves_source_recovery(): void
    {
        $this->seed(RecoveryClientSeeder::class);
        $manager = $this->activeUser(['recoveries.import.store', 'recoveries.batches.show', 'recoveries.show'], 'Recoveries Manager');
        $client = RecoveryClient::where('name', 'dfcu Bank')->firstOrFail();

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->setTitle('STANBIC_WRITE OFF')->fromArray([
            ['Cif_id', 'foracid', 'acct_name', 'NET EXPOSURE'],
            ['CIF-1', 'ST-1', 'Stanbic Debtor', 100000],
        ]);
        $spreadsheet->createSheet()->setTitle('DFCU WRITE OFF')->fromArray([
            ['ACCOUNT NAME', 'ACCOUNT NUMBER', 'CHRGE OFF AMNT BASE', 'OUTSTANDING_AMOUNT_BASE', 'PRINCIPAL RECOVERY', 'INTEREST RECOVERY', 'BRANCH NAME', 'CURRENCY', 'JANUARY COMMENT'],
            ['DFCU Debtor', 'DF-1', 400000, 300000, 20000, 5000, 'Source Branch', 'UGX', 'Called the debtor'],
        ]);

        $response = $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'DFCU Write Off',
            'file' => $this->uploadedRecoveryWorkbook($spreadsheet),
        ]);

        $batch = RecoveryImportBatch::firstOrFail();
        $account = RecoveryAccount::firstOrFail();
        $response->assertRedirect(route('recoveries.batches.show', $batch));
        $this->assertDatabaseCount('recovery_accounts', 1);
        $this->assertSame('DFCU WRITE OFF', $batch->sheet_name);
        $this->assertSame('DFCU Debtor', $account->debtor_name);
        $this->assertSame('Source Branch', $account->branch_name);
        $this->assertEquals(300000, $account->net_outstanding_balance);
        $this->assertEquals(0, (float) $account->amount_recovered);
        $this->assertEquals(25000, $account->sourceReportedRecovery());
        $this->actingAs($manager)->get(route('recoveries.show', $account))
            ->assertOk()->assertSee('Called the debtor')->assertSee('Prior recovery in source file');
    }

    public function test_import_rejects_a_wrong_single_sheet_without_creating_a_batch(): void
    {
        $this->seed(RecoveryClientSeeder::class);
        $manager = $this->activeUser(['recoveries.import.store'], 'Recoveries Manager');
        $client = RecoveryClient::where('name', 'Stanbic Bank Uganda')->firstOrFail();
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['ACCOUNT NAME', 'ACCOUNT NUMBER', 'CHRGE OFF AMNT BASE', 'OUTSTANDING_AMOUNT_BASE'],
            ['Other Bank Debtor', 'DF-1', 400000, 300000],
        ]);

        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Stanbic Write Off',
            'file' => $this->uploadedRecoveryWorkbook($spreadsheet),
        ])->assertSessionHasErrors('file');

        $this->assertDatabaseCount('recovery_import_batches', 0);
        $this->assertDatabaseCount('recovery_accounts', 0);
    }

    public function test_generic_csv_template_imports_into_the_selected_portfolio(): void
    {
        $this->seed(RecoveryClientSeeder::class);
        $manager = $this->activeUser(['recoveries.import.store'], 'Recoveries Manager');
        $client = RecoveryClient::where('name', 'Stanbic Bank Uganda')->firstOrFail();
        $path = tempnam(sys_get_temp_dir(), 'recovery-csv-').'.csv';
        file_put_contents($path, "debtor_name,account_number,outstanding_amount,currency\nCSV Debtor,CSV-1,120000,UGX\n");
        $file = new UploadedFile($path, 'recovery-portfolio.csv', 'text/csv', null, true);

        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Stanbic NPL',
            'file' => $file,
        ])->assertSessionHasNoErrors();

        $this->assertSame('CSV', RecoveryImportBatch::firstOrFail()->sheet_name);
        $this->assertSame('CSV Debtor', RecoveryAccount::firstOrFail()->debtor_name);
        $this->assertEquals(120000, (float) RecoveryAccount::firstOrFail()->outstanding_amount);
    }

    public function test_manager_can_review_a_batch_when_every_row_is_skipped(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser(['recoveries.import.store', 'recoveries.batches.show'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'New Lender', 'portfolio_types' => ['Retail NPL']]);
        $path = tempnam(sys_get_temp_dir(), 'recovery-invalid-').'.csv';
        file_put_contents($path, "debtor_name,outstanding_amount,currency\nUnusable Debtor,120000,ZZZ\nNo Balance,0,UGX\n");

        $response = $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Retail NPL',
            'file' => new UploadedFile($path, 'invalid.csv', 'text/csv', null, true),
        ]);

        $batch = RecoveryImportBatch::firstOrFail();
        $response->assertRedirect(route('recoveries.batches.show', $batch));
        $this->assertSame(0, $batch->imported_rows);
        $this->assertSame(2, $batch->skipped_rows);
        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()
            ->assertSee('Correct the skipped rows and import the file again.')
            ->assertDontSee('Assign Batch To');
    }

    public function test_branch_manager_cannot_assign_other_branch_accounts_in_a_shared_batch(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $manager = $this->activeUser(['recoveries.batches.show', 'recoveries.batches.assign'], 'Recoveries Manager', $branchA->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branchA->id);
        $client = RecoveryClient::create(['name' => 'New Lender', 'portfolio_types' => ['Retail NPL']]);
        $batch = RecoveryImportBatch::create([
            'recovery_client_id' => $client->id,
            'uploaded_by' => $manager->id,
            'portfolio_type' => 'Retail NPL',
        ]);
        $accountA = RecoveryAccount::create([
            'recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id,
            'debtor_name' => 'Branch A Debtor', 'branch_id' => $branchA->id,
        ]);
        $accountB = RecoveryAccount::create([
            'recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id,
            'debtor_name' => 'Branch B Debtor', 'branch_id' => $branchB->id,
        ]);

        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()->assertSee('Branch A Debtor')->assertDontSee('Branch B Debtor');
        $this->actingAs($manager)->patch(route('recoveries.batches.assign', $batch), [
            'assigned_to' => $officer->id, 'scope' => 'all',
        ])->assertRedirect();
        $this->assertSame($officer->id, $accountA->fresh()->assigned_to);
        $this->assertNull($accountB->fresh()->assigned_to);
    }

    public function test_manager_can_assign_selected_accounts_and_cannot_select_hidden_accounts(): void
    {
        $branchA = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $branchB = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $manager = $this->activeUser(['recoveries.batches.show', 'recoveries.batches.assign'], 'Recoveries Manager', $branchA->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branchA->id);
        $client = RecoveryClient::create(['name' => 'New Lender', 'portfolio_types' => ['Retail NPL']]);
        $batch = RecoveryImportBatch::create([
            'recovery_client_id' => $client->id,
            'uploaded_by' => $manager->id,
            'portfolio_type' => 'Retail NPL',
        ]);
        $first = RecoveryAccount::create(['recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id, 'debtor_name' => 'First Debtor', 'branch_id' => $branchA->id]);
        $second = RecoveryAccount::create(['recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id, 'debtor_name' => 'Second Debtor', 'branch_id' => $branchA->id]);
        $third = RecoveryAccount::create(['recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id, 'debtor_name' => 'Third Debtor', 'branch_id' => $branchA->id]);
        $hidden = RecoveryAccount::create(['recovery_client_id' => $client->id, 'recovery_import_batch_id' => $batch->id, 'debtor_name' => 'Hidden Debtor', 'branch_id' => $branchB->id]);

        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()
            ->assertSee('Select all accounts on this page')
            ->assertSee('Selected accounts')
            ->assertSee('All visible accounts in this batch')
            ->assertDontSee('Hidden Debtor');

        $this->actingAs($manager)->patch(route('recoveries.batches.assign', $batch), [
            'assigned_to' => $officer->id,
            'scope' => 'selected',
            'account_ids' => [$first->id, $second->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame($officer->id, $first->fresh()->assigned_to);
        $this->assertSame($officer->id, $second->fresh()->assigned_to);
        $this->assertNull($third->fresh()->assigned_to);
        $this->assertNull($hidden->fresh()->assigned_to);
        $this->assertTrue($first->audits()->where('event', 'updated')->exists());
        $this->assertTrue($second->audits()->where('event', 'updated')->exists());

        $this->actingAs($manager)->patch(route('recoveries.batches.assign', $batch), [
            'assigned_to' => $officer->id,
            'scope' => 'selected',
            'account_ids' => [$third->id, $hidden->id],
        ])->assertForbidden();
        $this->assertNull($third->fresh()->assigned_to);
        $this->assertNull($hidden->fresh()->assigned_to);

        $this->actingAs($manager)->patch(route('recoveries.batches.assign', $batch), [
            'assigned_to' => $officer->id,
            'scope' => 'selected',
        ])->assertSessionHasErrors('account_ids');
    }

    public function test_boa_and_udb_source_columns_keep_their_distinct_meanings(): void
    {
        $mapper = new RecoveryPortfolioMapper;
        $boa = $mapper->map([
            'Client' => '00123', 'Nam' => 'BOA Debtor', 'Total as at 10.11.25' => 65000,
            'Arrears Amount' => 7000, 'Cause of Default' => 'Job loss', 'Comments' => 'Follow up needed',
        ], 'BOA', 2);
        $udb = $mapper->map([
            'ACCOUNT NAME' => 'UDB Debtor', 'EXPOSURE' => 100000,
            'COLLATERAL HELD' => 'Property', 'AMOUNT RECOVERED IN OCTOBER' => 20000,
            'AMOUNT RECOVERED IN JAN' => 10000, 'AUGUST COMMENTS' => 'Initial visit',
        ], 'UDB', 2);

        $this->assertSame('00123', $boa['customer_number']);
        $this->assertNull($boa['account_number']);
        $this->assertEquals(65000, $boa['outstanding_amount']);
        $this->assertEquals(7000, $boa['arrears_amount']);
        $this->assertEquals(0, $boa['interest_amount']);
        $this->assertNull($boa['bucket']);
        $this->assertSame('Job loss', $boa['cause_of_default']);
        $this->assertEquals(100000, $udb['outstanding_amount']);
        $this->assertEquals(0, $udb['amount_recovered']);
        $this->assertEquals(30000, $udb['raw_payload']['_source_reported_recovery']);
        $this->assertNull($udb['bucket']);
    }

    public function test_npl_import_balances_do_not_count_arrears_as_interest(): void
    {
        $mapper = new RecoveryPortfolioMapper;
        $stanbic = $mapper->map([
            'Account Number' => 'ST-1', 'Customer Number' => 'C-1', 'Customer Name' => 'Stanbic Debtor',
            'Book Balance LCY' => 90000, 'Total Arrears' => 12000,
        ], 'Stanbic NPL', 2);
        $dfcu = $mapper->map([
            'LA TYPE' => 'LOAN', 'ACCOUNT NUMBER' => 'DF-1', 'ACCOUNT NAME' => 'DFCU Debtor',
            'GROSS BALANCE BASE' => 80000, 'ARREARS BALANCE BASE' => 11000,
            'DISBURSEMENT AMOUNT BASE' => 100000,
        ], 'DFCU NPL', 2);

        $this->assertEquals(90000, $stanbic['outstanding_amount']);
        $this->assertEquals(12000, $stanbic['arrears_amount']);
        $this->assertEquals(0, $stanbic['interest_amount']);
        $this->assertEquals(80000, $dfcu['outstanding_amount']);
        $this->assertEquals(11000, $dfcu['arrears_amount']);
        $this->assertEquals(100000, $dfcu['principal_amount']);
    }

    public function test_manager_can_import_review_assign_and_export_recovery_portfolio(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser([
            'recoveries.import',
            'recoveries.import.store',
            'recoveries.batches.show',
            'recoveries.batches.assign',
            'recoveries.accounts.export',
        ], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create([
            'name' => 'Stanbic Bank',
            'portfolio_types' => ['Stanbic Write Off'],
        ]);

        $file = $this->sampleRecoveryWorkbook();

        $response = $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Stanbic Write Off',
            'file' => $file,
        ]);

        $batch = RecoveryImportBatch::first();
        $account = RecoveryAccount::first();

        $this->assertNotNull($batch);
        $this->assertNotNull($account);
        $response->assertRedirect(route('recoveries.batches.show', $batch));
        $this->assertSame('John Debtor', $account->debtor_name);
        $this->assertSame('ACC-001', $account->account_number);
        $this->assertSame('0700000000', $account->phone);
        $this->assertFalse($account->sms_opt_in);
        $this->assertEquals(300000, (float) $account->outstanding_amount);

        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()
            ->assertSee('0700000000')
            ->assertSee('Assign to recovery officer');

        $this->actingAs($manager)->patch(route('recoveries.batches.assign', $batch), [
            'assigned_to' => $officer->id,
            'scope' => 'unassigned',
        ])->assertSessionHasNoErrors();

        $this->assertSame($officer->id, $account->fresh()->assigned_to);

        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()
            ->assertSee('Assign to recovery officer')
            ->assertSee('Selected accounts');

        $export = $this->actingAs($manager)->get(route('recoveries.accounts.export', [
            'client' => $client->id,
            'portfolio_type' => 'Stanbic Write Off',
        ]));

        $export->assertOk();
    }

    public function test_import_preview_blocks_repeat_files_and_skips_matching_account_identities(): void
    {
        Storage::fake('local');
        $manager = $this->activeUser(['recoveries.import.store', 'recoveries.batches.show', 'recoveries.batches.commit'], 'Recoveries Manager');
        $client = RecoveryClient::create(['name' => 'Preview Bank', 'portfolio_types' => ['Retail NPL']]);
        $firstCsv = "debtor_name,account_number,outstanding_amount,phone\nPreview Debtor,ACC-900,100000,0700000000\n";
        $firstPath = tempnam(sys_get_temp_dir(), 'recovery-preview-').'.csv';
        file_put_contents($firstPath, $firstCsv);

        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Retail NPL',
            '_preview' => 1,
            'file' => new UploadedFile($firstPath, 'retail.csv', 'text/csv', null, true),
        ])->assertRedirect();

        $batch = RecoveryImportBatch::firstOrFail();
        $this->assertSame('preview', $batch->status);
        $this->assertSame(0, RecoveryAccount::count());
        $this->assertSame(1, $batch->preview_counts['new']);
        $this->actingAs($manager)->get(route('recoveries.batches.show', $batch))
            ->assertOk()->assertSee('Confirm Import')->assertSee('Preview Debtor');
        $this->actingAs($manager)->post(route('recoveries.batches.commit', $batch), [
            'duplicate_action' => 'skip',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, RecoveryAccount::count());

        $repeatPath = tempnam(sys_get_temp_dir(), 'recovery-repeat-').'.csv';
        file_put_contents($repeatPath, $firstCsv);
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Retail NPL',
            'file' => new UploadedFile($repeatPath, 'repeat.csv', 'text/csv', null, true),
        ])->assertSessionHasErrors('file');

        $changedPath = tempnam(sys_get_temp_dir(), 'recovery-changed-').'.csv';
        file_put_contents($changedPath, "debtor_name,account_number,outstanding_amount,phone\nPreview Debtor,ACC-900,120000,0711111111\n");
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Retail NPL',
            '_preview' => 1,
            'file' => new UploadedFile($changedPath, 'changed.csv', 'text/csv', null, true),
        ])->assertRedirect();
        $secondBatch = RecoveryImportBatch::latest('id')->firstOrFail();
        $this->assertSame(1, $secondBatch->preview_counts['duplicates']);
        $this->actingAs($manager)->post(route('recoveries.batches.commit', $secondBatch), [
            'duplicate_action' => 'update',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(1, RecoveryAccount::count());
        $account = RecoveryAccount::firstOrFail();
        $this->assertSame('0711111111', $account->phone);
        $this->assertEquals(100000, (float) $account->outstanding_amount);

        $account->delete();
        $archivedPath = tempnam(sys_get_temp_dir(), 'recovery-archived-').'.csv';
        file_put_contents($archivedPath, "debtor_name,account_number,outstanding_amount,phone\nPreview Debtor,ACC-900,125000,0722222222\n");
        $this->actingAs($manager)->post(route('recoveries.import.store'), [
            'recovery_client_id' => $client->id,
            'portfolio_type' => 'Retail NPL',
            '_preview' => 1,
            'file' => new UploadedFile($archivedPath, 'archived.csv', 'text/csv', null, true),
        ])->assertRedirect();
        $archivedBatch = RecoveryImportBatch::latest('id')->firstOrFail();
        $this->assertSame(1, $archivedBatch->preview_counts['duplicates']);
        $this->actingAs($manager)->post(route('recoveries.batches.commit', $archivedBatch), [
            'duplicate_action' => 'update',
        ])->assertRedirect();
        $this->assertSame(0, RecoveryAccount::count());
        $this->assertSame(1, RecoveryAccount::withTrashed()->count());
    }

    public function test_officer_payment_updates_recovered_total_and_running_balance(): void
    {
        Storage::fake('local');

        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.mine', 'recoveries.mine.export', 'recoveries.show', 'recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $manager = $this->activeUser(['recoveries.reviews.decision', 'recoveries.reviews.reverse'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-2']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Jane Debtor',
            'outstanding_amount' => 1000000,
            'amount_recovered' => 0,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'amount_paid' => '250,000',
            'receipt' => UploadedFile::fake()->image('receipt-one.jpg'),
            'notes' => 'Debtor paid cash at branch.',
        ])->assertRedirect(route('recoveries.show', $account));

        $account->refresh();
        $this->assertEquals(0, (float) $account->amount_recovered);
        $firstActivity = RecoveryActivity::where('recovery_account_id', $account->id)->firstOrFail();
        $this->assertSame('pending', $firstActivity->review_status);
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $firstActivity), ['decision' => 'approve', 'review_notes' => 'Matched bank statement 1001'])->assertRedirect();
        $account->refresh();
        $this->assertEquals(250000, (float) $account->amount_recovered);
        $this->assertEquals(750000, $account->net_outstanding_balance);
        $this->assertDatabaseHas('recovery_activities', [
            'recovery_account_id' => $account->id,
            'activity_type' => 'call',
            'response_outcome' => 'paid',
            'user_id' => $officer->id,
            'outstanding_balance_after' => 750000,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'visit',
            'response_outcome' => 'paid',
            'activity_at' => now()->addDay()->format('Y-m-d\TH:i'),
            'amount_paid' => 150000,
            'receipt' => UploadedFile::fake()->image('receipt-two.jpg'),
            'notes' => 'Second instalment.',
        ])->assertRedirect(route('recoveries.show', $account));

        $secondActivity = RecoveryActivity::where('recovery_account_id', $account->id)->latest('id')->firstOrFail();
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $secondActivity), ['decision' => 'approve', 'review_notes' => 'Matched bank statement 1002'])->assertRedirect();

        $account->refresh();

        $this->assertEquals(400000, (float) $account->amount_recovered);
        $this->assertEquals(600000, $account->net_outstanding_balance);
        $this->assertDatabaseHas('recovery_activities', [
            'recovery_account_id' => $account->id,
            'amount_paid' => 150000,
            'outstanding_balance_after' => 600000,
        ]);
        $activityIds = RecoveryActivity::where('recovery_account_id', $account->id)->pluck('id');
        $this->assertSame(2, Attachment::where('attachable_type', RecoveryActivity::class)->whereIn('attachable_id', $activityIds)->count());
    }

    public function test_officer_cannot_log_payment_above_net_outstanding_balance(): void
    {
        Storage::fake('local');

        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-22']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Overpay Debtor',
            'outstanding_amount' => 1000000,
            'amount_recovered' => 900000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment',
            'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'amount_paid' => 250000,
            'notes' => 'Debtor paid cash at branch.',
        ])->assertSessionHasErrors('receipt');

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment',
            'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'amount_paid' => 150000,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            'notes' => 'Too much.',
        ])->assertSessionHasErrors('amount_paid');
    }

    public function test_new_payment_preserves_imported_opening_recovered_amount(): void
    {
        Storage::fake('local');

        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $manager = $this->activeUser(['recoveries.reviews.decision'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-23']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Opening Recovery Debtor',
            'outstanding_amount' => 1000000,
            'opening_recovered_amount' => 100000,
            'amount_recovered' => 100000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment',
            'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'amount_paid' => 250000,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'),
            'notes' => 'New instalment after imported recovery.',
        ])->assertRedirect(route('recoveries.show', $account));

        $activity = RecoveryActivity::where('recovery_account_id', $account->id)->firstOrFail();
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $activity), ['decision' => 'approve', 'review_notes' => 'Matched lender confirmation'])->assertRedirect();

        $account->refresh();

        $this->assertEquals(350000, (float) $account->amount_recovered);
        $this->assertEquals(650000, $account->net_outstanding_balance);
        $this->assertDatabaseHas('recovery_activities', [
            'recovery_account_id' => $account->id,
            'response_outcome' => 'paid',
            'amount_paid' => 250000,
            'outstanding_balance_after' => 650000,
        ]);
    }

    public function test_promise_activity_requires_promise_fields_without_receipt_or_payment(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-24']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Promise Debtor',
            'outstanding_amount' => 1000000,
            'amount_recovered' => 0,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'promised',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'notes' => 'Client promised to clear part of the balance.',
        ])->assertSessionHasErrors(['promised_amount', 'promised_on']);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'promised',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'promised_amount' => 200000,
            'promised_on' => now()->addWeek()->toDateString(),
            'notes' => 'Client promised to clear part of the balance.',
        ])->assertRedirect(route('recoveries.show', $account));

        $activity = RecoveryActivity::where('recovery_account_id', $account->id)->first();

        $this->assertEquals(200000, (float) $activity->promised_amount);
        $this->assertSame('promised', $activity->response_outcome);
        $this->assertNull($activity->amount_paid);
        $this->assertSame(0, $activity->attachments()->count());
    }

    public function test_full_repayment_closes_account_without_counting_as_officer_collection(): void
    {
        Storage::fake('local');
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $manager = $this->activeUser(['recoveries.reviews.decision'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'DFCU Bank', 'code' => 'RC-25']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Already Repaid Debtor',
            'outstanding_amount' => 1000000,
            'opening_recovered_amount' => 150000,
            'amount_recovered' => 150000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'fully_repaid',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'receipt' => UploadedFile::fake()->image('repayment-proof.jpg'),
            'notes' => 'Client provided proof that the balance was already fully repaid.',
        ])->assertRedirect(route('recoveries.show', $account));

        $account->refresh();

        $this->assertSame('active', $account->status);
        $this->assertNull($account->settled_externally_at);
        $activity = RecoveryActivity::where('recovery_account_id', $account->id)->firstOrFail();
        $this->assertSame('pending', $activity->review_status);
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $activity), ['decision' => 'approve', 'review_notes' => 'Matched lender settlement letter'])->assertRedirect();
        $account->refresh();

        $this->assertSame('closed', $account->status);
        $this->assertNotNull($account->settled_externally_at);
        $this->assertEquals(150000, (float) $account->amount_recovered);
        $this->assertEquals(0, $account->net_outstanding_balance);
        $this->assertDatabaseHas('recovery_activities', [
            'recovery_account_id' => $account->id,
            'response_outcome' => 'fully_repaid',
            'amount_paid' => null,
            'outstanding_balance_after' => 0,
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'call',
            'response_outcome' => 'no_response',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'notes' => 'This should not be logged after settlement.',
        ])->assertStatus(422);
    }

    public function test_manager_can_reverse_approved_payment_and_reject_a_correction(): void
    {
        Storage::fake('local');
        $branch = Branch::create(['name' => 'Review Branch', 'code' => 'RVB']);
        $officer = $this->activeUser(['recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $manager = $this->activeUser(['recoveries.reviews.index', 'recoveries.reviews.decision', 'recoveries.reviews.reverse'], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Review Bank', 'portfolio_types' => ['Retail']]);
        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id, 'portfolio_type' => 'Retail',
            'debtor_name' => 'Review Debtor', 'outstanding_amount' => 100000,
            'assigned_to' => $officer->id, 'branch_id' => $branch->id, 'status' => 'active',
        ]);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment', 'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'), 'amount_paid' => 100000,
            'receipt' => UploadedFile::fake()->image('receipt.jpg'), 'notes' => 'Bank deposit.',
        ])->assertRedirect();
        $activity = RecoveryActivity::firstOrFail();
        $this->assertSame('active', $account->fresh()->status);
        $this->assertEquals(0, (float) $account->fresh()->amount_recovered);
        $this->actingAs($manager)->get(route('recoveries.reviews.index'))->assertOk()->assertSee('Review Debtor');
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $activity), ['decision' => 'approve'])
            ->assertSessionHasErrors('review_notes');
        $this->assertSame('pending', $activity->fresh()->review_status);
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $activity), ['decision' => 'approve', 'review_notes' => 'Matched bank statement 2001'])->assertRedirect();
        $this->assertSame('closed', $account->fresh()->status);
        $this->assertEquals(100000, (float) $account->fresh()->amount_recovered);

        $this->actingAs($manager)->post(route('recoveries.reviews.reverse', $activity), [
            'reason' => 'Deposit was returned by the bank.',
        ])->assertRedirect();
        $this->assertSame('active', $account->fresh()->status);
        $this->assertEquals(0, (float) $account->fresh()->amount_recovered);
        $this->assertSame('reversed', $activity->fresh()->review_status);

        $this->actingAs($officer)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'payment', 'response_outcome' => 'paid',
            'activity_at' => now()->format('Y-m-d\TH:i'), 'amount_paid' => 50000,
            'receipt' => UploadedFile::fake()->image('new-receipt.jpg'), 'notes' => 'Replacement report.',
        ])->assertRedirect();
        $replacement = RecoveryActivity::latest('id')->firstOrFail();
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $replacement), [
            'decision' => 'reject', 'review_notes' => 'Amount does not match the bank statement.',
        ])->assertRedirect();
        $this->assertSame('rejected', $replacement->fresh()->review_status);
        $this->assertEquals(0, (float) $account->fresh()->amount_recovered);
        $this->actingAs($officer)->post(route('recoveries.reviews.decision', $replacement), ['decision' => 'approve'])->assertForbidden();
    }

    public function test_write_off_requires_review_and_generic_edit_cannot_close_an_account(): void
    {
        Storage::fake('local');
        $branch = Branch::create(['name' => 'Closure Branch', 'code' => 'CLB']);
        $manager = $this->activeUser([
            'recoveries.activities.store', 'recoveries.reviews.index', 'recoveries.reviews.decision', 'recoveries.update',
        ], 'Recoveries Manager', $branch->id);
        $client = RecoveryClient::create(['name' => 'Closure Bank', 'portfolio_types' => ['Retail']]);
        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id, 'portfolio_type' => 'Retail',
            'debtor_name' => 'Closure Debtor', 'outstanding_amount' => 300000,
            'branch_id' => $branch->id, 'status' => 'active',
        ]);

        $this->actingAs($manager)->put(route('recoveries.update', $account), [
            'recovery_client_id' => $client->id, 'portfolio_type' => 'Retail',
            'debtor_name' => 'Closure Debtor', 'outstanding_amount' => 300000,
            'status' => 'written_off',
        ])->assertSessionHasErrors('status');
        $this->assertSame('active', $account->fresh()->status);

        $this->actingAs($manager)->post(route('recoveries.activities.store', $account), [
            'activity_type' => 'other', 'response_outcome' => 'write_off_requested',
            'activity_at' => now()->format('Y-m-d\TH:i'),
            'receipt' => UploadedFile::fake()->create('write-off-approval.pdf', 20, 'application/pdf'),
            'notes' => 'Lender requested the account to be written off.',
        ])->assertRedirect();
        $activity = RecoveryActivity::firstOrFail();
        $this->assertSame('pending', $activity->review_status);
        $this->assertSame('active', $account->fresh()->status);
        $this->actingAs($manager)->post(route('recoveries.reviews.decision', $activity), ['decision' => 'approve', 'review_notes' => 'Matched lender write-off instruction'])->assertRedirect();
        $this->assertSame('written_off', $account->fresh()->status);
    }

    public function test_officer_only_sees_own_assigned_recoveries(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $officer = $this->activeUser(['recoveries.mine', 'recoveries.mine.export', 'recoveries.show', 'recoveries.activities.store'], 'Recovery Officer', $branch->id);
        $other = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Centenary Bank', 'code' => 'RC-3']);

        $mine = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Mine Debtor',
            'outstanding_amount' => 500000,
            'amount_recovered' => 100000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);
        RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Other Debtor',
            'status' => 'active',
            'assigned_to' => $other->id,
            'branch_id' => $branch->id,
        ]);
        RecoveryActivity::create([
            'recovery_account_id' => $mine->id,
            'user_id' => $officer->id,
            'activity_type' => 'call',
            'response_outcome' => 'paid',
            'activity_at' => now(),
            'amount_paid' => 120000,
            'outstanding_balance_after' => 280000,
            'notes' => 'Collection logged for officer report.',
        ]);
        RecoveryActivity::create([
            'recovery_account_id' => $mine->id,
            'user_id' => $officer->id,
            'activity_type' => 'visit',
            'response_outcome' => 'paid',
            'activity_at' => now()->subMonthsNoOverflow(2),
            'amount_paid' => 50000,
            'outstanding_balance_after' => 230000,
            'notes' => 'Older collection outside default report.',
        ]);

        $response = $this->actingAs($officer)->get(route('recoveries.mine'));

        $response->assertOk();
        $response->assertSee('My Collection Report');
        $response->assertSee('Group By');
        $response->assertSee('Excel');
        $response->assertSee('PDF');
        $response->assertSee('Collections by Period');
        $response->assertSee('Collections by Bank');
        $response->assertSee('Today');
        $response->assertSee('This Week');
        $response->assertSee('This Month');
        $response->assertSee('UGX 120,000.00');
        $response->assertDontSee('Older collection outside default report.');
        $response->assertSee('Mine Debtor');
        $response->assertDontSee('Other Debtor');
        $response->assertSee('Net Outstanding');
        $response->assertSee('Report Activity');
        $response->assertSee('Client Response');
        $response->assertSee('Response Notes');
        $response->assertSee('Remaining If This Payment Is Approved');
        $response->assertSee('data-current-balance="400000"', false);
        $response->assertSee('data-response-panel="paid"', false);
        $response->assertSee('data-response-panel="promised"', false);
        $response->assertSee('recovery-activity-modal-'.$mine->id, false);

        $filtered = $this->actingAs($officer)->get(route('recoveries.mine', [
            'report_grain' => 'monthly',
            'report_date_from' => now()->subMonthsNoOverflow(3)->toDateString(),
            'report_date_to' => now()->toDateString(),
            'report_client' => $client->id,
        ]));

        $filtered->assertOk();
        $filtered->assertSee('170,000.00');

        $xlsx = $this->actingAs($officer)->get(route('recoveries.mine.export', [
            'report_grain' => 'monthly',
            'report_date_from' => now()->subMonthsNoOverflow(3)->toDateString(),
            'report_date_to' => now()->toDateString(),
            'report_client' => $client->id,
            'format' => 'xlsx',
        ]));
        $xlsx->assertOk();

        $pdf = $this->actingAs($officer)->get(route('recoveries.mine.export', [
            'report_grain' => 'monthly',
            'report_date_from' => now()->subMonthsNoOverflow(3)->toDateString(),
            'report_date_to' => now()->toDateString(),
            'report_client' => $client->id,
            'format' => 'pdf',
        ]));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_manager_edits_recovery_details_separately_from_assignment(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $otherBranch = Branch::create(['name' => 'Branch B', 'code' => 'BRB']);
        $manager = $this->activeUser([
            'recoveries.show',
            'recoveries.edit',
            'recoveries.update',
            'recoveries.assignment.edit',
            'recoveries.assignment.update',
        ], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $newOfficer = $this->activeUser([], 'Recovery Officer', $otherBranch->id);
        $client = RecoveryClient::create(['name' => 'Centenary Bank', 'code' => 'RC-33']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Separate Flow Debtor',
            'outstanding_amount' => 500000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($manager)->get(route('recoveries.edit', $account))
            ->assertOk()
            ->assertSee('Update Recovery')
            ->assertSee('Change Officer')
            ->assertDontSee('Assign to Recovery Officer');

        $this->actingAs($manager)->put(route('recoveries.update', $account), [
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Updated Recovery Debtor',
            'outstanding_amount' => 600000,
            'currency' => 'UGX',
            'status' => 'active',
            'assigned_to' => $newOfficer->id,
            'branch_id' => $otherBranch->id,
        ])->assertRedirect(route('recoveries.show', $account));

        $account->refresh();

        $this->assertSame('Updated Recovery Debtor', $account->debtor_name);
        $this->assertEquals(600000, (float) $account->outstanding_amount);
        $this->assertSame($officer->id, $account->assigned_to);
        $this->assertSame($branch->id, $account->branch_id);

        $this->actingAs($manager)->get(route('recoveries.assignment.edit', $account))
            ->assertOk()
            ->assertSee('Change Recovery Officer')
            ->assertSee($officer->name);

        $this->actingAs($manager)->patch(route('recoveries.assignment.update', $account), [
            'assigned_to' => $newOfficer->id,
            'branch_id' => $otherBranch->id,
        ])->assertRedirect(route('recoveries.show', $account));

        $account->refresh();

        $this->assertSame($newOfficer->id, $account->assigned_to);
        $this->assertSame($manager->id, $account->assigned_by);
        $this->assertSame($otherBranch->id, $account->branch_id);
        $this->assertNotNull($account->assigned_at);
    }

    public function test_manager_can_view_reports_and_export(): void
    {
        $branch = Branch::create(['name' => 'Branch A', 'code' => 'BRA']);
        $manager = $this->activeUser(['recoveries.reports', 'recoveries.export'], 'Recoveries Manager', $branch->id);
        $officer = $this->activeUser([], 'Recovery Officer', $branch->id);
        $client = RecoveryClient::create(['name' => 'Bank of Africa', 'code' => 'RC-4']);

        $account = RecoveryAccount::create([
            'recovery_client_id' => $client->id,
            'debtor_name' => 'Report Debtor',
            'outstanding_amount' => 750000,
            'amount_recovered' => 100000,
            'status' => 'active',
            'assigned_to' => $officer->id,
            'branch_id' => $branch->id,
        ]);
        RecoveryActivity::create([
            'recovery_account_id' => $account->id,
            'user_id' => $officer->id,
            'activity_type' => 'payment',
            'response_outcome' => 'paid',
            'activity_at' => now(),
            'amount_paid' => 100000,
            'notes' => 'Payment captured for daily report.',
        ]);

        $this->actingAs($manager)->get(route('recoveries.reports'))
            ->assertOk()
            ->assertSee('Recovery Reports')
            ->assertSee('Whole year')
            ->assertSee('Collections in Selected Period')
            ->assertSee('Monthly Recovery Graph')
            ->assertSee('Export');

        $this->actingAs($manager)->get(route('recoveries.reports', [
            'period' => 'quarter',
            'period_value' => 2,
            'year' => now()->year,
        ]))
            ->assertOk()
            ->assertSee('Quarter 2 '.now()->year)
            ->assertSee('Monthly Recovery Graph');

        $this->actingAs($manager)->get(route('recoveries.reports', [
            'grain' => 'daily',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'client' => $client->id,
            'officer' => $officer->id,
        ]))
            ->assertOk()
            ->assertSee('Bank of Africa')
            ->assertSee($officer->name)
            ->assertSee('100,000.00');

        $pdf = $this->actingAs($manager)->get(route('recoveries.export', ['type' => 'officers', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));

        $xlsx = $this->actingAs($manager)->get(route('recoveries.export', ['type' => 'clients', 'format' => 'xlsx']));
        $xlsx->assertOk();

        $dailyExport = $this->actingAs($manager)->get(route('recoveries.export', [
            'type' => 'collections',
            'format' => 'xlsx',
            'grain' => 'daily',
            'date_from' => now()->toDateString(),
            'date_to' => now()->toDateString(),
            'client' => $client->id,
            'officer' => $officer->id,
        ]));
        $dailyExport->assertOk();
    }

    private function sampleRecoveryWorkbook(): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['Cif_id', 'foracid', 'Contacts', 'Email', 'Employer', 'acct_name', 'BRANCH', 'Region', 'INTEREST', 'PRINCIPAL', 'NET EXPOSURE', 'ACCT_CRNCY_CODE', 'Year Category', 'FEBRUARY COLLECTOR'],
            ['CIF-001', 'ACC-001', '0700000000', 'john@example.test', 'ABC Ltd', 'John Debtor', 'Kampala', 'Central', 50000, 250000, 300000, 'UGX', '2024 Write Off', 'Officer One'],
        ]);

        return $this->uploadedRecoveryWorkbook($spreadsheet);
    }

    private function uploadedRecoveryWorkbook(Spreadsheet $spreadsheet): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'recovery-import-').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile($path, 'recovery-portfolio.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }
}
