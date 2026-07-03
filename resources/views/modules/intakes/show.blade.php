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
                        <th>Contact</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($intake->conflictParties as $party)
                        <tr>
                            <td>{{ $party->name }}</td>
                            <td>{{ $party->relationship ?: '-' }}</td>
                            <td>{{ $party->contact ?: '-' }}</td>
                            <td>{{ $party->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="kfms-empty">No conflict parties recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="kfms-section-heading">
            <h3>Review Decision</h3>
            <span>Approve the client into the Approved Clients register, reject, or request more information.</span>
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
                        <span>Reason / Review Notes <span class="kfms-required">*</span></span>
                        <textarea name="review_notes" rows="4" required>{{ old('review_notes', $intake->review_notes) }}</textarea>
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
