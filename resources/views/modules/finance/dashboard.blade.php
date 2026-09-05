@extends('layouts.admin')

@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')

@section('content')
    <section class="kfms-dashboard-hero kfms-finance-dashboard-hero">
        <div>
            <span>Finance control room</span>
            <h2>Track spending, invoices, collections, and ledger movement.</h2>
            <p>Finance teams monitor requisitions, expenses, petty cash, invoice balances, and chart-of-account activity from one place.</p>
        </div>
        <div class="kfms-dashboard-hero-actions">
            @can('finance.index')
                <a class="kfms-btn" href="{{ route('finance.index') }}">
                    <i class="mdi mdi-file-document-outline"></i>
                    Finance Overview
                </a>
            @endcan
            @can('requisitions.create')
                <a class="kfms-link-btn" href="{{ route('requisitions.create') }}">
                    <i class="mdi mdi-clipboard-plus-outline"></i>
                    New Requisition
                </a>
            @endcan
            @can('expenses.create')
                <a class="kfms-link-btn" href="{{ route('expenses.create') }}">
                    <i class="mdi mdi-cash-minus"></i>
                    Record Expense
                </a>
            @endcan
            @can('petty-cash.index')
                <a class="kfms-link-btn" href="{{ route('petty-cash.index') }}">
                    <i class="mdi mdi-wallet-outline"></i>
                    Petty Cash
                </a>
            @endcan
        </div>
    </section>

    <div class="kfms-stat-grid kfms-dashboard-kpis">
        @foreach ($stats as $stat)
            @php($canOpen = $stat['route'] && (! $stat['permission'] || auth()->user()?->can($stat['permission'])))
            @if ($canOpen)
                <a class="kfms-card kfms-stat-card" href="{{ $stat['route'] }}">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ $stat['value'] }}</strong>
                    </span>
                </a>
            @else
                <section class="kfms-card kfms-stat-card">
                    <span class="kfms-stat-icon"><i class="mdi {{ $stat['icon'] }}"></i></span>
                    <span class="kfms-stat-body">
                        <span class="kfms-card-label">{{ $stat['label'] }}</span>
                        <strong class="kfms-stat">{{ $stat['value'] }}</strong>
                    </span>
                </section>
            @endif
        @endforeach
    </div>

    <section class="kfms-panel kfms-finance-workflow-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Finance Workflow</h2>
                <span>From spending requests to invoicing and collection</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('finance.chart-accounts.index')
                    <a class="kfms-link-btn kfms-link-btn-info" href="{{ route('finance.chart-accounts.index') }}">
                        <i class="mdi mdi-format-list-numbered"></i>
                        Chart of Accounts
                    </a>
                @endcan
                @can('finance.index')
                    <a class="kfms-btn" href="{{ route('finance.index') }}">
                        <i class="mdi mdi-file-document-outline"></i>
                        Finance Overview
                    </a>
                @endcan
            </div>
        </div>

        <div class="kfms-finance-workflow-buttons" aria-label="Finance workflow shortcuts">
            @foreach ($flow as $item)
                @php($canOpen = $item['route'] && (! $item['permission'] || auth()->user()?->can($item['permission'])))
                @if ($canOpen)
                    <a class="kfms-finance-workflow-button" href="{{ $item['route'] }}">
                        <span><i class="mdi {{ $item['icon'] }}"></i>{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $item['stage'] }}</strong>
                            <p>{{ $item['description'] }}</p>
                        </div>
                        <em>{{ number_format($item['count']) }}</em>
                    </a>
                @else
                    <section class="kfms-finance-workflow-button is-disabled">
                        <span><i class="mdi {{ $item['icon'] }}"></i>{{ $loop->iteration }}</span>
                        <div>
                            <strong>{{ $item['stage'] }}</strong>
                            <p>{{ $item['description'] }}</p>
                        </div>
                        <em>{{ number_format($item['count']) }}</em>
                    </section>
                @endif
            @endforeach
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Invoices</h2>
                    <span>Latest billing activity</span>
                </div>
                @can('finance.index')
                    <a class="kfms-link-btn" href="{{ route('finance.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr><th>Invoice</th><th>Client</th><th>Total</th><th>Paid</th><th>Status</th><th>Documents</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->client?->display_name ?: $invoice->client?->name ?: '-' }}</td>
                                <td>{{ number_format($invoice->total) }}</td>
                                <td>{{ number_format($invoice->paid_amount) }}</td>
                                <td>{{ str($invoice->status)->headline() }}</td>
                                <td>
                                    <div class="kfms-table-actions">
                                        <a href="{{ route('finance.invoices.documents.show', [$invoice, 'invoice']) }}">Invoice</a>
                                        <a href="{{ route('finance.invoices.documents.show', [$invoice, 'fee-note']) }}">Fee Note</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="kfms-empty">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Requisitions Awaiting Approval</h2>
                    <span>Spending requests pending a finance decision</span>
                </div>
                @can('requisitions.index')
                    <a class="kfms-link-btn" href="{{ route('requisitions.index', ['status' => 'submitted']) }}">View all <i class="mdi mdi-arrow-right"></i></a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr><th>Reference</th><th>Requested By</th><th>Purpose</th><th>Amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($pendingRequisitions as $requisition)
                            <tr>
                                <td>{{ $requisition->reference_no }}</td>
                                <td>{{ $requisition->requester?->name ?: '-' }}</td>
                                <td>{{ $requisition->purpose }}</td>
                                <td>{{ number_format($requisition->amount) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="kfms-empty">No requisitions awaiting approval.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recent Expenses</h2>
                <span>Latest recorded spending</span>
            </div>
            @can('expenses.index')
                <a class="kfms-link-btn" href="{{ route('expenses.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
            @endcan
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr><th>Reference</th><th>Category</th><th>Amount</th><th>Spent On</th></tr>
                </thead>
                <tbody>
                    @forelse ($recentExpenses as $expense)
                        <tr>
                            <td>{{ $expense->reference_no }}</td>
                            <td>{{ $expense->category?->name ?: '-' }}</td>
                            <td>{{ number_format($expense->amount) }}</td>
                            <td>{{ $expense->spent_on?->format('d M Y') ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="kfms-empty">No expenses recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
