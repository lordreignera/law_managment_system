@extends('layouts.admin')

@section('title', 'Petty Cash')
@section('page-title', 'Petty Cash')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ number_format($value, 2) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Petty Cash Book</h2>
                <span>{{ $transactions->total() }} transactions</span>
            </div>
            <a class="kfms-btn" href="{{ route('petty-cash.create') }}">
                <i class="mdi mdi-plus"></i>
                New Transaction
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('petty-cash.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, description or payee">
            </label>
            <label>
                <span>Type</span>
                <select name="type">
                    <option value="">All Types</option>
                    @foreach ($types as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('petty-cash.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Top-up</th>
                        <th>Disbursed</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $transaction)
                        <tr>
                            <td>{{ $transaction->reference_no }}</td>
                            <td>{{ $transaction->transacted_on?->format('d M Y') }}</td>
                            <td>{{ $transaction->typeLabel() }}</td>
                            <td>{{ $transaction->category?->name ?: '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($transaction->description, 32) }}</td>
                            <td>{{ $transaction->isInflow() ? number_format($transaction->amount, 2) : '-' }}</td>
                            <td>{{ $transaction->isInflow() ? '-' : number_format($transaction->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="kfms-empty">No petty cash transactions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $transactions->links() }}
    </section>
@endsection
