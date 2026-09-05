@extends('layouts.admin')

@section('title', 'Finance')
@section('page-title', 'Finance')

@section('content')
    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Invoices</h2>
                <span>{{ $invoices->count() }} latest</span>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Paid</th>
                            <th>Status</th>
                            <th>Documents</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                                <td>{{ number_format($invoice->total, 2) }}</td>
                                <td>{{ number_format($invoice->paid_amount, 2) }}</td>
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
                                <td colspan="6" class="kfms-empty">No invoices yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Requisitions</h2>
                <span>{{ $requisitions->count() }} latest</span>
            </div>
            @include('modules.partials.table', [
                'headers' => ['Reference', 'Purpose', 'Amount', 'Status'],
                'rows' => $requisitions->map(fn ($requisition) => [$requisition->reference_no, $requisition->purpose, number_format($requisition->amount), $requisition->status]),
            ])
        </section>
    </div>
@endsection
