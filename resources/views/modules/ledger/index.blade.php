@extends('layouts.admin')

@section('title', 'Expenditure Ledger')
@section('page-title', 'Expenditure Ledger')

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
                <h2>Expenditure by Category</h2>
                <span>Direct expenses + petty cash disbursements</span>
            </div>
        </div>

        <form class="kfms-table-toolbar" method="GET" action="{{ route('ledger.index') }}">
            <label>
                <span>From</span>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}">
            </label>
            <label>
                <span>To</span>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}">
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply</button>
                <a class="kfms-link-btn" href="{{ route('ledger.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Direct Expenses</th>
                        <th>Petty Cash</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($ledgerRows as $row)
                        <tr>
                            <td>{{ $row['category'] }}</td>
                            <td>{{ number_format($row['expenses'], 2) }}</td>
                            <td>{{ number_format($row['petty_cash'], 2) }}</td>
                            <td><strong>{{ number_format($row['total'], 2) }}</strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="kfms-empty">No expenditure recorded for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($ledgerRows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th>Total</th>
                            <th>{{ number_format($ledgerRows->sum('expenses'), 2) }}</th>
                            <th>{{ number_format($ledgerRows->sum('petty_cash'), 2) }}</th>
                            <th>{{ number_format($ledgerRows->sum('total'), 2) }}</th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </section>
@endsection
