<?php

namespace App\Http\Controllers;

use App\Exports\RecoveryReportExport;
use App\Models\RecoveryAccount;
use App\Models\RecoveryActivity;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
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
            'monthly' => $monthly,
            'byOfficer' => $byOfficer,
            'byClient' => $byClient,
            'summary' => $summary,
        ];
    }

    private function yearOptions($accounts): array
    {
        $years = $accounts
            ->map(fn ($a) => (int) $a->created_at->year)
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
}
