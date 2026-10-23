<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\CourtEvent;
use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\Matter;
use App\Models\PracticeArea;
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
            ->assertViewHas('totalReports', 4)
            ->assertViewHas('operationalReports', 4)
            ->assertViewHas('managementPacks', 0)
            ->assertSee('<h3>Recovery collections</h3>', false)
            ->assertDontSee('<h3>Matter workload</h3>', false);

        $this->actingAs($user)->get(route('reports.index', ['q' => 'turnaround']))
            ->assertOk()
            ->assertViewHas('totalReports', 2)
            ->assertSee('<h3>Matter turnaround</h3>', false)
            ->assertSee('<h3>Securities turnaround</h3>', false)
            ->assertDontSee('<h3>Recovery collections</h3>', false);
    }

    public function test_sidebar_compacts_reports_into_module_links_without_submenu_icons(): void
    {
        $user = $this->activeUser();
        $response = $this->actingAs($user)->get(route('reports.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $links = $xpath->query('//div[@id="sidebar-reports-analytics"]//a');
        $expectedGroups = [
            '' => 'All Reports',
            'casework' => 'Clients & Legal Work',
            'recoveries-securities' => 'Recoveries & Securities',
            'finance-payroll' => 'Finance & Payroll',
            'people' => 'HR & Attendance',
            'management' => 'Management',
        ];

        $this->assertCount(6, $links);
        foreach (array_keys($expectedGroups) as $index => $group) {
            $link = $links->item($index);
            $this->assertSame($expectedGroups[$group], trim($link->textContent));
            $this->assertSame(route('reports.index', $group === '' ? [] : ['category' => $group]), $link->getAttribute('href'));
        }
        $this->assertSame(0, $xpath->query('//div[@id="sidebar-reports-analytics"]//i')->length);
    }

    public function test_report_module_stays_selected_in_the_catalogue_and_individual_reports(): void
    {
        $user = $this->activeUser();
        $moduleUrl = route('reports.index', ['category' => 'recoveries-securities']);

        foreach ([$moduleUrl, route('reports.show', 'bank-performance')] as $url) {
            $response = $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('Recoveries &amp; Securities reports', false);
            $document = new \DOMDocument;
            @$document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $activeLinks = $xpath->query('//div[@id="sidebar-reports-analytics"]//a[@aria-current="page"]');

            $this->assertCount(1, $activeLinks);
            $this->assertSame($moduleUrl, $activeLinks->item(0)->getAttribute('href'));
        }

        $this->actingAs($user)->get(route('reports.show', 'bank-performance'))
            ->assertSee('class="kfms-report-back" href="'.e($moduleUrl).'"', false);
    }

    public function test_combined_categories_include_all_related_reports_and_keep_the_period_when_switching(): void
    {
        $user = $this->activeUser();
        $this->actingAs($user)->get(route('reports.index', ['category' => 'finance-payroll']))
            ->assertOk()->assertViewHas('totalReports', 7)
            ->assertSee('<h3>Billing totals</h3>', false)->assertSee('<h3>Payroll</h3>', false)
            ->assertDontSee('<h3>Matter workload</h3>', false);
        $response = $this->get(route('reports.show', ['report' => 'aging', 'period' => 'quarterly']))
            ->assertOk()->assertSee('Report 2 of 7');
        foreach (['billing-totals' => 'prev', 'collections' => 'next'] as $slug => $relation) {
            $url = route('reports.show', ['report' => $slug, 'category' => 'finance-payroll', 'period' => 'quarterly']);
            $response->assertSee('href="'.e($url).'" rel="'.$relation.'"', false);
        }
        $groups = collect(config('reporting.navigation_groups'))->flatMap(fn ($category) => $category['groups']);
        $this->assertCount($groups->unique()->count(), $groups);
        foreach (config('reporting.reports') as $report) {
            $this->assertTrue($groups->contains($report['group']));
        }
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
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'report_generated',
        ]);
        $generated = AuditLog::query()->where('user_id', $user->id)->where('event', 'report_generated')->firstOrFail();
        $this->assertSame('matter-workload', $generated->new_values['report']);
        $this->assertSame('weekly', $generated->new_values['period']);
        $this->assertSame('screen', $generated->new_values['format']);
        $this->assertArrayHasKey('branch_scope', $generated->new_values);

        $this->actingAs($user)->get(route('reports.export', ['report' => 'matter-workload', 'period' => 'weekly', 'format' => 'csv']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => 'report_exported',
        ]);
        $exported = AuditLog::query()->where('user_id', $user->id)->where('event', 'report_exported')->firstOrFail();
        $this->assertSame('matter-workload', $exported->new_values['report']);
        $this->assertSame('weekly', $exported->new_values['period']);
        $this->assertSame('csv', $exported->new_values['format']);

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
            'securities-custody',
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

    public function test_empty_attendance_report_does_not_render_invented_historical_periods(): void
    {
        $user = $this->activeUser();

        $analytics = app(ReportingAnalyticsService::class)->build('attendance', $user, 'monthly');
        $labels = $analytics['charts'][0]['labels'];

        $this->assertCount(1, $labels);
        $this->assertSame(now()->format('M Y'), $labels[0]);
        $this->assertSame('Current period (live data)', $analytics['period_label']);
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

    public function test_client_intake_funnel_reports_conversion_from_creation_cohorts(): void
    {
        $user = $this->activeUser();
        $base = [
            'created_by' => $user->id,
            'client_name' => 'Funnel Client',
            'legal_issue' => 'Contract advice',
            'created_at' => now()->subDays(12),
        ];

        ClientIntake::create($base + [
            'intake_no' => 'IN-REPORT-APPROVED',
            'status' => 'approved',
            'review_decision' => 'approved',
            'conflict_status' => 'cleared',
            'conflict_checked_at' => now()->subDays(10),
        ]);
        ClientIntake::create($base + [
            'intake_no' => 'IN-REPORT-INFO',
            'status' => 'more_information_needed',
            'review_decision' => 'more_information_needed',
        ]);
        ClientIntake::create($base + [
            'intake_no' => 'IN-REPORT-PENDING',
            'status' => 'pending_review',
            'review_decision' => 'pending',
        ]);

        $analytics = app(ReportingAnalyticsService::class)->build('client-intake-funnel', $user, 'monthly');

        $this->assertSame(3, $analytics['cards'][0]['value']);
        $this->assertSame(1, $analytics['cards'][1]['value']);
        $this->assertSame('33.3', $analytics['cards'][2]['value']);
        $this->assertSame(2, $analytics['cards'][3]['value']);
        $this->assertStringContainsString('cohort', strtolower($analytics['scope']));
    }

    public function test_matter_reports_match_current_lifecycle_and_calculate_turnaround(): void
    {
        $user = $this->activeUser();
        $client = Client::create(['client_no' => 'CL-MATTER-REPORT', 'name' => 'Matter Report Client', 'status' => 'active']);
        $practiceArea = PracticeArea::create(['name' => 'Commercial Litigation', 'is_active' => true]);
        $matter = fn (string $ref, string $title, string $status, $opened, $closed = null) => Matter::create([
            'client_id' => $client->id,
            'reference_no' => $ref,
            'title' => $title,
            'status' => $status,
            'opened_on' => $opened,
            'closed_on' => $closed,
            'opened_by' => $user->id,
            'privacy_status' => 'public',
        ]);

        $matter('MT-REPORT-1', 'Closed matter one', 'closed', today()->subDays(20), today()->subDays(5));
        $matter('MT-REPORT-2', 'Archived matter two', 'archived', today()->subDays(60), today()->subDays(2));
        $openMatter = $matter('MT-REPORT-3', 'Open matter', 'open', today());
        $openMatter->update(['practice_area_id' => $practiceArea->id]);
        $openMatter->assignments()->create([
            'user_id' => $user->id,
            'assignment_role' => 'partner',
            'assigned_on' => today()->toDateString(),
            'is_lead' => true,
        ]);

        $service = app(ReportingAnalyticsService::class);
        $workload = $service->build('matter-workload', $user, 'monthly');
        $turnaround = $service->build('matter-turnaround', $user, 'monthly');

        $this->assertSame(3.0, (float) $workload['cards'][0]['value']);
        $this->assertSame(1, $workload['cards'][1]['value']);
        $practiceChart = collect($workload['charts'])->firstWhere('id', 'matter-practice-areas');
        $leadChart = collect($workload['charts'])->firstWhere('id', 'matter-lead-workload');
        $this->assertSame(['Commercial Litigation'], $practiceChart['labels']);
        $this->assertSame([1], $practiceChart['datasets'][0]['data']);
        $this->assertSame([$user->name], $leadChart['labels']);
        $this->assertSame([1], $leadChart['datasets'][0]['data']);
        $this->assertSame(2, $turnaround['cards'][0]['value']);
        $this->assertSame('36.5', $turnaround['cards'][1]['value']);
        $this->assertSame('36.5', $turnaround['cards'][2]['value']);
        $this->assertSame(58.0, (float) $turnaround['cards'][3]['value']);
        $this->assertSame(array_keys(Matter::STATUSES), config('reporting.reports.matter-workload.statuses'));
        $this->assertSame('Matters closed', $turnaround['table']['columns'][0]['label']);
    }

    public function test_court_outcomes_report_completed_and_adjourned_events_with_details(): void
    {
        $user = $this->activeUser();
        $client = Client::create(['client_no' => 'CL-COURT-REPORT', 'name' => 'Court Report Client', 'status' => 'active']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT-COURT-REPORT',
            'title' => 'Court outcome matter',
            'status' => 'active',
            'opened_on' => today()->subDays(30),
            'opened_by' => $user->id,
            'privacy_status' => 'public',
        ]);
        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'completed',
            'starts_at' => now()->subDay(),
            'case_number' => 'HCCS-101',
            'outcome' => 'Adjourned for submissions',
            'next_step' => 'File written submissions',
            'next_step_due' => today()->addDays(7),
        ]);
        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'mention',
            'status' => 'adjourned',
            'starts_at' => now()->subDays(2),
            'case_number' => 'HCCS-102',
        ]);
        CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'judgment',
            'status' => 'scheduled',
            'starts_at' => now()->addDays(4),
            'outcome' => 'This is not a completed result',
        ]);

        $analytics = app(ReportingAnalyticsService::class)->build('court-outcomes', $user, 'monthly');

        $this->assertSame(2, $analytics['cards'][0]['value']);
        $this->assertSame(1, $analytics['cards'][1]['value']);
        $this->assertSame(1, $analytics['cards'][2]['value']);
        $this->assertCount(2, $analytics['table']['rows']);
        $hearingRow = collect($analytics['table']['rows'])->first(fn (array $row) => $row['values'][2] === 'Hearing');
        $this->assertNotNull($hearingRow);
        $this->assertSame('Completed', $hearingRow['values'][3]);
        $this->assertSame('Adjourned for submissions', $hearingRow['values'][4]);
    }

    public function test_litigation_dashboard_and_report_share_overdue_deadline_rules(): void
    {
        $user = $this->activeUser(['reports.index', 'reports.show', 'litigation.dashboard']);
        $client = Client::create(['client_no' => 'CL-OVERDUE-REPORT', 'name' => 'Overdue Report Client', 'status' => 'active']);
        $matter = Matter::create([
            'client_id' => $client->id,
            'reference_no' => 'MT-OVERDUE-REPORT',
            'title' => 'Overdue reporting matter',
            'status' => 'active',
            'opened_on' => today()->subDays(30),
            'opened_by' => $user->id,
            'privacy_status' => 'public',
        ]);
        $missedCourtDate = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'hearing',
            'status' => 'scheduled',
            'starts_at' => now()->subHour(),
        ]);
        $missedFollowUp = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'mention',
            'status' => 'adjourned',
            'starts_at' => now()->subDays(3),
            'next_step' => 'File submissions',
            'next_step_due' => today()->subDay(),
        ]);
        $futureFollowUp = CourtEvent::create([
            'matter_id' => $matter->id,
            'event_type' => 'mention',
            'status' => 'adjourned',
            'starts_at' => now()->subDays(3),
            'next_step' => 'Attend next mention',
            'next_step_due' => today()->addDays(5),
        ]);

        $this->assertTrue($missedCourtDate->isOverdue());
        $this->assertTrue($missedFollowUp->isOverdue());
        $this->assertFalse($futureFollowUp->isOverdue());

        $analytics = app(ReportingAnalyticsService::class)->build('litigation-deadlines', $user, 'monthly');
        $this->assertSame(2, $analytics['cards'][2]['value']);

        $this->actingAs($user)
            ->get(route('litigation.dashboard'))
            ->assertOk()
            ->assertViewHas('stats', fn (array $stats) => $stats['Overdue'] === 2);
    }
}
