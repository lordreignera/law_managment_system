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
                        <th>Outstanding</th>
                        <th>Recovered</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><a href="{{ route('recoveries.show', $account) }}">{{ $account->debtor_name }}</a></td>
                            <td>{{ $account->client?->name ?: '-' }}</td>
                            <td>{{ $account->account_number ?: '-' }}</td>
                            <td>{{ $account->assignee?->name ?: 'Unassigned' }}</td>
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td>{{ number_format($account->amount_recovered) }}</td>
                            <td><span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No recovery accounts yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
@endsection
