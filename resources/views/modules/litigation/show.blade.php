@extends('layouts.admin')

@section('title', 'Court Event')
@section('page-title', 'Court Event')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>{{ $event->matter?->reference_no }} — {{ $event->eventTypeLabel() }}</h2>
                    <span><span class="kfms-status kfms-status-{{ $event->status }}">{{ $event->statusLabel() }}</span></span>
                </div>
                <div class="kfms-toolbar-actions">
                    <a class="kfms-link-btn" href="{{ route('litigation.edit', $event) }}"><i class="mdi mdi-pencil"></i> Edit</a>
                    <a class="kfms-link-btn" href="{{ route('litigation.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
                </div>
            </div>

            <dl class="kfms-detail-list">
                <div><dt>Matter</dt><dd>{{ $event->matter ? $event->matter->reference_no.' — '.$event->matter->title : '-' }}</dd></div>
                <div><dt>Court</dt><dd>{{ $event->court?->name ?: $event->court_name ?: '-' }}</dd></div>
                <div><dt>Case Number</dt><dd>{{ $event->case_number ?: '-' }}</dd></div>
                <div><dt>Judicial Officer</dt><dd>{{ $event->judicial_officer ?: '-' }}</dd></div>
                <div><dt>Assigned Advocate</dt><dd>{{ $event->assignee?->name ?: '-' }}</dd></div>
                <div><dt>Starts At</dt><dd>{{ $event->starts_at?->format('d M Y, H:i') }}</dd></div>
                <div><dt>Ends At</dt><dd>{{ $event->ends_at?->format('d M Y, H:i') ?: '-' }}</dd></div>
                <div><dt>Next Step</dt><dd>{{ $event->next_step ?: '-' }}</dd></div>
                <div><dt>Next Step Due</dt><dd>{{ $event->next_step_due?->format('d M Y') ?: '-' }}</dd></div>
                <div><dt>Notes</dt><dd>{{ $event->notes ?: '-' }}</dd></div>
                <div><dt>Outcome</dt><dd>{{ $event->outcome ?: '-' }}</dd></div>
            </dl>

            @if ($event->attachments->isNotEmpty())
                <div class="kfms-panel-subheader"><h3>Court Documents</h3></div>
                <ul class="kfms-file-list">
                    @foreach ($event->attachments as $attachment)
                        <li><i class="mdi mdi-paperclip"></i> <a href="{{ route('attachments.download', $attachment) }}">{{ $attachment->original_name }}</a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <h2>Record Outcome</h2>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('litigation.outcome', $event) }}">
                @csrf
                @method('PATCH')

                <div class="kfms-form-grid">
                    <label class="kfms-span-2">
                        <span>Status</span>
                        <select name="status" required>
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $event->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="kfms-span-2">
                        <span>Outcome / Ruling</span>
                        <textarea name="outcome" rows="3">{{ old('outcome', $event->outcome) }}</textarea>
                    </label>

                    <label>
                        <span>Next Step</span>
                        <input type="text" name="next_step" value="{{ old('next_step', $event->next_step) }}" maxlength="255">
                    </label>

                    <label>
                        <span>Next Step Due</span>
                        <input type="date" name="next_step_due" value="{{ old('next_step_due', optional($event->next_step_due)->format('Y-m-d')) }}">
                    </label>
                </div>

                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-gavel"></i> Save Outcome</button>
                </div>
            </form>
        </section>
    </div>
@endsection
