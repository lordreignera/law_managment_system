@extends('layouts.admin')

@section('title', 'New Intake')
@section('page-title', 'New Intake')

@section('content')
    @php
        $oldConflictParties = collect(old('conflict_parties', [['name' => '', 'relationship' => '', 'contact' => '', 'notes' => '']]))
            ->values()
            ->all();
    @endphp

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

        <p class="kfms-required-note"><span class="kfms-required">*</span> Mandatory field</p>

        <form class="kfms-form" method="POST" action="{{ route('intakes.store') }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Intake Number</span>
                    <input type="text" value="{{ $intakeNumber }}" readonly disabled>
                    @error('intake_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Type <span class="kfms-required">*</span></span>
                    <select name="client_type" id="client-type" required>
                        <option value="individual" @selected(old('client_type', 'individual') === 'individual')>Individual</option>
                        <option value="organization" @selected(old('client_type') === 'organization')>Organization</option>
                    </select>
                    @error('client_type') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Name <span class="kfms-required">*</span></span>
                    <input type="text" name="client_name" value="{{ old('client_name') }}" required>
                    @error('client_name') <small>{{ $message }}</small> @enderror
                </label>

                <label id="organization-name-field">
                    <span>Organization Name <span class="kfms-required">*</span></span>
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
                    <span>Legal Issue <span class="kfms-required">*</span></span>
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
                    <span>Preferred Advocate</span>
                    <select name="preferred_lawyer_id">
                        <option value="">Select advocate</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('preferred_lawyer_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('preferred_lawyer_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Urgency <span class="kfms-required">*</span></span>
                    <select name="urgency" required>
                        @foreach ($urgencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('urgency', 'normal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('urgency') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Referral Source</span>
                    <select name="referral_source" id="referral-source">
                        <option value="">Select referral source</option>
                        @foreach ($referralSources as $value => $label)
                            <option value="{{ $value }}" @selected(old('referral_source') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('referral_source') <small>{{ $message }}</small> @enderror
                </label>

                <label id="referral-contact-field">
                    <span>Referral Name</span>
                    <input type="text" name="referral_name" value="{{ old('referral_name') }}" placeholder="Person or organization">
                    @error('referral_name') <small>{{ $message }}</small> @enderror
                </label>

                <label id="referral-contact-detail-field">
                    <span id="referral-contact-label">Referral Contact</span>
                    <input type="text" name="referral_contact" value="{{ old('referral_contact') }}" placeholder="Name, phone, or email">
                    @error('referral_contact') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Date</span>
                    <input type="date" name="consultation_on" value="{{ old('consultation_on', now()->toDateString()) }}">
                    @error('consultation_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Time</span>
                    <input type="time" name="consultation_at" value="{{ old('consultation_at', now()->format('H:i')) }}">
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
                <span>Add opponents, related parties, directors, witnesses, or anyone who should be checked.</span>
            </div>

            <div id="conflict-party-list" class="kfms-conflict-party-list">
                @foreach ($oldConflictParties as $index => $party)
                    <div class="kfms-conflict-party-row" data-conflict-party-row>
                        <label>
                            <span>Party Name</span>
                            <input type="text" name="conflict_parties[{{ $index }}][name]" value="{{ $party['name'] ?? '' }}">
                            @error("conflict_parties.$index.name") <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span>Relationship</span>
                            <input type="text" name="conflict_parties[{{ $index }}][relationship]" value="{{ $party['relationship'] ?? '' }}" placeholder="Opponent, witness, director">
                            @error("conflict_parties.$index.relationship") <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span>Contact</span>
                            <input type="text" name="conflict_parties[{{ $index }}][contact]" value="{{ $party['contact'] ?? '' }}" placeholder="Phone or email">
                            @error("conflict_parties.$index.contact") <small>{{ $message }}</small> @enderror
                        </label>
                        <label>
                            <span>Notes</span>
                            <input type="text" name="conflict_parties[{{ $index }}][notes]" value="{{ $party['notes'] ?? '' }}">
                            @error("conflict_parties.$index.notes") <small>{{ $message }}</small> @enderror
                        </label>
                        <button class="kfms-link-btn" type="button" data-remove-conflict-party>
                            <i class="mdi mdi-close"></i>
                            Remove
                        </button>
                    </div>
                @endforeach
            </div>

            <button class="kfms-link-btn" type="button" id="add-conflict-party">
                <i class="mdi mdi-plus"></i>
                Add Conflict Party
            </button>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('intakes.index') }}">Cancel</a>
                <button type="submit">Save Intake</button>
            </div>
        </form>
    </section>

    <template id="conflict-party-template">
        <div class="kfms-conflict-party-row" data-conflict-party-row>
            <label>
                <span>Party Name</span>
                <input type="text" data-name-template="conflict_parties[__INDEX__][name]">
            </label>
            <label>
                <span>Relationship</span>
                <input type="text" data-name-template="conflict_parties[__INDEX__][relationship]" placeholder="Opponent, witness, director">
            </label>
            <label>
                <span>Contact</span>
                <input type="text" data-name-template="conflict_parties[__INDEX__][contact]" placeholder="Phone or email">
            </label>
            <label>
                <span>Notes</span>
                <input type="text" data-name-template="conflict_parties[__INDEX__][notes]">
            </label>
            <button class="kfms-link-btn" type="button" data-remove-conflict-party>
                <i class="mdi mdi-close"></i>
                Remove
            </button>
        </div>
    </template>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const clientType = document.getElementById('client-type');
            const organizationField = document.getElementById('organization-name-field');
            const organizationInput = organizationField?.querySelector('input');
            const referralSource = document.getElementById('referral-source');
            const referralNameField = document.getElementById('referral-contact-field');
            const referralContactField = document.getElementById('referral-contact-detail-field');
            const referralContactLabel = document.getElementById('referral-contact-label');
            const referralContactInput = referralContactField?.querySelector('input');
            const conflictList = document.getElementById('conflict-party-list');
            const template = document.getElementById('conflict-party-template');

            const toggleOrganization = () => {
                const isOrganization = clientType?.value === 'organization';
                organizationField.hidden = ! isOrganization;
                organizationInput.required = isOrganization;
                if (! isOrganization) {
                    organizationInput.value = '';
                }
            };

            const toggleReferralContact = () => {
                const isEmail = referralSource?.value === 'email';
                const hasSource = Boolean(referralSource?.value);
                referralNameField.hidden = ! hasSource;
                referralContactField.hidden = ! hasSource;
                referralContactInput.required = isEmail;
                referralContactInput.type = isEmail ? 'email' : 'text';
                referralContactInput.placeholder = isEmail ? 'Referral email address' : 'Name, phone, or email';
                referralContactLabel.innerHTML = isEmail
                    ? 'Referral Email <span class="kfms-required">*</span>'
                    : 'Referral Contact';
            };

            const reindexConflictRows = () => {
                conflictList.querySelectorAll('[data-conflict-party-row]').forEach((row, index) => {
                    row.querySelectorAll('[data-name-template]').forEach((input) => {
                        input.name = input.dataset.nameTemplate.replace('__INDEX__', index);
                    });

                    row.querySelectorAll('input[name^="conflict_parties"]').forEach((input) => {
                        input.name = input.name.replace(/conflict_parties\[\d+\]/, `conflict_parties[${index}]`);
                    });
                });
            };

            document.getElementById('add-conflict-party')?.addEventListener('click', () => {
                const clone = template.content.firstElementChild.cloneNode(true);
                conflictList.appendChild(clone);
                reindexConflictRows();
            });

            conflictList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-conflict-party]');
                if (! button) {
                    return;
                }

                const rows = conflictList.querySelectorAll('[data-conflict-party-row]');
                if (rows.length === 1) {
                    button.closest('[data-conflict-party-row]').querySelectorAll('input').forEach((input) => input.value = '');
                    return;
                }

                button.closest('[data-conflict-party-row]').remove();
                reindexConflictRows();
            });

            clientType?.addEventListener('change', toggleOrganization);
            referralSource?.addEventListener('change', toggleReferralContact);
            toggleOrganization();
            toggleReferralContact();
            reindexConflictRows();
        });
    </script>
@endsection
