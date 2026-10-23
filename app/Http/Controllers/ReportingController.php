<?php

namespace App\Http\Controllers;

use App\Exports\ReportingTableExport;
use App\Models\AuditLog;
use App\Services\ReportingAnalyticsService;
use App\Support\ReportNavigation;
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
        $category = trim($request->string('category')->toString());
        $categoryDefinition = ReportNavigation::categories()->get($category);
        abort_if($category !== '' && ! $categoryDefinition, 404);

        $reports = $catalogue
            ->filter(function (array $report) use ($search, $group, $categoryDefinition): bool {
                if ($categoryDefinition && ! in_array($report['group'], $categoryDefinition['groups'], true)) {
                    return false;
                }
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
            ->values();

        return view('modules.reports.index', [
            'reports' => $reports,
            'groups' => $catalogue->when($categoryDefinition, fn ($items) => $items->whereIn('group', $categoryDefinition['groups']))->pluck('group')->unique()->sort()->values(),
            'groupLabels' => config('reporting.group_labels', []),
            'selectedGroup' => $group,
            'selectedCategory' => $category,
            'categoryLabel' => $categoryDefinition['label'] ?? null,
            'search' => $search,
            'totalReports' => $reports->count(),
            'managementPacks' => $reports->where('group', 'Management')->count(),
            'operationalReports' => $reports->whereNotIn('group', ['Management'])->count(),
        ]);
    }

    public function show(Request $request, string $report, ReportingAnalyticsService $analyticsService)
    {
        $definition = config("reporting.reports.{$report}");

        abort_if(! is_array($definition), 404);

        $category = ReportNavigation::categoryForGroup($definition['group']);
        $categoryDefinition = ReportNavigation::categories()->get($category);
        $categoryReports = $this->catalogue()->whereIn('group', $categoryDefinition['groups'] ?? [$definition['group']])->values();
        $position = $categoryReports->search(fn (array $item) => $item['slug'] === $report);
        $period = $request->string('period')->toString() ?: ($report === 'annual-management-pack' ? 'yearly' : 'monthly');
        $analytics = $analyticsService->build($report, $request->user(), $period);

        if ($analytics) {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'event' => 'report_generated',
                'auditable_type' => null,
                'auditable_id' => null,
                'new_values' => [
                    'report' => $report,
                    'period' => $analytics['period'],
                    'filters' => $request->query(),
                    'branch_scope' => $analytics['scope'],
                    'format' => 'screen',
                    'row_count' => count($analytics['table']['rows'] ?? []),
                ],
                'url' => $request->fullUrl(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return view('modules.reports.show', [
            'report' => array_merge($definition, ['slug' => $report]),
            'groupLabel' => config('reporting.group_labels.'.$definition['group'], $definition['group']),
            'category' => $category,
            'categoryLabel' => $categoryDefinition['label'] ?? $definition['group'],
            'previousReport' => $position > 0 ? $categoryReports->get($position - 1) : null,
            'nextReport' => $categoryReports->get($position + 1),
            'reportPosition' => $position + 1,
            'reportTotal' => $categoryReports->count(),
            'analytics' => $analytics,
            'relatedReports' => $categoryReports
                ->filter(fn (array $item) => $item['slug'] !== $report)
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
                'filters' => $request->query(),
                'branch_scope' => $analytics['scope'],
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
