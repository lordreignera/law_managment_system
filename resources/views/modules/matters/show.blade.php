@extends('layouts.admin')

@section('title', 'Review Matter')
@section('page-title', 'Review Matter')

@section('content')
    @php
        $engagement = $matter->engagement;
    @endphp

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }}</h2>
                <span>{{ $matter->title }} - {{ $matter->statusLabel() }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('matters.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Matters
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Client</span>
                <strong>{{ $matter->client?->display_name ?: '-' }}</strong>
            </div>
            <div>
                <span>Practice Area</span>
                <strong>{{ $matter->practiceArea?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Status</span>
                <strong>{{ $matter->statusLabel() }}</strong>
            </div>
            <div>
                <span>Engagement</span>
                <strong>{{ str($engagement?->status ?? 'pending')->headline() }}</strong>
            </div>
            <div>
                <span>Opened On</span>
                <strong>{{ $matter->opened_on?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Engagement Type</span>
                <strong>{{ $engagement?->engagementType?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Client Accepted</span>
                <strong>{{ $engagement?->client_accepted_on?->format('d M Y') ?: '-' }}</strong>
            </div>
            <div>
                <span>Retainer</span>
                <strong>{{ $engagement?->retainer_required ? number_format($engagement->retainer_amount ?? 0, 2) : 'Not required' }}</strong>
            </div>
            <div>
                <span>Engagement No</span>
                <strong>{{ $engagement?->engagement_no ?: '-' }}</strong>
            </div>
        </div>

        <div class="kfms-section-heading">
            <h3>Description</h3>
        </div>
        <p class="kfms-muted-text">{{ $matter->description ?: 'No description recorded.' }}</p>

        @if ($matter->status === 'engagement_pending')
            <div class="kfms-section-heading">
                <h3>Engagement Review</h3>
                <span>Complete this before the matter becomes open.</span>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('matters.engagement.update', $matter) }}">
                @csrf
                @method('PATCH')

                <div class="kfms-form-grid">
                    <label>
                        <span>Engagement Type</span>
                        <select name="engagement_type_id">
                            <option value="">Select engagement type</option>
                            @foreach ($engagementTypes as $engagementType)
                                <option value="{{ $engagementType->id }}" @selected((string) old('engagement_type_id', $engagement?->engagement_type_id) === (string) $engagementType->id)>{{ $engagementType->name }}</option>
                            @endforeach
                        </select>
                        @error('engagement_type_id') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Engagement Letter Sent</span>
                        <input type="date" name="engagement_letter_sent_on" value="{{ old('engagement_letter_sent_on', $engagement?->engagement_letter_sent_on?->toDateString()) }}" required>
                        @error('engagement_letter_sent_on') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Fee Agreement Sent</span>
                        <input type="date" name="fee_agreement_sent_on" value="{{ old('fee_agreement_sent_on', $engagement?->fee_agreement_sent_on?->toDateString()) }}" required>
                        @error('fee_agreement_sent_on') <small>{{ $message }}</small> @enderror
                    </label>

                    <label>
                        <span>Client Accepted On</span>
                        <input type="date" name="client_accepted_on" value="{{ old('client_accepted_on', $engagement?->client_accepted_on?->toDateString()) }}" required>
                        @error('client_accepted_on') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-check-row">
                        <input type="checkbox" name="retainer_required" value="1" @checked(old('retainer_required', $engagement?->retainer_required))>
                        <span>Retainer Required</span>
                    </label>

                    <label>
                        <span>Retainer Amount</span>
                        <input type="number" step="0.01" min="0" name="retainer_amount" value="{{ old('retainer_amount', $engagement?->retainer_amount) }}">
                        @error('retainer_amount') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Engagement Notes</span>
                        <textarea name="engagement_notes" rows="4">{{ old('engagement_notes', $engagement?->notes) }}</textarea>
                        @error('engagement_notes') <small>{{ $message }}</small> @enderror
                    </label>
                </div>

                <div class="kfms-form-actions">
                    <button type="submit">Accept Engagement and Open Matter</button>
                </div>
            </form>
        @else
            <div class="kfms-section-heading">
                <h3>Engagement Details</h3>
            </div>
            <p class="kfms-muted-text">{{ $engagement?->notes ?: 'No engagement notes recorded.' }}</p>
        @endif
    </section>
@endsection
