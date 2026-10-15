@extends('layouts.admin')

@section('title', isset($matter) ? 'Edit Matter' : 'Add Matter')
@section('page-title', isset($matter) ? 'Edit Matter' : 'Add Matter')

@section('content')
    @php
        $matter = $matter ?? null;
        $editing = $matter !== null;
        $matterNumber = $matterNumber ?? $matter?->reference_no;
        $oldPartners = array_map('strval', old('partner_ids', $editing ? $matter->assignments->where('assignment_role', 'partner')->pluck('user_id')->all() : []));
        $oldAssociates = array_map('strval', old('associate_ids', $editing ? $matter->assignments->where('assignment_role', 'associate')->pluck('user_id')->all() : []));
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $editing ? 'Edit Matter' : 'Create New Matter' }}</h2>
                <span>{{ $editing ? 'Update the matter, its next action, and responsible staff.' : 'Open a matter, classify it, and assign responsible staff.' }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('matters.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Matters
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ $editing ? route('matters.update', $matter) : route('matters.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="kfms-form-grid">
                <label>
                    <span>Matter Title</span>
                    <input type="text" name="title" value="{{ old('title', $matter?->title) }}" required>
                    @error('title') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Matter Number</span>
                    <input type="text" value="{{ $matterNumber }}" readonly disabled>
                    @error('reference_no') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Client</span>
                    <select name="client_id" required @disabled($editing)>
                        <option value="">Select client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('client_id', $matter?->client_id) === (string) $client->id)>{{ $client->display_name }}</option>
                        @endforeach
                    </select>
                    @if ($editing) <small>Client stays linked to this matter and its files.</small> @endif
                    @error('client_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Ultimate Client</span>
                    <select name="ultimate_client_id">
                        <option value="">Select ultimate client</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected((string) old('ultimate_client_id', $matter?->ultimate_client_id) === (string) $client->id)>{{ $client->display_name }}</option>
                        @endforeach
                    </select>
                    @error('ultimate_client_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Business Industry</span>
                    <select name="business_industry_id">
                        <option value="">Select industry</option>
                        @foreach ($businessIndustries as $industry)
                            <option value="{{ $industry->id }}" @selected((string) old('business_industry_id', $matter?->business_industry_id) === (string) $industry->id)>{{ $industry->name }}</option>
                        @endforeach
                    </select>
                    @error('business_industry_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Practice Area</span>
                    <select name="practice_area_id" required>
                        <option value="">Select practice area</option>
                        @foreach ($practiceAreas as $practiceArea)
                            <option value="{{ $practiceArea->id }}" @selected((string) old('practice_area_id', $matter?->practice_area_id) === (string) $practiceArea->id)>{{ $practiceArea->name }}</option>
                        @endforeach
                    </select>
                    @error('practice_area_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Instruction Type</span>
                    <select name="instruction_type_id">
                        <option value="">Select instruction type</option>
                        @foreach ($instructionTypes as $instructionType)
                            <option value="{{ $instructionType->id }}" @selected((string) old('instruction_type_id', $matter?->instruction_type_id) === (string) $instructionType->id)>{{ $instructionType->name }}</option>
                        @endforeach
                    </select>
                    @error('instruction_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Matter Category</span>
                    <select name="matter_category_id">
                        <option value="">Select category</option>
                        @foreach ($matterCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('matter_category_id', $matter?->matter_category_id) === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('matter_category_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Partners</span>
                    <select name="partner_ids[]" multiple required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array((string) $user->id, $oldPartners, true))>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('partner_ids') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Associates</span>
                    <select name="associate_ids[]" multiple>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(in_array((string) $user->id, $oldAssociates, true))>{{ $user->name }}</option>
                        @endforeach
                    </select>
                    @error('associate_ids') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date Opened</span>
                    <input type="date" name="opened_on" value="{{ old('opened_on', $matter?->opened_on?->toDateString() ?? now()->toDateString()) }}" required>
                    @error('opened_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date of Instruction</span>
                    <input type="date" name="instruction_date" value="{{ old('instruction_date', $matter?->instruction_date?->toDateString()) }}">
                    @error('instruction_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Lifecycle Status</span>
                    <select name="status" required>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('status', $matter?->status ?? 'inquiry') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('status') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date Closed</span>
                    <input type="date" name="closed_on" value="{{ old('closed_on', $matter?->closed_on?->toDateString()) }}">
                    @error('closed_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Shelf</span>
                    <select name="shelf_id">
                        <option value="">Select shelf</option>
                        @foreach ($shelves as $shelf)
                            <option value="{{ $shelf->id }}" @selected((string) old('shelf_id', $matter?->shelf_id) === (string) $shelf->id)>{{ $shelf->name }}</option>
                        @endforeach
                    </select>
                    @error('shelf_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Opposite Counsel</span>
                    <input type="text" name="opposite_counsel" value="{{ old('opposite_counsel', $matter?->opposite_counsel) }}">
                    @error('opposite_counsel') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Opposing Counsel Register</span>
                    <select name="opposing_counsel_id">
                        <option value="">Select counsel</option>
                        @foreach ($opposingCounsels as $counsel)
                            <option value="{{ $counsel->id }}" @selected((string) old('opposing_counsel_id', $matter?->opposing_counsel_id) === (string) $counsel->id)>{{ $counsel->name }}{{ $counsel->firm_name ? ' - '.$counsel->firm_name : '' }}</option>
                        @endforeach
                    </select>
                    @error('opposing_counsel_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Privacy Status</span>
                    <select name="privacy_status" required>
                        <option value="public" @selected(old('privacy_status', $matter?->privacy_status ?? 'public') === 'public')>Public</option>
                        <option value="private" @selected(old('privacy_status', $matter?->privacy_status) === 'private')>Private</option>
                    </select>
                    @error('privacy_status') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>About the File</span>
                    <textarea name="description" rows="5" required>{{ old('description', $matter?->description) }}</textarea>
                    @error('description') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Next Action</span>
                    <input type="text" name="next_action" value="{{ old('next_action', $matter?->next_action) }}" maxlength="255">
                    @error('next_action') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Next Action Due</span>
                    <input type="date" name="next_action_due" value="{{ old('next_action_due', $matter?->next_action_due?->toDateString()) }}">
                    @error('next_action_due') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('matters.index') }}">Cancel</a>
                <button type="submit">{{ $editing ? 'Update Matter' : 'Save Matter' }}</button>
            </div>
        </form>
    </section>
@endsection
