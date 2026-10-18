@extends('layouts.admin')

@section('title', $report['title'])

@section('content')
    <div class="kfms-report-page kfms-report-detail-page">
        <div class="kfms-report-detail-topbar">
            <a class="kfms-report-back" href="{{ route('reports.index') }}"><i class="mdi mdi-arrow-left"></i> Reports &amp; Analytics</a>
            <span class="kfms-report-count">{{ $report['group'] }}</span>
        </div>

        <section class="kfms-report-detail-hero">
            <div class="kfms-report-card-icon"><i class="mdi mdi-chart-timeline-variant"></i></div>
            <div>
                <span class="kfms-report-eyebrow">Report definition</span>
                <h1>{{ $report['title'] }}</h1>
                <p>{{ $report['description'] }}</p>
            </div>
            @if (! empty($report['route']) && Route::has($report['route']))
                <a class="kfms-report-button" href="{{ route($report['route']) }}">Open operational report <i class="mdi mdi-open-in-new"></i></a>
            @endif
        </section>

        <section class="kfms-report-contract-grid">
            <article class="kfms-report-contract-card kfms-report-contract-card-wide">
                <span class="kfms-report-eyebrow">Defined formula</span>
                <h2>{{ $report['formula'] }}</h2>
                <p class="kfms-report-muted">The formula is applied after branch and permission scope, status treatment and date rules have been evaluated.</p>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Included statuses</span>
                <div class="kfms-report-tags">
                    @foreach ($report['statuses'] as $status)
                        <span>{{ $status }}</span>
                    @endforeach
                </div>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Export formats</span>
                <div class="kfms-report-tags">
                    @foreach ($report['export_formats'] as $format)
                        <span>{{ $format }}</span>
                    @endforeach
                </div>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Date rule</span>
                <p>{{ $report['date_rule'] }}</p>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Branch rule</span>
                <p>{{ $report['branch_rule'] }}</p>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Permission rule</span>
                <div class="kfms-report-tags">
                    @foreach ($report['permission_rule'] as $permission)
                        <span>{{ $permission }}</span>
                    @endforeach
                </div>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Draft, void &amp; archived treatment</span>
                <p>{{ $report['draft_void_archived'] }}</p>
            </article>

            <article class="kfms-report-contract-card">
                <span class="kfms-report-eyebrow">Audit record</span>
                <p>{{ $report['audit_record'] }}</p>
            </article>
        </section>

        <section class="kfms-report-source-panel">
            <div>
                <span class="kfms-report-eyebrow">Data sources</span>
                <h2>Traceable report inputs</h2>
                <p>Every generated report records its filters, branch scope, actor, period and export format. These source areas define which records are eligible for the calculation.</p>
            </div>
            <div class="kfms-report-tags">
                @foreach ($report['source'] as $source)
                    <span>{{ $source }}</span>
                @endforeach
            </div>
        </section>

        @if ($relatedReports->isNotEmpty())
            <section class="kfms-report-related">
                <div class="kfms-report-section-heading">
                    <div>
                        <span class="kfms-report-eyebrow">Continue exploring</span>
                        <h2>Related {{ $report['group'] }} reports</h2>
                    </div>
                </div>
                <div class="kfms-report-related-links">
                    @foreach ($relatedReports as $related)
                        <a href="{{ route('reports.show', $related['slug']) }}">{{ $related['title'] }} <i class="mdi mdi-arrow-right"></i></a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
@endsection
