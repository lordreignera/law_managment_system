@extends('layouts.client')

@section('title', 'Messages')

@section('content')
    <section class="kfms-client-chat">
        <aside class="kfms-client-chat-list">
            <div class="kfms-panel-header">
                <div>
                    <h2>Messages</h2>
                    <span>{{ $conversations->total() }} conversation(s)</span>
                </div>
            </div>

            <form class="kfms-client-chat-search" method="GET" action="{{ route('client.messages.index') }}">
                <label class="kfms-search-box">
                    <i class="mdi mdi-magnify"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search matter or message">
                </label>
            </form>

            <div class="kfms-client-conversations">
                @forelse ($conversations as $conversation)
                    @php
                        $participant = $conversation->participants->firstWhere('user_id', auth()->id());
                        $latestMessage = $conversation->latestMessage;
                        $isUnread = $latestMessage && (! $participant?->last_read_at || $latestMessage->sent_at?->gt($participant->last_read_at));
                    @endphp
                    <a href="{{ route('client.messages.show', $conversation) }}" @class(['is-active' => $selectedConversation?->id === $conversation->id])>
                        <span class="kfms-client-conversation-icon"><i class="mdi mdi-briefcase-outline"></i></span>
                        <span>
                            <strong>{{ $conversation->matter?->title ?: $conversation->title }}</strong>
                            <em>{{ $latestMessage?->body ?: 'No messages yet.' }}</em>
                        </span>
                        @if ($isUnread)
                            <b>New</b>
                        @endif
                    </a>
                @empty
                    <div class="kfms-empty-state">No matter conversations yet. Open a matter and send the first message.</div>
                @endforelse
            </div>

            {{ $conversations->links() }}
        </aside>

        <section class="kfms-client-chat-panel">
            @if ($selectedConversation)
                <div class="kfms-client-chat-title">
                    <div>
                        <span>{{ $selectedConversation->matter?->reference_no ?: 'Matter conversation' }}</span>
                        <h2>{{ $selectedConversation->matter?->title ?: $selectedConversation->title }}</h2>
                    </div>
                    @if ($selectedConversation->matter)
                        <a class="kfms-link-btn" href="{{ route('client.matters.show', $selectedConversation->matter) }}">
                            <i class="mdi mdi-open-in-new"></i> Open Matter
                        </a>
                    @endif
                </div>

                <div class="kfms-client-thread kfms-client-thread-tall" data-realtime-conversation="{{ $selectedConversation->id }}" data-current-user="{{ auth()->id() }}">
                    @forelse ($selectedConversation->messages as $chatMessage)
                        <article @class(['is-mine' => $chatMessage->sender_id === auth()->id()]) data-message-id="{{ $chatMessage->id }}">
                            <strong>{{ $chatMessage->sender?->name ?: 'User' }}</strong>
                            <p>{{ $chatMessage->body }}</p>
                            <time>{{ $chatMessage->sent_at?->format('d M Y, H:i') }}</time>
                        </article>
                    @empty
                        <div class="kfms-empty-state">Start the conversation with your assigned advocate.</div>
                    @endforelse
                </div>

                @if ($selectedConversation->matter)
                    <form class="kfms-client-message-form" method="POST" action="{{ route('client.matters.messages.store', $selectedConversation->matter) }}">
                        @csrf
                        <label>
                            <span>Message</span>
                            <textarea name="body" rows="3" required placeholder="Type your message">{{ old('body') }}</textarea>
                            @error('body') <small>{{ $message }}</small> @enderror
                        </label>
                        <button class="kfms-btn" type="submit"><i class="mdi mdi-send"></i> Send</button>
                    </form>
                @endif
            @else
                <div class="kfms-client-chat-empty">
                    <i class="mdi mdi-message-text-outline"></i>
                    <h2>No conversation selected</h2>
                    <p>Open one of your matters and send a message to begin a matter conversation.</p>
                    <a class="kfms-btn" href="{{ route('client.matters.index') }}"><i class="mdi mdi-briefcase-search-outline"></i> View My Matters</a>
                </div>
            @endif
        </section>

        <aside class="kfms-client-chat-context">
            <div class="kfms-panel-header">
                <div>
                    <h2>Matter Context</h2>
                    <span>Quick details</span>
                </div>
            </div>
            @if ($selectedConversation?->matter)
                @php($matter = $selectedConversation->matter)
                <dl class="kfms-detail-list kfms-detail-list-bordered">
                    <div><dt>Reference</dt><dd>{{ $matter->reference_no }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $matter->statusLabel() }}</dd></div>
                    <div><dt>Practice Area</dt><dd>{{ $matter->practiceArea?->name ?: '-' }}</dd></div>
                    <div><dt>Lead Advocate</dt><dd>{{ $matter->assignments->firstWhere('is_lead', true)?->user?->name ?: $matter->assignments->first()?->user?->name ?: '-' }}</dd></div>
                </dl>
            @else
                <div class="kfms-empty-state">Matter details appear after selecting a conversation.</div>
            @endif
        </aside>
    </section>
@endsection
