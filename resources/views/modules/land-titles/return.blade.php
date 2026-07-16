@extends('layouts.admin')

@section('title', 'Return Security')
@section('page-title', 'Securities')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Return Security</h2>
                <span>{{ $title->reference_no }} - {{ $title->borrower_name }}</span>
            </div>
            <div class="kfms-row-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.show', $title) }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back to Details
                </a>
                <a class="kfms-link-btn" href="{{ route('land-titles.index') }}">
                    <i class="mdi mdi-format-list-bulleted"></i>
                    Register
                </a>
            </div>
        </div>

        <div class="kfms-detail-grid">
            <div>
                <span>Financial Institution</span>
                <strong>{{ $title->bank?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Source Branch</span>
                <strong>{{ $title->bankBranch?->name ?: $title->received_from ?: '-' }}</strong>
            </div>
            <div>
                <span>MZO / Zonal Office</span>
                <strong>{{ $title->zonalOffice?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Handled By</span>
                <strong>{{ $title->handler?->name ?: '-' }}</strong>
            </div>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('land-titles.return', $title) }}" enctype="multipart/form-data">
            @csrf
            @method('PATCH')
            <div class="kfms-form-grid">
                <label>
                    <span>Returned To <span class="kfms-required">*</span></span>
                    <input type="text" name="returned_to" value="{{ old('returned_to', $title->bankBranch?->name ?: $title->bank?->name) }}" required>
                    @error('returned_to') <small>{{ $message }}</small> @enderror
                </label>
                <label>
                    <span>Date &amp; Time Returned <span class="kfms-required">*</span></span>
                    <input type="datetime-local" name="returned_at" value="{{ old('returned_at', now()->format('Y-m-d\TH:i')) }}" required>
                    @error('returned_at') <small>{{ $message }}</small> @enderror
                </label>
                <label class="kfms-span-2">
                    <span>Return Document</span>
                    <input type="file" name="documents[]" multiple accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx">
                    @error('documents') <small>{{ $message }}</small> @enderror
                    @error('documents.*') <small>{{ $message }}</small> @enderror
                </label>
                <label class="kfms-span-2">
                    <span>Return Notes</span>
                    <textarea name="notes" rows="3">{{ old('notes', $title->notes) }}</textarea>
                    @error('notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>
            <div class="kfms-form-actions">
                <a class="kfms-link-btn" href="{{ route('land-titles.show', $title) }}">Cancel</a>
                <button type="submit">
                    <i class="mdi mdi-keyboard-return"></i>
                    Mark as Returned
                </button>
            </div>
        </form>
    </section>
@endsection
