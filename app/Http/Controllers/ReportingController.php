<?php

namespace App\Http\Controllers;

use App\Services\ReportingAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

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
                $request->string('period')->toString() ?: 'monthly'
            ),
            'relatedReports' => $this->catalogue()
                ->filter(fn (array $item, string $slug) => $slug !== $report && $item['group'] === $definition['group'])
                ->sortBy('title')
                ->take(5)
                ->values(),
        ]);
    }

    private function catalogue(): Collection
    {
        return collect(config('reporting.reports', []))->map(
            fn (array $definition, string $slug) => array_merge($definition, ['slug' => $slug])
        );
    }
}
