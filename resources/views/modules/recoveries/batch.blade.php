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

    <section class="kfms-panel kfms-recovery-batch-summary">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $batch->client?->name }} - {{ $batch->portfolio_type }}</h2>
                <span>{{ $batch->source_file }}{{ $batch->sheet_name ? ' · '.$batch->sheet_name : '' }} imported by {{ $batch->uploader?->name ?: '-' }}</span>
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
            <section class="kfms-card"><span class="kfms-card-label">Assigned (visible)</span><strong class="kfms-stat">{{ number_format($batch->assigned_accounts_count) }}</strong></section>
        </div>

        @if ($batch->status === 'preview')
            <div class="kfms-alert">Preview only. No accounts have been created yet. Review possible matches and confirm the import below.</div>
        @elseif ($batch->accounts_count === 0)
            <div class="kfms-alert">No accounts in this batch are available for assignment. Correct the skipped rows and import the file again.</div>
        @endif
    </section>

    @if ($batch->status === 'preview')
        <section class="kfms-panel kfms-recovery-batch-accounts">
            <div class="kfms-panel-header"><div><h2>Import Preview</h2><span>{{ $batch->total_rows }} source rows · {{ $batch->preview_counts['new'] ?? 0 }} new · {{ $batch->preview_counts['duplicates'] ?? 0 }} matching accounts · {{ $batch->preview_counts['invalid'] ?? 0 }} invalid</span></div></div>
            <div class="kfms-table-wrap"><table class="kfms-table"><thead><tr><th>Row</th><th>Debtor</th><th>Account / Customer</th><th>Outstanding</th><th>Decision</th></tr></thead><tbody>
                @foreach ($batch->preview_rows ?: [] as $row)
                    <tr><td>{{ $row['row'] }}</td><td>{{ $row['debtor'] }}</td><td>{{ $row['account'] ?: '-' }}</td><td>{{ number_format((float) $row['outstanding'], 2) }}</td><td>{{ $row['decision'] }}</td></tr>
                @endforeach
            </tbody></table></div>
            @if ($batch->total_rows > count($batch->preview_rows ?: [])) <p class="kfms-muted-text">Showing the first 100 valid rows. All rows are checked again on confirmation.</p> @endif
            @can('recoveries.batches.commit')
                <form class="kfms-form kfms-recovery-batch-assign-form" method="POST" action="{{ route('recoveries.batches.commit', $batch) }}">
                    @csrf
                    <div class="kfms-recovery-batch-controls">
                        <label><span>When an account already exists</span><select name="duplicate_action" required><option value="skip">Skip existing accounts</option><option value="update">Update non-financial details only</option></select></label>
                        <p class="kfms-muted-text">New accounts will be created. Existing balances, payments, status and assignment will never be overwritten. Name-only matches are always skipped for manual review.</p>
                        <button class="kfms-btn" type="submit">Confirm Import</button>
                    </div>
                </form>
            @endcan
        </section>
    @else

    <section class="kfms-panel kfms-recovery-batch-accounts">
        <div class="kfms-panel-header">
            <div>
                <h2>Imported Accounts</h2>
                <span>{{ $accounts->total() }} records visible to you · {{ number_format($batch->assigned_accounts_count) }} assigned</span>
            </div>
        </div>
        @can('recoveries.batches.assign')
            @if ($accounts->total() > 0)
                <form id="recovery-batch-assign-form" class="kfms-form kfms-recovery-batch-assign-form" method="POST" action="{{ route('recoveries.batches.assign', $batch) }}">
                    @csrf
                    @method('PATCH')
                    <div class="kfms-recovery-batch-controls">
                        <label>
                            <span>Assign to recovery officer</span>
                            <select name="assigned_to" required>
                                <option value="">Select officer</option>
                                @foreach ($officers as $officer)
                                    <option value="{{ $officer->id }}" @selected(old('assigned_to') == $officer->id)>{{ $officer->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Accounts to assign</span>
                            <select name="scope" id="recovery-assignment-scope" required>
                                <option value="selected" @selected(old('scope', 'selected') === 'selected')>Selected accounts</option>
                                <option value="unassigned" @selected(old('scope') === 'unassigned')>All unassigned in this batch</option>
                                <option value="all" @selected(old('scope') === 'all')>All visible accounts in this batch</option>
                            </select>
                        </label>
                        <button class="kfms-btn" id="recovery-assign-submit" type="submit"><i class="mdi mdi-account-switch-outline"></i> Assign Accounts</button>
                    </div>
                    <p class="kfms-recovery-selection-help" id="recovery-selection-help" aria-live="polite">Select accounts below, or choose a batch-wide option.</p>
                    @error('assigned_to') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    @error('scope') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    @error('account_ids') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    @error('account_ids.*') <small class="text-danger d-block">{{ $message }}</small> @enderror
                </form>
            @endif
        @endcan
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        @can('recoveries.batches.assign')
                            <th class="kfms-recovery-select-cell"><label class="kfms-recovery-select-all"><input type="checkbox" id="recovery-select-page" aria-label="Select all accounts on this page" title="Select all accounts on this page"><span>Select page</span></label></th>
                        @endcan
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
                            @can('recoveries.batches.assign')
                                <td class="kfms-recovery-select-cell"><input type="checkbox" name="account_ids[]" value="{{ $account->id }}" form="recovery-batch-assign-form" class="recovery-account-select" aria-label="Select {{ $account->debtor_name }}" @checked(in_array($account->id, (array) old('account_ids', [])))></td>
                            @endcan
                            <td>{{ $account->debtor_name }}</td>
                            <td>{{ $account->phone ?: '-' }}</td>
                            <td>{{ $account->account_number ?: '-' }}</td>
                            <td>{{ $account->collector_name ?: '-' }}</td>
                            <td>{{ $account->assignee?->name ?: 'Unassigned' }}</td>
                            <td>{{ number_format($account->outstanding_amount) }}</td>
                            <td><a href="{{ route('recoveries.show', $account) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ auth()->user()->can('recoveries.batches.assign') ? 8 : 7 }}" class="kfms-empty">No accounts were imported in this batch.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $accounts->links() }}
    </section>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('recovery-batch-assign-form');
            if (!form) return;

            const selectPage = document.getElementById('recovery-select-page');
            const checkboxes = Array.from(document.querySelectorAll('.recovery-account-select'));
            const scope = document.getElementById('recovery-assignment-scope');
            const submit = document.getElementById('recovery-assign-submit');
            const help = document.getElementById('recovery-selection-help');

            function updateSelection() {
                const selected = checkboxes.filter(checkbox => checkbox.checked).length;
                selectPage.checked = checkboxes.length > 0 && selected === checkboxes.length;
                selectPage.indeterminate = selected > 0 && selected < checkboxes.length;
                submit.disabled = scope.value === 'selected' && selected === 0;
                help.textContent = scope.value === 'selected'
                    ? selected + ' of ' + checkboxes.length + ' accounts selected on this page.'
                    : 'This option applies to matching accounts across the entire visible batch.';
            }

            selectPage.addEventListener('change', function () {
                checkboxes.forEach(checkbox => { checkbox.checked = selectPage.checked; });
                scope.value = 'selected';
                updateSelection();
            });
            checkboxes.forEach(checkbox => checkbox.addEventListener('change', function () {
                scope.value = 'selected';
                updateSelection();
            }));
            scope.addEventListener('change', updateSelection);
            updateSelection();
        });
    </script>
@endpush
