@extends('layouts.admin')

@section('title', 'Recovery Account')
@section('page-title', 'Recovery Account')

@php
    $currency = $account->currency ?: 'UGX';
    $principal = (float) $account->principal_amount;
    $interest = (float) $account->interest_amount;
    $originalOutstanding = (float) $account->outstanding_amount;
    $recovered = (float) $account->amount_recovered;
    $netOutstanding = (float) $account->net_outstanding_balance;
    $totalDue = max($originalOutstanding, 1);
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
                        <i class="mdi mdi-pencil-outline"></i>
                        Edit Account
                    </a>
                @endcan
                @can('recoveries.assignment.edit')
                    <a class="kfms-link-btn" href="{{ route('recoveries.assignment.edit', $account) }}">
                        <i class="mdi mdi-account-switch-outline"></i>
                        {{ $account->assigned_to ? 'Change Officer' : 'Assign Officer' }}
                    </a>
                @endcan
                @can('recoveries.activities.store')
                    @if ($account->status === 'active' && ! $account->settled_externally_at)
                    <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#recovery-activity-modal-{{ $account->id }}">
                        <i class="mdi mdi-clipboard-text-clock-outline"></i>
                        Report Activity
                    </button>
                    @endif
                @endcan
                @can('letters.create')
                    <a class="kfms-link-btn" href="{{ route('letters.create', ['recovery_account_id' => $account->id, 'letter_type' => 'demand_notice']) }}">
                        <i class="mdi mdi-file-sign"></i>
                        Demand Notice
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

            <div class="kfms-stat-grid kfms-recovery-money-grid">
                <div class="kfms-card">
                    <span class="kfms-card-label">Net Outstanding</span>
                    <strong class="kfms-stat">{{ $currency }} {{ number_format($netOutstanding, 2) }}</strong>
                </div>
                <div class="kfms-card">
                    <span class="kfms-card-label">Recovered</span>
                    <strong class="kfms-stat">{{ $currency }} {{ number_format($recovered, 2) }}</strong>
                </div>
                <div class="kfms-card">
                    <span class="kfms-card-label">Original Outstanding</span>
                    <strong class="kfms-stat">{{ $currency }} {{ number_format($originalOutstanding, 2) }}</strong>
                </div>
                <div class="kfms-card">
                    <span class="kfms-card-label">Interest</span>
                    <strong class="kfms-stat">{{ $currency }} {{ number_format($interest, 2) }}</strong>
                </div>
            </div>
        </div>
    </section>

    <div class="kfms-recovery-layout kfms-recovery-layout-single">
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
                    <div><dt>Debtor Phone</dt><dd>{{ $account->phone ?: '-' }}</dd></div>
                    <div><dt>Alternate Phone</dt><dd>{{ $account->alternate_phone ?: '-' }}</dd></div>
                    <div><dt>WhatsApp</dt><dd>{{ $account->whatsapp_number ?: '-' }}</dd></div>
                    <div><dt>Email</dt><dd>{{ $account->email ?: '-' }}</dd></div>
                    <div><dt>NIN</dt><dd>{{ $account->nin ?: '-' }}</dd></div>
                    <div><dt>NSSF Number</dt><dd>{{ $account->nssf_number ?: '-' }}</dd></div>
                    <div><dt>CRB Reference</dt><dd>{{ $account->crb_reference ?: '-' }}</dd></div>
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
                    <div><dt>Pipeline Stage</dt><dd>{{ $account->pipelineStageLabel() }}</dd></div>
                    <div><dt>Priority</dt><dd>{{ $account->priorityLabel() }}</dd></div>
                    <div><dt>Last Contact</dt><dd>{{ $account->last_contact_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
                    <div><dt>Next Follow-up</dt><dd>{{ $account->next_follow_up_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
                    <div><dt>SMS reminders</dt><dd>{{ $account->sms_opt_in ? 'Allowed' : 'Disabled' }}</dd></div>
                    <div><dt>Imported From</dt><dd>{{ $account->importBatch?->source_file ?: 'Manual entry' }}</dd></div>
                    <div><dt>Original Collector</dt><dd>{{ $account->collector_name ?: '-' }}</dd></div>
                    @if ($account->settled_externally_at)
                        <div><dt>Settlement</dt><dd>Client reported full repayment before follow-up.</dd></div>
                    @endif
                </dl>
            </div>

            <div class="kfms-detail-section">
                <h3>Portfolio Notes</h3>
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Arrears</dt><dd>{{ $currency }} {{ number_format((float) $account->arrears_amount, 2) }}</dd></div>
                    <div><dt>Collateral Held</dt><dd>{{ $account->collateral_held ?: '-' }}</dd></div>
                    <div><dt>Cause of Default</dt><dd>{{ $account->cause_of_default ?: '-' }}</dd></div>
                    <div><dt>Skip-trace Status</dt><dd>{{ $account->skipTraceStatusLabel() }}</dd></div>
                    <div><dt>Skip-trace Checked</dt><dd>{{ $account->skip_trace_checked_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
                    <div><dt>Skip-trace Notes</dt><dd>{{ $account->skip_trace_notes ?: '-' }}</dd></div>
                </dl>
            </div>
        </section>

    </div>

    <div class="kfms-recovery-layout kfms-recovery-layout-single">
        @can('recoveries.skip-trace.store')
            <section class="kfms-panel">
                <div class="kfms-panel-header">
                    <div><h2>Skip Trace</h2><span>Record NIN, NSSF, CRB, or WhatsApp checks and their outcome.</span></div>
                </div>
                <form class="kfms-form" method="POST" action="{{ route('recoveries.skip-trace.store', $account) }}">
                    @csrf
                    <div class="kfms-form-grid">
                        <label><span>Source</span><select name="provider" required>@foreach (['manual' => 'Manual Check', 'nin' => 'NIN', 'nssf' => 'NSSF', 'crb' => 'CRB', 'whatsapp' => 'WhatsApp'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label><span>Outcome</span><select name="status" required>@foreach (['pending' => 'Pending', 'in_progress' => 'In Progress', 'found' => 'Found', 'not_found' => 'Not Found', 'failed' => 'Failed'] as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                        <label><span>NIN</span><input type="text" name="nin" value="{{ old('nin') }}"></label>
                        <label><span>NSSF Number</span><input type="text" name="nssf_number" value="{{ old('nssf_number') }}"></label>
                        <label><span>CRB Reference</span><input type="text" name="crb_reference" value="{{ old('crb_reference') }}"></label>
                        <label><span>WhatsApp Number</span><input type="text" name="whatsapp_number" value="{{ old('whatsapp_number') }}"></label>
                        <label class="kfms-span-2"><span>Result Notes</span><textarea name="notes" rows="3" maxlength="2000">{{ old('notes') }}</textarea></label>
                    </div>
                    <div class="kfms-form-actions"><button class="kfms-btn" type="submit"><i class="mdi mdi-map-search-outline"></i> Record Check</button></div>
                </form>
                <div class="kfms-table-wrap">
                    <table class="kfms-table"><thead><tr><th>Checked</th><th>Source</th><th>Outcome</th><th>By</th><th>Notes</th></tr></thead><tbody>
                        @forelse ($account->skipTraceChecks as $check)
                            <tr><td>{{ $check->checked_at?->format('d M Y, H:i') ?: '-' }}</td><td>{{ str($check->provider)->upper() }}</td><td>{{ str($check->status)->headline() }}</td><td>{{ $check->checker?->name ?: '-' }}</td><td>{{ $check->notes ?: '-' }}</td></tr>
                        @empty
                            <tr><td colspan="5" class="kfms-empty">No skip-trace checks recorded.</td></tr>
                        @endforelse
                    </tbody></table>
                </div>
            </section>
        @endcan
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
                        <th>Response</th>
                        <th>Paid</th>
                        <th>Balance After</th>
                        <th>Receipt</th>
                        <th>Promised</th>
                        <th>By</th>
                        <th>Promise SMS</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($account->activities as $activity)
                        <tr>
                            <td>{{ $activity->activity_at?->format('d M Y, H:i') }}</td>
                            <td>{{ $activity->typeLabel() }}</td>
                            <td>{{ $activity->outcomeLabel() }}</td>
                            <td>{{ $activity->amount_paid ? $currency.' '.number_format($activity->amount_paid, 2) : '-' }}</td>
                            <td>{{ $activity->outstanding_balance_after !== null ? $currency.' '.number_format($activity->outstanding_balance_after, 2) : '-' }}</td>
                            <td>
                                @forelse ($activity->attachments as $receipt)
                                    <a href="{{ route('attachments.download', $receipt) }}">{{ $receipt->original_name }}</a>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td>{{ $activity->promised_amount ? $currency.' '.number_format($activity->promised_amount, 2).($activity->promised_on ? ' by '.$activity->promised_on->format('d M') : '') : '-' }}</td>
                            <td>{{ $activity->user?->name ?: '-' }}</td>
                            <td>{{ $activity->promiseReminderLogs->first()?->status ? str($activity->promiseReminderLogs->first()->status)->headline() : '-' }}</td>
                            <td>{{ $activity->notes }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="kfms-empty">No follow-ups logged yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('recoveries.activities.store')
        @if ($account->status === 'active' && ! $account->settled_externally_at)
        @push('modals')
            @include('modules.recoveries.partials.activity-modal', ['account' => $account, 'activityTypes' => $activityTypes])
        @endpush
        @endif
    @endcan
@endsection
