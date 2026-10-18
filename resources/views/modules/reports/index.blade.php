@extends('layouts.admin')

@section('title', 'Reporting & Analytics')

@section('content')
    <div class="kfms-report-page">
        <section class="kfms-report-hero">
            <div>
                <span class="kfms-report-eyebrow">Management information</span>
                <h1>Reporting &amp; Analytics</h1>
                <p>Use the controlled catalogue to view, compare and export consistent management information across the firm. Each supported report can be read weekly, monthly, quarterly, or yearly.</p>
            </div>
            <div class="kfms-report-hero-stats" aria-label="Report catalogue summary">
                <div><strong>{{ $totalReports }}</strong><span>Defined reports</span></div>
                <div><strong>{{ $operationalReports }}</strong><span>Operational reports</span></div>
                <div><strong>{{ $managementPacks }}</strong><span>Management packs</span></div>
            </div>
        </section>

        <section class="kfms-report-toolbar" aria-label="Report filters">
            <form method="GET" action="{{ route('reports.index') }}" class="kfms-report-filters">
                <label>
                    <span>Search catalogue</span>
                    <input type="search" name="q" value="{{ $search }}" placeholder="Search reports, formulas or modules">
                </label>
                <label>
                    <span>Report group</span>
                    <select name="group">
                        <option value="">All report groups</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group }}" @selected($selectedGroup === $group)>{{ $group }}</option>
                        @endforeach
                    </select>
                </label>
                <button class="kfms-report-button" type="submit">Apply filters</button>
                @if ($search !== '' || $selectedGroup !== '')
                    <a class="kfms-report-clear" href="{{ route('reports.index') }}">Clear</a>
                @endif
            </form>
        </section>

        @forelse ($reports->groupBy('group') as $group => $groupReports)
            <section class="kfms-report-section">
                <div class="kfms-report-section-heading">
                    <div>
                        <span class="kfms-report-eyebrow">Catalogue group</span>
                        <h2>{{ $group }}</h2>
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
                                    <span>{{ $report['group'] }}</span>
                                </div>
                            </div>
                            <p class="kfms-report-description">{{ $report['description'] }}</p>
                            <div class="kfms-report-formula">
                                <span>Formula</span>
                                <p>{{ $report['formula'] }}</p>
                            </div>
                            <dl class="kfms-report-meta">
                                <div><dt>Date</dt><dd>{{ $report['date_rule'] }}</dd></div>
                                <div><dt>Branch</dt><dd>{{ $report['branch_rule'] }}</dd></div>
                                <div><dt>Access</dt><dd>{{ implode(', ', $report['permission_rule']) }}</dd></div>
                            </dl>
                            <div class="kfms-report-card-footer">
                                <span>{{ implode(' · ', $report['export_formats']) }}</span>
                                <a href="{{ route('reports.show', $report['slug']) }}">View definition <i class="mdi mdi-arrow-right"></i></a>
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
