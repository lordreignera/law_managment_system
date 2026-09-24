@extends('layouts.admin')

@section('title', 'Recovery Reports')
@section('page-title', 'Recovery Reports')

@section('content')
    @php
        $reportQuery = [
            'year' => $year,
            'period' => $reportFilters['period'],
            'period_value' => $reportFilters['period_value'],
            'grain' => $reportFilters['grain'],
            'date_from' => $reportFilters['date_from'],
            'date_to' => $reportFilters['date_to'],
            'client' => $reportFilters['client'],
            'officer' => $reportFilters['officer'],
        ];
    @endphp

    <section class="kfms-panel kfms-recovery-report-filter-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recovery Reports</h2>
                <span>{{ $reportFilters['period_label'] }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('recoveries.export')
                    <details class="kfms-action-menu">
                        <summary><i class="mdi mdi-download-outline"></i> Export</summary>
                        <div class="kfms-action-menu-list">
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'collections', 'format' => 'xlsx'])) }}"><i class="mdi mdi-file-excel-outline"></i> Collections - Excel</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'collections', 'format' => 'pdf'])) }}"><i class="mdi mdi-file-pdf-box"></i> Collections - PDF</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'monthly', 'format' => 'xlsx'])) }}"><i class="mdi mdi-file-excel-outline"></i> Monthly - Excel</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'monthly', 'format' => 'pdf'])) }}"><i class="mdi mdi-file-pdf-box"></i> Monthly - PDF</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'officers', 'format' => 'xlsx'])) }}"><i class="mdi mdi-file-excel-outline"></i> Officers - Excel</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'officers', 'format' => 'pdf'])) }}"><i class="mdi mdi-file-pdf-box"></i> Officers - PDF</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'clients', 'format' => 'xlsx'])) }}"><i class="mdi mdi-file-excel-outline"></i> Bank / Client - Excel</a>
                            <a class="dropdown-item" href="{{ route('recoveries.export', array_merge($reportQuery, ['type' => 'clients', 'format' => 'pdf'])) }}"><i class="mdi mdi-file-pdf-box"></i> Bank / Client - PDF</a>
                        </div>
                    </details>
                @endcan
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
            </div>
        </div>

        <form class="kfms-table-toolbar kfms-recovery-report-filter-form" method="GET" action="{{ route('recoveries.reports') }}">
            <label>
                <span>Year</span>
                <select name="year">
                    @foreach ($years as $y)
                        <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Period</span>
                <select name="period">
                    <option value="month" @selected($reportFilters['period'] === 'month')>Monthly</option>
                    <option value="quarter" @selected($reportFilters['period'] === 'quarter')>Quarterly</option>
                    <option value="half_year" @selected($reportFilters['period'] === 'half_year')>Semi-year</option>
                    <option value="year" @selected($reportFilters['period'] === 'year')>Whole year</option>
                </select>
            </label>
            @if ($reportFilters['period'] === 'month')
                <label>
                    <span>Month</span>
                    <select name="period_value">
                        @foreach (range(1, 12) as $month)
                            <option value="{{ $month }}" @selected($reportFilters['period_value'] === $month)>{{ \Illuminate\Support\Carbon::create($year, $month, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif ($reportFilters['period'] === 'quarter')
                <label>
                    <span>Quarter</span>
                    <select name="period_value">
                        @foreach (range(1, 4) as $quarter)
                            <option value="{{ $quarter }}" @selected($reportFilters['period_value'] === $quarter)>Quarter {{ $quarter }}</option>
                        @endforeach
                    </select>
                </label>
            @elseif ($reportFilters['period'] === 'half_year')
                <label>
                    <span>Semi-year</span>
                    <select name="period_value">
                        <option value="1" @selected($reportFilters['period_value'] === 1)>January - June</option>
                        <option value="2" @selected($reportFilters['period_value'] === 2)>July - December</option>
                    </select>
                </label>
            @endif
            <label>
                <span>Bank / Client</span>
                <select name="client">
                    <option value="">All clients</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($reportFilters['client'] == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Assigned Officer</span>
                <select name="officer">
                    <option value="">All officers</option>
                    @foreach ($officers as $officer)
                        <option value="{{ $officer->id }}" @selected($reportFilters['officer'] == $officer->id)>{{ $officer->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit"><i class="mdi mdi-filter-outline"></i> Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('recoveries.reports') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-stat-grid">
            <section class="kfms-card">
                <span class="kfms-card-label">Accounts</span>
                <strong class="kfms-stat">{{ number_format($summary['accounts']) }}</strong>
            </section>
            <section class="kfms-card">
                <span class="kfms-card-label">Active</span>
                <strong class="kfms-stat">{{ number_format($summary['active']) }}</strong>
            </section>
            <section class="kfms-card">
                <span class="kfms-card-label">Outstanding</span>
                <strong class="kfms-stat">{{ number_format($summary['outstanding']) }}</strong>
            </section>
            <section class="kfms-card">
                <span class="kfms-card-label">Recovered</span>
                <strong class="kfms-stat">{{ number_format($summary['recovered']) }}</strong>
            </section>
        </div>
    </section>

    <div class="kfms-recovery-reports-stack">
    <section class="kfms-panel" data-recovery-report-section="collections">
        <div class="kfms-panel-header">
            <div>
                <h2>Collections in Selected Period</h2>
                <span>{{ $reportFilters['period_label'] }} grouped {{ ucfirst($reportFilters['grain']) }}</span>
            </div>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Bank / Client</th>
                        <th>Officer</th>
                        <th>Accounts Touched</th>
                        <th>Activities</th>
                        <th>Payments</th>
                        <th>Promised</th>
                        <th>Recovered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($activityRows as $row)
                        <tr>
                            <td>{{ $row['period'] }}</td>
                            <td>{{ $row['client'] }}</td>
                            <td>{{ $row['officer'] }}</td>
                            <td>{{ number_format($row['accounts']) }}</td>
                            <td>{{ number_format($row['activities']) }}</td>
                            <td>{{ number_format($row['payments']) }}</td>
                            <td>{{ number_format($row['promised'], 2) }}</td>
                            <td>{{ number_format($row['recovered'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="kfms-empty">No collection activity found for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel" data-recovery-report-section="monthly">
        <div class="kfms-panel-header">
            <div><h2>Monthly Recovery Graph</h2><span>Recovered amount by month within {{ $reportFilters['period_label'] }}</span></div>
        </div>
        <div class="kfms-recovery-monthly-chart" role="img" aria-label="Monthly recovery amounts">
            <div class="kfms-recovery-chart-heading">
                <span>Month and accounts opened</span>
                <strong>Recovered (UGX)</strong>
            </div>
            <div class="kfms-bar-chart">
                @forelse ($monthly as $row)
                    <div class="kfms-bar-row">
                        <span>{{ $row['month'] }} <small>{{ number_format($row['opened']) }} opened</small></span>
                        <div title="{{ number_format($row['recovered'], 2) }} recovered"><i style="width: {{ $row['percent'] }}%"></i></div>
                        <strong>{{ number_format($row['recovered'], 2) }}</strong>
                    </div>
                @empty
                    <div class="kfms-empty">No monthly recovery data for this filter.</div>
                @endforelse
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Month</th><th>Recoveries Opened</th><th>Amount Recovered</th></tr></thead>
                <tbody>
                    @forelse ($monthly as $row)
                        <tr>
                            <td>{{ $row['month'] }}</td>
                            <td>{{ number_format($row['opened']) }}</td>
                            <td>{{ number_format($row['recovered'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="kfms-empty">No monthly recovery data for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel" data-recovery-report-section="officers">
        <div class="kfms-panel-header">
            <div><h2>Performance by Officer</h2><span>Assigned vs recovered</span></div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Officer</th><th>Accounts</th><th>Outstanding</th><th>Recovered</th></tr></thead>
                <tbody>
                    @forelse ($byOfficer as $row)
                        <tr>
                            <td>{{ $row['officer'] }}</td>
                            <td>{{ number_format($row['accounts']) }}</td>
                            <td>{{ number_format($row['outstanding'], 2) }}</td>
                            <td>{{ number_format($row['recovered'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="kfms-empty">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel" data-recovery-report-section="clients">
        <div class="kfms-panel-header">
            <div><h2>Totals by Bank / Client</h2><span>Portfolio breakdown</span></div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Bank / Client</th><th>Accounts</th><th>Outstanding</th><th>Recovered</th></tr></thead>
                <tbody>
                    @forelse ($byClient as $row)
                        <tr>
                            <td>{{ $row['client'] }}</td>
                            <td>{{ number_format($row['accounts']) }}</td>
                            <td>{{ number_format($row['outstanding'], 2) }}</td>
                            <td>{{ number_format($row['recovered'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="kfms-empty">No data.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
    </div>
@endsection
