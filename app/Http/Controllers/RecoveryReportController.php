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

        $filename = 'recoveries-'.$type.'-'.$report['year'];

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
     * Build every report dataset for the selected year, scoped to the user's branch.
     */
    private function buildReport(Request $request): array
    {
        $user = $request->user();
        $year = $request->filled('year') ? (int) $request->integer('year') : (int) now()->year;
        $grain = in_array($request->string('grain')->toString(), ['daily', 'weekly'], true)
            ? $request->string('grain')->toString()
            : 'weekly';
        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->string('date_from')->toString())->startOfDay()
            : now()->startOfWeek()->startOfDay();
        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->string('date_to')->toString())->endOfDay()
            : now()->endOfWeek()->endOfDay();

        $accounts = RecoveryAccount::with(['client', 'assignee'])
            ->forBranchOf($user)
            ->get();

        $accountIds = $accounts->pluck('id');

        $payments = RecoveryActivity::whereIn('recovery_account_id', $accountIds)
            ->whereYear('activity_at', $year)
            ->get();

        // 1. Monthly: new recoveries opened + money recovered each month.
        $openedByMonth = $accounts
            ->filter(fn ($a) => (int) $a->created_at->year === $year)
            ->groupBy(fn ($a) => $a->created_at->format('n'));

        $recoveredByMonth = $payments->groupBy(fn ($a) => $a->activity_at->format('n'));

        $monthly = collect(range(1, 12))->map(fn ($m) => [
            'month' => \Illuminate\Support\Carbon::create($year, $m, 1)->format('M'),
            'opened' => $openedByMonth->get((string) $m, collect())->count(),
            'recovered' => (float) $recoveredByMonth->get((string) $m, collect())->sum('amount_paid'),
        ]);

        // 2. Per-officer performance.
        $byOfficer = $accounts->groupBy('assigned_to')->map(function ($group) {
            $first = $group->first();

            return [
                'officer' => $first->assignee?->name ?? 'Unassigned',
                'accounts' => $group->count(),
                'outstanding' => (float) $group->sum('outstanding_amount'),
                'recovered' => (float) $group->sum('amount_recovered'),
            ];
        })->sortByDesc('recovered')->values();

        // 3. Per-bank / client totals.
        $byClient = $accounts->groupBy('recovery_client_id')->map(function ($group) {
            return [
                'client' => $group->first()->client?->name ?? 'Unknown',
                'accounts' => $group->count(),
                'outstanding' => (float) $group->sum('outstanding_amount'),
                'recovered' => (float) $group->sum('amount_recovered'),
            ];
        })->sortByDesc('outstanding')->values();

        // 4. Outstanding vs recovered summary.
        $summary = [
            'accounts' => $accounts->count(),
            'active' => $accounts->where('status', 'active')->count(),
            'outstanding' => (float) $accounts->sum('outstanding_amount'),
            'recovered' => (float) $accounts->sum('amount_recovered'),
        ];

        return [
            'year' => $year,
            'years' => $this->yearOptions($accounts),
            'clients' => RecoveryClient::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'officers' => User::role('Recovery Officer')->orderBy('name')->get(['id', 'name']),
            'reportFilters' => [
                'grain' => $grain,
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString(),
                'client' => $request->filled('client') ? $request->integer('client') : null,
                'officer' => $request->filled('officer') ? $request->integer('officer') : null,
            ],
            'monthly' => $monthly,
            'byOfficer' => $byOfficer,
            'byClient' => $byClient,
            'activityRows' => $this->activityRows($accounts, $request, $grain, $dateFrom, $dateTo),
            'summary' => $summary,
        ];
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
                ucfirst($report['reportFilters']['grain']).' Recovery Collections',
            ],
            'monthly' => [
                ['Month', 'Recoveries Opened', 'Amount Recovered'],
                $report['monthly']->map(fn ($r) => [$r['month'], $r['opened'], number_format($r['recovered'], 2)])->all(),
                'Monthly Recoveries '.$report['year'],
            ],
            'clients' => [
                ['Bank / Client', 'Accounts', 'Outstanding', 'Recovered'],
                $report['byClient']->map(fn ($r) => [$r['client'], $r['accounts'], number_format($r['outstanding'], 2), number_format($r['recovered'], 2)])->all(),
                'Recoveries by Bank '.$report['year'],
            ],
            default => [
                ['Officer', 'Accounts', 'Outstanding', 'Recovered'],
                $report['byOfficer']->map(fn ($r) => [$r['officer'], $r['accounts'], number_format($r['outstanding'], 2), number_format($r['recovered'], 2)])->all(),
                'Recoveries by Officer '.$report['year'],
            ],
        };
    }

    private function activityRows($accounts, Request $request, string $grain, Carbon $dateFrom, Carbon $dateTo)
    {
        $filteredAccounts = $accounts
            ->when($request->filled('client'), fn ($accounts) => $accounts->where('recovery_client_id', $request->integer('client')))
            ->when($request->filled('officer'), fn ($accounts) => $accounts->where('assigned_to', $request->integer('officer')));

        $activities = RecoveryActivity::with(['account.client', 'account.assignee'])
            ->whereIn('recovery_account_id', $filteredAccounts->pluck('id'))
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
