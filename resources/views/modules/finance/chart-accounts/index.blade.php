@extends('layouts.admin')

@section('title', 'Chart of Accounts')
@section('page-title', 'Chart of Accounts')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Legal Chart of Accounts</h2>
                <span>{{ $accounts->total() }} accounts from the finance register</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('finance.chart-accounts.export')
                    <a class="kfms-link-btn kfms-link-btn-success" href="{{ route('finance.chart-accounts.export', $filters) }}">
                        <i class="mdi mdi-microsoft-excel"></i>
                        Export
                    </a>
                @endcan
                @can('finance.chart-accounts.create')
                    <a class="kfms-btn" href="{{ route('finance.chart-accounts.create') }}">
                        <i class="mdi mdi-plus"></i>
                        Add Account
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('finance.chart-accounts.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search account no, name, class or parent">
            </label>
            <label>
                <span>Class</span>
                <select name="account_class_id">
                    <option value="">All Classes</option>
                    @foreach ($classes as $class)
                        <option value="{{ $class->id }}" @selected((string) ($filters['account_class_id'] ?? '') === (string) $class->id)>{{ $class->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Type</span>
                <select name="account_type">
                    <option value="">All Types</option>
                    @foreach ($accountTypes as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['account_type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Postable</span>
                <select name="postable">
                    <option value="">All</option>
                    <option value="yes" @selected(($filters['postable'] ?? '') === 'yes')>Postable only</option>
                    <option value="no" @selected(($filters['postable'] ?? '') === 'no')>Control only</option>
                </select>
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Account No.</th>
                        <th>Name</th>
                        <th>Class</th>
                        <th>Parent</th>
                        <th>Type</th>
                        <th>Flags</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td><strong>{{ $account->account_number }}</strong></td>
                            <td>
                                <span style="padding-left: {{ max($account->level - 1, 0) * 16 }}px">{{ $account->name }}</span>
                                @unless ($account->is_postable)
                                    <span class="kfms-status">Control</span>
                                @endunless
                            </td>
                            <td>{{ $account->accountClass?->name ?: '-' }}</td>
                            <td>{{ $account->parent?->fullName() ?: '-' }}</td>
                            <td>{{ $account->typeLabel() }}</td>
                            <td>
                                @if ($account->is_bank_account)<span class="kfms-status">Bank</span>@endif
                                @if ($account->is_cash_account)<span class="kfms-status">Cash</span>@endif
                                @if ($account->is_client_funds_account)<span class="kfms-status">Client Funds</span>@endif
                                {{ $account->currency_code ?: '' }}
                            </td>
                            <td><span class="kfms-status kfms-status-{{ $account->is_active ? 'active' : 'rejected' }}">{{ $account->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td>
                                <div class="kfms-inline-actions">
                                    <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.show', $account) }}">View</a>
                                    @can('finance.chart-accounts.edit')
                                        <a class="kfms-link-btn" href="{{ route('finance.chart-accounts.edit', $account) }}">Edit</a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No chart accounts found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $accounts->links() }}
    </section>
@endsection
