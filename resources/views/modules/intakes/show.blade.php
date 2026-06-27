@extends('layouts.admin')

@section('title', 'Review Intake')
@section('page-title', 'Review Intake')

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $intake->intake_no }}</h2>
                <span>{{ $intake->client_name }} - {{ $intake->statusLabel() }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('intakes.index') }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Intakes
            </a>
        </div>

        @if (session('status'))
            <div class="kfms-alert">{{ session('status') }}</div>
        @endif

        <div class="kfms-detail-grid">
            <div>
                <span>Client Type</span>
                <strong>{{ ucfirst($intake->client_type) }}</strong>
            </div>
            <div>
                <span>Legal Issue</span>
                <strong>{{ $intake->legal_issue }}</strong>
            </div>
            <div>
                <span>Practice Area</span>
                <strong>{{ $intake->practiceArea?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Preferred Lawyer</span>
                <strong>{{ $intake->preferredLawyer?->name ?: '-' }}</strong>
            </div>
            <div>
                <span>Phone</span>
                <strong>{{ $intake->phone ?: '-' }}</strong>
            </div>
            <div>
                <span>Email</span>
                <strong>{{ $intake->email ?: '-' }}</strong>
            </div>
            <div>
                <span>Conflict Status</span>
                <strong>{{ $intake->conflictStatusLabel() }}</strong>
            </div>
            <div>
                <span>Converted Matter</span>
                <strong>{{ $intake->convertedMatter?->reference_no ?: '-' }}</strong>
            </div>
        </div>

        <div class="kfms-section-heading">
            <h3>Issue Summary</h3>
        </div>
        <p class="kfms-muted-text">{{ $intake->summary ?: 'No summary recorded.' }}</p>

        <div class="kfms-section-heading">
            <h3>Conflict Parties</h3>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Relationship</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($intake->conflictParties as $party)
                        <tr>
                            <td>{{ $party->name }}</td>
                            <td>{{ $party->relationship ?: '-' }}</td>
                            <td>{{ $party->notes ?: '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="kfms-empty">No conflict parties recorded.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="kfms-section-heading">
            <h3>Conflict Review</h3>
            <span>Clear, reject, or request more information before matter opening.</span>
        </div>

        <form class="kfms-form" method="POST" action="{{ route('intakes.conflict-review', $intake) }}">
            @csrf
            @method('PATCH')

            <div class="kfms-form-grid">
                <label>
                    <span>Decision</span>
                    <select name="conflict_status" required>
                        @foreach ($conflictStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('conflict_status', $intake->conflict_status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('conflict_status') <small>{{ $message }}</small> @enderror
                </label>

                <label class="kfms-span-2">
                    <span>Review Notes</span>
                    <textarea name="conflict_notes" rows="4" required>{{ old('conflict_notes', $intake->conflict_notes) }}</textarea>
                    @error('conflict_notes') <small>{{ $message }}</small> @enderror
                </label>
            </div>

            <div class="kfms-form-actions">
                <button type="submit">Save Review</button>
            </div>
        </form>

        @if ($intake->conflict_status === 'cleared' && ! $intake->converted_matter_id)
            <form class="kfms-form-actions" method="POST" action="{{ route('intakes.convert-matter', $intake) }}">
                @csrf
                <button type="submit">Convert to Matter</button>
            </form>
        @endif
    </section>
@endsection
