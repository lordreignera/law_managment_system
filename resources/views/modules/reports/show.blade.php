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

        @if ($analytics)
            <section class="kfms-report-analytics-panel">
                <div class="kfms-report-analytics-heading">
                    <div>
                        <span class="kfms-report-eyebrow">Live module analytics</span>
                        <h2>Decision view</h2>
                        <p>{{ $analytics['scope'] }} {{ $analytics['period_label'] }}.</p>
                    </div>
                    <form method="GET" action="{{ route('reports.show', $report['slug']) }}" class="kfms-report-period-filter">
                        <label for="report-period">View by</label>
                        <select id="report-period" name="period" onchange="this.form.submit()">
                            <option value="monthly" @selected($analytics['period'] === 'monthly')>Monthly</option>
                            <option value="quarterly" @selected($analytics['period'] === 'quarterly')>Quarterly</option>
                            <option value="annual" @selected($analytics['period'] === 'annual')>Annual</option>
                        </select>
                    </form>
                </div>

                <div class="kfms-report-analytics-cards">
                    @foreach ($analytics['cards'] as $card)
                        <article class="kfms-report-analytics-card" style="--report-card-accent: {{ $card['color'] }}">
                            <span>{{ $card['label'] }}</span>
                            <strong>
                                @if ($card['money']) UGX @endif{{ $card['value'] }}@if ($card['suffix']) <small>{{ $card['suffix'] }}</small>@endif
                            </strong>
                            <em>{{ $card['caption'] }}</em>
                        </article>
                    @endforeach
                </div>

                <div class="kfms-report-chart-grid">
                    @foreach ($analytics['charts'] as $chart)
                        <article class="kfms-report-chart-card">
                            <div class="kfms-report-chart-heading">
                                <div>
                                    <h3>{{ $chart['title'] }}</h3>
                                    <p>{{ $chart['subtitle'] }}</p>
                                </div>
                            </div>
                            <div class="kfms-report-chart-wrap">
                                <canvas id="{{ $chart['id'] }}" aria-label="{{ $chart['title'] }}"></canvas>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="kfms-report-analytics-insight">
                    <i class="mdi mdi-lightbulb-outline"></i>
                    <span><strong>Management reading:</strong> {{ $analytics['insight'] }}</span>
                </div>
            </section>
        @endif

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

@if ($analytics)
    @push('scripts')
        <script src="{{ asset('admin/assets/vendors/chart.js/Chart.min.js') }}"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const charts = @json($analytics['charts']);
                const formatValue = (value, dataset) => dataset.money ? `UGX ${Number(value || 0).toLocaleString()}` : Number(value || 0).toLocaleString();

                charts.forEach((chartConfig) => {
                    const canvas = document.getElementById(chartConfig.id);
                    if (!canvas || typeof Chart === 'undefined') {
                        return;
                    }

                    const datasets = chartConfig.type === 'doughnut'
                        ? [{
                            data: chartConfig.data,
                            backgroundColor: chartConfig.colors,
                            borderColor: '#ffffff',
                            borderWidth: 2,
                        }]
                        : chartConfig.datasets.map((dataset) => ({
                            label: dataset.label,
                            data: dataset.data,
                            backgroundColor: dataset.color,
                            borderColor: dataset.color,
                            borderWidth: 2,
                            fill: chartConfig.type === 'line' ? false : undefined,
                            tension: 0.28,
                            pointRadius: chartConfig.type === 'line' ? 3 : 0,
                        }));

                    new Chart(canvas.getContext('2d'), {
                        type: chartConfig.type,
                        data: { labels: chartConfig.labels, datasets },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            legend: { display: chartConfig.type !== 'doughnut', position: 'bottom' },
                            cutoutPercentage: chartConfig.type === 'doughnut' ? 62 : undefined,
                            scales: chartConfig.type === 'doughnut' ? {} : {
                                xAxes: [{ stacked: chartConfig.stacked, gridLines: { display: false } }],
                                yAxes: [{ stacked: chartConfig.stacked, ticks: { beginAtZero: true } }],
                            },
                            tooltips: {
                                callbacks: {
                                    label: function (tooltipItem, data) {
                                        const dataset = data.datasets[tooltipItem.datasetIndex] || {};
                                        const value = chartConfig.type === 'doughnut'
                                            ? data.datasets[0].data[tooltipItem.index]
                                            : dataset.data[tooltipItem.index];
                                        return `${dataset.label ? dataset.label + ': ' : ''}${formatValue(value, dataset)}`;
                                    },
                                },
                            },
                        },
                    });
                });
            });
        </script>
    @endpush
@endif
