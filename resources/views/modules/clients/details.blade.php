@extends('layouts.admin')

@section('title', 'Client Details')
@section('page-title', 'Client Details')

@section('content')
    @php
        $nextOfKin = $client->nextOfKin;
        $showNextOfKin = old('add_next_of_kin', $nextOfKin ? '1' : null);
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Complete Client Profile</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('clients.details.update', $client) }}">
            @csrf
            @method('PUT')

            <div class="kfms-form-grid">
                <label>
                    <span>Client Number</span>
                    <input type="text" value="{{ $client->client_no }}" readonly disabled>
                </label>

                <label>
                    <span>Type</span>
                    <select name="client_type" required data-client-type-toggle>
                        <option value="individual" @selected(old('client_type', $client->client_type) === 'individual')>Individual</option>
                        <option value="organization" @selected(old('client_type', $client->client_type) === 'organization')>Organization</option>
                    </select>
                    @error('client_type') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-check-row">
                    <input type="checkbox" name="is_prospect" value="1" @checked(old('is_prospect', $client->is_prospect))>
                    <span>Prospect client</span>
                </label>

                <label data-client-type-field="organization">
                    <span>Organisation Name</span>
                    <input type="text" name="organization_name" value="{{ old('organization_name', $client->organization_name) }}">
                    @error('organization_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Salutation</span>
                    <select name="salutation_id">
                        <option value="">Select salutation</option>
                        @foreach ($salutations as $salutation)
                            <option value="{{ $salutation->id }}" @selected((string) old('salutation_id', $client->salutation_id) === (string) $salutation->id)>{{ $salutation->name }}</option>
                        @endforeach
                    </select>
                    @error('salutation_id') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>First Name</span>
                    <input type="text" name="first_name" value="{{ old('first_name', $client->first_name) }}">
                    @error('first_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Last Name</span>
                    <input type="text" name="last_name" value="{{ old('last_name', $client->last_name) }}">
                    @error('last_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Middle Name</span>
                    <input type="text" name="middle_name" value="{{ old('middle_name', $client->middle_name) }}">
                    @error('middle_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Position</span>
                    <select name="position_id">
                        <option value="">Select position</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}" @selected((string) old('position_id', $client->position_id) === (string) $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                    @error('position_id') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Gender</span>
                    <select name="gender">
                        <option value="">Select gender</option>
                        <option value="female" @selected(old('gender', $client->gender) === 'female')>Female</option>
                        <option value="male" @selected(old('gender', $client->gender) === 'male')>Male</option>
                    </select>
                    @error('gender') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Country of Origin</span>
                    <select name="country_id">
                        <option value="">Select country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('country_id', $client->country_id) === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client In Charge</span>
                    <select name="client_in_charge_id">
                        <option value="">Select staff member</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('client_in_charge_id', $client->client_in_charge_id) === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('client_in_charge_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>NIN / Passport / Registration No</span>
                    <input type="text" name="nin_passport_no" value="{{ old('nin_passport_no', $client->nin_passport_no) }}">
                    @error('nin_passport_no') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Date of Birth</span>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $client->date_of_birth?->toDateString()) }}">
                    @error('date_of_birth') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Status</span>
                    <select name="status" required>
                        <option value="active" @selected(old('status', $client->status) === 'active')>Active</option>
                        <option value="inactive" @selected(old('status', $client->status) === 'inactive')>Inactive</option>
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Phone</span>
                    <input type="text" name="phone" value="{{ old('phone', $client->phone) }}" required>
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email', $client->email) }}" required>
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Occupation</span>
                    <input type="text" name="occupation" value="{{ old('occupation', $client->occupation) }}">
                    @error('occupation') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>TIN</span>
                    <input type="text" name="tin" value="{{ old('tin', $client->tin) }}">
                    @error('tin') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Address</span>
                    <textarea name="address" rows="3" required>{{ old('address', $client->address) }}</textarea>
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-section-title">Next of Kin</div>
            <label class="kfms-check-row">
                <input type="checkbox" name="add_next_of_kin" value="1" data-next-of-kin-toggle @checked($showNextOfKin)>
                <span>Add or update next of kin</span>
            </label>

            <div class="kfms-form-grid" data-next-of-kin-fields>
                <label>
                    <span>Relationship</span>
                    <select name="next_of_kin[relationship_type_id]">
                        <option value="">Select relationship</option>
                        @foreach ($relationships as $relationship)
                            <option value="{{ $relationship->id }}" @selected((string) old('next_of_kin.relationship_type_id', $nextOfKin?->relationship_type_id) === (string) $relationship->id)>{{ $relationship->name }}</option>
                        @endforeach
                    </select>
                    @error('next_of_kin.relationship_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Salutation</span>
                    <select name="next_of_kin[salutation_id]">
                        <option value="">Select salutation</option>
                        @foreach ($salutations as $salutation)
                            <option value="{{ $salutation->id }}" @selected((string) old('next_of_kin.salutation_id', $nextOfKin?->salutation_id) === (string) $salutation->id)>{{ $salutation->name }}</option>
                        @endforeach
                    </select>
                    @error('next_of_kin.salutation_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>First Name</span>
                    <input type="text" name="next_of_kin[first_name]" value="{{ old('next_of_kin.first_name', $nextOfKin?->first_name) }}">
                    @error('next_of_kin.first_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Last Name</span>
                    <input type="text" name="next_of_kin[last_name]" value="{{ old('next_of_kin.last_name', $nextOfKin?->last_name) }}">
                    @error('next_of_kin.last_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Middle Name</span>
                    <input type="text" name="next_of_kin[middle_name]" value="{{ old('next_of_kin.middle_name', $nextOfKin?->middle_name) }}">
                    @error('next_of_kin.middle_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Gender</span>
                    <select name="next_of_kin[gender]">
                        <option value="">Select gender</option>
                        <option value="female" @selected(old('next_of_kin.gender', $nextOfKin?->gender) === 'female')>Female</option>
                        <option value="male" @selected(old('next_of_kin.gender', $nextOfKin?->gender) === 'male')>Male</option>
                    </select>
                    @error('next_of_kin.gender') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Telephone</span>
                    <input type="text" name="next_of_kin[phone]" value="{{ old('next_of_kin.phone', $nextOfKin?->phone) }}">
                    @error('next_of_kin.phone') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="next_of_kin[email]" value="{{ old('next_of_kin.email', $nextOfKin?->email) }}">
                    @error('next_of_kin.email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>NIN / Passport No</span>
                    <input type="text" name="next_of_kin[nin_passport_no]" value="{{ old('next_of_kin.nin_passport_no', $nextOfKin?->nin_passport_no) }}">
                    @error('next_of_kin.nin_passport_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date of Birth</span>
                    <input type="date" name="next_of_kin[date_of_birth]" value="{{ old('next_of_kin.date_of_birth', $nextOfKin?->date_of_birth?->toDateString()) }}">
                    @error('next_of_kin.date_of_birth') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Country of Origin</span>
                    <select name="next_of_kin[country_id]">
                        <option value="">Select country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('next_of_kin.country_id', $nextOfKin?->country_id) === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('next_of_kin.country_id') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Contact Address</span>
                    <textarea name="next_of_kin[address]" rows="3">{{ old('next_of_kin.address', $nextOfKin?->address) }}</textarea>
                    @error('next_of_kin.address') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">Cancel</a>
                <button type="submit">Save Details</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientType = document.querySelector('[data-client-type-toggle]');
            const clientTypeFields = document.querySelectorAll('[data-client-type-field]');
            const nextOfKinToggle = document.querySelector('[data-next-of-kin-toggle]');
            const nextOfKinFields = document.querySelector('[data-next-of-kin-fields]');

            function syncClientTypeFields() {
                if (! clientType) {
                    return;
                }

                const selectedType = clientType.value;

                clientTypeFields.forEach(function (field) {
                    const isVisible = field.dataset.clientTypeField === selectedType;
                    field.hidden = ! isVisible;

                    field.querySelectorAll('input, select, textarea').forEach(function (input) {
                        input.disabled = ! isVisible;
                        input.required = isVisible && ['organization_name', 'first_name', 'last_name', 'gender'].includes(input.name);
                    });
                });
            }

            function syncNextOfKinFields() {
                if (! nextOfKinToggle || ! nextOfKinFields) {
                    return;
                }

                const isVisible = nextOfKinToggle.checked;
                nextOfKinFields.hidden = ! isVisible;

                nextOfKinFields.querySelectorAll('input, select, textarea').forEach(function (input) {
                    input.disabled = ! isVisible;
                    input.required = isVisible && [
                        'next_of_kin[relationship_type_id]',
                        'next_of_kin[first_name]',
                        'next_of_kin[last_name]'
                    ].includes(input.name);
                });
            }

            if (clientType) {
                clientType.addEventListener('change', syncClientTypeFields);
                syncClientTypeFields();
            }

            if (nextOfKinToggle) {
                nextOfKinToggle.addEventListener('change', syncNextOfKinFields);
                syncNextOfKinFields();
            }
        });
    </script>
@endpush
