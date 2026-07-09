@extends('layouts.admin')

@section('title', 'Recovery Account')
@section('page-title', 'Recovery Account')

@php
    $currency = $account->currency ?: 'UGX';
    $principal = (float) $account->principal_amount;
    $interest = (float) $account->interest_amount;
    $outstanding = (float) $account->outstanding_amount;
    $recovered = (float) $account->amount_recovered;
    $totalDue = max($outstanding + $recovered, 1);
    $recoveryRate = min(100, round(($recovered / $totalDue) * 100));
@endphp

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel kfms-recovery-hero">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $account->debtor_name }}</h2>
                <span>
                    {{ $account->client?->name ?: 'No bank/client selected' }}
                    @if ($account->account_number)
                        <span class="kfms-dot-separator"></span>
                        Account {{ $account->account_number }}
                    @endif
                </span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('recoveries.update')
                    <a class="kfms-link-btn" href="{{ route('recoveries.edit', $account) }}">
                        <i class="mdi mdi-account-switch-outline"></i>
                        Edit / Assign
                    </a>
                @endcan
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </a>
                @can('recoveries.destroy')
                    <form method="POST" action="{{ route('recoveries.destroy', $account) }}" onsubmit="return confirm('Remove this recovery account?');">
                        @csrf
                        @method('DELETE')
                        <button class="kfms-link-btn kfms-danger" type="submit">
                            <i class="mdi mdi-delete-outline"></i>
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        <div class="kfms-recovery-summary">
            <div class="kfms-recovery-status-card">
                <span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span>
                <h3>{{ $account->assignee?->name ?: 'Unassigned' }}</h3>
                <p>Recovery officer</p>
                <div class="kfms-progress-track" aria-label="Recovery progress">
                    <span style="width: {{ $recoveryRate }}%"></span>
                </div>
                <small>{{ $recoveryRate }}% recovered from tracked collections</small>
            </div>

            <div class="kfms-recovery-money-grid">
                <div>
                    <span>Outstanding</span>
                    <strong>{{ $currency }} {{ number_format($outstanding, 2) }}</strong>
                </div>
                <div>
                    <span>Recovered</span>
                    <strong>{{ $currency }} {{ number_format($recovered, 2) }}</strong>
                </div>
                <div>
                    <span>Principal</span>
                    <strong>{{ $currency }} {{ number_format($principal, 2) }}</strong>
                </div>
                <div>
                    <span>Interest</span>
                    <strong>{{ $currency }} {{ number_format($interest, 2) }}</strong>
                </div>
            </div>
        </div>
    </section>

    <div class="kfms-recovery-layout">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Account Details</h2>
                    <span>Client, debtor, assignment, and portfolio information</span>
                </div>
            </div>

            <div class="kfms-detail-section">
                <h3>Debtor Information</h3>
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Bank / Client</dt><dd>{{ $account->client?->name ?: '-' }}</dd></div>
                    <div><dt>Customer Number</dt><dd>{{ $account->customer_number ?: '-' }}</dd></div>
                    <div><dt>Phone</dt><dd>{{ $account->phone ?: '-' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $account->email ?: '-' }}</dd></div>
                    <div><dt>Employer</dt><dd>{{ $account->employer ?: '-' }}</dd></div>
                    <div><dt>Branch Name</dt><dd>{{ $account->branch_name ?: '-' }}</dd></div>
                    <div><dt>Region</dt><dd>{{ $account->region ?: '-' }}</dd></div>
                    <div><dt>Operative Account</dt><dd>{{ $account->operative_account ?: '-' }}</dd></div>
                    <div><dt>Days Past Due</dt><dd>{{ $account->days_past_due ?: '-' }}</dd></div>
                </dl>
            </div>

            <div class="kfms-detail-section">
                <h3>Assignment</h3>
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Branch</dt><dd>{{ $account->branch?->name ?: 'Firm-wide' }}</dd></div>
                    <div><dt>Assigned Officer</dt><dd>{{ $account->assignee?->name ?: 'Unassigned' }}</dd></div>
                    <div>
                        <dt>Assigned By</dt>
                        <dd>
                            {{ $account->assigner?->name ?: '-' }}
                            @if ($account->assigned_at)
                                <span class="kfms-muted-text">on {{ $account->assigned_at->format('d M Y') }}</span>
                            @endif
                        </dd>
                    </div>
                    <div><dt>Portfolio Type</dt><dd>{{ $account->portfolio_type ?: '-' }}</dd></div>
                    <div><dt>Bucket</dt><dd>{{ $account->bucket ?: '-' }}</dd></div>
                    <div><dt>Imported From</dt><dd>{{ $account->importBatch?->source_file ?: 'Manual entry' }}</dd></div>
                    <div><dt>Original Collector</dt><dd>{{ $account->collector_name ?: '-' }}</dd></div>
                </dl>
            </div>

            <div class="kfms-detail-section">
                <h3>Portfolio Notes</h3>
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Arrears</dt><dd>{{ $currency }} {{ number_format((float) $account->arrears_amount, 2) }}</dd></div>
                    <div><dt>Collateral Held</dt><dd>{{ $account->collateral_held ?: '-' }}</dd></div>
                    <div><dt>Cause of Default</dt><dd>{{ $account->cause_of_default ?: '-' }}</dd></div>
                </dl>
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Log Follow-up</h2>
                    <span>Record a demand, call, visit, promise, or payment</span>
                </div>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('recoveries.activities.store', $account) }}">
                @csrf
                <div class="kfms-form-grid">
                    <label>
                        <span>Activity Type</span>
                        <select name="activity_type" required>
                            @foreach ($activityTypes as $value => $label)
                                <option value="{{ $value }}" @selected(old('activity_type', 'call') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('activity_type') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Date &amp; Time</span>
                        <input type="datetime-local" name="activity_at" value="{{ old('activity_at', now()->format('Y-m-d\TH:i')) }}" required>
                        @error('activity_at') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Amount Paid</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" value="{{ old('amount_paid') }}" placeholder="0.00">
                        @error('amount_paid') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Promised Amount</span>
                        <input type="number" step="0.01" min="0" name="promised_amount" value="{{ old('promised_amount') }}" placeholder="0.00">
                        @error('promised_amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Promised On</span>
                        <input type="date" name="promised_on" value="{{ old('promised_on') }}">
                        @error('promised_on') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Notes</span>
                        <textarea name="notes" rows="4" maxlength="2000" required>{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-content-save"></i>
                        Save Follow-up
                    </button>
                </div>
            </form>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Activity History</h2>
                <span>{{ $account->activities->count() }} entries</span>
            </div>
        </div>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Type</th>
                        <th>Paid</th>
                        <th>Promised</th>
                        <th>By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($account->activities as $activity)
                        <tr>
                            <td>{{ $activity->activity_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $activity->typeLabel() }}</td>
                            <td>{{ $activity->amount_paid ? $currency.' '.number_format($activity->amount_paid, 2) : '-' }}</td>
                            <td>{{ $activity->promised_amount ? $currency.' '.number_format($activity->promised_amount, 2).($activity->promised_on ? ' by '.$activity->promised_on->format('d M') : '') : '-' }}</td>
                            <td>{{ $activity->user?->name ?: '-' }}</td>
                            <td>{{ $activity->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="kfms-empty">No follow-ups logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
