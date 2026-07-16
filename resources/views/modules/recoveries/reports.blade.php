@extends('layouts.admin')

@section('title', 'Recovery Reports')
@section('page-title', 'Recovery Reports')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recovery Reports</h2>
                <span>Year {{ $year }}</span>
            </div>
            <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.reports') }}">
                <label>
                    <span>Year</span>
                    <select name="year" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                        @endforeach
                    </select>
                </label>
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
            </form>
        </div>

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

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Daily / Weekly Collections</h2>
                <span>Filter collection activity by assigned officer and bank/client</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.export', array_merge($reportFilters, ['type' => 'collections', 'format' => 'xlsx', 'year' => $year])) }}"><i class="mdi mdi-file-excel"></i> Excel</a>
                <a class="kfms-link-btn" href="{{ route('recoveries.export', array_merge($reportFilters, ['type' => 'collections', 'format' => 'pdf', 'year' => $year])) }}"><i class="mdi mdi-file-pdf-box"></i> PDF</a>
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.reports') }}">
            <input type="hidden" name="year" value="{{ $year }}">
            <label>
                <span>Report Type</span>
                <select name="grain">
                    <option value="daily" @selected($reportFilters['grain'] === 'daily')>Daily</option>
                    <option value="weekly" @selected($reportFilters['grain'] === 'weekly')>Weekly</option>
                </select>
            </label>
            <label>
                <span>From</span>
                <input type="date" name="date_from" value="{{ $reportFilters['date_from'] }}">
            </label>
            <label>
                <span>To</span>
                <input type="date" name="date_to" value="{{ $reportFilters['date_to'] }}">
            </label>
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
                <button class="kfms-link-btn" type="submit"><i class="mdi mdi-filter-outline"></i> Apply</button>
                <a class="kfms-link-btn" href="{{ route('recoveries.reports') }}">Reset</a>
            </div>
        </form>

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

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Monthly Recoveries</h2><span>Opened &amp; recovered per month</span></div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'monthly', 'format' => 'xlsx', 'year' => $year]) }}"><i class="mdi mdi-file-excel"></i> Excel</a>
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'monthly', 'format' => 'pdf', 'year' => $year]) }}"><i class="mdi mdi-file-pdf-box"></i> PDF</a>
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead><tr><th>Month</th><th>Recoveries Opened</th><th>Amount Recovered</th></tr></thead>
                <tbody>
                    @foreach ($monthly as $row)
                        <tr>
                            <td>{{ $row['month'] }}</td>
                            <td>{{ number_format($row['opened']) }}</td>
                            <td>{{ number_format($row['recovered'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Performance by Officer</h2><span>Assigned vs recovered</span></div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'officers', 'format' => 'xlsx', 'year' => $year]) }}"><i class="mdi mdi-file-excel"></i> Excel</a>
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'officers', 'format' => 'pdf', 'year' => $year]) }}"><i class="mdi mdi-file-pdf-box"></i> PDF</a>
            </div>
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

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div><h2>Totals by Bank / Client</h2><span>Portfolio breakdown</span></div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'clients', 'format' => 'xlsx', 'year' => $year]) }}"><i class="mdi mdi-file-excel"></i> Excel</a>
                <a class="kfms-link-btn" href="{{ route('recoveries.export', ['type' => 'clients', 'format' => 'pdf', 'year' => $year]) }}"><i class="mdi mdi-file-pdf-box"></i> PDF</a>
            </div>
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
@endsection
