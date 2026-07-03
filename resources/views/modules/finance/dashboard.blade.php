@extends('layouts.admin')

@section('title', 'Finance Dashboard')
@section('page-title', 'Finance Dashboard')

@section('content')
    <div class="kfms-stat-grid">
        @foreach ($stats as $label => $value)
            <section class="kfms-card">
                <span class="kfms-card-label">{{ $label }}</span>
                <strong class="kfms-stat">{{ $value }}</strong>
            </section>
        @endforeach
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Finance Workflow</h2>
                <span>From spending requests to invoicing and collection</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('expenses.create')
                    <a class="kfms-link-btn" href="{{ route('expenses.create') }}">
                        <i class="mdi mdi-cash-minus"></i>
                        Record Expense
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

        <div class="kfms-litigation-lifecycle">
            @foreach ($flow as $item)
                <a href="{{ $item['route'] }}">
                    <span>{{ $loop->iteration }}</span>
                    <div>
                        <strong>{{ $item['stage'] }}</strong>
                        <p>{{ $item['description'] }}</p>
                    </div>
                    <em>{{ number_format($item['count']) }}</em>
                </a>
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
                        <tr><th>Invoice</th><th>Client</th><th>Total</th><th>Paid</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($recentInvoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->client?->display_name ?: $invoice->client?->name ?: '-' }}</td>
                                <td>{{ number_format($invoice->total) }}</td>
                                <td>{{ number_format($invoice->paid_amount) }}</td>
                                <td>{{ str($invoice->status)->headline() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No invoices yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

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
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Requisitions Awaiting Approval</h2>
                <span>Spending requests pending a finance decision</span>
            </div>
            @can('requisitions.index')
                <a class="kfms-link-btn" href="{{ route('requisitions.index') }}">View all <i class="mdi mdi-arrow-right"></i></a>
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
@endsection
