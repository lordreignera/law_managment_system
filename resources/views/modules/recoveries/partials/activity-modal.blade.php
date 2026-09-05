@php
    $modalId = $modalId ?? 'recovery-activity-modal-'.$account->id;
    $activityTypes = $activityTypes ?? \App\Models\RecoveryActivity::TYPES;
    $responseOutcomes = $responseOutcomes ?? \App\Models\RecoveryActivity::OUTCOMES;
    $defaultActivityType = $defaultActivityType ?? 'call';
    $defaultResponseOutcome = $defaultResponseOutcome ?? 'no_response';
    $redirectTo = $redirectTo ?? null;
    $currency = $account->currency ?: 'UGX';
    $netOutstanding = $account->net_outstanding_balance;
@endphp

<div class="modal fade kfms-modal" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog kfms-recovery-log-modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Log Recovery Activity</h5>
                    <span>{{ $account->debtor_name }} - {{ $currency }} {{ number_format($netOutstanding, 2) }} net outstanding</span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form class="kfms-form kfms-recovery-activity-form" method="POST" action="{{ route('recoveries.activities.store', $account) }}" enctype="multipart/form-data" data-recovery-activity-form data-current-balance="{{ $netOutstanding }}" data-currency="{{ $currency }}">
                @csrf
                @if ($redirectTo)
                    <input type="hidden" name="redirect_to" value="{{ $redirectTo }}">
                @endif
                <div class="modal-body">
                    <div class="kfms-recovery-log-balance">
                        <span>Current Net Outstanding</span>
                        <strong>{{ $currency }} {{ number_format($netOutstanding, 2) }}</strong>
                    </div>

                    <div class="kfms-form-grid">
                        <label>
                            <span>What happened?</span>
                            <select name="activity_type" required data-activity-type>
                                @foreach ($activityTypes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('activity_type', $defaultActivityType) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('activity_type') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Client Response</span>
                            <select name="response_outcome" required data-response-outcome>
                                @foreach ($responseOutcomes as $value => $label)
                                    <option value="{{ $value }}" @selected(old('response_outcome', $defaultResponseOutcome) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('response_outcome') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Date &amp; Time</span>
                            <input type="datetime-local" name="activity_at" value="{{ old('activity_at', now()->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}" required>
                            @error('activity_at') <small>{{ $message }}</small> @enderror
                        </label>

                        <label data-response-panel="paid">
                            <span>Amount Paid</span>
                            <input type="text" inputmode="decimal" name="amount_paid" value="{{ old('amount_paid') }}" placeholder="0.00" data-amount-paid>
                            @error('amount_paid') <small>{{ $message }}</small> @enderror
                        </label>

                        <div class="kfms-recovery-remaining-preview kfms-span-2" data-response-panel="paid" data-remaining-preview>
                            <span>Remaining After This Payment</span>
                            <strong data-remaining-amount>{{ $currency }} {{ number_format($netOutstanding, 2) }}</strong>
                            <small data-remaining-note></small>
                        </div>

                        <label data-response-panel="paid">
                            <span>Receipt *</span>
                            <input type="file" name="receipt" accept=".pdf,.jpg,.jpeg,.png,.webp">
                            @error('receipt') <small>{{ $message }}</small> @enderror
                        </label>

                        <label data-response-panel="promised">
                            <span>Promised Amount</span>
                            <input type="text" inputmode="decimal" name="promised_amount" value="{{ old('promised_amount') }}" placeholder="0.00">
                            @error('promised_amount') <small>{{ $message }}</small> @enderror
                        </label>

                        <label data-response-panel="promised">
                            <span>Promised On</span>
                            <input type="date" name="promised_on" value="{{ old('promised_on') }}">
                            @error('promised_on') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="kfms-span-2">
                            <span>Response Notes</span>
                            <textarea name="notes" rows="4" maxlength="2000" required>{{ old('notes') }}</textarea>
                            @error('notes') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <div class="kfms-form-actions">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="kfms-btn" type="submit">
                            <i class="mdi mdi-content-save"></i>
                            Save Activity
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
            document.querySelectorAll('[data-recovery-activity-form]').forEach(function (form) {
                const responseField = form.querySelector('[data-response-outcome]');
                const panels = form.querySelectorAll('[data-response-panel]');
                const amountField = form.querySelector('[data-amount-paid]');
                const remainingAmount = form.querySelector('[data-remaining-amount]');
                const remainingNote = form.querySelector('[data-remaining-note]');
                const currentBalance = Number.parseFloat(form.dataset.currentBalance || '0');
                const currency = form.dataset.currency || 'UGX';
                const formatter = new Intl.NumberFormat('en-UG', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                });

                const updateRemainingBalance = function () {
                    if (! amountField || ! remainingAmount) {
                        return;
                    }

                    const normalized = (amountField.value || '0').replace(/,/g, '').replace(/\s/g, '');
                    const paid = Number.parseFloat(normalized);
                    const remaining = currentBalance - (Number.isFinite(paid) ? paid : 0);
                    const isOverpaid = remaining < 0;

                    remainingAmount.textContent = currency + ' ' + formatter.format(Math.max(remaining, 0));
                    form.querySelector('[data-remaining-preview]')?.classList.toggle('is-overpaid', isOverpaid);

                    if (remainingNote) {
                        remainingNote.textContent = isOverpaid ? 'Amount paid is above the current net outstanding balance.' : '';
                    }
                };

                const syncActivityFields = function () {
                    const selectedResponse = responseField?.value;

                    panels.forEach(function (panel) {
                        const isVisible = panel.dataset.responsePanel === selectedResponse;
                        panel.hidden = ! isVisible;

                        panel.querySelectorAll('input, select, textarea').forEach(function (field) {
                            field.disabled = ! isVisible;
                            field.required = isVisible && (
                                field.name === 'amount_paid' ||
                                field.name === 'receipt' ||
                                field.name === 'promised_amount' ||
                                field.name === 'promised_on'
                            );
                        });
                    });

                    updateRemainingBalance();
                };

                responseField?.addEventListener('change', syncActivityFields);
                amountField?.addEventListener('input', updateRemainingBalance);
                syncActivityFields();
            });
        </script>
    @endpush
@endonce
