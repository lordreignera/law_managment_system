@extends('layouts.admin')

@section('title', 'Start ADR')
@section('page-title', 'Alternative Dispute Resolution (ADR)')

@section('content')
    @php
        $hasConflictParties = $conflictParties->isNotEmpty();
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Record ADR (Alternative Dispute Resolution)</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('clients.adr.store', $client) }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>ADR Number</span>
                    <input type="text" value="{{ $adrNumber }}" readonly disabled>
                </label>

                <label>
                    <span>ADR Title / Legal Issue</span>
                    <input type="text" name="title" value="{{ old('title', $suggestedTitle) }}" @if ($suggestedTitle) readonly @endif required>
                    @error('title') <small>{{ $message }}</small> @enderror
                </label>

                @if ($sourceIntake)
                    <label>
                        <span>Source Intake</span>
                        <input type="text" value="{{ $sourceIntake->intake_no }}" readonly disabled>
                    </label>
                @endif

                @if ($hasConflictParties)
                    <label>
                        <span>Conflict Party From Intake</span>
                        <select name="intake_conflict_party_id" required>
                            <option value="">Select conflict party</option>
                            @foreach ($conflictParties as $party)
                                <option value="{{ $party->id }}" @selected((string) old('intake_conflict_party_id') === (string) $party->id)>
                                    {{ $party->name }}{{ $party->relationship ? ' - '.$party->relationship : '' }}{{ $party->contact ? ' - '.$party->contact : '' }}
                                </option>
                            @endforeach
                        </select>
                        @error('intake_conflict_party_id') <small>{{ $message }}</small> @enderror
                    </label>
                @else
                    <label>
                        <span>Conflict Party Name</span>
                        <input type="text" name="conflict_party_name" value="{{ old('conflict_party_name') }}" required>
                        @error('conflict_party_name') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Conflict Party Contact</span>
                        <input type="text" name="conflict_party_contact" value="{{ old('conflict_party_contact') }}">
                        @error('conflict_party_contact') <small>{{ $message }}</small> @enderror
                    </label>
                @endif

                <label>
                    <span>Method</span>
                    <select name="method">
                        <option value="">Select method</option>
                        <option value="call" @selected(old('method') === 'call')>Call</option>
                        <option value="email" @selected(old('method') === 'email')>Email</option>
                        <option value="letter" @selected(old('method') === 'letter')>Letter</option>
                        <option value="meeting" @selected(old('method') === 'meeting')>Meeting</option>
                        <option value="mediation" @selected(old('method') === 'mediation')>Mediation</option>
                        <option value="other" @selected(old('method') === 'other')>Other</option>
                    </select>
                    @error('method') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>ADR Date</span>
                    <input type="date" name="resolved_on" value="{{ old('resolved_on', now()->toDateString()) }}">
                    @error('resolved_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Response</span>
                    <select name="response" required>
                        <option value="pending" @selected(old('response', 'pending') === 'pending')>Pending</option>
                        <option value="accepted_negotiation" @selected(old('response') === 'accepted_negotiation')>Accepted resolution</option>
                        <option value="declined" @selected(old('response') === 'declined')>Declined</option>
                        <option value="no_response" @selected(old('response') === 'no_response')>No response</option>
                        <option value="settled" @selected(old('response') === 'settled')>Settled</option>
                        <option value="court_required" @selected(old('response') === 'court_required')>Court action required</option>
                        <option value="other" @selected(old('response') === 'other')>Other</option>
                    </select>
                    @error('response') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Response Notes</span>
                    <textarea name="response_notes" rows="5">{{ old('response_notes') }}</textarea>
                    @error('response_notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">Cancel</a>
                <button type="submit">Save ADR</button>
            </div>
        </form>
    </section>
@endsection
