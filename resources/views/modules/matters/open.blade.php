@extends('layouts.admin')

@section('title', 'Open Matter')
@section('page-title', 'Open Matter')

@section('content')
    @php
        $oldPartners = array_map('strval', old('partner_ids', []));
        $oldAssociates = array_map('strval', old('associate_ids', []));
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Open Matter</h2>
                <span>{{ $client->client_no }} - {{ $client->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Client
            </a>
        </div>

        <p class="kfms-muted-text">Open another matter for {{ $client->display_name }} and deliberately select which existing files belong to it.</p>

        <form class="kfms-form" method="POST" action="{{ route('clients.matters.store', $client) }}">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Matter Number</span>
                    <input type="text" value="{{ $matterNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Matter Title</span>
                    <input type="text" name="title" value="{{ old('title', $suggestedTitle ?: $client->display_name) }}" required>
                    @error('title') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Practice Area</span>
                    <select name="practice_area_id" required>
                        <option value="">Select practice area</option>
                        @foreach ($practiceAreas as $practiceArea)
                            <option value="{{ $practiceArea->id }}" @selected((string) old('practice_area_id') === (string) $practiceArea->id)>{{ $practiceArea->name }}</option>
                        @endforeach
                    </select>
                    @error('practice_area_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Business Industry</span>
                    <select name="business_industry_id">
                        <option value="">Select industry</option>
                        @foreach ($businessIndustries as $industry)
                            <option value="{{ $industry->id }}" @selected((string) old('business_industry_id') === (string) $industry->id)>{{ $industry->name }}</option>
                        @endforeach
                    </select>
                    @error('business_industry_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Matter Category</span>
                    <select name="matter_category_id">
                        <option value="">Select category</option>
                        @foreach ($matterCategories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('matter_category_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('matter_category_id') <small>{{ $message }}</small> @enderror
                </label>

                @include('modules.matters.partials.assignment-picker', [
                    'name' => 'partner_ids[]',
                    'field' => 'partner_ids',
                    'label' => 'Partners',
                    'placeholder' => 'Select partners',
                    'users' => $users,
                    'selected' => $oldPartners,
                ])

                @include('modules.matters.partials.assignment-picker', [
                    'name' => 'associate_ids[]',
                    'field' => 'associate_ids',
                    'label' => 'Associates',
                    'placeholder' => 'Select associates',
                    'users' => $users,
                    'selected' => $oldAssociates,
                ])

                <label>
                    <span>Date Opened</span>
                    <input type="date" name="opened_on" value="{{ old('opened_on', now()->toDateString()) }}" required>
                    @error('opened_on') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Instruction Type</span>
                    <select name="instruction_type_id">
                        <option value="">Select instruction type</option>
                        @foreach ($instructionTypes as $instructionType)
                            <option value="{{ $instructionType->id }}" @selected((string) old('instruction_type_id') === (string) $instructionType->id)>{{ $instructionType->name }}</option>
                        @endforeach
                    </select>
                    @error('instruction_type_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Date of Instruction</span>
                    <input type="date" name="instruction_date" value="{{ old('instruction_date') }}">
                    @error('instruction_date') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Shelf</span>
                    <select name="shelf_id">
                        <option value="">Select shelf</option>
                        @foreach ($shelves as $shelf)
                            <option value="{{ $shelf->id }}" @selected((string) old('shelf_id') === (string) $shelf->id)>{{ $shelf->name }}</option>
                        @endforeach
                    </select>
                    @error('shelf_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Opposite Counsel</span>
                    <input type="text" name="opposite_counsel" value="{{ old('opposite_counsel') }}">
                    @error('opposite_counsel') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Opposing Counsel Register</span>
                    <select name="opposing_counsel_id">
                        <option value="">Select counsel</option>
                        @foreach ($opposingCounsels as $counsel)
                            <option value="{{ $counsel->id }}" @selected((string) old('opposing_counsel_id') === (string) $counsel->id)>{{ $counsel->name }}{{ $counsel->firm_name ? ' - '.$counsel->firm_name : '' }}</option>
                        @endforeach
                    </select>
                    @error('opposing_counsel_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Privacy Status</span>
                    <select name="privacy_status" required>
                        <option value="public" @selected(old('privacy_status', 'public') === 'public')>Public</option>
                        <option value="private" @selected(old('privacy_status') === 'private')>Private</option>
                    </select>
                    @error('privacy_status') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Client-Visible Summary</span>
                    <textarea name="client_summary" rows="4" placeholder="Safe progress summary for the client portal">{{ old('client_summary') }}</textarea>
                    @error('client_summary') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Internal Instructions / File Summary</span>
                    <textarea name="description" rows="5" required>{{ old('description') }}</textarea>
                    @error('description') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Files to Link</span>
                    <select name="file_ids[]" multiple>
                        @foreach (($availableFiles ?? collect()) as $availableFile)
                            <option value="{{ $availableFile->id }}" @selected(in_array((string) $availableFile->id, array_map('strval', old('file_ids', [])), true))>{{ $availableFile->file_number }} - {{ $availableFile->file_name }}</option>
                        @endforeach
                    </select>
                    <small>Only the files selected here will be linked to this matter.</small>
                    @error('file_ids') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Next Action</span>
                    <input type="text" name="next_action" value="{{ old('next_action') }}" maxlength="255">
                    @error('next_action') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Next Action Due</span>
                    <input type="date" name="next_action_due" value="{{ old('next_action_due') }}">
                    @error('next_action_due') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('clients.show', $client) }}">Cancel</a>
                <button type="submit">Open Matter</button>
            </div>
        </form>
    </section>
@endsection
