@extends('layouts.admin')

@section('title', 'Securities Dashboard')
@section('page-title', 'Securities Dashboard')

@section('content')
    <section class="kfms-hero-panel">
        <div>
            <span>Securities Control</span>
            <h2>Track land titles and securities in custody.</h2>
            <p>Monitor documents received from financial institutions, movement to MZO/zonal offices, handling officers, and returns.</p>
        </div>
        <div class="kfms-row-actions">
            <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">
                <i class="mdi mdi-format-list-bulleted"></i>
                Register
            </a>
            @can('land-titles.import')
                <a class="kfms-link-btn" href="{{ route('land-titles.import') }}">
                    <i class="mdi mdi-upload"></i>
                    Import
                </a>
            @endcan
            <a class="kfms-btn" href="{{ route('land-titles.create') }}">
                <i class="mdi mdi-plus"></i>
                Add Security
            </a>
        </div>
    </section>

    <div class="kfms-stat-grid">
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-file-document-outline"></i></span>
            <span class="kfms-card-label">Total Securities</span>
            <strong class="kfms-stat">{{ number_format($summary['total']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-lock-outline"></i></span>
            <span class="kfms-card-label">In Custody</span>
            <strong class="kfms-stat">{{ number_format($summary['in_custody']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-clock-outline"></i></span>
            <span class="kfms-card-label">Pending</span>
            <strong class="kfms-stat">{{ number_format($summary['pending']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-truck-delivery"></i></span>
            <span class="kfms-card-label">Dispatched</span>
            <strong class="kfms-stat">{{ number_format($summary['dispatched']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-check-circle-outline"></i></span>
            <span class="kfms-card-label">Returned</span>
            <strong class="kfms-stat">{{ number_format($summary['returned']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-icon"><i class="mdi mdi-calendar-month-outline"></i></span>
            <span class="kfms-card-label">Received This Month</span>
            <strong class="kfms-stat">{{ number_format($summary['received_this_month']) }}</strong>
        </section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Custody Queue</h2>
                    <span>Active securities awaiting return or closure</span>
                </div>
                <div class="kfms-row-actions">
                    @can('land-titles.dashboard.export')
                        <a class="kfms-link-btn" href="{{ route('land-titles.dashboard.export', 'custody') }}"><i class="mdi mdi-download"></i> Export</a>
                    @endcan
                    <a class="kfms-link-btn" href="{{ route('land-titles.index', ['status' => 'pending']) }}">
                        Review Pending
                        <i class="mdi mdi-arrow-right"></i>
                    </a>
                </div>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Borrower</th>
                            <th>Institution</th>
                            <th>Handler</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($custodyQueue as $title)
                            <tr>
                                <td><a href="{{ route('land-titles.show', $title) }}">{{ $title->reference_no }}</a></td>
                                <td>{{ $title->borrower_name }}</td>
                                <td>{{ $title->bank?->name ?: '-' }}</td>
                                <td>{{ $title->handler?->name ?: '-' }}</td>
                                <td>{{ $title->statusLabel() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No active securities in custody.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Status Breakdown</h2>
                    <span>Current securities movement state</span>
                </div>
                @can('land-titles.dashboard.export')
                    <a class="kfms-link-btn" href="{{ route('land-titles.dashboard.export', 'status') }}"><i class="mdi mdi-download"></i> Export</a>
                @endcan
            </div>
            <div class="kfms-dashboard-bar-list">
                @php($maxStatus = max($statusRows->max('count') ?: 0, 1))
                @foreach ($statusRows as $row)
                    <div class="kfms-bar-row">
                        <span>{{ $row['label'] }}</span>
                        <div><i style="width: {{ max(8, round(($row['count'] / $maxStatus) * 100)) }}%"></i></div>
                        <strong>{{ number_format($row['count']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>By Financial Institution</h2>
                    <span>Top institutions by securities volume</span>
                </div>
                @can('land-titles.dashboard.export')
                    <a class="kfms-link-btn" href="{{ route('land-titles.dashboard.export', 'banks') }}"><i class="mdi mdi-download"></i> Export</a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead><tr><th>Institution</th><th>Total</th><th>In Custody</th><th>Returned</th></tr></thead>
                    <tbody>
                        @forelse ($bankRows as $row)
                            <tr>
                                <td>{{ $row['bank'] }}</td>
                                <td>{{ number_format($row['count']) }}</td>
                                <td>{{ number_format($row['in_custody']) }}</td>
                                <td>{{ number_format($row['returned']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No institution data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>By MZO / Zonal Office</h2>
                    <span>Where securities are moving from or to</span>
                </div>
                @can('land-titles.dashboard.export')
                    <a class="kfms-link-btn" href="{{ route('land-titles.dashboard.export', 'zonal-offices') }}"><i class="mdi mdi-download"></i> Export</a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead><tr><th>MZO / Zonal Office</th><th>Total</th><th>In Custody</th></tr></thead>
                    <tbody>
                        @forelse ($zonalRows as $row)
                            <tr>
                                <td>{{ $row['office'] }}</td>
                                <td>{{ number_format($row['count']) }}</td>
                                <td>{{ number_format($row['in_custody']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="kfms-empty">No MZO data yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recent Securities</h2>
                <span>Latest records added or updated</span>
            </div>
            <div class="kfms-row-actions">
                @can('land-titles.dashboard.export')
                    <a class="kfms-link-btn" href="{{ route('land-titles.dashboard.export', 'recent') }}"><i class="mdi mdi-download"></i> Export</a>
                @endcan
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">View All</a>
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Borrower</th>
                        <th>Institution / Branch</th>
                        <th>MZO</th>
                        <th>Received</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentTitles as $title)
                        <tr>
                            <td><a href="{{ route('land-titles.show', $title) }}">{{ $title->reference_no }}</a></td>
                            <td>{{ $title->borrower_name }}</td>
                            <td>
                                {{ $title->bank?->name ?: '-' }}<br>
                                <span class="kfms-muted">{{ $title->bankBranch?->name ?: '-' }}</span>
                            </td>
                            <td>{{ $title->zonalOffice?->name ?: '-' }}</td>
                            <td>{{ $title->received_at?->format('d M Y, H:i') ?: '-' }}</td>
                            <td>{{ $title->statusLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="kfms-empty">No securities registered yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
