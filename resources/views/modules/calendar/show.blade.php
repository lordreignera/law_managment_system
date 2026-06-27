@extends('layouts.admin')

@section('title', 'Calendar Event')
@section('page-title', 'Calendar Event')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $event->title }}</h2>
                <span>
                    <span class="kfms-status kfms-status-{{ $event->status }}">{{ $event->statusLabel() }}</span>
                    {{ $event->typeLabel() }}
                </span>
            </div>
            <div class="kfms-toolbar-actions">
                <a class="kfms-link-btn" href="{{ route('calendar.edit', $event) }}"><i class="mdi mdi-pencil"></i> Edit</a>
                <a class="kfms-link-btn" href="{{ route('calendar.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
                <form method="POST" action="{{ route('calendar.destroy', $event) }}" onsubmit="return confirm('Remove this event from the calendar?');">
                    @csrf
                    @method('DELETE')
                    <button class="kfms-link-btn kfms-danger" type="submit"><i class="mdi mdi-delete"></i> Delete</button>
                </form>
            </div>
        </div>

        <dl class="kfms-detail-list">
            <div><dt>When</dt><dd>{{ $event->all_day ? $event->starts_at?->format('d M Y').' (All day)' : $event->starts_at?->format('d M Y, H:i') }}</dd></div>
            <div><dt>Ends</dt><dd>{{ $event->ends_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
            <div><dt>Branch</dt><dd>{{ $event->branch?->name ?: 'Firm-wide' }}</dd></div>
            <div><dt>Related Matter</dt><dd>{{ $event->matter ? $event->matter->reference_no.' — '.$event->matter->title : '-' }}</dd></div>
            <div><dt>Assigned To</dt><dd>{{ $event->assignee?->name ?: 'Unassigned' }}</dd></div>
            <div><dt>Location</dt><dd>{{ $event->location ?: '-' }}</dd></div>
            <div><dt>Created By</dt><dd>{{ $event->creator?->name ?: '-' }}</dd></div>
            <div><dt>Notes</dt><dd>{{ $event->notes ?: '-' }}</dd></div>
        </dl>
    </section>
@endsection
