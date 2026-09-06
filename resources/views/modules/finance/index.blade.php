@extends('layouts.admin')

@section('title', 'Finance')
@section('page-title', 'Finance')

@section('content')
    @php($fromDashboard = request('from') === 'finance-dashboard')

    <section class="kfms-panel kfms-finance-overview-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Finance Overview</h2>
                <span>Invoices, payment receipts, and spending requests in one finance view.</span>
            </div>
            <div class="kfms-toolbar-actions">
                @if ($fromDashboard)
                    <a class="kfms-link-btn" href="{{ route('finance.dashboard') }}">
                        <i class="mdi mdi-arrow-left"></i>
                        Back to Finance Dashboard
                    </a>
                @endif
                @can('finance.invoices.create')
                    <a class="kfms-btn" href="{{ route('finance.invoices.create', ['from' => $fromDashboard ? 'finance-dashboard' : null]) }}">
                        <i class="mdi mdi-file-document-plus-outline"></i>
                        Add Invoice
                    </a>
                @endcan
                @can('finance.payments.create')
                    <button class="kfms-link-btn" type="button" data-bs-toggle="modal" data-bs-target="#finance-overview-payment-modal">
                        <i class="mdi mdi-cash-check"></i>
                        Record Payment
                    </button>
                @endcan
                @can('requisitions.create')
                    <a class="kfms-link-btn" href="{{ route('requisitions.create', ['from' => $fromDashboard ? 'finance-dashboard' : null]) }}">
                        <i class="mdi mdi-clipboard-plus-outline"></i>
                        New Requisition
                    </a>
                @endcan
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Invoices</h2>
                    <span>{{ $invoices->count() }} latest billing records</span>
                </div>
                @can('finance.invoices.create')
                    <a class="kfms-link-btn" href="{{ route('finance.invoices.create', ['from' => $fromDashboard ? 'finance-dashboard' : null]) }}">
                        <i class="mdi mdi-plus"></i>
                        Add Invoice
                    </a>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->client?->display_name ?: '-' }}</td>
                                <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                                <td>{{ number_format($invoice->total, 2) }}</td>
                                <td>{{ number_format($invoice->paid_amount, 2) }}</td>
                                <td>{{ number_format($invoice->balance, 2) }}</td>
                                <td>{{ $invoice->statusLabel() }}</td>
                                <td>
                                    <div class="kfms-table-actions">
                                        <a href="{{ route('finance.invoices.documents.show', [$invoice, 'invoice']) }}">Invoice</a>
                                        <a href="{{ route('finance.invoices.documents.show', [$invoice, 'fee-note']) }}">Fee Note</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="kfms-empty">No invoices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Recent Payments</h2>
                    <span>{{ $payments->count() }} latest receipts</span>
                </div>
                @can('finance.payments.create')
                    <button class="kfms-link-btn" type="button" data-bs-toggle="modal" data-bs-target="#finance-overview-payment-modal">
                        <i class="mdi mdi-cash-plus"></i>
                        Record Payment
                    </button>
                @endcan
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Reference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td>{{ $payment->paid_on?->format('d M Y') }}</td>
                                <td>{{ $payment->invoice?->invoice_no }}</td>
                                <td>{{ $payment->invoice?->client?->display_name ?: '-' }}</td>
                                <td>{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->reference ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="kfms-empty">No payment records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Recent Requisitions</h2>
                <span>{{ $requisitions->count() }} latest requests</span>
            </div>
            @can('requisitions.index')
                <a class="kfms-link-btn" href="{{ route('requisitions.index', ['from' => $fromDashboard ? 'finance-dashboard' : null]) }}">
                    View all
                    <i class="mdi mdi-arrow-right"></i>
                </a>
            @endcan
        </div>
        @include('modules.partials.table', [
            'headers' => ['Reference', 'Requested By', 'Purpose', 'Amount', 'Status'],
            'rows' => $requisitions->map(fn ($requisition) => [
                $requisition->reference_no,
                $requisition->requester?->name ?: '-',
                $requisition->purpose,
                number_format($requisition->amount, 2),
                $requisition->statusLabel(),
            ]),
        ])
    </section>

    @can('finance.payments.create')
        @push('modals')
            @include('modules.finance.payments.partials.modal', [
                'modalId' => 'finance-overview-payment-modal',
                'openInvoices' => $openInvoices,
                'paymentAccounts' => $paymentAccounts,
                'redirectFrom' => $fromDashboard ? 'finance-dashboard' : null,
            ])
        @endpush
    @endcan
@endsection
