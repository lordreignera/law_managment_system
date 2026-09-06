@php
    $modalId = $modalId ?? 'finance-payment-modal';
    $inputPrefix = str_replace('-', '_', $modalId);
    $redirectFrom = $redirectFrom ?? null;
    $selectedInvoice = $selectedInvoice ?? null;
@endphp

<div class="modal fade kfms-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form class="kfms-form" method="POST" action="{{ route('finance.payments.store') }}">
                @csrf
                @if ($redirectFrom)
                    <input type="hidden" name="from" value="{{ $redirectFrom }}">
                @endif

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title">Record Payment</h5>
                        <span>Post a receipt against an invoice and receiving chart account.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    @if ($errors->has('payments'))
                        <div class="kfms-alert kfms-alert-danger">{{ $errors->first('payments') }}</div>
                    @endif

                    <div class="kfms-form-grid">
                        <label>
                            <span>Invoice</span>
                            <select name="invoice_id" id="{{ $inputPrefix }}_invoice" data-finance-payment-invoice required>
                                <option value="">Select invoice</option>
                                @foreach ($openInvoices as $invoice)
                                    <option
                                        value="{{ $invoice->id }}"
                                        data-balance="{{ $invoice->balance }}"
                                        @selected(old('invoice_id', $selectedInvoice?->id) == $invoice->id)
                                    >
                                        {{ $invoice->invoice_no }} - {{ $invoice->client?->display_name ?: 'Client' }} - balance {{ number_format($invoice->balance, 2) }}
                                    </option>
                                @endforeach
                            </select>
                            @error('invoice_id') <small>{{ $message }}</small> @enderror
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
                            <span>Payment Date</span>
                            <input type="date" name="paid_on" value="{{ old('paid_on', now()->toDateString()) }}" required>
                            @error('paid_on') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Amount Paid</span>
                            <input type="text" inputmode="decimal" name="amount" id="{{ $inputPrefix }}_amount" data-finance-payment-amount value="{{ old('amount') }}" required>
                            @error('amount') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Payment Method</span>
                            <input type="text" name="payment_method" value="{{ old('payment_method') }}" maxlength="100" placeholder="Bank, cash, mobile money">
                            @error('payment_method') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Receipt / Reference</span>
                            <input type="text" name="reference" value="{{ old('reference') }}" maxlength="191">
                            @error('reference') <small>{{ $message }}</small> @enderror
                        </label>

                        <div class="kfms-form-summary">
                            <span>Outstanding before payment</span>
                            <strong data-finance-payment-current>Select invoice</strong>
                            <span>Remaining after payment</span>
                            <strong data-finance-payment-remaining>-</strong>
                        </div>

                        <label>
                            <span>Notes</span>
                            <textarea name="notes" rows="3" placeholder="Optional payment notes">{{ old('notes') }}</textarea>
                            @error('notes') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>

                    @if ($paymentAccounts->isEmpty())
                        <div class="kfms-alert kfms-alert-danger">Add an active postable bank, cash, or client funds chart account before recording payments.</div>
                    @endif

                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="kfms-btn" type="submit" @disabled($paymentAccounts->isEmpty())>
                            <i class="mdi mdi-content-save"></i>
                            Save Payment
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const money = new Intl.NumberFormat('en-UG', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                const numeric = (value) => Number(String(value || '').replace(/,/g, '')) || 0;

                document.querySelectorAll('[data-finance-payment-invoice]').forEach((invoice) => {
                    const form = invoice.closest('form');
                    const amount = form?.querySelector('[data-finance-payment-amount]');
                    const current = form?.querySelector('[data-finance-payment-current]');
                    const remaining = form?.querySelector('[data-finance-payment-remaining]');

                    if (!amount || !current || !remaining) {
                        return;
                    }

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

                @if ($errors->hasAny(['payments', 'invoice_id', 'chart_account_id', 'amount', 'paid_on', 'payment_method', 'reference', 'notes']))
                    const errorModal = document.getElementById('{{ $modalId }}');

                    if (errorModal && window.bootstrap?.Modal) {
                        window.bootstrap.Modal.getOrCreateInstance(errorModal).show();
                    }
                @endif
            });
        </script>
    @endpush
@endonce
