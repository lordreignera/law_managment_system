@extends('layouts.admin')

@section('title', 'Recoveries')
@section('page-title', 'Recoveries')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <h2>Recovery Accounts</h2>
            <span>{{ $accounts->total() }} records</span>
        </div>
        <form class="kfms-table-toolbar" method="GET" action="{{ route('recoveries.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search debtor, bank/client, or account">
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
        @include('modules.partials.table', [
            'headers' => ['Debtor', 'Bank/Client', 'Account', 'Outstanding', 'Recovered', 'Status'],
            'rows' => $accounts->map(fn ($account) => [$account->debtor_name, $account->client?->name, $account->account_number, number_format($account->outstanding_amount), number_format($account->amount_recovered), $account->status]),
        ])
        {{ $accounts->links() }}
    </section>
@endsection
