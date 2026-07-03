@extends('layouts.admin')

@section('title', 'Expense')
@section('page-title', 'Expense')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $expense->reference_no }}</h2>
                <span>{{ number_format($expense->amount, 2) }} · {{ $expense->paymentSourceLabel() }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('expenses.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Expenses
            </a>
        </div>

        <dl class="kfms-detail-list">
            <div><dt>Category</dt><dd>{{ $expense->category?->name ?: '-' }}</dd></div>
            <div><dt>Description</dt><dd>{{ $expense->description }}</dd></div>
            <div><dt>Payee</dt><dd>{{ $expense->payee ?: '-' }}</dd></div>
            <div><dt>Related Matter</dt><dd>{{ $expense->matter ? $expense->matter->reference_no.' — '.$expense->matter->title : '-' }}</dd></div>
            <div><dt>From Requisition</dt><dd>{{ $expense->requisition?->reference_no ?: '-' }}</dd></div>
            <div><dt>Quantity</dt><dd>{{ $expense->quantity !== null ? rtrim(rtrim(number_format($expense->quantity, 2), '0'), '.') : '-' }}</dd></div>
            <div><dt>Unit Price</dt><dd>{{ $expense->unit_price !== null ? number_format($expense->unit_price, 2) : '-' }}</dd></div>
            <div><dt>Amount</dt><dd>{{ number_format($expense->amount, 2) }}</dd></div>
            <div><dt>Payment Source</dt><dd>{{ $expense->paymentSourceLabel() }}</dd></div>
            <div><dt>Date Spent</dt><dd>{{ $expense->spent_on?->format('d M Y') }}</dd></div>
            <div><dt>Recorded By</dt><dd>{{ $expense->recorder?->name ?: '-' }}</dd></div>
            <div><dt>Notes</dt><dd>{{ $expense->notes ?: '-' }}</dd></div>
        </dl>

        @if ($expense->attachments->isNotEmpty())
            <div class="kfms-panel-subheader"><h3>Attachments</h3></div>
            <ul class="kfms-file-list">
                @foreach ($expense->attachments as $attachment)
                    <li><i class="mdi mdi-paperclip"></i> <a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a></li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
