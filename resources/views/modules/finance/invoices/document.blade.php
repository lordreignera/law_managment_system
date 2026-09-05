@extends('layouts.admin')

@section('title', $documentTitle.' '.$invoice->invoice_no)
@section('page-title', $documentTitle)

@section('content')
    <section class="kfms-panel kfms-print-toolbar">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $documentTitle }} {{ $invoice->invoice_no }}</h2>
                <span>{{ $invoice->client?->display_name ?: 'Client' }} - {{ $invoice->invoice_date?->format('d M Y') }}</span>
            </div>
            <div class="kfms-button-row">
                <a class="kfms-link-btn" href="{{ route('finance.invoices.documents.pdf', [$invoice, $documentType]) }}">
                    <i class="mdi mdi-file-pdf-box"></i>
                    Download PDF
                </a>
                <button class="kfms-btn" type="button" onclick="window.print()">
                    <i class="mdi mdi-printer-outline"></i>
                    Print
                </button>
                <a class="kfms-link-btn" href="{{ $invoice->matter ? route('matters.billing.show', $invoice->matter) : route('finance.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </a>
            </div>
        </div>
    </section>

    @include('modules.finance.invoices.partials.document-content')
@endsection
