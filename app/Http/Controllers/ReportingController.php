<?php

namespace App\Http\Controllers;

use App\Exports\ReportingTableExport;
use App\Models\AuditLog;
use App\Services\ReportingAnalyticsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ReportingController extends Controller
{
    public function index(Request $request)
    {
        $catalogue = $this->catalogue();
        $search = trim($request->string('q')->toString());
        $group = trim($request->string('group')->toString());

        $reports = $catalogue
            ->filter(function (array $report) use ($search, $group): bool {
                if ($group !== '' && $report['group'] !== $group) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return Str::contains(Str::lower(implode(' ', [
                    $report['title'],
                    $report['group'],
                    $report['description'],
                    $report['formula'],
                ])), Str::lower($search));
            })
            ->sortBy(fn (array $report) => $report['title'])
            ->values();

        return view('modules.reports.index', [
            'reports' => $reports,
            'groups' => $catalogue->pluck('group')->unique()->sort()->values(),
            'selectedGroup' => $group,
            'search' => $search,
            'totalReports' => $catalogue->count(),
            'managementPacks' => $catalogue->where('group', 'Management')->count(),
            'operationalReports' => $catalogue->whereNotIn('group', ['Management'])->count(),
        ]);
    }

    public function show(Request $request, string $report, ReportingAnalyticsService $analyticsService)
    {
        $definition = config("reporting.reports.{$report}");

        abort_if(! is_array($definition), 404);

        return view('modules.reports.show', [
            'report' => array_merge($definition, ['slug' => $report]),
            'analytics' => $analyticsService->build(
                $report,
                $request->user(),
                $request->string('period')->toString() ?: ($report === 'annual-management-pack' ? 'yearly' : 'monthly')
            ),
            'relatedReports' => $this->catalogue()
                ->filter(fn (array $item, string $slug) => $slug !== $report && $item['group'] === $definition['group'])
                ->sortBy('title')
                ->take(5)
                ->values(),
        ]);
    }

    public function export(Request $request, string $report, ReportingAnalyticsService $analyticsService)
    {
        $definition = config("reporting.reports.{$report}");

        abort_if(! is_array($definition), 404);
        abort_unless($request->user()->can('reports.show') || $request->user()->can('reports.index'), 403);

        $period = $request->string('period')->toString() ?: ($report === 'annual-management-pack' ? 'yearly' : 'monthly');
        $analytics = $analyticsService->build($report, $request->user(), $period);
        abort_if(! $analytics || empty($analytics['table']), 422, 'This report has no tabular data to export.');

        $table = $analytics['table'];
        $headings = array_merge([$table['dimension'] ?? 'Period'], array_column($table['columns'], 'label'));
        $rows = collect($table['rows'])->map(fn (array $row) => array_merge([$row['period']], $row['values']))->all();
        $format = strtolower($request->string('format')->toString() ?: 'xlsx');
        abort_unless(in_array($format, ['csv', 'xlsx', 'pdf'], true), 422, 'Unsupported report export format.');

        AuditLog::create([
            'user_id' => $request->user()->id,
            'event' => 'report_exported',
            'auditable_type' => null,
            'auditable_id' => null,
            'new_values' => [
                'report' => $report,
                'period' => $analytics['period'],
                'format' => $format,
                'row_count' => count($rows),
            ],
            'url' => $request->fullUrl(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        $filename = Str::slug($definition['title']).'-'.$analytics['period'].'-'.now()->format('Ymd-His');
        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headings, $rows): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $headings);
                foreach ($rows as $row) {
                    fputcsv($handle, $row);
                }
                fclose($handle);
            }, $filename.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        if ($format === 'pdf') {
            return Pdf::loadView('modules.reports.export', [
                'title' => $definition['title'],
                'periodLabel' => $analytics['period_label'],
                'headings' => $headings,
                'rows' => $rows,
                'generatedAt' => now(),
            ])->setPaper('a4', 'landscape')->download($filename.'.pdf');
        }

        return Excel::download(new ReportingTableExport($headings, $rows, Str::limit($definition['title'], 31, '')), $filename.'.xlsx');
    }

    private function catalogue(): Collection
    {
        return collect(config('reporting.reports', []))->map(
            fn (array $definition, string $slug) => array_merge($definition, ['slug' => $slug])
        );
    }
}
