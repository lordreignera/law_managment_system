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
