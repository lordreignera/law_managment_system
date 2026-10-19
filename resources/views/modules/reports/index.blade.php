@extends('layouts.admin')

@section('title', $categoryLabel ? $categoryLabel.' reports' : ($selectedGroup !== '' ? ($groupLabels[$selectedGroup] ?? $selectedGroup).' reports' : 'Reporting & Analytics'))

@section('content')
    <div class="kfms-report-page">
        <section class="kfms-report-hero">
            <div>
                <span class="kfms-report-eyebrow">Management information</span>
                @if ($categoryLabel || $selectedGroup !== '')
                    <h1>{{ $categoryLabel ?: ($groupLabels[$selectedGroup] ?? $selectedGroup) }} reports</h1>
                    <p>Related reports are grouped here. Open a report to view its charts, tables, and downloads by week, month, quarter, or year.</p>
                @else
                    <h1>Reporting &amp; Analytics</h1>
                    <p>Use the controlled catalogue to view, compare and export consistent management information across the firm. Each supported report can be read weekly, monthly, quarterly, or yearly.</p>
                @endif
            </div>
        </section>

        <div class="kfms-stat-grid" aria-label="Report catalogue summary">
            <section class="kfms-card"><span class="kfms-card-label">Defined reports</span><strong class="kfms-stat">{{ $totalReports }}</strong></section>
            <section class="kfms-card"><span class="kfms-card-label">Operational reports</span><strong class="kfms-stat">{{ $operationalReports }}</strong></section>
            <section class="kfms-card"><span class="kfms-card-label">Management packs</span><strong class="kfms-stat">{{ $managementPacks }}</strong></section>
        </div>

        <section class="kfms-report-toolbar" aria-label="Report filters">
            <form method="GET" action="{{ route('reports.index') }}" class="kfms-report-filters">
                @if ($selectedCategory !== '')<input type="hidden" name="category" value="{{ $selectedCategory }}">@endif
                <label>
                    <span>Search catalogue</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search reports, formulas or modules">
                </label>
                <label>
                    <span>Report group</span>
                    <select name="group">
                        <option value="">All report groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}" @selected($selectedGroup === $group)>{{ $groupLabels[$group] ?? $group }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="kfms-report-button" type="submit">Apply filters</button>
                @if ($search !== '' || $selectedGroup !== '')
                    <a class="kfms-report-clear" href="{{ route('reports.index', array_filter(['category' => $selectedCategory])) }}">Clear filters</a>
                @endif
                @if ($selectedCategory !== '')<a class="kfms-report-clear" href="{{ route('reports.index') }}">All Reports</a>@endif
            </form>
        </section>

        @forelse ($reports->groupBy('group') as $group => $groupReports)
            <section class="kfms-report-section">
                <div class="kfms-report-section-heading">
                    <div>
                        <span class="kfms-report-eyebrow">Catalogue group</span>
                        <h2>{{ $groupLabels[$group] ?? $group }}</h2>
                    </div>
                    <span class="kfms-report-count">{{ $groupReports->count() }} {{ \Illuminate\Support\Str::plural('report', $groupReports->count()) }}</span>
                </div>

                <div class="kfms-report-grid">
                    @foreach ($groupReports as $report)
                        <article class="kfms-report-card">
                            <div class="kfms-report-card-header">
                                <div class="kfms-report-card-icon"><i class="mdi mdi-chart-timeline-variant"></i></div>
                                <div>
                                    <h3>{{ $report['title'] }}</h3>
                                    <span>{{ $groupLabels[$report['group']] ?? $report['group'] }}</span>
                                </div>
                            </div>
                            <p class="kfms-report-description">{{ $report['description'] }}</p>
                            <details class="kfms-report-details">
                            <summary>Calculation and scope</summary>
                            <div class="kfms-report-formula">
                                <span>Formula</span>
                                <p>{{ $report['formula'] }}</p>
                            </div>
                            <dl class="kfms-report-meta">
                                <div><dt>Date</dt><dd>{{ $report['date_rule'] }}</dd></div>
                                <div><dt>Branch</dt><dd>{{ $report['branch_rule'] }}</dd></div>
                                <div><dt>Access</dt><dd>{{ implode(', ', $report['permission_rule']) }}</dd></div>
                            </dl>
                            </details>
                            <div class="kfms-report-card-footer">
                                <span>{{ implode(' · ', $report['export_formats']) }}</span>
                                <a href="{{ route('reports.show', array_filter(['report' => $report['slug'], 'category' => $selectedCategory])) }}">Open report <i class="mdi mdi-arrow-right"></i></a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @empty
            <section class="kfms-report-empty">
                <i class="mdi mdi-file-search-outline"></i>
                <h2>No reports match those filters</h2>
                <p>Clear the search or group filter to view the full controlled catalogue.</p>
                <a class="kfms-report-button" href="{{ route('reports.index') }}">View all reports</a>
            </section>
        @endforelse
    </div>
@endsection
