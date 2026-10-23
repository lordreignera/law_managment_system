@extends('layouts.admin')

@section('title', 'Review Intake')
@section('page-title', 'Review Intake')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $intake->intake_no }}</h2>
                <span>{{ $intake->client_name }} - {{ $intake->statusLabel() }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('intakes.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Intakes
            </a>
            @if ($intake->review_decision === 'more_information_needed')
                @can('intakes.edit')
                    <a class="kfms-btn" href="{{ route('intakes.edit', $intake) }}">Update Intake</a>
                @endcan
            @endif
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Client Type</span>
                <strong>{{ ucfirst($intake->client_type) }}</strong>
            </div>
            <div>
                <span>Legal Issue</span>
                <strong>{{ $intake->legal_issue }}</strong>
            </div>
            <div>
                <span>Practice Area</span>
                <strong>{{ $intake->practiceArea?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Preferred Advocate</span>
                <strong>{{ $intake->preferredLawyer?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Phone</span>
                <strong>{{ $intake->phone ?: '-' }}</strong>
            </div>
            <div>
                <span>Email</span>
                <strong>{{ $intake->email ?: '-' }}</strong>
            </div>
            <div>
                <span>Referral Source</span>
                <strong>{{ $intake->referral_source ? $intake->referralSourceLabel() : '-' }}</strong>
            </div>
            <div>
                <span>Referral Name</span>
                <strong>{{ $intake->referral_name ?: '-' }}</strong>
            </div>
            <div>
                <span>Referral Contact</span>
                <strong>{{ $intake->referral_contact ?: '-' }}</strong>
            </div>
            <div>
                <span>Review Decision</span>
                <strong>{{ $intake->reviewDecisionLabel() }}</strong>
            </div>
            <div>
                <span>Approved Client</span>
                <strong>
                    @if ($intake->client)
                        <a href="{{ route('clients.show', $intake->client) }}">{{ $intake->client->client_no }}</a>
                    @else
                        -
                    @endif
                </strong>
            </div>
        </div>

        <div class="kfms-section-heading">
            <h3>Issue Summary</h3>
        </div>
        <p class="kfms-muted-text">{{ $intake->summary ?: 'No summary recorded.' }}</p>

        <div class="kfms-section-heading">
            <h3>Conflict Parties</h3>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($intake->conflictParties as $party)
                        <tr>
                            <td>{{ $party->name }}</td>
                            <td>{{ $party->relationship ?: '-' }}</td>
                            <td>{{ $party->phone ?: '-' }}</td>
                            <td>{{ $party->email ?: '-' }}</td>
                            <td>{{ $party->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="kfms-empty">No conflict parties recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="kfms-section-heading">
            <h3>Conflict Check</h3>
            <span>Record the screening result before approving this intake.</span>
        </div>
        <div class="kfms-detail-grid">
            <div><span>Result</span><strong>{{ $intake->conflictStatusLabel() }}</strong></div>
            <div><span>Checked By</span><strong>{{ $intake->conflictChecker?->name ?: '-' }}</strong></div>
            <div><span>Checked On</span><strong>{{ $intake->conflict_checked_at?->format('d M Y H:i') ?: '-' }}</strong></div>
            <div class="kfms-span-2"><span>Check Notes</span><strong>{{ $intake->conflict_check_notes ?: 'No check recorded.' }}</strong></div>
        </div>

        @if (! in_array($intake->review_decision, ['approved', 'rejected'], true))
            @can('intakes.conflict-check')
                <form class="kfms-form kfms-intake-conflict-check-form" method="POST" action="{{ route('intakes.conflict-check', $intake) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="intake_id" value="{{ $intake->id }}">
                    <div class="kfms-form-grid">
                        <label>
                            <span>Conflict check result <span class="kfms-required">*</span></span>
                            <select name="conflict_status" required>
                                <option value="">Select result</option>
                                @foreach ($conflictStatuses as $value => $label)
                                    <option value="{{ $value }}" @selected(old('conflict_status', $intake->conflict_status === 'pending' ? '' : $intake->conflict_status) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('conflict_status') <small>{{ $message }}</small> @enderror
                        </label>
                        <label class="kfms-span-2">
                            <span>Check notes <span class="kfms-required">*</span></span>
                            <textarea name="conflict_check_notes" rows="3" required maxlength="3000" placeholder="Record the search performed and the basis for this result.">{{ old('conflict_check_notes', $intake->conflict_check_notes) }}</textarea>
                            @error('conflict_check_notes') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>
                    <div class="kfms-form-actions"><button type="submit">Save Conflict Check</button></div>
                </form>
            @endcan
        @endif

        <div class="kfms-section-heading">
            <h3>Review Decision</h3>
            <span>Approve the client into the Approved Clients register only after the conflict check is clear.</span>
        </div>

        @if (in_array($intake->review_decision, ['approved', 'rejected'], true))
            <div class="kfms-detail-grid">
                <div>
                    <span>Final Decision</span>
                    <strong>{{ $intake->reviewDecisionLabel() }}</strong>
                </div>
                <div>
                    <span>Reviewed By</span>
                    <strong>{{ $intake->reviewer?->name ?: '-' }}</strong>
                </div>
                <div>
                    <span>Reviewed On</span>
                    <strong>{{ $intake->reviewed_at?->format('d M Y H:i') ?: '-' }}</strong>
                </div>
                <div class="kfms-span-2">
                    <span>Reason / Review Notes</span>
                    <strong>{{ $intake->review_notes ?: '-' }}</strong>
                </div>
            </div>

            @if ($intake->client)
                <div class="kfms-form-actions">
                    <a class="kfms-btn" href="{{ route('clients.show', $intake->client) }}">
                        <i class="mdi mdi-account-check"></i>
                        Open Approved Client
                    </a>
                    <a class="kfms-link-btn" href="{{ route('clients.details.edit', $intake->client) }}">
                        Add More Details
                    </a>
                    <a class="kfms-link-btn" href="{{ route('clients.adr.create', $intake->client) }}">
                        Start ADR
                    </a>
                    <a class="kfms-btn" href="{{ route('clients.files.create', $intake->client) }}">
                        <i class="mdi mdi-folder-plus"></i>
                        Open File
                    </a>
                </div>
            @endif
        @else
            @if ($intake->conflict_status !== 'cleared' || ! $intake->conflict_checked_at)
                <div class="kfms-alert" role="status">Client approval is blocked until an authorized reviewer records a cleared conflict check.</div>
            @endif
            <form class="kfms-form" method="POST" action="{{ route('intakes.review', $intake) }}">
                @csrf
                @method('PATCH')

                <div class="kfms-form-grid">
                    <label>
                        <span>Decision <span class="kfms-required">*</span></span>
                        <select name="review_decision" required>
                            <option value="">Select decision</option>
                            @foreach ($reviewDecisions as $value => $label)
                                @continue($value === 'pending')
                                <option value="{{ $value }}" @selected(old('review_decision') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('review_decision') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Reason / Review Notes</span>
                        <textarea id="intake-review-notes" name="review_notes" rows="4">{{ old('review_notes', $intake->review_notes) }}</textarea>
                        <small>Required when rejecting or requesting more information. Optional when approving.</small>
                        @error('review_notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="kfms-form-actions">
                    <button type="submit">Save Decision</button>
                </div>
            </form>
        @endif
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const decision = document.querySelector('[name="review_decision"]');
            const notes = document.getElementById('intake-review-notes');
            if (!decision || !notes) return;
            const syncReasonRequirement = () => {
                notes.required = ['rejected', 'more_information_needed'].includes(decision.value);
            };
            decision.addEventListener('change', syncReasonRequirement);
            syncReasonRequirement();
        });
    </script>
@endpush
