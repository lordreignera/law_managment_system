@extends('layouts.admin')

@section('title', 'New Intake')
@section('page-title', 'New Intake')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Record Client Intake</h2>
                <span>Capture enquiry details before conflict review and matter opening.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('intakes.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Intakes
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('intakes.store') }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Intake Number</span>
                    <input type="text" value="{{ $intakeNumber }}" readonly disabled>
                    @error('intake_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Type</span>
                    <select name="client_type" required>
                        <option value="individual" @selected(old('client_type', 'individual') === 'individual')>Individual</option>
                        <option value="organization" @selected(old('client_type') === 'organization')>Organization</option>
                    </select>
                    @error('client_type') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Name</span>
                    <input type="text" name="client_name" value="{{ old('client_name') }}" required>
                    @error('client_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Organization Name</span>
                    <input type="text" name="organization_name" value="{{ old('organization_name') }}">
                    @error('organization_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}">
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Phone</span>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Address</span>
                    <textarea name="address" rows="2">{{ old('address') }}</textarea>
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Legal Issue</span>
                    <input type="text" name="legal_issue" value="{{ old('legal_issue') }}" required>
                    @error('legal_issue') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Practice Area</span>
                    <select name="practice_area_id">
                        <option value="">Select practice area</option>
                        @foreach ($practiceAreas as $practiceArea)
                            <option value="{{ $practiceArea->id }}" @selected((string) old('practice_area_id') === (string) $practiceArea->id)>{{ $practiceArea->name }}</option>
                        @endforeach
                    </select>
                    @error('practice_area_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Preferred Lawyer</span>
                    <select name="preferred_lawyer_id">
                        <option value="">Select lawyer</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('preferred_lawyer_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('preferred_lawyer_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Urgency</span>
                    <select name="urgency" required>
                        @foreach ($urgencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('urgency', 'normal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('urgency') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Referral Source</span>
                    <input type="text" name="referral_source" value="{{ old('referral_source') }}">
                    @error('referral_source') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Date</span>
                    <input type="date" name="consultation_on" value="{{ old('consultation_on') }}">
                    @error('consultation_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Time</span>
                    <input type="time" name="consultation_at" value="{{ old('consultation_at') }}">
                    @error('consultation_at') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Issue Summary</span>
                    <textarea name="summary" rows="4">{{ old('summary') }}</textarea>
                    @error('summary') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-section-heading">
                <h3>Conflict Parties</h3>
                <span>Opponents, related parties, directors, witnesses, or anyone who should be checked.</span>
            </div>

            <div class="kfms-form-grid">
                @for ($index = 0; $index < 4; $index++)
                    <label>
                        <span>Party Name</span>
                        <input type="text" name="conflict_parties[{{ $index }}][name]" value="{{ old("conflict_parties.$index.name") }}">
                        @error("conflict_parties.$index.name") <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>Relationship</span>
                        <input type="text" name="conflict_parties[{{ $index }}][relationship]" value="{{ old("conflict_parties.$index.relationship") }}">
                        @error("conflict_parties.$index.relationship") <small>{{ $message }}</small> @enderror
                    </label>
                @endfor
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('intakes.index') }}">Cancel</a>
                <button type="submit">Save Intake</button>
            </div>
        </form>
    </section>
@endsection
