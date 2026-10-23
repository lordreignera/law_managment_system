@extends('layouts.admin')

@section('title', 'Client Intake Review')
@section('page-title', 'Client Intake Review')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Intake Review Queue</h2>
                <span>{{ $intakes->total() }} pending, rejected, or returned intake requests</span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('clients.index') }}">
                    <i class="mdi mdi-account-check"></i>
                    Approved Clients
                </a>
                <a class="kfms-btn" href="{{ route('intakes.create') }}">
                    <i class="mdi mdi-plus"></i>
                    New Intake
                </a>
            </div>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <form class="kfms-table-toolbar" method="GET" action="{{ route('intakes.index') }}">
            <label class="kfms-search-box">
                <i class="mdi mdi-magnify"></i>
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search intake, client, or issue">
            </label>
            <label>
                <span>Status</span>
                <select name="status">
                    <option value="">All Review Queue Statuses</option>
                    @foreach ($statuses as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                <span>Decision</span>
                <select name="review_decision">
                    <option value="">All Open Decisions</option>
                    @foreach ($reviewDecisions as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['review_decision'] ?? '') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <div class="kfms-toolbar-actions">
                <button class="kfms-link-btn" type="submit">Apply Filters</button>
                <a class="kfms-link-btn" href="{{ route('intakes.index') }}">Reset</a>
            </div>
        </form>

        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Intake</th>
                        <th>Client</th>
                        <th>Legal Issue</th>
                        <th>Practice Area</th>
                        <th>Conflict Check</th>
                        <th>Status</th>
                        <th>Decision</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($intakes as $intake)
                        <tr>
                            <td>{{ $intake->intake_no }}</td>
                            <td>{{ $intake->client_name }}</td>
                            <td>{{ $intake->legal_issue }}</td>
                            <td>{{ $intake->practiceArea?->name ?: '-' }}</td>
                            <td>{{ $intake->conflictStatusLabel() }}</td>
                            <td>{{ $intake->statusLabel() }}</td>
                            <td>{{ $intake->reviewDecisionLabel() }}</td>
                            <td>
                                <a class="kfms-link-btn"
                                    href="{{ route('intakes.show', $intake) }}"
                                    data-intake-review-trigger
                                    data-intake-id="{{ $intake->id }}"
                                    data-intake-number="{{ $intake->intake_no }}"
                                    data-client-name="{{ $intake->client_name }}"
                                    data-final="{{ in_array($intake->review_decision, ['approved', 'rejected'], true) ? '1' : '0' }}"
                                    data-review-url="{{ route('intakes.review', $intake) }}"
                                    data-conflict-url="{{ route('intakes.conflict-check', $intake) }}"
                                    data-conflict-status="{{ $intake->conflict_status }}"
                                    data-conflict-notes="{{ $intake->conflict_check_notes }}"
                                    data-conflict-checker="{{ $intake->conflictChecker?->name ?: '' }}"
                                    data-conflict-checked-at="{{ $intake->conflict_checked_at?->format('d M Y, H:i') ?: '' }}">
                                    {{ in_array($intake->review_decision, ['approved', 'rejected'], true) ? 'View Decision' : 'Review / Decide' }}
                                </a>
                                @if ($intake->review_decision === 'more_information_needed')
                                    @can('intakes.edit')
                                        <a class="kfms-link-btn" href="{{ route('intakes.edit', $intake) }}">Update Intake</a>
                                    @endcan
                                @endif
                                <template id="intake-review-content-{{ $intake->id }}">
                                    <div class="kfms-review-summary">
                                        <div><span>Client type</span><strong>{{ ucfirst($intake->client_type) }}</strong></div>
                                        <div><span>Legal issue</span><strong>{{ $intake->legal_issue }}</strong></div>
                                        <div><span>Organisation</span><strong>{{ $intake->organization_name ?: 'Not provided' }}</strong></div>
                                        <div><span>Practice area</span><strong>{{ $intake->practiceArea?->name ?: 'Not selected' }}</strong></div>
                                        <div><span>Phone</span><strong>{{ $intake->phone ?: 'Not provided' }}</strong></div>
                                        <div><span>Email</span><strong>{{ $intake->email ?: 'Not provided' }}</strong></div>
                                        <div><span>Address</span><strong>{{ $intake->address ?: 'Not provided' }}</strong></div>
                                        <div><span>Preferred advocate</span><strong>{{ $intake->preferredLawyer?->name ?: 'Not selected' }}</strong></div>
                                        <div><span>Urgency</span><strong>{{ \App\Models\ClientIntake::URGENCIES[$intake->urgency] ?? str($intake->urgency)->headline() }}</strong></div>
                                        <div><span>Consultation</span><strong>{{ $intake->consultation_on?->format('d M Y') ?: 'Not scheduled' }}{{ $intake->consultation_at ? ' at '.substr((string) $intake->consultation_at, 0, 5) : '' }}</strong></div>
                                        <div><span>Referral source</span><strong>{{ $intake->referral_source ? $intake->referralSourceLabel() : 'Not recorded' }}</strong></div>
                                        <div><span>Referred by</span><strong>{{ $intake->referral_name ?: 'Not provided' }}</strong></div>
                                        <div><span>Referral contact</span><strong>{{ $intake->referral_contact ?: 'Not provided' }}</strong></div>
                                        <div><span>Submitted</span><strong>{{ $intake->created_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
                                    </div>
                                    <div class="kfms-section-heading"><h3>Issue summary</h3></div>
                                    <p class="kfms-muted-text kfms-client-intake-summary">{{ $intake->summary ?: 'No issue summary was provided.' }}</p>
                                    <div class="kfms-section-heading"><h3>Conflict parties</h3></div>
                                    <div class="kfms-table-wrap">
                                        <table class="kfms-table">
                                            <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>Email</th><th>Notes</th></tr></thead>
                                            <tbody>
                                                @forelse ($intake->conflictParties as $party)
                                                    <tr><td>{{ $party->name }}</td><td>{{ $party->relationship ?: '-' }}</td><td>{{ $party->phone ?: '-' }}</td><td>{{ $party->email ?: '-' }}</td><td>{{ $party->notes ?: '-' }}</td></tr>
                                                @empty
                                                    <tr><td colspan="5" class="kfms-empty">No conflict parties recorded.</td></tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>
                                    @if ($intake->review_notes)
                                        <div class="kfms-section-heading"><h3>Previous review note</h3></div>
                                        <p class="kfms-muted-text kfms-client-intake-summary">{{ $intake->review_notes }}</p>
                                    @endif
                                </template>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="kfms-empty">No intake requests are waiting for review.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{ $intakes->links() }}
    </section>

    <div class="modal fade kfms-modal kfms-intake-review-modal" id="intake-review-modal" tabindex="-1" aria-labelledby="intake-review-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h2 class="modal-title" id="intake-review-title">Review client intake</h2>
                        <span id="intake-review-subtitle"></span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="intake-review-body"></div>
                @can('intakes.conflict-check')
                    <form id="intake-conflict-check-form" class="kfms-intake-conflict-check-form" method="POST" action="">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="intake_id" value="">
                        <div class="kfms-intake-conflict-check-current" id="intake-conflict-check-current"></div>
                        <div class="kfms-intake-conflict-check-fields">
                            <label>
                                <span>Conflict check result <span class="kfms-required">*</span></span>
                                <select id="intake-conflict-status" name="conflict_status" required>
                                    <option value="">Select result</option>
                                    <option value="cleared">Cleared</option>
                                    <option value="potential_conflict">Potential conflict — needs review</option>
                                    <option value="conflict_found">Conflict found</option>
                                </select>
                            </label>
                            <label>
                                <span>Check notes <span class="kfms-required">*</span></span>
                                <textarea id="intake-conflict-notes" name="conflict_check_notes" maxlength="3000" rows="2" required placeholder="Record the search performed and the basis for this result."></textarea>
                            </label>
                        </div>
                        <button class="kfms-link-btn" type="submit">Save Conflict Check</button>
                    </form>
                @endcan
                </div>
                <form id="intake-review-form" class="kfms-intake-review-form" method="POST" action="">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="intake_id" value="">
                    <div class="kfms-intake-review-note">
                        <label for="intake-review-notes">Reason or review note</label>
                        <textarea id="intake-review-notes" name="review_notes" maxlength="3000" placeholder="Add a reason for rejection or explain what information is needed.">{{ old('review_notes') }}</textarea>
                        <small>Required for rejection and information requests. Optional when approving.</small>
                    </div>
                </form>
                <div class="modal-footer kfms-intake-review-footer">
                    <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="kfms-link-btn kfms-intake-decision-action kfms-danger" type="submit" form="intake-review-form" name="review_decision" value="rejected" data-review-decision="rejected">Reject client</button>
                    <button class="kfms-link-btn kfms-intake-decision-action" type="submit" form="intake-review-form" name="review_decision" value="more_information_needed" data-review-decision="more_information_needed">Request information</button>
                    <button class="kfms-btn kfms-intake-decision-action" type="submit" form="intake-review-form" name="review_decision" value="approved" data-review-decision="approved" disabled title="Clear the conflict check before approving">Approve client</button>
                </div>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="d-none" id="intake-review-errors" data-intake-id="{{ old('intake_id') }}" data-old-decision="{{ old('review_decision') }}" data-old-conflict-status="{{ old('conflict_status') }}" data-old-conflict-notes="{{ old('conflict_check_notes') }}">
            @foreach ($errors->all() as $error)<span>{{ $error }}</span>@endforeach
        </div>
    @endif
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('intake-review-modal');
            const modal = window.bootstrap?.Modal && window.bootstrap.Modal.getOrCreateInstance(modalElement);
            const body = document.getElementById('intake-review-body');
            const form = document.getElementById('intake-review-form');
            const notes = document.getElementById('intake-review-notes');
            const notePanel = document.querySelector('.kfms-intake-review-note');
            const conflictForm = document.getElementById('intake-conflict-check-form');
            const conflictStatus = document.getElementById('intake-conflict-status');
            const conflictNotes = document.getElementById('intake-conflict-notes');
            const conflictPanel = document.querySelector('.kfms-intake-conflict-check-form');
            const conflictCurrent = document.getElementById('intake-conflict-check-current');
            const title = document.getElementById('intake-review-title');
            const subtitle = document.getElementById('intake-review-subtitle');
            const actions = modalElement.querySelectorAll('.kfms-intake-decision-action');
            const errors = document.getElementById('intake-review-errors');

            if (!modal) return;

            function openReview(trigger, restoreOldInput = false) {
                const template = document.getElementById('intake-review-content-' + trigger.dataset.intakeId);
                if (!template) return;

                title.textContent = trigger.dataset.clientName || 'Review client intake';
                subtitle.textContent = trigger.dataset.intakeNumber + ' - ' + (trigger.dataset.final === '1' ? 'Decision recorded' : 'Review pending');
                body.replaceChildren(template.content.cloneNode(true));
                form.action = trigger.dataset.reviewUrl;
                form.querySelector('[name="intake_id"]').value = trigger.dataset.intakeId;
                actions.forEach((button) => { button.hidden = trigger.dataset.final === '1'; });
                notePanel.hidden = trigger.dataset.final === '1';

                if (conflictForm) {
                    conflictForm.action = trigger.dataset.conflictUrl;
                    conflictForm.querySelector('[name="intake_id"]').value = trigger.dataset.intakeId;
                    conflictPanel.hidden = trigger.dataset.final === '1';
                    conflictStatus.value = restoreOldInput && errors?.dataset.intakeId === trigger.dataset.intakeId
                        ? errors.dataset.oldConflictStatus
                        : (trigger.dataset.conflictStatus === 'pending' ? '' : trigger.dataset.conflictStatus);
                    conflictNotes.value = restoreOldInput && errors?.dataset.intakeId === trigger.dataset.intakeId
                        ? errors.dataset.oldConflictNotes
                        : (trigger.dataset.conflictNotes || '');
                    conflictCurrent.textContent = trigger.dataset.conflictStatus === 'pending'
                        ? 'Conflict check: Not checked. Client approval is blocked until this is cleared.'
                        : `Conflict check: ${trigger.dataset.conflictStatus.replaceAll('_', ' ')}${trigger.dataset.conflictChecker ? ` by ${trigger.dataset.conflictChecker}` : ''}${trigger.dataset.conflictCheckedAt ? ` on ${trigger.dataset.conflictCheckedAt}` : ''}.`;
                }

                const approveButton = modalElement.querySelector('[data-review-decision="approved"]');
                if (approveButton) {
                    approveButton.disabled = trigger.dataset.final === '1' || trigger.dataset.conflictStatus !== 'cleared';
                    approveButton.title = approveButton.disabled ? 'Record and clear the conflict check before approving' : '';
                }

                if (trigger.dataset.final === '1') {
                    notes.required = false;
                    notes.value = '';
                    body.insertAdjacentHTML('beforeend', '<div class="kfms-alert">This intake has a final decision and cannot be reviewed again.</div>');
                } else {
                    const errorsForModal = errors?.innerHTML;
                    if (restoreOldInput && errorsForModal) body.insertAdjacentHTML('afterbegin', '<div class="kfms-alert" role="alert">' + errorsForModal + '</div>');
                    notes.required = ['rejected', 'more_information_needed'].includes(errors?.dataset.oldDecision || '');
                    notes.value = restoreOldInput && errors?.dataset.intakeId === trigger.dataset.intakeId ? notes.value : '';
                }

                const url = new URL(window.location.href);
                url.searchParams.set('review_intake', trigger.dataset.intakeId);
                window.history.replaceState({}, '', url);
                modal.show();
            }

            document.querySelectorAll('[data-intake-review-trigger]').forEach((trigger) => {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    openReview(trigger);
                });
            });

            modalElement.addEventListener('click', function (event) {
                const decision = event.target.closest('[data-review-decision]');
                if (decision) notes.required = ['rejected', 'more_information_needed'].includes(decision.value);
            });

            modalElement.addEventListener('hidden.bs.modal', function () {
                const url = new URL(window.location.href);
                url.searchParams.delete('review_intake');
                window.history.replaceState({}, '', url);
            });

            const query = new URLSearchParams(window.location.search);
            const reviewId = query.get('review_intake');
            if (reviewId) {
                const trigger = document.querySelector('[data-intake-review-trigger][data-intake-id="' + CSS.escape(reviewId) + '"]');
                if (trigger) openReview(trigger, true);
            } else if (errors?.dataset.intakeId) {
                const trigger = document.querySelector('[data-intake-review-trigger][data-intake-id="' + CSS.escape(errors.dataset.intakeId) + '"]');
                if (trigger) openReview(trigger, true);
            }
        });
    </script>
@endpush
