@extends('layouts.admin')

@section('title', 'My Recoveries')
@section('page-title', 'My Recoveries')

@section('content')
    @php
        $reportExportParams = [
            'report_grain' => $reportFilters['grain'],
            'report_date_from' => $reportFilters['date_from'],
            'report_date_to' => $reportFilters['date_to'],
            'report_client' => $reportFilters['client'],
        ];
    @endphp

    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <div class="kfms-stat-grid">
        <section class="kfms-card">
            <span class="kfms-card-label">Assigned</span>
            <strong class="kfms-stat">{{ number_format($summary['total']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-label">Active</span>
            <strong class="kfms-stat">{{ number_format($summary['active']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-label">Net Outstanding</span>
            <strong class="kfms-stat">{{ number_format($summary['outstanding']) }}</strong>
        </section>
        <section class="kfms-card">
            <span class="kfms-card-label">Recovered</span>
            <strong class="kfms-stat">{{ number_format($summary['recovered']) }}</strong>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>My Collection Report</h2>
                <span>Payments you have logged today, this week, and this month</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.mine.export', array_merge($reportExportParams, ['format' => 'xlsx'])) }}">
                    <i class="mdi mdi-file-excel"></i>
                    Excel
                </a>
                <a class="kfms-link-btn" href="{{ route('recoveries.mine.export', array_merge($reportExportParams, ['format' => 'pdf'])) }}">
                    <i class="mdi mdi-file-pdf-box"></i>
                    PDF
                </a>
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.mine') }}">
            <input type="hidden" name="search" value="{{ $filters['search'] ?? '' }}">
            <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
            <label>
                <span>Group By</span>
                <select name="report_grain">
                    <option value="daily" @selected($reportFilters['grain'] === 'daily')>Daily</option>
                    <option value="weekly" @selected($reportFilters['grain'] === 'weekly')>Weekly</option>
                    <option value="monthly" @selected($reportFilters['grain'] === 'monthly')>Monthly</option>
                </select>
            </label>
            <label>
                <span>From</span>
                <input type="date" name="report_date_from" value="{{ $reportFilters['date_from'] }}">
            </label>
            <label>
                <span>To</span>
                <input type="date" name="report_date_to" value="{{ $reportFilters['date_to'] }}">
            </label>
            <label>
                <span>Bank / Client</span>
                <select name="report_client">
                    <option value="">All banks</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected($reportFilters['client'] == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit"><i class="mdi mdi-filter-outline"></i> Apply</button>
                <a class="kfms-link-btn" href="{{ route('recoveries.mine', $filters) }}">Reset Report</a>
            </div>
        </form>

        <div class="kfms-stat-grid kfms-recovery-collections-grid">
            @foreach ($collectionReports as $report)
                <section class="kfms-card">
                    <span class="kfms-card-label">{{ $report['label'] }}</span>
                    <strong class="kfms-stat">UGX {{ number_format($report['amount'], 2) }}</strong>
                    <small>{{ number_format($report['payments']) }} payment(s) - {{ $report['period'] }}</small>
                </section>
            @endforeach
        </div>

        <div class="kfms-recovery-report-visuals">
            <section class="kfms-recovery-chart-panel">
                <div class="kfms-panel-header">
                    <div>
                        <h2>Collections by Period</h2>
                        <span>{{ ucfirst($reportFilters['grain']) }} totals in the selected range</span>
                    </div>
                </div>
                <div class="kfms-bar-chart" aria-label="Collections by period">
                    @forelse ($collectionChart['rows'] as $row)
                        <div class="kfms-bar-row">
                            <span>{{ $row['label'] }}</span>
                            <div>
                                <i style="width: {{ $row['percent'] }}%"></i>
                            </div>
                            <strong>{{ number_format($row['amount'], 2) }}</strong>
                        </div>
                    @empty
                        <div class="kfms-empty">No collection totals for this filter.</div>
                    @endforelse
                </div>
            </section>

            <section class="kfms-recovery-chart-panel">
                <div class="kfms-panel-header">
                    <div>
                        <h2>Collections by Bank</h2>
                        <span>Share of recovered money in the selected range</span>
                    </div>
                </div>
                <div class="kfms-pie-chart-wrap">
                    <div class="kfms-pie-chart" style="background: conic-gradient({{ $bankChart['gradient'] }});">
                        <span>{{ number_format($bankChart['total'], 0) }}</span>
                    </div>
                    <div class="kfms-pie-legend">
                        @forelse ($bankChart['rows'] as $row)
                            <div>
                                <i style="background: {{ $row['color'] }}"></i>
                                <span>{{ $row['client'] }}</span>
                                <strong>{{ number_format($row['amount'], 2) }} ({{ number_format($row['share'], 1) }}%)</strong>
                            </div>
                        @empty
                            <div class="kfms-empty">No bank/client collections for this filter.</div>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Debtor</th>
                        <th>Bank/Client</th>
                        <th>Amount Paid</th>
                        <th>Balance After</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($filteredCollections as $collection)
                        <tr>
                            <td>{{ $collection->activity_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $collection->account?->debtor_name ?: '-' }}</td>
                            <td>{{ $collection->account?->client?->name ?: '-' }}</td>
                            <td>{{ $collection->account?->currency ?: 'UGX' }} {{ number_format($collection->amount_paid, 2) }}</td>
                            <td>{{ $collection->outstanding_balance_after !== null ? ($collection->account?->currency ?: 'UGX').' '.number_format($collection->outstanding_balance_after, 2) : '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="kfms-empty">No collections found for this report filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Accounts Assigned to Me</h2>
                <span>{{ $accounts->total() }} records</span>
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.mine') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search debtor or account">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                    <option value="written_off" @selected(($filters['status'] ?? '') === 'written_off')>Written Off</option>
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('recoveries.mine') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Debtor</th>
                        <th>Bank/Client</th>
                        <th>Account</th>
                        <th>Original Outstanding</th>
                        <th>Net Outstanding</th>
                        <th>Recovered</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><a href="{{ route('recoveries.show', $account) }}">{{ $account->debtor_name }}</a></td>
                            <td>{{ $account->client?->name ?: '-' }}</td>
                            <td>{{ $account->account_number ?: '-' }}</td>
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td>{{ number_format($account->net_outstanding_balance) }}</td>
                            <td>{{ number_format($account->amount_recovered) }}</td>
                            <td><span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    @can('recoveries.activities.store')
                                        <button type="button" data-bs-toggle="modal" data-bs-target="#recovery-activity-modal-{{ $account->id }}">
                                            <i class="mdi mdi-clipboard-text-clock-outline"></i>
                                            Report Activity
                                        </button>
                                    @endcan
                                    @can('recoveries.show')
                                        <a href="{{ route('recoveries.show', $account) }}">View</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>

                        @can('recoveries.activities.store')
                            @push('modals')
                                @include('modules.recoveries.partials.activity-modal', ['account' => $account, 'activityTypes' => $activityTypes, 'redirectTo' => 'mine'])
                            @endpush
                        @endcan
                    @empty
                        <tr><td colspan="8" class="kfms-empty">Nothing assigned to you yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
@endsection
