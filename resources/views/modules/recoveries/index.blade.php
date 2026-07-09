@extends('layouts.admin')

@section('title', 'Recoveries')
@section('page-title', 'Recoveries')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recovery Accounts</h2>
                <span>{{ $accounts->total() }} records</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('recoveries.dashboard')
                    <a class="kfms-link-btn" href="{{ route('recoveries.dashboard') }}"><i class="mdi mdi-view-dashboard-outline"></i> Dashboard</a>
                @endcan
                @can('recoveries.import')
                    <a class="kfms-link-btn" href="{{ route('recoveries.import') }}"><i class="mdi mdi-file-upload-outline"></i> Import</a>
                @endcan
                @can('recoveries.accounts.export')
                    <a class="kfms-link-btn" href="{{ route('recoveries.accounts.export', $filters) }}"><i class="mdi mdi-file-excel-outline"></i> Export</a>
                @endcan
                @can('recoveries.reports')
                    <a class="kfms-link-btn" href="{{ route('recoveries.reports') }}"><i class="mdi mdi-chart-bar"></i> Reports</a>
                @endcan
                @can('recoveries.create')
                    <a class="kfms-btn" href="{{ route('recoveries.create') }}"><i class="mdi mdi-plus"></i> Add Recovery</a>
                @endcan
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search debtor, bank/client, or account">
            </label>
            <label>
                <span>Bank/Client</span>
                <select name="client">
                    <option value="">All</option>
                    @foreach ($clients as $client)
                        <option value="{{ $client->id }}" @selected(($filters['client'] ?? '') == $client->id)>{{ $client->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Officer</span>
                <select name="officer">
                    <option value="">All</option>
                    @foreach ($officers as $officer)
                        <option value="{{ $officer->id }}" @selected(($filters['officer'] ?? '') == $officer->id)>{{ $officer->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Portfolio</span>
                <select name="portfolio_type">
                    <option value="">All Portfolios</option>
                    @foreach ($portfolioTypes as $type)
                        <option value="{{ $type }}" @selected(($filters['portfolio_type'] ?? '') === $type)>{{ $type }}</option>
                    @endforeach
                </select>
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
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Debtor</th>
                        <th>Bank/Client</th>
                        <th>Account</th>
                        <th>Officer</th>
                        <th>Portfolio</th>
                        <th>Outstanding</th>
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
                            <td>{{ $account->assignee?->name ?: 'Unassigned' }}</td>
                            <td>{{ $account->portfolio_type ?: $account->bucket ?: '-' }}</td>
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td>{{ number_format($account->amount_recovered) }}</td>
                            <td><span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span></td>
                            <td>
                                <div class="kfms-table-actions">
                                    @can('recoveries.show')
                                        <a href="{{ route('recoveries.show', $account) }}">
                                            <i class="mdi mdi-eye-outline"></i>
                                            View
                                        </a>
                                    @endcan

                                    @can('recoveries.update')
                                        <a href="{{ route('recoveries.edit', $account) }}">
                                            <i class="mdi mdi-account-switch-outline"></i>
                                            Edit / Assign
                                        </a>
                                    @endcan

                                    @can('recoveries.destroy')
                                        <form method="POST" action="{{ route('recoveries.destroy', $account) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="is-danger" type="submit" onclick="return confirm('Delete this recovery account?')">
                                                <i class="mdi mdi-delete-outline"></i>
                                                Delete
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="kfms-empty">No recovery accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
@endsection
