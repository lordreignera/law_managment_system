<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Branch;
use App\Models\Client;
use App\Models\ClientIntake;
use App\Models\CourtEvent;
use App\Models\Department;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\LandTitle;
use App\Models\LandTitleMilestone;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Matter;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PettyCashTransaction;
use App\Models\PublicHoliday;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\Requisition;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReportingAnalyticsService
{
    private const NAVY = '#071426';

    private const GOLD = '#c4902f';

    private const BLUE = '#2d6a9f';

    private const GREEN = '#2f7d5a';

    private const RED = '#b44c4c';

    public function build(string $slug, User $user, string $period = 'monthly'): ?array
    {
        $period = $this->normalizePeriod($period);
        $buckets = $this->buckets($period);

        $analytics = match ($slug) {
            'client-activity', 'client-intake-funnel' => $this->clients($user, $period, $buckets),
            'matter-workload', 'matter-turnaround' => $this->matters($user, $period, $buckets),
            'litigation-deadlines', 'court-outcomes' => $this->litigation($user, $period, $buckets),
            'recovery-collections', 'recovery-promises', 'bank-performance', 'recovery-officer-performance' => $this->recoveries($user, $period, $buckets),
            'leave-utilization' => $this->leave($user, $period, $buckets),
            'attendance' => $this->attendance($user, $period, $buckets),
            'land-title-custody', 'title-turnaround' => $this->securities($user, $period, $buckets),
            'billing-totals', 'aging', 'collections', 'expenses', 'petty-cash', 'requisitions' => $this->finance($slug, $user, $period, $buckets),
            'payroll' => $this->payroll($period, $buckets),
            'branch-comparison', 'department-comparison', 'monthly-management-pack', 'annual-management-pack' => $this->management($slug, $user, $period, $buckets),
            default => null,
        };

        return $this->withTable($analytics);
    }

    private function normalizePeriod(string $period): string
    {
        return match ($period) {
            'weekly', 'monthly', 'quarterly', 'yearly' => $period,
            'annual' => 'yearly',
            default => 'monthly',
        };
    }

    private function clients(User $user, string $period, array $buckets): array
    {
        $clients = Client::query()->forBranchOf($user);
        $intakes = $this->intakes($user);
        $labels = $this->labels($buckets);

        $newClients = $this->countSeries($clients, 'created_at', $buckets);
        $newIntakes = $this->countSeries($intakes, 'created_at', $buckets);
        $approvedIntakes = $this->countSeries((clone $intakes)->where('status', 'approved'), 'reviewed_at', $buckets);
        $intakeStatuses = $this->statusCounts($intakes, ['pending_review', 'more_information_needed', 'approved', 'rejected']);

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Client and intake records within your permitted branch scope.',
            'cards' => [
                $this->card('Clients in period', $this->sum($newClients), 'New client records created', self::NAVY),
                $this->card('Active clients', (clone $clients)->where('status', 'active')->count(), 'Current active register', self::BLUE),
                $this->card('Instructions worked', $this->sum($approvedIntakes), 'Instructions approved in the selected periods', self::GREEN),
                $this->card('Pending instructions', (clone $intakes)->whereIn('status', ['pending_review', 'more_information_needed'])->count(), 'Awaiting review or information', self::RED),
            ],
            'charts' => [
                $this->lineChart('client-intake-trend', 'Client and intake trend', 'New clients, new instructions and approvals by period.', $labels, [
                    $this->dataset('New clients', $newClients, self::NAVY),
                    $this->dataset('New intakes', $newIntakes, self::BLUE),
                    $this->dataset('Approved intakes', $approvedIntakes, self::GREEN),
                ]),
                $this->doughnutChart('client-intake-status', 'Instruction queue', 'Current intake status distribution.', array_keys($intakeStatuses), array_values($intakeStatuses), [self::GOLD, self::RED, self::GREEN, self::NAVY]),
                $this->barChart('client-growth', 'Client growth', 'New clients recorded in each period.', $labels, [$this->dataset('Clients', $newClients, self::BLUE)]),
            ],
            'insight' => 'Use the intake queue to decide where review capacity or follow-up is needed before instructions become active matters.',
        ];
    }

    private function matters(User $user, string $period, array $buckets): array
    {
        $matters = Matter::query()->visibleTo($user);
        $labels = $this->labels($buckets);
        $opened = $this->countSeries($matters, 'opened_on', $buckets);
        $closed = $this->countSeries((clone $matters)->whereNotNull('closed_on'), 'closed_on', $buckets);
        $statusCounts = $this->statusCounts($matters, array_keys(Matter::STATUSES));
        $pendingStatuses = ['inquiry', 'consultation', 'conflict_check', 'file_pending', 'waiting_for_client', 'waiting_for_third_party_or_court', 'billing_pending', 'under_review'];

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Matters visible to the current user, including branch and privacy rules.',
            'cards' => [
                $this->card('This month opened', (clone $matters)->whereBetween('opened_on', [now()->startOfMonth(), now()->endOfMonth()])->count(), 'New matters opened this month', self::NAVY),
                $this->card('Active matters', (clone $matters)->whereIn('status', ['open', 'planning', 'active'])->count(), 'Open work in progress', self::BLUE),
                $this->card('Pending action', (clone $matters)->whereIn('status', $pendingStatuses)->count(), 'Instructions, review or billing follow-up', self::GOLD),
                $this->card('Next month actions', (clone $matters)->whereBetween('next_action_due', [now()->addMonth()->startOfMonth(), now()->addMonth()->endOfMonth()])->count(), 'Matter actions due next month', self::RED),
            ],
            'charts' => [
                $this->lineChart('matter-trend', 'Matter movement', 'Opened and closed matters by period.', $labels, [
                    $this->dataset('Opened', $opened, self::NAVY),
                    $this->dataset('Closed', $closed, self::GREEN),
                ]),
                $this->doughnutChart('matter-status', 'Matter status mix', 'Current status distribution for visible matters.', array_keys($statusCounts), array_values($statusCounts), [self::NAVY, self::BLUE, self::GOLD, self::GREEN, self::RED]),
                $this->barChart('matter-closed', 'Closed matters', 'Matters completed in each period.', $labels, [$this->dataset('Closed', $closed, self::GREEN)]),
            ],
            'insight' => 'Compare opened, closed and pending-action volumes to see whether the active register is growing faster than the team is completing work.',
        ];
    }

    private function litigation(User $user, string $period, array $buckets): array
    {
        $events = CourtEvent::query()->whereHas('matter', fn (Builder $query) => $query->visibleTo($user));
        $labels = $this->labels($buckets);
        $scheduled = $this->countSeries((clone $events)->whereIn('status', ['scheduled', 'adjourned']), 'starts_at', $buckets);
        $completed = $this->countSeries((clone $events)->where('status', 'completed'), 'starts_at', $buckets);
        $statusCounts = $this->statusCounts($events, array_keys(CourtEvent::STATUSES));
        $typeCounts = $this->groupCounts($events, 'event_type', CourtEvent::EVENT_TYPES);

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Court events linked to matters visible to the current user.',
            'cards' => [
                $this->card('Events in period', $this->sum($scheduled) + $this->sum($completed), 'Court activity scheduled or completed', self::NAVY),
                $this->card('Upcoming 30 days', (clone $events)->open()->whereBetween('starts_at', [now(), now()->addDays(30)])->count(), 'Scheduled or adjourned events', self::BLUE),
                $this->card('Overdue', (clone $events)->where('status', 'scheduled')->where('starts_at', '<', now())->count(), 'Scheduled events past their date', self::RED),
                $this->card('Filing deadlines', (clone $events)->where('event_type', 'filing_deadline')->whereIn('status', ['scheduled', 'adjourned'])->count(), 'Open filing deadlines', self::GOLD),
            ],
            'charts' => [
                $this->lineChart('litigation-trend', 'Court workload trend', 'Scheduled/adjourned and completed events by period.', $labels, [
                    $this->dataset('Open court work', $scheduled, self::NAVY),
                    $this->dataset('Completed', $completed, self::GREEN),
                ]),
                $this->doughnutChart('litigation-status', 'Court event status', 'Current status distribution.', array_keys($statusCounts), array_values($statusCounts), [self::NAVY, self::GOLD, self::GREEN, self::RED]),
                $this->barChart('litigation-types', 'Event types', 'Court work by event type.', array_keys($typeCounts), [$this->dataset('Events', array_values($typeCounts), self::BLUE)]),
            ],
            'insight' => 'Overdue events and filing deadlines are the immediate risk indicators; use the event-type chart to plan court and drafting capacity.',
        ];
    }

    private function recoveries(User $user, string $period, array $buckets): array
    {
        $accounts = RecoveryAccount::query()->forBranchOf($user);
        $activities = RecoveryActivity::query()->whereHas('account', fn (Builder $query) => $query->forBranchOf($user));
        $labels = $this->labels($buckets);
        $collected = $this->sumSeries($activities, 'activity_at', 'amount_paid', $buckets);
        $contacts = $this->countSeries($activities, 'activity_at', $buckets);
        $pipeline = $this->groupCounts($accounts, 'pipeline_stage', RecoveryAccount::PIPELINE_STAGES);

        $officerRows = (clone $accounts)->with('assignee')->get()
            ->groupBy('assigned_to')
            ->map(fn ($rows) => [
                'label' => $rows->first()->assignee?->name ?? 'Unassigned',
                'value' => round((float) $rows->sum('amount_recovered'), 2),
            ])
            ->sortByDesc('value')
            ->take(8)
            ->values();

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Recovery accounts and activities within the permitted branch and portfolio scope.',
            'cards' => [
                $this->card('Collected in period', number_format($this->sum($collected), 2), 'Payments recorded through recovery activities', self::GREEN, true),
                $this->card('Accounts', (clone $accounts)->count(), 'Accounts in the register', self::NAVY),
                $this->card('Assigned accounts', (clone $accounts)->whereNotNull('assigned_to')->count(), 'Accounts with a recovery officer', self::BLUE),
                $this->card('Follow-ups due', (clone $accounts)->whereBetween('next_follow_up_at', [now(), now()->addDays(30)])->count(), 'Next 30-day follow-up workload', self::GOLD),
            ],
            'charts' => [
                $this->lineChart('recovery-collections', 'Collections and follow-up activity', 'Money recovered and activity volume by period.', $labels, [
                    $this->dataset('Amount collected', $collected, self::GREEN, true),
                    $this->dataset('Activities', $contacts, self::BLUE),
                ]),
                $this->doughnutChart('recovery-pipeline', 'Recovery pipeline', 'Accounts by current pipeline stage.', array_keys($pipeline), array_values($pipeline), [self::NAVY, self::BLUE, self::GOLD, self::GREEN, self::RED]),
                $this->barChart('recovery-officers', 'Officer recovery value', 'Cumulative recovered value by assigned officer.', $officerRows->pluck('label')->all(), [$this->dataset('Recovered', $officerRows->pluck('value')->all(), self::NAVY, true)]),
            ],
            'insight' => 'Use collected value with activity volume to separate productive follow-up from contact effort that has not yet converted to payment.',
        ];
    }

    private function leave(User $user, string $period, array $buckets): array
    {
        $leave = $this->leaveRequests($user);
        $staff = $this->staffScope($user);
        $labels = $this->labels($buckets);
        $approvedDays = $this->sumSeries($leave->where('status', 'approved'), 'start_date', 'days', $buckets, true);
        $submittedDays = $this->sumSeries((clone $leave)->where('status', 'submitted'), 'start_date', 'days', $buckets, true);
        $statusCounts = $this->statusCounts($leave, array_keys(LeaveRequest::STATUSES));
        $annualType = LeaveType::query()->where('name', 'Annual Leave')->first();
        $staffRows = (clone $staff)->get();
        $unusedStaff = 0;
        $remainingDays = 0.0;
        if ($annualType) {
            foreach ($staffRows as $staffMember) {
                $used = LeaveRequest::scheduledDaysFor($staffMember, $annualType->id, now()->year);
                $remaining = max(0, LeaveRequest::entitlementFor($staffMember, $annualType, now()->year) - $used);
                $remainingDays += $remaining;
                $unusedStaff += $remaining > 0 ? 1 : 0;
            }
        }

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Leave requests for active staff in the permitted branch scope.',
            'cards' => [
                $this->card('Approved days', number_format($this->sum($approvedDays), 1), 'Approved leave days in period', self::GREEN),
                $this->card('Pending requests', (clone $leave)->where('status', 'submitted')->count(), 'Requests still in approval', self::GOLD),
                $this->card('On leave today', (clone $leave)->where('status', 'approved')->whereDate('start_date', '<=', today())->whereDate('end_date', '>=', today())->distinct('user_id')->count('user_id'), 'Staff currently away', self::BLUE),
                $this->card('Staff with balance', $unusedStaff, 'Active staff with unused annual leave', self::NAVY, false, number_format($remainingDays, 1).' days remain'),
            ],
            'charts' => [
                $this->lineChart('leave-days', 'Leave utilization trend', 'Approved and pending leave days by period.', $labels, [
                    $this->dataset('Approved days', $approvedDays, self::GREEN),
                    $this->dataset('Pending days', $submittedDays, self::GOLD),
                ]),
                $this->doughnutChart('leave-status', 'Leave request status', 'Current request status distribution.', array_keys($statusCounts), array_values($statusCounts), [self::GOLD, self::GREEN, self::RED, self::NAVY]),
                $this->barChart('leave-request-volume', 'Leave requests', 'Requests submitted in each period.', $labels, [$this->dataset('Requests', $this->countSeries($leave, 'created_at', $buckets), self::BLUE)]),
            ],
            'insight' => 'The remaining-balance card shows staff who have not scheduled their annual entitlement; pair it with pending approvals before approving new leave.',
        ];
    }

    private function attendance(User $user, string $period, array $buckets): array
    {
        $staff = $this->staffScope($user)->get();
        $staffIds = $staff->pluck('id');
        $records = AttendanceRecord::query()->whereIn('user_id', $staffIds);
        $labels = $this->labels($buckets);
        $present = [];
        $late = [];
        $incomplete = [];
        $absent = [];

        foreach ($buckets as $bucket) {
            $rows = (clone $records)->whereBetween('attendance_date', [$bucket['start']->toDateString(), $bucket['end']->toDateString()])->get();
            $present[] = $rows->whereIn('status', ['present', 'corrected'])->count();
            $late[] = $rows->where('status', 'late')->count();
            $incomplete[] = $rows->where('status', 'incomplete')->count();
            $expected = $staff->count() * $this->workingDays($bucket['start'], $bucket['end']);
            $leaveDays = $this->approvedLeaveDaysForStaff($staffIds, $bucket['start'], $bucket['end']);
            $worked = $rows->whereNotNull('checked_in_at')->count();
            $absent[] = max(0, $expected - $leaveDays - $worked);
        }

        $currentRecords = (clone $records)->whereBetween('attendance_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]);
        $expectedCurrent = $staff->count() * $this->workingDays(now()->startOfMonth(), now()->endOfMonth());
        $workedCurrent = (clone $currentRecords)->whereNotNull('checked_in_at')->count();
        $leaveCurrent = $this->approvedLeaveDaysForStaff($staffIds, now()->startOfMonth(), now()->endOfMonth());
        $attendanceRate = $expectedCurrent > 0 ? round(($workedCurrent / max(1, $expectedCurrent - $leaveCurrent)) * 100, 1) : 0;

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Active staff attendance records within the permitted branch scope.',
            'cards' => [
                $this->card('Attendance rate', $attendanceRate.'%', 'Current month check-in rate excluding approved leave', self::GREEN),
                $this->card('Staff tracked', $staff->count(), 'Active staff in scope', self::NAVY),
                $this->card('Late entries', array_sum($late), 'Late check-ins in selected periods', self::GOLD),
                $this->card('Incomplete days', array_sum($incomplete), 'Checked in without a checkout', self::RED),
            ],
            'charts' => [
                $this->barChart('attendance-status', 'Attendance by period', 'Present, late, incomplete and derived absence counts.', $labels, [
                    $this->dataset('Present', $present, self::GREEN),
                    $this->dataset('Late', $late, self::GOLD),
                    $this->dataset('Incomplete', $incomplete, self::BLUE),
                    $this->dataset('Absent', $absent, self::RED),
                ], true),
                $this->lineChart('attendance-rate', 'Attendance trend', 'Recorded check-ins compared with expected workdays.', $labels, [
                    $this->dataset('Recorded check-ins', $present, self::NAVY),
                    $this->dataset('Absent', $absent, self::RED),
                ]),
            ],
            'insight' => 'Incomplete days are operationally different from absence: they require checkout correction or manager review before payroll and attendance decisions.',
        ];
    }

    private function finance(string $slug, User $user, string $period, array $buckets): array
    {
        $labels = $this->labels($buckets);
        $title = match ($slug) {
            'billing-totals', 'aging' => 'Billing totals',
            'collections' => 'Collections',
            'expenses' => 'Expenses',
            'petty-cash' => 'Petty cash',
            default => 'Requisitions',
        };

        if (in_array($slug, ['billing-totals', 'aging'], true)) {
            $invoices = Invoice::query()
                ->whereIn('status', ['sent', 'part_paid', 'paid', 'overdue'])
                ->whereHas('client', fn (Builder $query) => $query->forBranchOf($user));

            if ($slug === 'aging') {
                $asOf = today();
                $bucketsByName = [
                    'No due date' => fn ($invoice) => $invoice->due_date === null,
                    'Current' => fn ($invoice) => $invoice->due_date !== null && $invoice->due_date->greaterThanOrEqualTo($asOf),
                    '1-30 days' => fn ($invoice) => $invoice->due_date !== null && $invoice->due_date->lt($asOf) && $invoice->due_date->diffInDays($asOf) <= 30,
                    '31-60 days' => fn ($invoice) => $invoice->due_date !== null && $invoice->due_date->diffInDays($asOf) > 30 && $invoice->due_date->diffInDays($asOf) <= 60,
                    '61-90 days' => fn ($invoice) => $invoice->due_date !== null && $invoice->due_date->diffInDays($asOf) > 60 && $invoice->due_date->diffInDays($asOf) <= 90,
                    '90+ days' => fn ($invoice) => $invoice->due_date !== null && $invoice->due_date->diffInDays($asOf) > 90,
                ];
                $agingRows = (clone $invoices)->get()->groupBy(function (Invoice $invoice) use ($bucketsByName) {
                    foreach ($bucketsByName as $label => $rule) {
                        if ($rule($invoice)) {
                            return $label;
                        }
                    }

                    return 'Current';
                });
                $agingLabels = array_keys($bucketsByName);
                $agingValues = array_map(fn (string $label) => round((float) $agingRows->get($label, collect())->sum(fn (Invoice $invoice) => $invoice->balance), 2), $agingLabels);

                return [
                    'period' => $period,
                    'period_label' => $this->periodLabel($period),
                    'scope' => 'Outstanding eligible invoices as at '.today()->toDateString().' within the permitted branch scope.',
                    'cards' => [
                        $this->card('Outstanding', number_format(array_sum($agingValues), 2), 'Eligible invoice balance at the as-of date', self::RED, true),
                        $this->card('Invoices aged', (clone $invoices)->count(), 'Eligible unpaid or partially paid invoices', self::NAVY),
                        $this->card('Over 90 days', number_format($agingValues[array_search('90+ days', $agingLabels, true)] ?? 0, 2), 'Balance requiring escalation', self::RED, true),
                        $this->card('No due date', number_format($agingValues[array_search('No due date', $agingLabels, true)] ?? 0, 2), 'Balances requiring invoice clean-up', self::GOLD, true),
                    ],
                    'charts' => [
                        $this->barChart('invoice-aging', 'Receivables aging', 'Outstanding balance by due-date bucket at the as-of date.', $agingLabels, [$this->dataset('Outstanding', $agingValues, self::NAVY, true)]),
                        $this->doughnutChart('invoice-aging-mix', 'Aging mix', 'Share of outstanding balance by age bucket.', $agingLabels, $agingValues, [self::BLUE, self::GREEN, self::GOLD, self::RED, '#8b3a62', '#4b5563']),
                    ],
                    'insight' => 'Prioritize the 90+ day balance, then resolve invoices without due dates so collection work is based on reliable aging data.',
                ];
            }

            $invoiced = $this->sumSeries($invoices, 'invoice_date', 'total', $buckets, true);
            $paid = $this->sumSeries($invoices, 'invoice_date', 'paid_amount', $buckets, true);
            $outstanding = array_map(fn ($total, $index) => max(0, (float) $total - (float) ($paid[$index] ?? 0)), $invoiced, array_keys($invoiced));

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'Invoices linked to clients in the permitted branch scope.',
                'cards' => [
                    $this->card('Invoiced', number_format($this->sum($invoiced), 2), 'Invoice totals in period', self::NAVY, true),
                    $this->card('Collected', number_format($this->sum($paid), 2), 'Paid amount allocated to invoices', self::GREEN, true),
                    $this->card('Outstanding', number_format($this->sum($outstanding), 2), 'Unpaid invoice balance', self::RED, true),
                    $this->card('Overdue invoices', (clone $invoices)->whereIn('status', ['sent', 'part_paid'])->whereNotNull('due_date')->whereDate('due_date', '<', today())->whereColumn('paid_amount', '<', 'total')->count(), 'Invoices requiring follow-up', self::GOLD),
                ],
                'charts' => [
                    $this->lineChart('billing-trend', 'Billing and collection trend', 'Invoice totals, payments and outstanding balance by period.', $labels, [
                        $this->dataset('Invoiced', $invoiced, self::NAVY, true),
                        $this->dataset('Collected', $paid, self::GREEN, true),
                        $this->dataset('Outstanding', $outstanding, self::RED, true),
                    ]),
                    $this->barChart('billing-balance', 'Outstanding balance', 'Outstanding balance at each period.', $labels, [$this->dataset('Outstanding', $outstanding, self::RED, true)]),
                ],
                'insight' => 'Use the outstanding line with the overdue KPI to prioritize collection calls and avoid confusing invoices raised with cash actually received.',
            ];
        }

        if ($slug === 'collections') {
            $payments = InvoicePayment::query()
                ->whereHas('invoice', fn (Builder $invoice) => $invoice
                    ->whereIn('status', ['sent', 'part_paid', 'paid', 'overdue'])
                    ->whereHas('client', fn (Builder $query) => $query->forBranchOf($user)));
            $collected = $this->sumSeries($payments, 'paid_on', 'amount', $buckets, true);

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'Posted invoice payments linked to clients in the permitted branch scope.',
                'cards' => [
                    $this->card('Collected', number_format($this->sum($collected), 2), 'Payments received in period', self::GREEN, true),
                    $this->card('Payments recorded', (clone $payments)->count(), 'Payment entries in scope', self::NAVY),
                    $this->card('Average payment', number_format((float) ((clone $payments)->avg('amount') ?: 0), 2), 'Average payment entry', self::BLUE, true),
                    $this->card('Payment modes', (clone $payments)->whereNotNull('payment_method')->distinct('payment_method')->count('payment_method'), 'Configured modes used', self::GOLD),
                ],
                'charts' => [$this->barChart('collections-trend', 'Collections trend', 'Cash received by payment date.', $labels, [$this->dataset('Collected', $collected, self::GREEN, true)])],
                'insight' => 'Collections use payment date, while billing uses invoice date. Keeping these lines separate gives management a reliable cash view.',
            ];
        }

        if ($slug === 'expenses') {
            $expenses = $this->expenses($user);
            $spent = $this->sumSeries($expenses, 'spent_on', 'amount', $buckets, true);
            $categoryCounts = (clone $expenses)->with('category')->get()->groupBy(fn ($expense) => $expense->category?->name ?? 'Uncategorised')->map->sum('amount')->sortDesc()->take(8);

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'Expenses recorded by users or matters in the permitted branch scope.',
                'cards' => [
                    $this->card('Spent', number_format($this->sum($spent), 2), 'Expense amount incurred', self::RED, true),
                    $this->card('Expense entries', (clone $expenses)->count(), 'Recorded expenses in scope', self::NAVY),
                    $this->card('Average expense', number_format((float) ((clone $expenses)->avg('amount') ?: 0), 2), 'Average recorded amount', self::BLUE, true),
                    $this->card('Categories used', $categoryCounts->count(), 'Expense categories represented', self::GOLD),
                ],
                'charts' => [
                    $this->lineChart('expense-trend', 'Expense trend', 'Expense amount by period.', $labels, [$this->dataset('Expenses', $spent, self::RED, true)]),
                    $this->barChart('expense-categories', 'Expense categories', 'Highest spend categories in scope.', $categoryCounts->keys()->all(), [$this->dataset('Amount', $categoryCounts->values()->map(fn ($value) => round((float) $value, 2))->all(), self::NAVY, true)]),
                ],
                'insight' => 'Review categories with a rising trend alongside requisitions and petty-cash disbursements to understand committed versus realized spend.',
            ];
        }

        if ($slug === 'petty-cash') {
            $transactions = $this->pettyCash($user);
            $inflows = $this->sumSeries((clone $transactions)->where('type', 'top_up'), 'transacted_on', 'amount', $buckets, true);
            $outflows = $this->sumSeries((clone $transactions)->where('type', 'disbursement'), 'transacted_on', 'amount', $buckets, true);
            $balance = array_map(fn ($inflow, $index) => round($this->sum(array_slice($inflows, 0, $index + 1)) - $this->sum(array_slice($outflows, 0, $index + 1)), 2), $inflows, array_keys($inflows));

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'Petty-cash transactions recorded within the permitted branch scope.',
                'cards' => [
                    $this->card('Current balance', number_format(PettyCashTransaction::balance(), 2), 'All non-deleted petty-cash transactions', self::NAVY, true),
                    $this->card('Top-ups', number_format($this->sum($inflows), 2), 'Float added in period', self::GREEN, true),
                    $this->card('Disbursements', number_format($this->sum($outflows), 2), 'Cash issued in period', self::RED, true),
                    $this->card('Transactions', (clone $transactions)->count(), 'Top-ups and disbursements', self::BLUE),
                ],
                'charts' => [$this->barChart('petty-cash-trend', 'Petty-cash movement', 'Top-ups, disbursements and running balance.', $labels, [
                    $this->dataset('Top-ups', $inflows, self::GREEN, true),
                    $this->dataset('Disbursements', $outflows, self::RED, true),
                    $this->dataset('Running balance', $balance, self::NAVY, true),
                ])],
                'insight' => 'The running balance is calculated from top-ups and disbursements; reconcile it to the physical cash count before closing each period.',
            ];
        }

        $requisitions = $this->requisitions($user);
        $requested = $this->sumSeries($requisitions, 'created_at', 'amount', $buckets);
        $statusCounts = $this->statusCounts($requisitions, array_keys(Requisition::STATUSES));

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Requisitions raised by users or matters in the permitted branch scope.',
            'cards' => [
                $this->card('Requested', number_format($this->sum($requested), 2), 'Requisition value in period', self::NAVY, true),
                $this->card('Pending approval', (clone $requisitions)->where('status', 'submitted')->count(), 'Requests awaiting a decision', self::GOLD),
                $this->card('Approved value', number_format((float) (clone $requisitions)->where('status', 'approved')->sum('amount'), 2), 'Approved requisition value', self::GREEN, true),
                $this->card('Rejected/cancelled', (clone $requisitions)->whereIn('status', ['rejected', 'cancelled'])->count(), 'Requests not proceeding', self::RED),
            ],
            'charts' => [
                $this->barChart('requisition-trend', 'Requisition value', 'Requested spend by period.', $labels, [$this->dataset('Requested', $requested, self::NAVY, true)]),
                $this->doughnutChart('requisition-status', 'Requisition status', 'Current workflow status.', array_keys($statusCounts), array_values($statusCounts), [self::GOLD, self::GREEN, self::RED, self::BLUE]),
            ],
            'insight' => 'Pending approval value is the immediate commitment indicator; compare it with approved and realized expense totals before releasing funds.',
        ];
    }

    private function securities(User $user, string $period, array $buckets): array
    {
        $titles = LandTitle::query()->forBranchOf($user);
        $labels = $this->labels($buckets);
        $received = $this->countSeries((clone $titles)->whereNotNull('received_at'), 'received_at', $buckets);
        $returned = $this->countSeries((clone $titles)->whereNotNull('returned_at'), 'returned_at', $buckets);
        $statusCounts = $this->statusCounts($titles, array_keys(LandTitle::STATUSES));
        $turnaround = [];
        $completedTitles = (clone $titles)->whereNotNull('received_at')->whereNotNull('returned_at')->get(['received_at', 'returned_at']);
        foreach ($buckets as $bucket) {
            $durations = $completedTitles
                ->filter(fn (LandTitle $title) => $title->returned_at->between($bucket['start'], $bucket['end']))
                ->map(fn (LandTitle $title) => $title->received_at->diffInDays($title->returned_at));
            $turnaround[] = $durations->isEmpty() ? 0 : round((float) $durations->avg(), 1);
        }

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Securities custody records within the permitted branch scope.',
            'cards' => [
                $this->card('In custody', (clone $titles)->whereIn('status', ['received', 'in_progress', 'dispatched'])->count(), 'Securities currently held or moving', self::NAVY),
                $this->card('Received in period', $this->sum($received), 'Incoming security custody records', self::BLUE),
                $this->card('Returned in period', $this->sum($returned), 'Completed security custody returns', self::GREEN),
                $this->card('Overdue milestones', LandTitleMilestone::query()->whereHas('landTitle', fn (Builder $query) => $query->forBranchOf($user))->where('status', 'pending')->where('due_at', '<', now())->count(), 'Custody actions past due', self::RED),
            ],
            'charts' => [
                $this->lineChart('securities-movement', 'Custody movement', 'Titles received and returned by period.', $labels, [
                    $this->dataset('Received', $received, self::BLUE),
                    $this->dataset('Returned', $returned, self::GREEN),
                ]),
                $this->doughnutChart('securities-status', 'Custody status', 'Current security status distribution.', array_keys($statusCounts), array_values($statusCounts), [self::GOLD, self::BLUE, self::NAVY, self::GREEN, self::RED]),
                $this->barChart('securities-turnaround', 'Securities turnaround', 'Average days from receipt to return.', $labels, [$this->dataset('Average days', $turnaround, self::NAVY)]),
            ],
            'insight' => 'Use custody status with turnaround days to identify securities that are accumulating in progress or missing a completed return movement.',
        ];
    }

    private function payroll(string $period, array $buckets): array
    {
        $periods = PayrollPeriod::query();
        $runs = PayrollRun::query()->whereIn('status', ['approved', 'paid'])->with('period');
        $labels = $this->labels($buckets);
        $gross = [];
        $net = [];
        $deductions = [];
        foreach ($buckets as $bucket) {
            $runRows = (clone $runs)->whereHas('period', fn (Builder $query) => $query->whereBetween('starts_on', [$bucket['start']->toDateString(), $bucket['end']->toDateString()]))->get();
            $totals = $runRows->map(fn (PayrollRun $run) => $run->totals ?? [])->filter()->values();
            $gross[] = round((float) $totals->sum(fn ($total) => (float) ($total['gross_pay'] ?? $total['gross'] ?? 0)), 2);
            $net[] = round((float) $totals->sum(fn ($total) => (float) ($total['net_pay'] ?? $total['net'] ?? 0)), 2);
            $deductions[] = round((float) $totals->sum(fn ($total) => (float) ($total['employee_deductions'] ?? $total['deductions'] ?? 0)), 2);
        }

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Payroll periods and approved/paid runs. Payroll data remains restricted to payroll and administrator permissions.',
            'cards' => [
                $this->card('Payroll periods', (clone $periods)->count(), 'Configured payroll periods', self::NAVY),
                $this->card('Approved runs', (clone $runs)->whereIn('status', ['approved', 'paid'])->count(), 'Runs cleared for payment', self::GREEN),
                $this->card('Pending approval', (clone $runs)->where('status', 'awaiting_approval')->count(), 'Runs awaiting review', self::GOLD),
                $this->card('Voided runs', (clone $runs)->where('status', 'voided')->count(), 'Excluded from finalized totals', self::RED),
            ],
            'charts' => [$this->barChart('payroll-trend', 'Payroll movement', 'Gross pay, deductions and net pay by payroll period.', $labels, [
                $this->dataset('Gross pay', $gross, self::NAVY, true),
                $this->dataset('Deductions', $deductions, self::GOLD, true),
                $this->dataset('Net pay', $net, self::GREEN, true),
            ])],
            'insight' => 'Only approved and paid runs should feed a finalized management pack; draft, calculated and voided runs remain workflow indicators.',
        ];
    }

    private function management(string $slug, User $user, string $period, array $buckets): array
    {
        $labels = $this->labels($buckets);
        $clients = Client::query()->forBranchOf($user);
        $matters = Matter::query()->visibleTo($user);
        $events = CourtEvent::query()->whereHas('matter', fn (Builder $query) => $query->visibleTo($user));
        $activities = RecoveryActivity::query()->whereHas('account', fn (Builder $query) => $query->forBranchOf($user));
        $newClients = $this->countSeries($clients, 'created_at', $buckets);
        $openedMatters = $this->countSeries($matters, 'opened_on', $buckets);
        $courtEvents = $this->countSeries($events, 'starts_at', $buckets);
        $recovered = $this->sumSeries($activities, 'activity_at', 'amount_paid', $buckets);

        if ($slug === 'branch-comparison') {
            $branches = Branch::query()
                ->when(! $user->canSeeAllBranches(), fn (Builder $query) => $query->whereKey($user->branch_id))
                ->orderBy('name')
                ->get();
            $branchLabels = $branches->pluck('name')->all();
            $clientCounts = $branches->map(fn (Branch $branch) => (clone $clients)->where('branch_id', $branch->id)->count())->all();
            $matterCounts = $branches->map(fn (Branch $branch) => (clone $matters)->where('branch_id', $branch->id)->count())->all();
            $staffCounts = $branches->map(fn (Branch $branch) => (clone $this->staffScope($user))->where('branch_id', $branch->id)->count())->all();

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'Firm-wide operating indicators grouped by branch within the permitted scope.',
                'cards' => [
                    $this->card('Branches', $branches->count(), 'Branches included in the comparison', self::NAVY),
                    $this->card('Clients in period', $this->sum($newClients), 'New client records across the period', self::BLUE),
                    $this->card('Matters opened', $this->sum($openedMatters), 'Matters opened across the period', self::GOLD),
                    $this->card('Recovered', number_format($this->sum($recovered), 2), 'Recovery activity value across the period', self::GREEN, true),
                ],
                'charts' => [
                    $this->barChart('branch-workload', 'Branch workload', 'Clients, matters and active staff by branch.', $branchLabels, [
                        $this->dataset('Clients', $clientCounts, self::BLUE),
                        $this->dataset('Matters', $matterCounts, self::NAVY),
                        $this->dataset('Staff', $staffCounts, self::GOLD),
                    ], false, 'Branch'),
                    $this->lineChart('branch-trend', 'Firm movement', 'New clients, matters and court events by period.', $labels, [
                        $this->dataset('Clients', $newClients, self::BLUE),
                        $this->dataset('Matters', $openedMatters, self::NAVY),
                        $this->dataset('Court events', $courtEvents, self::GOLD),
                    ]),
                ],
                'insight' => 'Use branch workload beside the movement trend to identify where capacity, intake or recovery follow-up is falling behind.',
            ];
        }

        if ($slug === 'department-comparison') {
            $departments = Department::query()
                ->when(! $user->canSeeAllBranches(), fn (Builder $query) => $query->where('branch_id', $user->branch_id))
                ->orderBy('name')
                ->get();
            $departmentLabels = $departments->pluck('name')->all();
            $staffCounts = $departments->map(fn (Department $department) => (clone $this->staffScope($user))->where('department_id', $department->id)->count())->all();
            $attendanceCounts = $departments->map(function (Department $department) use ($user): int {
                return AttendanceRecord::query()
                    ->whereHas('user', fn (Builder $query) => $query->where('department_id', $department->id))
                    ->whereNotNull('checked_in_at')
                    ->whereMonth('attendance_date', now()->month)
                    ->whereYear('attendance_date', now()->year)
                    ->when(! $user->canSeeAllBranches(), fn (Builder $query) => $query->whereHas('user', fn (Builder $staff) => $staff->where('branch_id', $user->branch_id)))
                    ->count();
            })->all();

            return [
                'period' => $period,
                'period_label' => $this->periodLabel($period),
                'scope' => 'People and attendance indicators grouped by department within the permitted branch scope.',
                'cards' => [
                    $this->card('Departments', $departments->count(), 'Departments included in the comparison', self::NAVY),
                    $this->card('Staff tracked', $this->staffScope($user)->count(), 'Active staff in scope', self::BLUE),
                    $this->card('Attendance entries', array_sum($attendanceCounts), 'Current-month check-ins recorded', self::GREEN),
                    $this->card('Court events', $this->sum($courtEvents), 'Court activity across the period', self::GOLD),
                ],
                'charts' => [
                    $this->barChart('department-workload', 'Department capacity', 'Active staff and current-month attendance by department.', $departmentLabels, [
                        $this->dataset('Staff', $staffCounts, self::NAVY),
                        $this->dataset('Attendance entries', $attendanceCounts, self::GREEN),
                    ], false, 'Department'),
                    $this->lineChart('department-trend', 'Operating movement', 'New clients, matters and court events by period.', $labels, [
                        $this->dataset('Clients', $newClients, self::BLUE),
                        $this->dataset('Matters', $openedMatters, self::NAVY),
                        $this->dataset('Court events', $courtEvents, self::GOLD),
                    ]),
                ],
                'insight' => 'Compare staff capacity with attendance entries, then use the movement trend to decide where work should be reassigned or escalated.',
            ];
        }

        return [
            'period' => $period,
            'period_label' => $this->periodLabel($period),
            'scope' => 'Cross-module management indicators from the current operational records.',
            'cards' => [
                $this->card('New clients', $this->sum($newClients), 'Client records created in the period', self::BLUE),
                $this->card('Matters opened', $this->sum($openedMatters), 'Matters opened in the period', self::NAVY),
                $this->card('Court events', $this->sum($courtEvents), 'Court activity in the period', self::GOLD),
                $this->card('Recovered', number_format($this->sum($recovered), 2), 'Recovery activity value in the period', self::GREEN, true),
            ],
            'charts' => [
                $this->lineChart('management-trend', 'Management movement', 'Clients, matters and court events by period.', $labels, [
                    $this->dataset('Clients', $newClients, self::BLUE),
                    $this->dataset('Matters', $openedMatters, self::NAVY),
                    $this->dataset('Court events', $courtEvents, self::GOLD),
                ]),
                $this->barChart('management-recovery', 'Recovery value', 'Recovery activity value by period.', $labels, [$this->dataset('Recovered', $recovered, self::GREEN, true)]),
            ],
            'insight' => 'Use the pack to read movement across intake, legal work, court activity and recoveries before opening the detailed module report for action.',
        ];
    }

    private function intakes(User $user): Builder
    {
        $query = ClientIntake::query();
        if (! $user->canSeeAllBranches()) {
            $query->where(function (Builder $query) use ($user) {
                $query
                    ->whereHas('client', fn (Builder $client) => $client->where('branch_id', $user->branch_id))
                    ->orWhereHas('creator', fn (Builder $creator) => $creator->where('branch_id', $user->branch_id));
            });
        }

        return $query;
    }

    private function leaveRequests(User $user): Builder
    {
        return LeaveRequest::query()->whereHas('user', function (Builder $query) use ($user) {
            $query->whereHas('staffProfile', fn (Builder $staff) => $staff->where('employment_status', 'active'))
                ->when(! $user->canSeeAllBranches(), fn (Builder $staff) => $staff->where('branch_id', $user->branch_id));
        });
    }

    private function staffScope(User $user): Builder
    {
        return User::query()
            ->whereHas('staffProfile', fn (Builder $query) => $query->where('employment_status', 'active'))
            ->when(! $user->canSeeAllBranches(), fn (Builder $query) => $query->where('branch_id', $user->branch_id));
    }

    private function expenses(User $user): Builder
    {
        $query = Expense::query();
        if (! $user->canSeeAllBranches()) {
            $query->where(function (Builder $query) use ($user) {
                $query
                    ->whereHas('matter', fn (Builder $matter) => $matter->where('branch_id', $user->branch_id))
                    ->orWhereHas('recorder', fn (Builder $recorder) => $recorder->where('branch_id', $user->branch_id));
            });
        }

        return $query;
    }

    private function pettyCash(User $user): Builder
    {
        $query = PettyCashTransaction::query();
        if (! $user->canSeeAllBranches()) {
            $query->whereHas('recorder', fn (Builder $recorder) => $recorder->where('branch_id', $user->branch_id));
        }

        return $query;
    }

    private function requisitions(User $user): Builder
    {
        $query = Requisition::query();
        if (! $user->canSeeAllBranches()) {
            $query->where(function (Builder $query) use ($user) {
                $query
                    ->whereHas('requester', fn (Builder $requester) => $requester->where('branch_id', $user->branch_id))
                    ->orWhereHas('matter', fn (Builder $matter) => $matter->where('branch_id', $user->branch_id));
            });
        }

        return $query;
    }

    private function countSeries(Builder $query, string $column, array $buckets): array
    {
        return array_map(fn (array $bucket) => (clone $query)->whereBetween($column, [$bucket['start'], $bucket['end']])->count(), $buckets);
    }

    private function sumSeries(Builder $query, string $dateColumn, string $sumColumn, array $buckets, bool $dateOnly = false): array
    {
        return array_map(function (array $bucket) use ($query, $dateColumn, $sumColumn, $dateOnly): float {
            $builder = clone $query;
            $value = $dateOnly
                ? $builder->whereBetween($dateColumn, [$bucket['start']->toDateString(), $bucket['end']->toDateString()])->sum($sumColumn)
                : $builder->whereBetween($dateColumn, [$bucket['start'], $bucket['end']])->sum($sumColumn);

            return round((float) $value, 2);
        }, $buckets);
    }

    private function statusCounts(Builder $query, array $statuses): array
    {
        return collect($statuses)->mapWithKeys(fn (string $status) => [$status => (clone $query)->where('status', $status)->count()])->all();
    }

    private function groupCounts(Builder $query, string $column, array $labels): array
    {
        return collect($labels)->mapWithKeys(fn (string $label, string $key) => [$label => (clone $query)->where($column, $key)->count()])->filter(fn ($value) => $value > 0)->all();
    }

    private function approvedLeaveDaysForStaff($staffIds, Carbon $start, Carbon $end): float
    {
        return (float) LeaveRequest::query()
            ->whereIn('user_id', $staffIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end)
            ->whereDate('end_date', '>=', $start)
            ->sum('days');
    }

    private function workingDays(Carbon $start, Carbon $end): int
    {
        $holidays = collect();
        for ($month = $start->copy()->startOfMonth(); $month->lte($end); $month->addMonth()) {
            $holidays = $holidays->merge(PublicHoliday::forMonth($month->year, $month->month)->keys());
        }

        $days = 0;
        for ($date = $start->copy()->startOfDay(); $date->lte($end); $date->addDay()) {
            if ($date->isWeekday() && ! $holidays->contains($date->toDateString())) {
                $days++;
            }
        }

        return $days;
    }

    private function buckets(string $period): array
    {
        $count = match ($period) {
            'weekly' => 12,
            'quarterly' => 8,
            'yearly' => 5,
            default => 12,
        };
        $cursor = match ($period) {
            'weekly' => now()->startOfWeek()->subWeeks($count - 1),
            'quarterly' => now()->startOfQuarter()->subQuarters($count - 1),
            'yearly' => now()->startOfYear()->subYears($count - 1),
            default => now()->startOfMonth()->subMonths($count - 1),
        };
        $buckets = [];
        for ($index = 0; $index < $count; $index++) {
            $start = $cursor->copy();
            $end = match ($period) {
                'weekly' => $start->copy()->endOfWeek(),
                'quarterly' => $start->copy()->endOfQuarter(),
                'yearly' => $start->copy()->endOfYear(),
                default => $start->copy()->endOfMonth(),
            };
            $buckets[] = [
                'key' => $start->format(match ($period) {
                    'weekly' => 'o-\\WW',
                    'quarterly' => 'Y-\QQ',
                    'yearly' => 'Y',
                    default => 'Y-m',
                }),
                'label' => match ($period) {
                    'weekly' => $start->format('d M').' - '.$end->format('d M Y'),
                    'quarterly' => 'Q'.$start->quarter.' '.$start->year,
                    'yearly' => (string) $start->year,
                    default => $start->format('M Y'),
                },
                'start' => $start,
                'end' => $end,
            ];
            $cursor = match ($period) {
                'weekly' => $cursor->copy()->addWeek(),
                'quarterly' => $cursor->copy()->addQuarter(),
                'yearly' => $cursor->copy()->addYear(),
                default => $cursor->copy()->addMonth(),
            };
        }

        return $buckets;
    }

    private function labels(array $buckets): array
    {
        return array_column($buckets, 'label');
    }

    private function periodLabel(string $period): string
    {
        return match ($period) {
            'weekly' => 'Last 12 weeks',
            'quarterly' => 'Last 8 quarters',
            'yearly' => 'Last 5 years',
            default => 'Last 12 months',
        };
    }

    private function withTable(?array $analytics): ?array
    {
        if (! $analytics || ! empty($analytics['table'])) {
            return $analytics;
        }

        $chart = collect($analytics['charts'] ?? [])->first(fn (array $chart) => ! empty($chart['datasets']));
        if (! $chart) {
            return $analytics + ['table' => ['columns' => [], 'rows' => []]];
        }

        $columns = collect($chart['datasets'])->map(fn (array $dataset) => [
            'key' => Str::slug($dataset['label']),
            'label' => $dataset['label'],
            'money' => (bool) ($dataset['money'] ?? false),
        ])->values()->all();

        $rows = collect($chart['labels'] ?? [])->map(function (string $label, int $index) use ($chart): array {
            return [
                'period' => $label,
                'values' => collect($chart['datasets'])->map(fn (array $dataset) => $dataset['data'][$index] ?? 0)->values()->all(),
            ];
        })->values()->all();

        $dimension = $chart['dimension'] ?? 'Period';

        return $analytics + ['table' => compact('columns', 'rows', 'dimension')];
    }

    private function card(string $label, mixed $value, string $caption, string $color, bool $money = false, ?string $suffix = null): array
    {
        return compact('label', 'value', 'caption', 'color', 'money', 'suffix');
    }

    private function dataset(string $label, array $data, string $color, bool $money = false): array
    {
        return compact('label', 'data', 'color', 'money');
    }

    private function lineChart(string $id, string $title, string $subtitle, array $labels, array $datasets, string $dimension = 'Period'): array
    {
        return compact('id', 'title', 'subtitle', 'labels', 'datasets', 'dimension') + ['type' => 'line', 'stacked' => false];
    }

    private function barChart(string $id, string $title, string $subtitle, array $labels, array $datasets, bool $stacked = false, string $dimension = 'Period'): array
    {
        return compact('id', 'title', 'subtitle', 'labels', 'datasets', 'dimension') + ['type' => 'bar', 'stacked' => $stacked];
    }

    private function doughnutChart(string $id, string $title, string $subtitle, array $labels, array $data, array $colors): array
    {
        return compact('id', 'title', 'subtitle', 'labels', 'data', 'colors') + ['type' => 'pie', 'datasets' => []];
    }

    private function sum(array $values): float
    {
        return round((float) array_sum($values), 2);
    }
}
