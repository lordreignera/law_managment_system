@extends('layouts.admin')

@section('title', 'Recovery Account')
@section('page-title', 'Recovery Account')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $account->debtor_name }}</h2>
                <span>
                    <span class="kfms-status kfms-status-{{ $account->status }}">{{ $account->statusLabel() }}</span>
                    {{ $account->client?->name }}
                </span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('recoveries.update')
                    <a class="kfms-link-btn" href="{{ route('recoveries.edit', $account) }}"><i class="mdi mdi-pencil"></i> Edit</a>
                @endcan
                <a class="kfms-link-btn" href="{{ route('recoveries.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
                @can('recoveries.destroy')
                    <form method="POST" action="{{ route('recoveries.destroy', $account) }}" onsubmit="return confirm('Remove this recovery account?');">
                        @csrf
                        @method('DELETE')
                        <button class="kfms-link-btn kfms-danger" type="submit"><i class="mdi mdi-delete"></i> Delete</button>
                    </form>
                @endcan
            </div>
        </div>

        <dl class="kfms-detail-list">
            <div><dt>Bank / Client</dt><dd>{{ $account->client?->name ?: '-' }}</dd></div>
            <div><dt>Account Number</dt><dd>{{ $account->account_number ?: '-' }}</dd></div>
            <div><dt>Customer Number</dt><dd>{{ $account->customer_number ?: '-' }}</dd></div>
            <div><dt>Phone</dt><dd>{{ $account->phone ?: '-' }}</dd></div>
            <div><dt>Email</dt><dd>{{ $account->email ?: '-' }}</dd></div>
            <div><dt>Employer</dt><dd>{{ $account->employer ?: '-' }}</dd></div>
            <div><dt>Region</dt><dd>{{ $account->region ?: '-' }}</dd></div>
            <div><dt>Branch</dt><dd>{{ $account->branch?->name ?: 'Firm-wide' }}</dd></div>
            <div><dt>Assigned Officer</dt><dd>{{ $account->assignee?->name ?: 'Unassigned' }}</dd></div>
            <div><dt>Assigned By</dt><dd>{{ $account->assigner?->name ?: '-' }}{{ $account->assigned_at ? ' on '.$account->assigned_at->format('d M Y') : '' }}</dd></div>
            <div><dt>Principal</dt><dd>{{ $account->currency }} {{ number_format($account->principal_amount, 2) }}</dd></div>
            <div><dt>Interest</dt><dd>{{ $account->currency }} {{ number_format($account->interest_amount, 2) }}</dd></div>
            <div><dt>Outstanding</dt><dd>{{ $account->currency }} {{ number_format($account->outstanding_amount, 2) }}</dd></div>
            <div><dt>Recovered</dt><dd>{{ $account->currency }} {{ number_format($account->amount_recovered, 2) }}</dd></div>
        </dl>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Log Follow-up</h2>
                <span>Record a demand or money collected</span>
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
                        <span>Amount Paid (collected)</span>
                        <input type="number" step="0.01" min="0" name="amount_paid" value="{{ old('amount_paid') }}" placeholder="0.00">
                        @error('amount_paid') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Promised Amount</span>
                        <input type="number" step="0.01" min="0" name="promised_amount" value="{{ old('promised_amount') }}" placeholder="0.00">
                        @error('promised_amount') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Promised On</span>
                        <input type="date" name="promised_on" value="{{ old('promised_on') }}">
                        @error('promised_on') <small>{{ $message }}</small> @enderror
                    </label>
                    <label class="kfms-span-2">
                        <span>Notes</span>
                        <textarea name="notes" rows="3" maxlength="2000" required>{{ old('notes') }}</textarea>
                        @error('notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-content-save"></i> Save Follow-up</button>
                </div>
            </form>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Activity History</h2>
                <span>{{ $account->activities->count() }} entries</span>
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
                                <td>{{ $activity->amount_paid ? number_format($activity->amount_paid, 2) : '-' }}</td>
                                <td>{{ $activity->promised_amount ? number_format($activity->promised_amount, 2).($activity->promised_on ? ' by '.$activity->promised_on->format('d M') : '') : '-' }}</td>
                                <td>{{ $activity->user?->name ?: '-' }}</td>
                                <td>{{ $activity->notes }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="kfms-empty">No follow-ups logged yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
