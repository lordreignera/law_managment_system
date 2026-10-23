@php($inModal = $inModal ?? false)

<form class="kfms-form" method="POST" action="{{ route('clients.adr.store', $client) }}">
    @csrf
    @if ($inModal)
        <input type="hidden" name="_client_profile_modal" value="adr">
    @endif

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

        @if ($conflictParties->isNotEmpty())
            <label>
                <span>Conflict Party From Intake</span>
                <select name="intake_conflict_party_id" required>
                    <option value="">Select conflict party</option>
                    @foreach ($conflictParties as $party)
                        <option value="{{ $party->id }}" @selected((string) old('intake_conflict_party_id') === (string) $party->id)>
                            {{ $party->name }}{{ $party->relationship ? ' - '.$party->relationship : '' }}{{ $party->phone ? ' - Phone: '.$party->phone : '' }}{{ $party->email ? ' - Email: '.$party->email : '' }}
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
                <span>Conflict Party Phone</span>
                <input type="tel" name="conflict_party_phone" value="{{ old('conflict_party_phone') }}" autocomplete="tel">
                @error('conflict_party_phone') <small>{{ $message }}</small> @enderror
            </label>

            <label>
                <span>Conflict Party Email</span>
                <input type="email" name="conflict_party_email" value="{{ old('conflict_party_email') }}" autocomplete="email">
                @error('conflict_party_email') <small>{{ $message }}</small> @enderror
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
            <textarea name="response_notes" rows="4">{{ old('response_notes') }}</textarea>
            @error('response_notes') <small>{{ $message }}</small> @enderror
        </label>
    </div>

    <div class="kfms-form-actions">
        @if ($inModal)
            <button type="button" class="kfms-link-btn" data-bs-dismiss="modal">Cancel</button>
        @else
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">Cancel</a>
        @endif
        <button type="submit">Save ADR</button>
    </div>
</form>
