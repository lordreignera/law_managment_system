@extends('layouts.admin')

@section('title', 'Billing / Costs')
@section('page-title', 'Matter Billing / Costs')

@section('content')
    @php
        $invoiceTotal = $matter->invoices->sum('total');
        $paidTotal = $matter->invoices->sum('paid_amount');
        $costTotal = $matter->expenses->sum('amount');
    @endphp

    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }} - Billing / Costs</h2>
                <span>{{ $matter->title }} - {{ $matter->client?->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Workspace
            </a>
        </div>

        <div class="kfms-detail-grid">
            <div>
                <span>Invoices</span>
                <strong>{{ number_format($matter->invoices->count()) }}</strong>
            </div>
            <div>
                <span>Invoiced</span>
                <strong>{{ number_format($invoiceTotal, 2) }}</strong>
            </div>
            <div>
                <span>Paid</span>
                <strong>{{ number_format($paidTotal, 2) }}</strong>
            </div>
            <div>
                <span>Matter Costs</span>
                <strong>{{ number_format($costTotal, 2) }}</strong>
            </div>
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Add Billing</h2>
                    <span>Create a draft invoice for this matter.</span>
                </div>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('matters.billing.invoices.store', $matter) }}">
                @csrf
                <div class="kfms-form-grid">
                    <label>
                        <span>Invoice Date</span>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" required>
                        @error('invoice_date') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Due Date</span>
                        <input type="date" name="due_date" value="{{ old('due_date') }}">
                        @error('due_date') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Subtotal</span>
                        <input type="number" step="0.01" min="0" name="subtotal" value="{{ old('subtotal') }}" required>
                        @error('subtotal') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Tax</span>
                        <input type="number" step="0.01" min="0" name="tax" value="{{ old('tax', 0) }}">
                        @error('tax') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Paid Amount</span>
                        <input type="number" step="0.01" min="0" name="paid_amount" value="{{ old('paid_amount', 0) }}">
                        @error('paid_amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Status</span>
                        <select name="status" required>
                            @foreach ($invoiceStatuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-file-document-plus-outline"></i>
                        Save Billing
                    </button>
                </div>
            </form>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Add Cost</h2>
                    <span>Record a disbursement or cost incurred on this matter.</span>
                </div>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('matters.billing.costs.store', $matter) }}">
                @csrf
                <div class="kfms-form-grid">
                    <label>
                        <span>Category</span>
                        <select name="expense_category_id">
                            <option value="">Select category</option>
                            @foreach ($expenseCategories as $category)
                                <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('expense_category_id') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Spent On</span>
                        <input type="date" name="spent_on" value="{{ old('spent_on', now()->toDateString()) }}" required>
                        @error('spent_on') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Payee</span>
                        <input type="text" name="payee" value="{{ old('payee') }}" maxlength="255">
                        @error('payee') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Payment Source</span>
                        <select name="payment_source" required>
                            @foreach ($paymentSources as $value => $label)
                                <option value="{{ $value }}" @selected(old('payment_source', 'bank') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('payment_source') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Quantity</span>
                        <input type="number" step="0.01" min="0" name="quantity" value="{{ old('quantity') }}">
                        @error('quantity') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Unit Price</span>
                        <input type="number" step="0.01" min="0" name="unit_price" value="{{ old('unit_price') }}">
                        @error('unit_price') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Amount</span>
                        <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
                        @error('amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Description</span>
                        <input type="text" name="description" value="{{ old('description') }}" maxlength="255" required>
                        @error('description') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Notes</span>
                        <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-cash-plus"></i>
                        Save Cost
                    </button>
                </div>
            </form>
        </section>
    </div>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Invoices</h2>
                    <span>{{ $matter->invoices->count() }} records</span>
                </div>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matter->invoices as $invoice)
                            <tr>
                                <td>{{ $invoice->invoice_no }}</td>
                                <td>{{ $invoice->invoice_date?->format('d M Y') }}</td>
                                <td>{{ number_format($invoice->total, 2) }}</td>
                                <td>{{ number_format($invoice->paid_amount, 2) }}</td>
                                <td>{{ str($invoice->status)->headline() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="kfms-empty">No invoices recorded for this matter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Costs</h2>
                    <span>{{ $matter->expenses->count() }} records</span>
                </div>
            </div>
            <div class="kfms-table-wrap">
                <table class="kfms-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($matter->expenses as $expense)
                            <tr>
                                <td>{{ $expense->reference_no }}</td>
                                <td>{{ $expense->spent_on?->format('d M Y') }}</td>
                                <td>{{ $expense->description }}</td>
                                <td>{{ number_format($expense->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="kfms-empty">No costs recorded for this matter.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
