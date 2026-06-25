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
            @include('modules.partials.table', [
                'headers' => ['Invoice', 'Date', 'Total', 'Paid', 'Status'],
                'rows' => $invoices->map(fn ($invoice) => [$invoice->invoice_no, $invoice->invoice_date?->format('d M Y'), number_format($invoice->total), number_format($invoice->paid_amount), $invoice->status]),
            ])
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
