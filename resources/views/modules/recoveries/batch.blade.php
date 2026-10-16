@extends('layouts.admin')

@section('title', 'Recovery Import Batch')
@section('page-title', 'Recovery Import Batch')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif
    @if (session('import_errors'))
        <div class="kfms-alert"><strong>Rows requiring correction</strong><ul>
            @foreach (session('import_errors') as $message) <li>{{ $message }}</li> @endforeach
        </ul></div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $batch->client?->name }} - {{ $batch->portfolio_type }}</h2>
                <span>{{ $batch->source_file }} imported by {{ $batch->uploader?->name ?: '-' }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('recoveries.import') }}"><i class="mdi mdi-file-upload-outline"></i> Import Another</a>
                <a class="kfms-link-btn" href="{{ route('recoveries.index', ['client' => $batch->recovery_client_id, 'portfolio_type' => $batch->portfolio_type]) }}"><i class="mdi mdi-format-list-bulleted"></i> View Register</a>
            </div>
        </div>

        <div class="kfms-stat-grid kfms-dashboard-kpis">
            <section class="kfms-card"><span class="kfms-card-label">Imported Rows</span><strong class="kfms-stat">{{ number_format($batch->imported_rows) }}</strong></section>
            <section class="kfms-card"><span class="kfms-card-label">Skipped Rows</span><strong class="kfms-stat">{{ number_format($batch->skipped_rows) }}</strong></section>
            <section class="kfms-card"><span class="kfms-card-label">Outstanding</span><strong class="kfms-stat">{{ number_format($batch->total_outstanding) }}</strong></section>
            <section class="kfms-card"><span class="kfms-card-label">Assigned</span><strong class="kfms-stat">{{ number_format($batch->assigned_accounts_count) }}</strong></section>
        </div>

        <section class="kfms-recovery-batch-assignment">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $hasAssignments ? 'Batch Assignment' : 'Assign Batch' }}</h2>
                    <span>
                        @if ($isFullyAssigned)
                            All {{ number_format($batch->accounts_count) }} accounts assigned
                        @else
                            {{ number_format($batch->assigned_accounts_count) }} of {{ number_format($batch->accounts_count) }} accounts currently assigned
                        @endif
                    </span>
                </div>
                @if ($hasAssignments)
                    <button class="kfms-link-btn" type="button" data-bs-toggle="collapse" data-bs-target="#batch-assignment-form" aria-expanded="false" aria-controls="batch-assignment-form">
                        <i class="mdi mdi-account-switch-outline"></i>
                        Change Assignment
                    </button>
                @endif
            </div>
            <div class="collapse {{ $hasAssignments ? '' : 'show' }}" id="batch-assignment-form">
                <form class="kfms-form kfms-inline-assign-form" method="POST" action="{{ route('recoveries.batches.assign', $batch) }}">
                    @csrf
                    @method('PATCH')
                    <div class="kfms-form-grid">
                        <label>
                            <span>{{ $hasAssignments ? 'Change Batch Officer' : 'Assign Batch To' }}</span>
                            <select name="assigned_to" required>
                                <option value="">Select recovery officer</option>
                                @foreach ($officers as $officer)
                                    <option value="{{ $officer->id }}">{{ $officer->name }}</option>
                                @endforeach
                            </select>
                            @error('assigned_to') <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span>Scope</span>
                            <select name="scope" required>
                                <option value="unassigned">Only unassigned accounts</option>
                                <option value="all">All accounts in this batch</option>
                            </select>
                            @error('scope') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <div class="kfms-form-actions">
                        <button class="kfms-btn" type="submit"><i class="mdi mdi-account-switch-outline"></i> {{ $hasAssignments ? 'Save Assignment Change' : 'Assign Accounts' }}</button>
                    </div>
                </form>
            </div>
        </section>
    </section>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Imported Accounts</h2>
                <span>{{ $accounts->total() }} records in this batch</span>
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Debtor</th>
                        <th>Phone</th>
                        <th>Account</th>
                        <th>Collector</th>
                        <th>Officer</th>
                        <th>Outstanding</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($accounts as $account)
                        <tr>
                            <td>{{ $account->debtor_name }}</td>
                            <td>{{ $account->phone ?: '-' }}</td>
                            <td>{{ $account->account_number ?: '-' }}</td>
                            <td>{{ $account->collector_name ?: '-' }}</td>
                            <td>{{ $account->assignee?->name ?: 'Unassigned' }}</td>
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td><a href="{{ route('recoveries.show', $account) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="kfms-empty">No accounts were imported in this batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
@endsection
