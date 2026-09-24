<?php

namespace App\Http\Controllers;

use App\Exports\RecoveryReportExport;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use App\Models\RecoveryClient;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class RecoveryReportController extends Controller
{
    public function index(Request $request)
    {
        $report = $this->buildReport($request);

        return view('modules.recoveries.reports', $report);
    }

    public function export(Request $request)
    {
        $type = $request->string('type')->toString() ?: 'officers';
        $format = $request->string('format')->toString() ?: 'xlsx';
        $report = $this->buildReport($request);

        [$headings, $rows, $title] = $this->dataset($type, $report);

        $filename = 'recoveries-'.$type.'-'.$report['year'].'-'.$report['reportFilters']['period'];

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('modules.recoveries.exports.pdf', [
                'title' => $title,
                'headings' => $headings,
                'rows' => $rows,
                'year' => $report['year'],
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape');

            return $pdf->download($filename.'.pdf');
        }

        return Excel::download(
            new RecoveryReportExport($headings, $rows, $title),
            $filename.'.xlsx'
        );
    }

    /**
     * Build every report dataset for the selected period, scoped to the user's branch.
     */
    private function buildReport(Request $request): array
    {
        $user = $request->user();
        $year = $request->filled('year') ? (int) $request->integer('year') : (int) now()->year;
        $period = $this->periodKey($request);
        $periodValue = $this->periodValue($request, $period, $year);
        [$dateFrom, $dateTo, $periodLabel] = $this->periodRange($year, $period, $periodValue);

        // Keep direct date links working while the dashboard uses named periods.
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->string('date_from')->toString())->startOfDay();
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->string('date_to')->toString())->endOfDay();
        }
        if ($dateTo->lt($dateFrom)) {
            [$dateFrom, $dateTo] = [$dateTo->copy()->startOfDay(), $dateFrom->copy()->endOfDay()];
        }

        $grain = in_array($request->string('grain')->toString(), ['daily', 'weekly'], true)
            ? $request->string('grain')->toString()
            : 'weekly';

        $accounts = RecoveryAccount::with(['client', 'assignee'])
            ->forBranchOf($user)
            ->get();

        $filteredAccounts = $accounts
            ->when($request->filled('client'), fn ($accounts) => $accounts->where('recovery_client_id', $request->integer('client')))
            ->when($request->filled('officer'), fn ($accounts) => $accounts->where('assigned_to', $request->integer('officer')))
            ->values();

        $accountIds = $filteredAccounts->pluck('id');

        $payments = RecoveryActivity::whereIn('recovery_account_id', $accountIds)
            ->whereBetween('activity_at', [$dateFrom, $dateTo])
            ->get();

        // 1. Monthly: new recoveries opened + money recovered within the selected period.
        $monthly = $this->monthlyRows($filteredAccounts, $payments, $dateFrom, $dateTo);
        $maxMonthlyRecovered = max((float) $monthly->max('recovered'), 1);
        $monthly = $monthly->map(function (array $row) use ($maxMonthlyRecovered) {
            $row['percent'] = round(($row['recovered'] / $maxMonthlyRecovered) * 100, 2);

            return $row;
        });

        // 2. Per-officer performance.
        $byOfficer = $filteredAccounts->groupBy('assigned_to')->map(function ($group) {
            $first = $group->first();

            return [
                'officer' => $first->assignee?->name ?? 'Unassigned',
                'accounts' => $group->count(),
                'outstanding' => (float) $group->sum(fn (RecoveryAccount $account) => $account->net_outstanding_balance),
                'recovered' => (float) $group->sum('amount_recovered'),
            ];
        })->sortByDesc('recovered')->values();

        // 3. Per-bank / client totals.
        $byClient = $filteredAccounts->groupBy('recovery_client_id')->map(function ($group) {
            return [
                'client' => $group->first()->client?->name ?? 'Unknown',
                'accounts' => $group->count(),
                'outstanding' => (float) $group->sum(fn (RecoveryAccount $account) => $account->net_outstanding_balance),
                'recovered' => (float) $group->sum('amount_recovered'),
            ];
        })->sortByDesc('outstanding')->values();

        // 4. Outstanding vs recovered summary.
        $summary = [
            'accounts' => $filteredAccounts->count(),
            'active' => $filteredAccounts->where('status', 'active')->count(),
            'outstanding' => (float) $filteredAccounts->sum(fn (RecoveryAccount $account) => $account->net_outstanding_balance),
            'recovered' => (float) $filteredAccounts->sum('amount_recovered'),
        ];

        return [
            'year' => $year,
            'years' => $this->yearOptions($accounts),
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => User::role('Recovery Officer')->orderBy('name')->get(['id', 'name']),
            'reportFilters' => [
                'period' => $period,
                'period_value' => $periodValue,
                'period_label' => $periodLabel,
                'grain' => $grain,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'client' => $request->filled('client') ? $request->integer('client') : null,
                'officer' => $request->filled('officer') ? $request->integer('officer') : null,
            ],
            'monthly' => $monthly,
            'byOfficer' => $byOfficer,
            'byClient' => $byClient,
            'activityRows' => $this->activityRows($filteredAccounts, $grain, $dateFrom, $dateTo),
            'summary' => $summary,
        ];
    }

    private function periodKey(Request $request): string
    {
        $period = $request->string('period')->toString();

        if ($period === 'semi_year') {
            $period = 'half_year';
        }

        return in_array($period, ['month', 'quarter', 'half_year', 'year'], true) ? $period : 'year';
    }

    private function periodValue(Request $request, string $period, int $year): int
    {
        $defaults = [
            'month' => $year === now()->year ? now()->month : 1,
            'quarter' => $year === now()->year ? (int) ceil(now()->month / 3) : 1,
            'half_year' => $year === now()->year && now()->month > 6 ? 2 : 1,
            'year' => 1,
        ];
        $max = ['month' => 12, 'quarter' => 4, 'half_year' => 2, 'year' => 1][$period];
        $value = $request->filled('period_value') ? $request->integer('period_value') : $defaults[$period];

        return min(max($value, 1), $max);
    }

    private function periodRange(int $year, string $period, int $periodValue): array
    {
        if ($period === 'month') {
            $from = Carbon::create($year, $periodValue, 1)->startOfMonth();
            $label = $from->format('F Y');
        } elseif ($period === 'quarter') {
            $startMonth = (($periodValue - 1) * 3) + 1;
            $from = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $label = 'Quarter '.$periodValue.' '.$year;
        } elseif ($period === 'half_year') {
            $startMonth = $periodValue === 1 ? 1 : 7;
            $from = Carbon::create($year, $startMonth, 1)->startOfMonth();
            $label = 'Semi-year '.$periodValue.' '.$year;
        } else {
            $from = Carbon::create($year, 1, 1)->startOfYear();
            $label = 'Whole year '.$year;
        }

        $to = match ($period) {
            'month' => $from->copy()->endOfMonth(),
            'quarter' => $from->copy()->addMonthsNoOverflow(2)->endOfMonth(),
            'half_year' => $from->copy()->addMonthsNoOverflow(5)->endOfMonth(),
            default => $from->copy()->endOfYear(),
        };

        return [$from->copy()->startOfDay(), $to->endOfDay(), $label];
    }

    private function monthlyRows($accounts, $payments, Carbon $dateFrom, Carbon $dateTo)
    {
        $rows = collect();
        $cursor = $dateFrom->copy()->startOfMonth();
        $lastMonth = $dateTo->copy()->startOfMonth();

        while ($cursor->lte($lastMonth)) {
            $monthStart = $cursor->copy()->startOfMonth();
            $monthEnd = $cursor->copy()->endOfMonth();
            $rows->push([
                'month' => $cursor->format('M Y'),
                'opened' => $accounts->filter(fn ($account) => $account->created_at?->betweenIncluded($monthStart, $monthEnd))->count(),
                'recovered' => (float) $payments->filter(fn ($payment) => $payment->activity_at?->betweenIncluded($monthStart, $monthEnd))->sum('amount_paid'),
            ]);
            $cursor->addMonthNoOverflow();
        }

        return $rows;
    }

    private function yearOptions($accounts): array
    {
        $years = $accounts
            ->map(fn ($a) => (int) $a->created_at->year)
            ->toBase()
            ->push((int) now()->year)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $years;
    }

    /**
     * Resolve a named dataset into [headings, rows, title] for export.
     */
    private function dataset(string $type, array $report): array
    {
        return match ($type) {
            'collections' => [
                ['Period', 'Bank / Client', 'Officer', 'Accounts Touched', 'Activities', 'Payments', 'Promised', 'Recovered'],
                $report['activityRows']->map(fn ($r) => [
                    $r['period'],
                    $r['client'],
                    $r['officer'],
                    $r['accounts'],
                    $r['activities'],
                    $r['payments'],
                    number_format($r['promised'], 2),
                    number_format($r['recovered'], 2),
                ])->all(),
                $report['reportFilters']['period_label'].' Recovery Collections',
            ],
            'monthly' => [
                ['Month', 'Recoveries Opened', 'Amount Recovered'],
                $report['monthly']->map(fn ($r) => [$r['month'], $r['opened'], number_format($r['recovered'], 2)])->all(),
                'Monthly Recoveries '.$report['reportFilters']['period_label'],
            ],
            'clients' => [
                ['Bank / Client', 'Accounts', 'Outstanding', 'Recovered'],
                $report['byClient']->map(fn ($r) => [$r['client'], $r['accounts'], number_format($r['outstanding'], 2), number_format($r['recovered'], 2)])->all(),
                'Recoveries by Bank '.$report['reportFilters']['period_label'],
            ],
            default => [
                ['Officer', 'Accounts', 'Outstanding', 'Recovered'],
                $report['byOfficer']->map(fn ($r) => [$r['officer'], $r['accounts'], number_format($r['outstanding'], 2), number_format($r['recovered'], 2)])->all(),
                'Recoveries by Officer '.$report['reportFilters']['period_label'],
            ],
        };
    }

    private function activityRows($accounts, string $grain, Carbon $dateFrom, Carbon $dateTo)
    {
        $activities = RecoveryActivity::with(['account.client', 'account.assignee'])
            ->whereIn('recovery_account_id', $accounts->pluck('id'))
            ->whereBetween('activity_at', [$dateFrom, $dateTo])
            ->orderBy('activity_at')
            ->get();

        return $activities
            ->groupBy(function (RecoveryActivity $activity) use ($grain) {
                $periodStart = $grain === 'daily'
                    ? $activity->activity_at->copy()->startOfDay()
                    : $activity->activity_at->copy()->startOfWeek();

                return implode('|', [
                    $periodStart->toDateString(),
                    $activity->account?->recovery_client_id ?: 'none',
                    $activity->account?->assigned_to ?: 'none',
                ]);
            })
            ->map(function ($group) use ($grain) {
                $first = $group->first();
                $periodStart = $grain === 'daily'
                    ? $first->activity_at->copy()->startOfDay()
                    : $first->activity_at->copy()->startOfWeek();

                return [
                    'period' => $grain === 'daily'
                        ? $periodStart->format('d M Y')
                        : $periodStart->format('d M').' - '.$periodStart->copy()->endOfWeek()->format('d M Y'),
                    'client' => $first->account?->client?->name ?: 'Unknown',
                    'officer' => $first->account?->assignee?->name ?: 'Unassigned',
                    'accounts' => $group->pluck('recovery_account_id')->unique()->count(),
                    'activities' => $group->count(),
                    'payments' => $group->filter(fn ($activity) => $activity->activity_type === 'payment' || (float) $activity->amount_paid > 0)->count(),
                    'promised' => (float) $group->sum('promised_amount'),
                    'recovered' => (float) $group->sum('amount_paid'),
                    'sort_key' => $periodStart->timestamp,
                ];
            })
            ->sortByDesc('sort_key')
            ->values();
    }
}
