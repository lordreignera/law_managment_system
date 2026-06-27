@extends('layouts.admin')

@section('title', 'My Recoveries')
@section('page-title', 'My Recoveries')

@section('content')
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
            <span class="kfms-card-label">Outstanding</span>
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
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td>{{ number_format($account->amount_recovered) }}</td>
                            <td><span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="kfms-empty">Nothing assigned to you yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
@endsection
