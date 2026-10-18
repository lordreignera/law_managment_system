<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\ReportingAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReportingCatalogueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function activeUser(array $permissions = ['reports.index', 'reports.show']): User
    {
        $role = Role::findOrCreate('Administrator');
        foreach ($permissions as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission));
        }

        $user = User::factory()->create();
        $user->assignRole($role);
        StaffProfile::create([
            'user_id' => $user->id,
            'employment_status' => 'active',
        ]);

        return $user;
    }

    public function test_authorized_admin_can_view_the_reporting_catalogue(): void
    {
        $user = $this->activeUser();

        $response = $this->actingAs($user)->get(route('reports.index'));

        $response->assertOk();
        $response->assertSee('Reporting &amp; Analytics', false);
        $response->assertSee('Matter workload');
        $response->assertSee('Monthly management pack');
        $response->assertSee('Formula');
        $this->assertCount(25, config('reporting.reports'));
    }

    public function test_catalogue_can_be_filtered_by_group_and_search(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->get(route('reports.index', ['group' => 'Recoveries']))
            ->assertOk()
            ->assertSee('<h3>Recovery collections</h3>', false)
            ->assertDontSee('<h3>Matter workload</h3>', false);

        $this->actingAs($user)->get(route('reports.index', ['q' => 'turnaround']))
            ->assertOk()
            ->assertSee('<h3>Matter turnaround</h3>', false)
            ->assertSee('<h3>Securities turnaround</h3>', false)
            ->assertDontSee('<h3>Recovery collections</h3>', false);
    }

    public function test_report_definition_page_displays_the_reporting_contract(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->get(route('reports.show', 'payroll'))
            ->assertOk()
            ->assertSee('Payroll')
            ->assertSee('Included statuses')
            ->assertSee('Draft, void &amp; archived treatment', false)
            ->assertSee('report_generated')
            ->assertSee('Open operational report');
    }

    public function test_report_periods_table_and_csv_export_are_available(): void
    {
        $user = $this->activeUser();

        $this->actingAs($user)->get(route('reports.show', ['report' => 'matter-workload', 'period' => 'weekly']))
            ->assertOk()
            ->assertSee('Weekly')
            ->assertSee('Yearly')
            ->assertSee('Decision table')
            ->assertSee('PNG')
            ->assertSee('/reports/matter-workload/export?period=weekly', false);

        $this->actingAs($user)->get(route('reports.export', ['report' => 'matter-workload', 'period' => 'weekly', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $this->actingAs($user)->get(route('reports.export', ['report' => 'matter-workload', 'period' => 'monthly', 'format' => 'xlsx']))
            ->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        $this->actingAs($user)->get(route('reports.export', ['report' => 'matter-workload', 'period' => 'monthly', 'format' => 'pdf']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_live_module_analytics_pages_render_with_an_empty_dataset(): void
    {
        $user = $this->activeUser();
        $slugs = [
            'client-activity',
            'matter-workload',
            'litigation-deadlines',
            'recovery-collections',
            'leave-utilization',
            'attendance',
            'billing-totals',
            'collections',
            'expenses',
            'petty-cash',
            'requisitions',
            'land-title-custody',
            'title-turnaround',
            'payroll',
            'branch-comparison',
            'department-comparison',
            'monthly-management-pack',
            'annual-management-pack',
        ];

        foreach ($slugs as $slug) {
            $this->actingAs($user)->get(route('reports.show', $slug))
                ->assertOk()
                ->assertSee('Live module analytics')
                ->assertSee('Decision view');
        }
    }

    public function test_user_without_reporting_permission_is_forbidden(): void
    {
        $user = $this->activeUser([]);

        $this->actingAs($user)->get(route('reports.index'))->assertForbidden();
    }

    public function test_finance_reports_ignore_non_billable_invoice_states_and_show_real_aging(): void
    {
        $user = $this->activeUser();
        $client = Client::create(['client_no' => 'CL-REPORT', 'name' => 'Reporting Client', 'status' => 'active']);

        Invoice::create([
            'client_id' => $client->id,
            'invoice_no' => 'INV-REPORT-SENT',
            'invoice_date' => today(),
            'due_date' => Carbon::today()->subDays(100),
            'total' => 1000,
            'paid_amount' => 100,
            'status' => 'sent',
        ]);
        Invoice::create([
            'client_id' => $client->id,
            'invoice_no' => 'INV-REPORT-DRAFT',
            'invoice_date' => today(),
            'total' => 5000,
            'status' => 'draft',
        ]);
        Invoice::create([
            'client_id' => $client->id,
            'invoice_no' => 'INV-REPORT-VOID',
            'invoice_date' => today(),
            'total' => 7000,
            'status' => 'void',
        ]);

        $service = app(ReportingAnalyticsService::class);
        $billing = $service->build('billing-totals', $user, 'monthly');
        $invoiced = collect($billing['charts'][0]['datasets'])->firstWhere('label', 'Invoiced')['data'];
        $this->assertSame(1000.0, array_sum($invoiced));

        $aging = $service->build('aging', $user, 'monthly');
        $agingChart = $aging['charts'][0];
        $over90Index = array_search('90+ days', $agingChart['labels'], true);
        $this->assertNotFalse($over90Index);
        $this->assertSame(900.0, (float) $agingChart['datasets'][0]['data'][$over90Index]);
        $this->assertStringContainsString('90+ days', $aging['insight'].' '.implode(' ', $agingChart['labels']));
    }
}
