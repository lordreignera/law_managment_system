@extends('layouts.client')

@section('title', $matter->title)

@section('content')
    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->title }}</h2>
                <span>{{ $matter->reference_no }} · {{ $matter->statusLabel() }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('client.matters.index') }}"><i class="mdi mdi-arrow-left"></i> Back</a>
        </div>

        <div class="kfms-client-matter-grid">
            <div>
                <h3>Matter Information</h3>
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Practice Area</dt><dd>{{ $matter->practiceArea?->name ?: '-' }}</dd></div>
                    <div><dt>Opened On</dt><dd>{{ $matter->opened_on?->format('d M Y') ?: '-' }}</dd></div>
                    <div><dt>Assigned Advocate</dt><dd>{{ $matter->assignments->firstWhere('is_lead', true)?->user?->name ?: $matter->assignments->first()?->user?->name ?: '-' }}</dd></div>
                    <div><dt>Public Summary</dt><dd>{{ $matter->description ?: '-' }}</dd></div>
                </dl>
            </div>
            <div>
                <h3>Upcoming Dates</h3>
                <div class="kfms-client-list">
                    @forelse ($matter->courtEvents->sortBy('starts_at')->take(5) as $event)
                        <span>
                            <strong>{{ $event->eventTypeLabel() }}</strong>
                            <em>{{ $event->starts_at?->format('d M Y, H:i') }}</em>
                        </span>
                    @empty
                        <div class="kfms-empty-state">No dates shared yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel" id="messages">
            <div class="kfms-panel-header">
                <div>
                    <h2>Shared Documents</h2>
                    <span>Only documents marked visible to client appear here</span>
                </div>
            </div>
            <div class="kfms-client-list">
                @forelse ($documents as $document)
                    <a href="{{ route('client.documents.download', $document) }}">
                        <strong>{{ $document->title ?: $document->original_name }}</strong>
                        <span>{{ $document->category ?: 'Document' }}</span>
                    </a>
                @empty
                    <div class="kfms-empty-state">No shared documents yet.</div>
                @endforelse
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Letters & Opinions</h2>
                    <span>Official letters shared by the firm</span>
                </div>
            </div>
            <div class="kfms-client-list">
                @forelse ($letters as $letter)
                    <a href="{{ route('client.letters.download', $letter) }}">
                        <strong>{{ $letter->subject }}</strong>
                        <span>{{ $letter->reference_no }} - {{ $letter->letter_date?->format('d M Y') }}</span>
                    </a>
                @empty
                    <div class="kfms-empty-state">No letters shared yet.</div>
                @endforelse
            </div>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Message Advocate</h2>
                    <span>Messages stay connected to this matter</span>
                </div>
            </div>
            <div class="kfms-client-thread" data-realtime-conversation="{{ $conversation->id }}" data-current-user="{{ auth()->id() }}">
                @forelse ($conversation->messages as $chatMessage)
                    <article @class(['is-mine' => $chatMessage->sender_id === auth()->id()]) data-message-id="{{ $chatMessage->id }}">
                        <strong>{{ $chatMessage->sender?->name ?: 'User' }}</strong>
                        <p>{{ $chatMessage->body }}</p>
                        <time>{{ $chatMessage->sent_at?->format('d M Y, H:i') }}</time>
                    </article>
                @empty
                    <div class="kfms-empty-state">Start the conversation with your assigned advocate.</div>
                @endforelse
            </div>
            <form class="kfms-form" method="POST" action="{{ route('client.matters.messages.store', $matter) }}">
                @csrf
                <label>
                    <span>Message</span>
                    <textarea name="body" rows="4" required placeholder="Type your message to the assigned advocate">{{ old('body') }}</textarea>
                    @error('body') <small>{{ $message }}</small> @enderror
                </label>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit"><i class="mdi mdi-send"></i> Send Message</button>
                </div>
            </form>
        </section>
    </div>
@endsection
