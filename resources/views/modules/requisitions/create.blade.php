@extends('layouts.admin')

@section('title', 'New Requisition')
@section('page-title', 'New Requisition')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Raise Requisition</h2>
                <span>Submit a funding or purchase requisition for approval.</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('requisitions.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Requisitions
            </a>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('requisitions.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="kfms-form-grid">
                <label>
                    <span>Reference Number</span>
                    <input type="text" value="{{ $referenceNumber }}" readonly disabled>
                </label>

                <label>
                    <span>Category</span>
                    <select name="requisition_category_id">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('requisition_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('requisition_category_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Related Matter (optional)</span>
                    <select name="matter_id">
                        <option value="">No matter</option>
                        @foreach ($matters as $matter)
                            <option value="{{ $matter->id }}" @selected(old('matter_id') == $matter->id)>{{ $matter->reference_no }} — {{ \Illuminate\Support\Str::limit($matter->title, 40) }}</option>
                        @endforeach
                    </select>
                    @error('matter_id') <small>{{ $message }}</small> @enderror
                </label>

                <label>
                    <span>Amount</span>
                    <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount') }}" required>
                    @error('amount') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Purpose</span>
                    <input type="text" name="purpose" value="{{ old('purpose') }}" maxlength="255" required>
                    @error('purpose') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Notes</span>
                    <textarea name="notes" rows="3" placeholder="Optional notes for the approver">{{ old('notes') }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Supporting Document (optional)</span>
                    <input type="file" name="attachment">
                    @error('attachment') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button class="kfms-btn" type="submit">
                    <i class="mdi mdi-send"></i>
                    Submit Requisition
                </button>
            </div>
        </form>
    </section>
@endsection
