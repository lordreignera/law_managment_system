@extends('layouts.admin')

@section('title', 'Expenses')
@section('page-title', 'Expenses')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($summary as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ $label === 'Total Entries' ? number_format($value) : number_format($value, 2) }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Expenditure Records</h2>
                <span>{{ $expenses->total() }} records</span>
            </div>
            <a class="kfms-btn" href="{{ route('expenses.create') }}">
                <i class="mdi mdi-plus"></i>
                Record Expense
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('expenses.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search reference, description or payee">
            </label>
            <label>
                <span>Category</span>
                <select name="expense_category_id">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) ($filters['expense_category_id'] ?? '') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Payment Source</span>
                <select name="payment_source">
                    <option value="">All Sources</option>
                    @foreach ($paymentSources as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['payment_source'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('expenses.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Payee</th>
                        <th>Source</th>
                        <th>Amount</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->reference_no }}</td>
                            <td>{{ $expense->spent_on?->format('d M Y') }}</td>
                            <td>{{ $expense->category?->name ?: '-' }}</td>
                            <td>{{ \Illuminate\Support\Str::limit($expense->description, 32) }}</td>
                            <td>{{ $expense->payee ?: '-' }}</td>
                            <td>{{ $expense->paymentSourceLabel() }}</td>
                            <td>{{ number_format($expense->amount, 2) }}</td>
                            <td><a class="kfms-link-btn" href="{{ route('expenses.show', $expense) }}">View</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No expenses recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $expenses->links() }}
    </section>
@endsection
