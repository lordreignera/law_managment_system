@extends('layouts.admin')

@section('title', ($editing ?? false) ? 'Update Intake' : 'New Intake')
@section('page-title', ($editing ?? false) ? 'Update Intake' : 'New Intake')

@section('content')
    @php
        $editing = $editing ?? false;
        $intake = $intake ?? null;
        $existingConflictParties = $intake?->conflictParties?->map(fn ($party) => $party->only(['name', 'relationship', 'phone', 'email', 'notes']))->all();
        $oldConflictParties = collect(old('conflict_parties', $existingConflictParties ?: [['name' => '', 'relationship' => '', 'phone' => '', 'email' => '', 'notes' => '']]))
            ->values()
            ->all();
    @endphp

    <section class="kfms-panel kfms-intake-create">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $editing ? 'Update Returned Intake' : 'Record Client Intake' }}</h2>
                <span>{{ $editing ? 'Correct the requested details and resubmit. Conflict screening will restart.' : 'Capture enquiry details before conflict review and matter opening.' }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('intakes.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Intakes
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert" role="status">{{ session('status') }}</div>
        @endif

        <p class="kfms-required-note"><span class="kfms-required">*</span> Mandatory field</p>

        <form class="kfms-form" method="POST" action="{{ $editing ? route('intakes.update', $intake) : route('intakes.store') }}">
            @csrf
            @if ($editing) @method('PATCH') @endif

            <div class="kfms-form-grid">
                <label>
                    <span>Intake Number</span>
                    <input type="text" value="{{ $intakeNumber }}" readonly disabled>
                    @error('intake_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client Type <span class="kfms-required">*</span></span>
                    <select name="client_type" id="client-type" required>
                        <option value="individual" @selected(old('client_type', $intake?->client_type ?? 'individual') === 'individual')>Individual</option>
                        <option value="organization" @selected(old('client_type', $intake?->client_type) === 'organization')>Organization</option>
                    </select>
                    @error('client_type') <small>{{ $message }}</small> @enderror
                </label>

                <div class="kfms-intake-client-details">
                    <div class="kfms-intake-client-details-heading">
                        <div>
                            <h3>Client details</h3>
                            <p>Enter the client's contact information. Add an address below the phone number if available.</p>
                        </div>
                        <span class="kfms-intake-section-step">01</span>
                    </div>

                    <div class="kfms-intake-contact-grid">
                        <label id="organization-name-field" class="kfms-intake-organization-field">
                            <span>Organization Name <span class="kfms-required">*</span></span>
                            <input type="text" name="organization_name" value="{{ old('organization_name', $intake?->organization_name) }}">
                            @error('organization_name') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Client Name <span class="kfms-required">*</span></span>
                            <input type="text" name="client_name" value="{{ old('client_name', $intake?->client_name) }}" required autocomplete="name">
                            @error('client_name') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="{{ old('email', $intake?->email) }}" autocomplete="email">
                            @error('email') <small>{{ $message }}</small> @enderror
                        </label>

                        <label>
                            <span>Phone</span>
                            <input type="tel" name="phone" value="{{ old('phone', $intake?->phone) }}" autocomplete="tel">
                            @error('phone') <small>{{ $message }}</small> @enderror
                        </label>

                        <label class="kfms-intake-address-field">
                            <span>Address</span>
                            <textarea name="address" rows="2" autocomplete="street-address" placeholder="Street, area, district, or postal address">{{ old('address', $intake?->address) }}</textarea>
                            @error('address') <small>{{ $message }}</small> @enderror
                        </label>
                    </div>
                </div>

                <label>
                    <span>Legal Issue <span class="kfms-required">*</span></span>
                    <input type="text" name="legal_issue" value="{{ old('legal_issue', $intake?->legal_issue) }}" required>
                    @error('legal_issue') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Practice Area</span>
                    <select name="practice_area_id">
                        <option value="">Select practice area</option>
                        @foreach ($practiceAreas as $practiceArea)
                            <option value="{{ $practiceArea->id }}" @selected((string) old('practice_area_id', $intake?->practice_area_id) === (string) $practiceArea->id)>{{ $practiceArea->name }}</option>
                        @endforeach
                    </select>
                    @error('practice_area_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Preferred Advocate</span>
                    <select name="preferred_lawyer_id">
                        <option value="">Select advocate</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('preferred_lawyer_id', $intake?->preferred_lawyer_id) === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('preferred_lawyer_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Urgency <span class="kfms-required">*</span></span>
                    <select name="urgency" required>
                        @foreach ($urgencies as $value => $label)
                            <option value="{{ $value }}" @selected(old('urgency', $intake?->urgency ?? 'normal') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('urgency') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Referral Source</span>
                    <select name="referral_source" id="referral-source">
                        <option value="">Select referral source</option>
                        @foreach ($referralSources as $value => $label)
                            <option value="{{ $value }}" @selected(old('referral_source', $intake?->referral_source) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('referral_source') <small>{{ $message }}</small> @enderror
                </label>

                <label id="referral-contact-field">
                    <span>Referral Name</span>
                    <input type="text" name="referral_name" value="{{ old('referral_name', $intake?->referral_name) }}" placeholder="Person or organization">
                    @error('referral_name') <small>{{ $message }}</small> @enderror
                </label>

                <label id="referral-contact-detail-field">
                    <span id="referral-contact-label">Referral Contact</span>
                    <input type="text" name="referral_contact" value="{{ old('referral_contact', $intake?->referral_contact) }}" placeholder="Name, phone, or email">
                    @error('referral_contact') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Date</span>
                    <input type="date" name="consultation_on" value="{{ old('consultation_on', $intake?->consultation_on?->toDateString() ?? now()->toDateString()) }}">
                    @error('consultation_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Consultation Time</span>
                    <input type="time" name="consultation_at" value="{{ old('consultation_at', $intake?->consultation_at ?? now()->format('H:i')) }}">
                    @error('consultation_at') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Issue Summary</span>
                    <textarea name="summary" rows="4">{{ old('summary', $intake?->summary) }}</textarea>
                    @error('summary') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-section-heading kfms-intake-conflict-heading">
                <div>
                    <h3>Conflict check</h3>
                    <span>Add opponents, related parties, directors, witnesses, or anyone who should be checked.</span>
                </div>
                <span class="kfms-intake-section-step">02</span>
            </div>

            <div id="conflict-party-list" class="kfms-conflict-party-list" aria-live="polite">
                @foreach ($oldConflictParties as $index => $party)
                    <div class="kfms-conflict-party-card" data-conflict-party-row>
                        <div class="kfms-conflict-party-card-heading">
                            <div class="kfms-conflict-party-title">
                                <span class="kfms-conflict-party-number" data-party-number>{{ $loop->iteration }}</span>
                                <div>
                                    <strong data-party-label>Party {{ $loop->iteration }}</strong>
                                    <small>Enter a name for each party. Relationship, phone, email, and notes are optional.</small>
                                </div>
                            </div>
                            <button class="kfms-conflict-party-remove" type="button" data-remove-conflict-party aria-label="Remove party {{ $loop->iteration }}">
                                <i class="mdi mdi-close" aria-hidden="true"></i>
                                Remove
                            </button>
                        </div>

                        <div class="kfms-conflict-party-fields">
                            <label>
                                <span>Party Name</span>
                                <input type="text" name="conflict_parties[{{ $index }}][name]" value="{{ $party['name'] ?? '' }}" placeholder="Person or organization">
                                @error("conflict_parties.$index.name") <small>{{ $message }}</small> @enderror
                            </label>
                            <label>
                                <span>Relationship</span>
                                <input type="text" name="conflict_parties[{{ $index }}][relationship]" value="{{ $party['relationship'] ?? '' }}" placeholder="Opponent, witness, director">
                                @error("conflict_parties.$index.relationship") <small>{{ $message }}</small> @enderror
                            </label>
                            <label>
                                <span>Phone</span>
                                <input type="tel" name="conflict_parties[{{ $index }}][phone]" value="{{ $party['phone'] ?? '' }}" placeholder="Phone number" autocomplete="tel">
                                @error("conflict_parties.$index.phone") <small>{{ $message }}</small> @enderror
                            </label>
                            <label>
                                <span>Email</span>
                                <input type="email" name="conflict_parties[{{ $index }}][email]" value="{{ $party['email'] ?? '' }}" placeholder="Email address" autocomplete="email">
                                @error("conflict_parties.$index.email") <small>{{ $message }}</small> @enderror
                            </label>
                            <label class="kfms-conflict-party-notes">
                                <span>Notes</span>
                                <textarea name="conflict_parties[{{ $index }}][notes]" rows="2" placeholder="Any useful details for the conflict check">{{ $party['notes'] ?? '' }}</textarea>
                                @error("conflict_parties.$index.notes") <small>{{ $message }}</small> @enderror
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>

            <button class="kfms-link-btn kfms-conflict-party-add" type="button" id="add-conflict-party">
                <i class="mdi mdi-plus"></i>
                Add another party
            </button>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('intakes.index') }}">Cancel</a>
                <button type="submit">{{ $editing ? 'Resubmit Intake' : 'Save Intake' }}</button>
            </div>
        </form>
    </section>

    <template id="conflict-party-template">
        <div class="kfms-conflict-party-card" data-conflict-party-row>
            <div class="kfms-conflict-party-card-heading">
                <div class="kfms-conflict-party-title">
                    <span class="kfms-conflict-party-number" data-party-number>1</span>
                    <div>
                        <strong data-party-label>Party 1</strong>
                        <small>Enter a name for each party. Relationship, phone, email, and notes are optional.</small>
                    </div>
                </div>
                <button class="kfms-conflict-party-remove" type="button" data-remove-conflict-party aria-label="Remove party">
                    <i class="mdi mdi-close" aria-hidden="true"></i>
                    Remove
                </button>
            </div>

            <div class="kfms-conflict-party-fields">
                <label>
                    <span>Party Name</span>
                    <input type="text" data-name-template="conflict_parties[__INDEX__][name]" placeholder="Person or organization">
                </label>
                <label>
                    <span>Relationship</span>
                    <input type="text" data-name-template="conflict_parties[__INDEX__][relationship]" placeholder="Opponent, witness, director">
                </label>
                <label>
                    <span>Phone</span>
                    <input type="tel" data-name-template="conflict_parties[__INDEX__][phone]" placeholder="Phone number" autocomplete="tel">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" data-name-template="conflict_parties[__INDEX__][email]" placeholder="Email address" autocomplete="email">
                </label>
                <label class="kfms-conflict-party-notes">
                    <span>Notes</span>
                    <textarea data-name-template="conflict_parties[__INDEX__][notes]" rows="2" placeholder="Any useful details for the conflict check"></textarea>
                </label>
            </div>
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

                    row.querySelectorAll('[name^="conflict_parties"]').forEach((field) => {
                        field.name = field.name.replace(/conflict_parties\[\d+\]/, `conflict_parties[${index}]`);
                    });

                    const number = index + 1;
                    const numberLabel = row.querySelector('[data-party-number]');
                    const partyLabel = row.querySelector('[data-party-label]');
                    const removeButton = row.querySelector('[data-remove-conflict-party]');
                    if (numberLabel) numberLabel.textContent = number;
                    if (partyLabel) partyLabel.textContent = `Party ${number}`;
                    if (removeButton) removeButton.setAttribute('aria-label', `Remove party ${number}`);
                });
            };

            document.getElementById('add-conflict-party')?.addEventListener('click', () => {
                const clone = template.content.firstElementChild.cloneNode(true);
                conflictList.appendChild(clone);
                reindexConflictRows();
                clone.querySelector('input')?.focus();
            });

            conflictList?.addEventListener('click', (event) => {
                const button = event.target.closest('[data-remove-conflict-party]');
                if (! button) {
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
