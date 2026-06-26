@extends('layouts.admin')

@section('title', 'Add Client')
@section('page-title', 'Add Client')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Create New Client</h2>
                <span>Register individual or organisation clients with contact and next-of-kin details.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Clients
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('clients.store') }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Type</span>
                    <select name="client_type" required data-client-type-toggle>
                        <option value="individual" @selected(old('client_type', 'individual') === 'individual')>Individual</option>
                        <option value="organization" @selected(old('client_type') === 'organization')>Organization</option>
                    </select>
                    @error('client_type') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-check-row">
                    <input type="checkbox" name="is_prospect" value="1" @checked(old('is_prospect'))>
                    <span>Prospect client</span>
                </label>

                <label data-client-type-field="organization">
                    <span>Organisation Name</span>
                    <input type="text" name="organization_name" value="{{ old('organization_name') }}">
                    @error('organization_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Salutation</span>
                    <select name="salutation_id">
                        <option value="">Select salutation</option>
                        @foreach ($salutations as $salutation)
                            <option value="{{ $salutation->id }}" @selected((string) old('salutation_id') === (string) $salutation->id)>{{ $salutation->name }}</option>
                        @endforeach
                    </select>
                    @error('salutation_id') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>First Name</span>
                    <input type="text" name="first_name" value="{{ old('first_name') }}">
                    @error('first_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Last Name</span>
                    <input type="text" name="last_name" value="{{ old('last_name') }}">
                    @error('last_name') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Middle Name</span>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}">
                    @error('middle_name') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Position</span>
                    <select name="position_id" required>
                        <option value="">Select position</option>
                        @foreach ($positions as $position)
                            <option value="{{ $position->id }}" @selected((string) old('position_id') === (string) $position->id)>{{ $position->name }}</option>
                        @endforeach
                    </select>
                    @error('position_id') <small>{{ $message }}</small> @enderror
                </label>

                <label data-client-type-field="individual">
                    <span>Gender</span>
                    <select name="gender" required>
                        <option value="">Select gender</option>
                        <option value="female" @selected(old('gender') === 'female')>Female</option>
                        <option value="male" @selected(old('gender') === 'male')>Male</option>
                    </select>
                    @error('gender') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Country of Origin</span>
                    <select name="country_id" required>
                        <option value="">Select country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('country_id') === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                    @error('country_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client In Charge</span>
                    <select name="client_in_charge_id">
                        <option value="">Select staff member</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected((string) old('client_in_charge_id') === (string) $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('client_in_charge_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Telephone</span>
                    <input type="text" name="phone" value="{{ old('phone') }}" required>
                    @error('phone') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="email" value="{{ old('email') }}" required>
                    @error('email') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>NIN / Passport No</span>
                    <input type="text" name="nin_passport_no" value="{{ old('nin_passport_no') }}">
                    @error('nin_passport_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date of Birth</span>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}">
                    @error('date_of_birth') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Occupation</span>
                    <input type="text" name="occupation" value="{{ old('occupation') }}">
                    @error('occupation') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>TIN</span>
                    <input type="text" name="tin" value="{{ old('tin') }}">
                    @error('tin') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Status</span>
                    <select name="status" required>
                        <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                        <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Address</span>
                    <textarea name="address" rows="3" required>{{ old('address') }}</textarea>
                    @error('address') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-section-title">Next of Kin</div>
            <div class="kfms-form-grid">
                <label>
                    <span>Relationship</span>
                    <select name="next_of_kin[relationship_type_id]">
                        <option value="">Select relationship</option>
                        @foreach ($relationships as $relationship)
                            <option value="{{ $relationship->id }}" @selected((string) old('next_of_kin.relationship_type_id') === (string) $relationship->id)>{{ $relationship->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Salutation</span>
                    <select name="next_of_kin[salutation_id]">
                        <option value="">Select salutation</option>
                        @foreach ($salutations as $salutation)
                            <option value="{{ $salutation->id }}" @selected((string) old('next_of_kin.salutation_id') === (string) $salutation->id)>{{ $salutation->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>First Name</span>
                    <input type="text" name="next_of_kin[first_name]" value="{{ old('next_of_kin.first_name') }}">
                </label>

                <label>
                    <span>Last Name</span>
                    <input type="text" name="next_of_kin[last_name]" value="{{ old('next_of_kin.last_name') }}">
                </label>

                <label>
                    <span>Middle Name</span>
                    <input type="text" name="next_of_kin[middle_name]" value="{{ old('next_of_kin.middle_name') }}">
                </label>

                <label>
                    <span>Gender</span>
                    <select name="next_of_kin[gender]">
                        <option value="">Select gender</option>
                        <option value="female" @selected(old('next_of_kin.gender') === 'female')>Female</option>
                        <option value="male" @selected(old('next_of_kin.gender') === 'male')>Male</option>
                    </select>
                </label>

                <label>
                    <span>Telephone</span>
                    <input type="text" name="next_of_kin[phone]" value="{{ old('next_of_kin.phone') }}">
                </label>

                <label>
                    <span>Email</span>
                    <input type="email" name="next_of_kin[email]" value="{{ old('next_of_kin.email') }}">
                </label>

                <label>
                    <span>NIN / Passport No</span>
                    <input type="text" name="next_of_kin[nin_passport_no]" value="{{ old('next_of_kin.nin_passport_no') }}">
                </label>

                <label>
                    <span>Date of Birth</span>
                    <input type="date" name="next_of_kin[date_of_birth]" value="{{ old('next_of_kin.date_of_birth') }}">
                </label>

                <label>
                    <span>Country of Origin</span>
                    <select name="next_of_kin[country_id]">
                        <option value="">Select country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}" @selected((string) old('next_of_kin.country_id') === (string) $country->id)>{{ $country->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="kfms-span-2">
                    <span>Contact Address</span>
                    <textarea name="next_of_kin[address]" rows="3">{{ old('next_of_kin.address') }}</textarea>
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('clients.index') }}">Cancel</a>
                <button type="submit">Save Client</button>
            </div>
        </form>
    </section>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const clientType = document.querySelector('[data-client-type-toggle]');
            const fields = document.querySelectorAll('[data-client-type-field]');

            function syncClientTypeFields() {
                const selectedType = clientType.value;

                fields.forEach(function (field) {
                    const isVisible = field.dataset.clientTypeField === selectedType;
                    field.hidden = ! isVisible;

                    field.querySelectorAll('input, select, textarea').forEach(function (input) {
                        input.disabled = ! isVisible;
                        if (input.name === 'gender' || input.name === 'first_name' || input.name === 'last_name') {
                            input.required = isVisible;
                        }
                    });
                });
            }

            if (clientType) {
                clientType.addEventListener('change', syncClientTypeFields);
                syncClientTypeFields();
            }
        });
    </script>
@endpush
