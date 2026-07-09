@extends('layouts.admin')

@section('title', 'Recovery Dashboard')
@section('page-title', 'Recovery Dashboard')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-dashboard-hero kfms-recovery-dashboard-hero">
        <div>
            <span>Recovery control room</span>
            <h2>Import, assign, and monitor client recovery portfolios.</h2>
            <p>Managers track imported bank portfolios, assignment coverage, collections, and officer performance from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('recoveries.import')
                <a class="kfms-btn" href="{{ route('recoveries.import') }}"><i class="mdi mdi-file-upload-outline"></i> Import Portfolio</a>
            @endcan
            @can('recoveries.index')
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}"><i class="mdi mdi-format-list-bulleted"></i> Register</a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        <section class="kfms-card kfms-stat-card">
            <span class="kfms-stat-icon"><i class="mdi mdi-bank-outline"></i></span>
            <span class="kfms-stat-body"><span class="kfms-card-label">Active Accounts</span><strong class="kfms-stat">{{ number_format($summary['active']) }}</strong></span>
        </section>
        <section class="kfms-card kfms-stat-card">
            <span class="kfms-stat-icon"><i class="mdi mdi-account-clock-outline"></i></span>
            <span class="kfms-stat-body"><span class="kfms-card-label">Unassigned</span><strong class="kfms-stat">{{ number_format($summary['unassigned']) }}</strong></span>
        </section>
        <section class="kfms-card kfms-stat-card">
            <span class="kfms-stat-icon"><i class="mdi mdi-cash-remove"></i></span>
            <span class="kfms-stat-body"><span class="kfms-card-label">Outstanding</span><strong class="kfms-stat">{{ number_format($summary['outstanding']) }}</strong></span>
        </section>
        <section class="kfms-card kfms-stat-card">
            <span class="kfms-stat-icon"><i class="mdi mdi-cash-check"></i></span>
            <span class="kfms-stat-body"><span class="kfms-card-label">Recovered</span><strong class="kfms-stat">{{ number_format($summary['recovered']) }}</strong></span>
        </section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Imports</h2>
                    <span>Latest portfolio batches</span>
                </div>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Batch</th>
                            <th>Client</th>
                            <th>Rows</th>
                            <th>Assigned</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentBatches as $batch)
                            <tr>
                                <td><a href="{{ route('recoveries.batches.show', $batch) }}">{{ $batch->portfolio_type }}</a><br><small>{{ $batch->source_file }}</small></td>
                                <td>{{ $batch->client?->name }}</td>
                                <td>{{ number_format($batch->imported_rows) }}</td>
                                <td>{{ number_format($batch->assigned_count) }}</td>
                                <td><span class="kfms-status is-active">{{ ucfirst($batch->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No imported portfolios yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Officer Load</h2>
                    <span>Active recovery workload and collections</span>
                </div>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Officer</th>
                            <th>Active</th>
                            <th>Outstanding</th>
                            <th>Recovered</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($officerRows as $officer)
                            <tr>
                                <td>{{ $officer->name }}</td>
                                <td>{{ number_format($officer->active_recoveries_count) }}</td>
                                <td>{{ number_format($officer->outstanding_total ?? 0) }}</td>
                                <td>{{ number_format($officer->recovered_total ?? 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No recovery officers found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recovery Clients</h2>
                <span>Client portfolios and current exposure</span>
            </div>
            @can('settings.system.index')
                <a class="kfms-link-btn" href="{{ route('settings.system.index', 'recovery-clients') }}"><i class="mdi mdi-cog-outline"></i> Manage Clients</a>
            @endcan
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Client</th>
                        <th>Portfolio Types</th>
                        <th>Accounts</th>
                        <th>Outstanding</th>
                        <th>Recovered</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($clientRows as $client)
                        <tr>
                            <td>{{ $client->name }}</td>
                            <td>{{ $client->portfolio_types ? implode(', ', $client->portfolio_types) : '-' }}</td>
                            <td>{{ number_format($client->accounts_count) }}</td>
                            <td>{{ number_format($client->outstanding_total ?? 0) }}</td>
                            <td>{{ number_format($client->recovered_total ?? 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="kfms-empty">No recovery clients configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
