@extends('layouts.admin')

@section('title', 'Record Payment')
@section('page-title', 'Record Payment')

@section('content')
    @php($fromDashboard = request('from') === 'finance-dashboard')

    <section class="kfms-panel kfms-finance-form-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Record Payment</h2>
                <span>Capture an invoice payment and update the outstanding balance.</span>
            </div>
            <a class="kfms-link-btn" href="{{ $fromDashboard ? route('finance.dashboard') : route('finance.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                {{ $fromDashboard ? 'Back to Finance Dashboard' : 'Back to Finance Overview' }}
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('finance.payments.store') }}">
            @csrf
            @if ($fromDashboard)
                <input type="hidden" name="from" value="finance-dashboard">
            @endif

            <div class="kfms-form-grid">
                <label class="kfms-span-2">
                    <span>Invoice</span>
                    <select name="invoice_id" id="finance-payment-invoice" required>
                        <option value="">Select invoice</option>
                        @foreach ($invoices as $invoice)
                            <option
                                value="{{ $invoice->id }}"
                                data-balance="{{ $invoice->balance }}"
                                data-total="{{ $invoice->total }}"
                                data-paid="{{ $invoice->paid_amount }}"
                                @selected(old('invoice_id', $selectedInvoice?->id) == $invoice->id)
                            >
                                {{ $invoice->invoice_no }} - {{ $invoice->client?->display_name ?: 'Client' }} - balance {{ number_format($invoice->balance, 2) }}
                            </option>
                        @endforeach
                    </select>
                    @error('invoice_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Payment Date</span>
                    <input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required>
                    @error('paid_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Payment Method</span>
                    <input type="text" name="payment_method" value="{{ old('payment_method') }}" maxlength="100" placeholder="Bank, cash, mobile money">
                    @error('payment_method') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Received Into</span>
                    <select name="chart_account_id" required>
                        <option value="">Select chart account</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" @selected(old('chart_account_id') == $account->id)>{{ $account->fullName() }}</option>
                        @endforeach
                    </select>
                    @error('chart_account_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Amount Paid</span>
                    <input type="text" inputmode="decimal" name="amount" id="finance-payment-amount" value="{{ old('amount') }}" required>
                    @error('amount') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Receipt / Reference</span>
                    <input type="text" name="reference" value="{{ old('reference') }}" maxlength="191">
                    @error('reference') <small>{{ $message }}</small> @enderror
                </label>

                <div class="kfms-form-summary kfms-span-2">
                    <span>Outstanding before payment</span>
                    <strong id="finance-payment-current">Select invoice</strong>
                    <span>Remaining after payment</span>
                    <strong id="finance-payment-remaining">-</strong>
                </div>

                <label class="kfms-span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3" placeholder="Optional payment notes">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-content-save"></i>
                    Save Payment
                </button>
            </div>
        </form>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const invoice = document.getElementById('finance-payment-invoice');
            const amount = document.getElementById('finance-payment-amount');
            const current = document.getElementById('finance-payment-current');
            const remaining = document.getElementById('finance-payment-remaining');
            const money = new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

            if (!invoice || !amount || !current || !remaining) {
                return;
            }

            const numeric = (value) => Number(String(value || '').replace(/,/g, '')) || 0;

            const refresh = () => {
                const option = invoice.selectedOptions[0];
                const balance = numeric(option?.dataset.balance);
                const paid = numeric(amount.value);

                current.textContent = option?.value ? `UGX ${money.format(balance)}` : 'Select invoice';
                remaining.textContent = option?.value ? `UGX ${money.format(Math.max(balance - paid, 0))}` : '-';
            };

            invoice.addEventListener('change', refresh);
            amount.addEventListener('input', refresh);
            refresh();
        });
    </script>
@endsection
