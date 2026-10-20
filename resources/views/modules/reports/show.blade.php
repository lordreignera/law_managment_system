@extends('layouts.admin')

@section('title', $report['title'])

@section('content')
    <div class="kfms-report-page kfms-report-detail-page">
        <div class="kfms-report-detail-topbar">
            <a class="kfms-report-back" href="{{ route('reports.index', $category ? ['category' => $category] : ['group' => $report['group']]) }}"><i class="mdi mdi-arrow-left"></i> {{ $categoryLabel }} reports</a>
            <span class="kfms-report-count">{{ $groupLabel }}</span>
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

        <nav class="kfms-report-sequence" aria-label="Related report navigation">
            <div>
                @if ($previousReport)
                    <a class="kfms-link-btn" href="{{ route('reports.show', array_filter(['report' => $previousReport['slug'], 'category' => $category, 'period' => $analytics['period'] ?? request('period', 'monthly')])) }}" rel="prev">Previous report</a>
                @else
                    <span class="kfms-muted-text">First report</span>
                @endif
            </div>
            <span>Report {{ $reportPosition }} of {{ $reportTotal }}</span>
            <div>
                @if ($nextReport)
                    <a class="kfms-link-btn" href="{{ route('reports.show', array_filter(['report' => $nextReport['slug'], 'category' => $category, 'period' => $analytics['period'] ?? request('period', 'monthly')])) }}" rel="next">Next report</a>
                @else
                    <span class="kfms-muted-text">Last report</span>
                @endif
            </div>
        </nav>

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
                            <option value="weekly" @selected($analytics['period'] === 'weekly')>Weekly</option>
                            <option value="monthly" @selected($analytics['period'] === 'monthly')>Monthly</option>
                            <option value="quarterly" @selected($analytics['period'] === 'quarterly')>Quarterly</option>
                            <option value="yearly" @selected($analytics['period'] === 'yearly')>Yearly</option>
                        </select>
                    </form>
                </div>

                <div class="kfms-stat-grid kfms-report-analytics-cards">
                    @foreach ($analytics['cards'] as $card)
                        <article class="kfms-card kfms-report-analytics-card" style="--kfms-kpi-accent: {{ $card['color'] }}">
                            <span class="kfms-card-label">{{ $card['label'] }}</span>
                            <strong class="kfms-stat">
                                @if ($card['money']) UGX @endif{{ $card['value'] }}@if ($card['suffix']) <small>{{ $card['suffix'] }}</small>@endif
                            </strong>
                            <em class="kfms-stat-caption">{{ $card['caption'] }}</em>
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
                                <button type="button" class="kfms-report-chart-download" data-chart-download="{{ $chart['id'] }}">
                                    <i class="mdi mdi-download"></i> PNG
                                </button>
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

                @if (! empty($analytics['table']['columns']))
                    <section class="kfms-report-data-table-panel">
                        <div class="kfms-report-data-table-heading">
                            <div>
                                <span class="kfms-report-eyebrow">Exportable detail</span>
                                <h3>Decision table</h3>
                                <p>These rows use the same period scope as the charts above.</p>
                            </div>
                            <div class="kfms-report-export-actions">
                                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                                    <a href="{{ route('reports.export', ['report' => $report['slug'], 'period' => $analytics['period'], 'format' => $format]) }}" class="kfms-report-export-button">
                                        <i class="mdi mdi-download"></i> {{ $label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                        <div class="kfms-report-table-scroll">
                            <table class="kfms-report-data-table">
                                <thead>
                                    <tr>
                                        <th>{{ $analytics['table']['dimension'] ?? 'Period' }}</th>
                                        @foreach ($analytics['table']['columns'] as $column)
                                            <th>{{ $column['label'] }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($analytics['table']['rows'] as $row)
                                        <tr>
                                            <th>{{ $row['period'] }}</th>
                                            @foreach ($row['values'] as $index => $value)
                                                <td>
                                                    @if (! empty($analytics['table']['columns'][$index]['money']))
                                                        UGX {{ number_format((float) $value, 2) }}
                                                    @else
                                                        {{ is_numeric($value) ? number_format((float) $value, 2) : $value }}
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr><td colspan="{{ count($analytics['table']['columns']) + 1 }}">No records matched this period.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
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
                @foreach (['related_statuses' => 'Related workflow values', 'pipeline_stages' => 'Pipeline stages', 'derived_statuses' => 'Derived values'] as $key => $label)
                    @if (! empty($report[$key]))
                        <span class="kfms-report-eyebrow kfms-report-contract-sub-eyebrow">{{ $label }}</span>
                        <div class="kfms-report-tags">
                            @foreach ($report[$key] as $status)
                                <span>{{ $status }}</span>
                            @endforeach
                        </div>
                    @endif
                @endforeach
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
                        <h2>Related {{ $categoryLabel }} reports</h2>
                    </div>
                </div>
                <div class="kfms-report-related-links">
                    @foreach ($relatedReports as $related)
                        <a href="{{ route('reports.show', array_filter(['report' => $related['slug'], 'category' => $category, 'period' => $analytics['period'] ?? request('period', 'monthly')])) }}">{{ $related['title'] }} <i class="mdi mdi-arrow-right"></i></a>
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

                    const isPie = ['pie', 'doughnut'].includes(chartConfig.type);
                    const datasets = isPie
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
                            legend: { display: isPie, position: 'bottom' },
                            cutoutPercentage: chartConfig.type === 'doughnut' ? 62 : undefined,
                            scales: isPie ? {} : {
                                xAxes: [{ stacked: chartConfig.stacked, gridLines: { display: false } }],
                                yAxes: [{ stacked: chartConfig.stacked, ticks: { beginAtZero: true } }],
                            },
                            tooltips: {
                                callbacks: {
                                    label: function (tooltipItem, data) {
                                        const dataset = data.datasets[tooltipItem.datasetIndex] || {};
                                        const value = isPie
                                            ? data.datasets[0].data[tooltipItem.index]
                                            : dataset.data[tooltipItem.index];
                                        const label = isPie
                                            ? chartConfig.labels[tooltipItem.index]
                                            : dataset.label;
                                        return `${label ? label + ': ' : ''}${formatValue(value, dataset)}`;
                                    },
                                },
                            },
                        },
                    });
                });

                document.querySelectorAll('[data-chart-download]').forEach((button) => {
                    button.addEventListener('click', function () {
                        const canvas = document.getElementById(this.dataset.chartDownload);
                        if (!canvas) {
                            return;
                        }

                        const link = document.createElement('a');
                        link.download = `${this.dataset.chartDownload}.png`;
                        link.href = canvas.toDataURL('image/png');
                        link.click();
                    });
                });
            });
        </script>
    @endpush
@endif
