@extends('layouts.admin')

@section('title', 'Messages')
@section('page-title', 'Messages')

@section('content')
    <section class="kfms-chat-topbar">
        <label class="kfms-chat-global-search">
            <i class="mdi mdi-magnify"></i>
            <input type="search" form="messages-filter-form" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search conversations, staff, departments...">
            <kbd>Ctrl + K</kbd>
        </label>
        <button class="kfms-btn" type="button" data-bs-toggle="modal" data-bs-target="#new-message-modal">
            <i class="mdi mdi-plus"></i>
            New Conversation
        </button>
    </section>

    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="kfms-alert kfms-alert-danger">
            <strong>Message not sent.</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="kfms-chat-shell">
        <aside class="kfms-chat-sidebar">
            <div class="kfms-chat-tabs">
                <a class="{{ empty($filters['audience_type']) && empty($filters['unread']) ? 'is-active' : '' }}" href="{{ route('messages.index') }}">All</a>
                <a class="{{ ($filters['audience_type'] ?? '') === 'users' ? 'is-active' : '' }}" href="{{ route('messages.index', ['audience_type' => 'users']) }}">People</a>
                <a class="{{ ($filters['audience_type'] ?? '') === 'department' ? 'is-active' : '' }}" href="{{ route('messages.index', ['audience_type' => 'department']) }}">Team</a>
                <a class="{{ ($filters['unread'] ?? null) ? 'is-active' : '' }}" href="{{ route('messages.index', ['unread' => 1]) }}">Unread</a>
            </div>

            <form id="messages-filter-form" class="kfms-chat-search-row" method="GET" action="{{ route('messages.index') }}">
                <label class="kfms-chat-search">
                    <i class="mdi mdi-magnify"></i>
                    <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search conversations...">
                </label>
                <button type="submit" title="Search">
                    <i class="mdi mdi-tune-variant"></i>
                </button>
            </form>

            <div class="kfms-chat-list">
                @forelse ($conversations as $conversation)
                    @php
                        $participant = $conversation->participants->firstWhere('user_id', auth()->id());
                        $isUnread = ! $participant?->last_read_at || ($conversation->last_message_at && $participant->last_read_at->lt($conversation->last_message_at));
                        $sender = $conversation->latestMessage?->sender;
                        $initials = str($sender?->name ?: $conversation->title)->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->join('');
                    @endphp
                    <a class="kfms-chat-item {{ $selectedConversation?->is($conversation) ? 'is-active' : '' }} {{ $isUnread ? 'is-unread' : '' }}" href="{{ route('messages.show', $conversation) }}">
                        <span class="kfms-chat-avatar">{{ $initials }}</span>
                        <span class="kfms-chat-preview">
                            <span>
                                <strong>{{ $conversation->title }}</strong>
                                <time>{{ $conversation->last_message_at?->diffForHumans() }}</time>
                            </span>
                            <em>
                                {{ $sender?->name ?: 'System' }}:
                                {{ filled($conversation->latestMessage?->body) ? str($conversation->latestMessage->body)->limit(58) : ($conversation->latestMessage?->attachments->isNotEmpty() ? 'Attachment' : 'No messages yet.') }}
                            </em>
                        </span>
                        @if ($isUnread)
                            <b>1</b>
                        @endif
                    </a>
                @empty
                    <div class="kfms-empty">No conversations yet.</div>
                @endforelse
            </div>

            <button class="kfms-chat-new-btn" type="button" data-bs-toggle="modal" data-bs-target="#new-message-modal">
                <i class="mdi mdi-plus"></i>
                New Conversation
            </button>
        </aside>

        <main class="kfms-chat-main">
            @if ($selectedConversation)
                <header class="kfms-chat-header">
                    <div class="kfms-chat-title">
                        <span class="kfms-chat-folder"><i class="mdi mdi-folder"></i></span>
                        <div>
                            <h2>{{ $selectedConversation->title }}</h2>
                            <p>
                                {{ $selectedConversation->audienceLabel() }}
                                @if ($selectedConversation->department)
                                    / {{ $selectedConversation->department->name }}
                                @endif
                                @if ($selectedConversation->branch)
                                    / {{ $selectedConversation->branch->name }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="kfms-chat-actions">
                        <form method="POST" action="{{ route('messages.read', $selectedConversation) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit" title="Mark read"><i class="mdi mdi-email-check-outline"></i></button>
                        </form>
                    </div>
                </header>

                <div class="kfms-chat-thread" data-realtime-conversation="{{ $selectedConversation->id }}" data-current-user="{{ auth()->id() }}">
                    <span class="kfms-chat-date">{{ optional($selectedConversation->messages->first()?->sent_at)->format('M d, Y') ?: 'Conversation' }}</span>
                    @foreach ($selectedConversation->messages as $message)
                        <article class="kfms-chat-message {{ $message->sender_id === auth()->id() ? 'is-mine' : '' }}" data-message-id="{{ $message->id }}">
                            <span class="kfms-chat-avatar">{{ str($message->sender?->name ?: 'S')->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->join('') }}</span>
                            <div>
                                <header>
                                    <strong>{{ $message->sender?->name ?: 'System' }}</strong>
                                    <time>{{ $message->sent_at?->format('H:i A') }}</time>
                                </header>
                                @if (filled($message->body))
                                    <p>{{ $message->body }}</p>
                                @endif
                                @if ($message->attachments->isNotEmpty())
                                    <div class="kfms-chat-attachments">
                                        @foreach ($message->attachments as $attachment)
                                            @php
                                                $mime = $attachment->mime_type ?? '';
                                                $url = route('attachments.view', $attachment);
                                            @endphp
                                            <div class="kfms-chat-attachment">
                                                @if (str_starts_with($mime, 'image/') && $url)
                                                    <img src="{{ $url }}" alt="{{ $attachment->original_name }}">
                                                @elseif (str_starts_with($mime, 'video/') && $url)
                                                    <video controls preload="metadata" src="{{ $url }}"></video>
                                                @elseif (str_starts_with($mime, 'audio/') && $url)
                                                    <audio controls src="{{ $url }}"></audio>
                                                @else
                                                    <i class="mdi mdi-file-document-outline"></i>
                                                @endif
                                                <span>
                                                    <strong>{{ $attachment->original_name }}</strong>
                                                    <small>{{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB</small>
                                                </span>
                                                <a href="{{ route('attachments.download', $attachment) }}" title="Download {{ $attachment->original_name }}">
                                                    <i class="mdi mdi-download"></i>
                                                </a>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                @if ($selectedConversation->allow_replies)
                    <form class="kfms-chat-composer" method="POST" action="{{ route('messages.reply', $selectedConversation) }}" enctype="multipart/form-data" data-chat-form>
                        @csrf
                        <textarea name="body" rows="2" placeholder="Type a message..."></textarea>
                        <input class="kfms-chat-file-input" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm,.mp3,.wav,.m4a,.ogg,.oga,audio/*,video/*,image/*">
                        <div class="kfms-chat-file-list" data-chat-files></div>
                        <div>
                            <span>
                                <button class="kfms-chat-icon-btn" type="button" data-chat-attach title="Attach document, image, video, or audio">
                                    <i class="mdi mdi-paperclip"></i>
                                </button>
                                <button class="kfms-chat-icon-btn" type="button" data-chat-record title="Record voice note">
                                    <i class="mdi mdi-microphone-outline"></i>
                                </button>
                                <i class="mdi mdi-at"></i>
                            </span>
                            <button type="submit" title="Send">
                                <i class="mdi mdi-send"></i>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="kfms-alert">Replies are disabled for this conversation.</div>
                @endif
            @else
                <div class="kfms-empty">Select or send a message to begin.</div>
            @endif
        </main>

        <aside class="kfms-chat-details">
            @if ($selectedConversation)
                <div class="kfms-chat-detail-tabs">
                    <strong>Details</strong>
                    <span>Files</span>
                    <span>Tasks</span>
                </div>

                <section>
                    <h3>Conversation Information</h3>
                    <dl>
                        <dt>Audience</dt>
                        <dd>{{ $selectedConversation->audienceLabel() }}</dd>
                        <dt>Created By</dt>
                        <dd>{{ $selectedConversation->creator?->name ?: '-' }}</dd>
                        <dt>Department</dt>
                        <dd>{{ $selectedConversation->department?->name ?: '-' }}</dd>
                        <dt>Branch</dt>
                        <dd>{{ $selectedConversation->branch?->name ?: '-' }}</dd>
                        <dt>Status</dt>
                        <dd><span class="kfms-status is-active">{{ $selectedConversation->allow_replies ? 'Open' : 'Read only' }}</span></dd>
                    </dl>
                </section>

                <section>
                    <h3>Participants ({{ $selectedConversation->participants->count() }})</h3>
                    <div class="kfms-chat-participants">
                        @foreach ($selectedConversation->participants->take(8) as $participant)
                            <span title="{{ $participant->user?->name }}">{{ str($participant->user?->name ?: 'U')->explode(' ')->map(fn ($part) => str($part)->substr(0, 1))->take(2)->join('') }}</span>
                        @endforeach
                    </div>
                </section>

                <section>
                    <h3>Recent Activity</h3>
                    <ul class="kfms-chat-activity">
                        @foreach ($selectedConversation->messages->take(-3) as $message)
                            <li>
                                <i class="mdi mdi-message-text-outline"></i>
                                <span>{{ $message->sender?->name ?: 'System' }} sent a message</span>
                                <time>{{ $message->sent_at?->diffForHumans() }}</time>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    <div class="modal fade kfms-modal" id="new-message-modal" tabindex="-1" aria-labelledby="new-message-modal-label" aria-hidden="true">
        <div class="modal-dialog kfms-setting-modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="new-message-modal-label">New Conversation</h5>
                        <span>Send to yourself, individuals, departments, branches, or the firm.</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form class="kfms-form" method="POST" action="{{ route('messages.store') }}" enctype="multipart/form-data" data-chat-form>
                    @csrf
                    <div class="modal-body">
                        <div class="kfms-form-grid kfms-message-form-grid">
                    <label class="kfms-span-2">
                        <span>Audience <span class="kfms-required">*</span></span>
                        <select name="audience_type" id="message-audience-type" required>
                            @foreach ($audienceTypes as $value => $label)
                                @continue($value === 'firm' && ! auth()->user()->can('messages.broadcast'))
                                <option value="{{ $value }}" @selected(old('audience_type', 'self') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('audience_type') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2" data-message-target="users">
                        <span>Individual User(s) <span class="kfms-required">*</span></span>
                        <div class="kfms-recipient-list">
                            @foreach ($users as $user)
                                <label class="kfms-recipient-option">
                                    <input type="checkbox" name="recipient_user_ids[]" value="{{ $user->id }}" @checked(in_array((string) $user->id, old('recipient_user_ids', []), true))>
                                    <span>
                                        <strong>{{ $user->name }}</strong>
                                        <small>
                                            {{ $user->email }}
                                            @if ($user->roles->isNotEmpty())
                                                &middot; {{ $user->roles->pluck('name')->join(', ') }}
                                            @endif
                                            @if ($user->branch)
                                                &middot; {{ $user->branch->name }}
                                            @endif
                                            @if ($user->department)
                                                &middot; {{ $user->department->name }}
                                            @endif
                                        </small>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('recipient_user_ids') <small>{{ $message }}</small> @enderror
                        @error('recipient_user_ids.*') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2" data-message-target="department">
                        <span>Department <span class="kfms-required">*</span></span>
                        <select name="department_id">
                            <option value="">Select department</option>
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) old('department_id') === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                        @error('department_id') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2" data-message-target="branch">
                        <span>Branch <span class="kfms-required">*</span></span>
                        <select name="branch_id">
                            <option value="">Select branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected((string) old('branch_id') === (string) $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Subject <span class="kfms-required">*</span></span>
                        <input type="text" name="title" value="{{ old('title') }}" required>
                        @error('title') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Message <span class="kfms-required">*</span></span>
                        <textarea name="body" rows="6">{{ old('body') }}</textarea>
                        @error('body') <small>{{ $message }}</small> @enderror
                    </label>

                    <label class="kfms-span-2">
                        <span>Attachments</span>
                        <input class="kfms-chat-file-input" type="file" name="attachments[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm,.mp3,.wav,.m4a,.ogg,.oga,audio/*,video/*,image/*">
                        <div class="kfms-chat-file-list" data-chat-files></div>
                        @error('attachments') <small>{{ $message }}</small> @enderror
                        @error('attachments.*') <small>{{ $message }}</small> @enderror
                    </label>

                    <div class="kfms-span-2 kfms-chat-recorder-row">
                        <button class="kfms-link-btn" type="button" data-chat-attach>
                            <i class="mdi mdi-paperclip"></i>
                            Attach File
                        </button>
                        <button class="kfms-link-btn" type="button" data-chat-record>
                            <i class="mdi mdi-microphone-outline"></i>
                            Record Voice Note
                        </button>
                    </div>

                    <label class="kfms-check-row kfms-span-2">
                        <input type="checkbox" name="allow_replies" value="1" @checked(old('allow_replies', '1'))>
                        <span>Allow replies</span>
                    </label>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="kfms-link-btn" type="button" data-bs-dismiss="modal">Cancel</button>
                        <button class="kfms-btn" type="submit">
                            <i class="mdi mdi-send"></i>
                            Send Message
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const audienceSelect = document.getElementById('message-audience-type');
            const targets = document.querySelectorAll('[data-message-target]');

            const syncAudienceFields = () => {
                const value = audienceSelect?.value;

                targets.forEach((target) => {
                    const isActive = target.dataset.messageTarget === value;
                    target.hidden = ! isActive;
                    target.querySelectorAll('select, input, textarea').forEach((field) => {
                        field.disabled = ! isActive;
                    });
                });
            };

            audienceSelect?.addEventListener('change', syncAudienceFields);
            syncAudienceFields();

            document.querySelectorAll('[data-chat-form]').forEach((form) => {
                const fileInput = form.querySelector('.kfms-chat-file-input');
                const fileList = form.querySelector('[data-chat-files]');
                const attachButtons = form.querySelectorAll('[data-chat-attach]');
                const recordButtons = form.querySelectorAll('[data-chat-record]');
                const submitButtons = form.querySelectorAll('[type="submit"]');
                let recorder;
                let chunks = [];
                let stream;
                let activeRecordButton;

                const setSubmitDisabled = (disabled) => {
                    submitButtons.forEach((button) => {
                        button.disabled = disabled;
                        button.classList.toggle('is-disabled', disabled);
                    });
                };

                const syncFileList = () => {
                    if (! fileList || ! fileInput) {
                        return;
                    }

                    const files = Array.from(fileInput.files || []);
                    fileList.innerHTML = files.map((file) => `<span><i class="mdi mdi-file-outline"></i>${file.name}</span>`).join('');
                };

                const addRecordedFile = (blob) => {
                    if (! fileInput) {
                        return;
                    }

                    const transfer = new DataTransfer();
                    Array.from(fileInput.files || []).forEach((file) => transfer.items.add(file));
                    transfer.items.add(new File([blob], `voice-note-${Date.now()}.webm`, { type: blob.type || 'audio/webm' }));
                    fileInput.files = transfer.files;
                    syncFileList();
                };

                const resetRecordButtons = () => {
                    recordButtons.forEach((button) => {
                        button.classList.remove('is-recording');
                        button.innerHTML = '<i class="mdi mdi-microphone-outline"></i>' + (button.dataset.recordLabel ? ` ${button.dataset.recordLabel}` : '');
                    });
                    activeRecordButton = null;
                    setSubmitDisabled(false);
                };

                attachButtons.forEach((button) => {
                    button.addEventListener('click', () => fileInput?.click());
                });

                fileInput?.addEventListener('change', syncFileList);

                recordButtons.forEach((button) => {
                    button.dataset.recordLabel = button.textContent.trim();

                    button.addEventListener('click', async () => {
                        if (recorder && recorder.state === 'recording') {
                            recorder.stop();
                            return;
                        }

                        if (! navigator.mediaDevices?.getUserMedia || ! window.MediaRecorder) {
                            alert('Voice recording is not supported in this browser.');
                            return;
                        }

                        try {
                            stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                            chunks = [];
                            recorder = new MediaRecorder(stream);
                            activeRecordButton = button;

                            recorder.addEventListener('dataavailable', (event) => {
                                if (event.data.size > 0) {
                                    chunks.push(event.data);
                                }
                            });

                            recorder.addEventListener('stop', () => {
                                if (chunks.length) {
                                    addRecordedFile(new Blob(chunks, { type: recorder.mimeType || 'audio/webm' }));
                                }

                                stream?.getTracks().forEach((track) => track.stop());
                                resetRecordButtons();
                            });

                            recorder.start();
                            setSubmitDisabled(true);
                            button.classList.add('is-recording');
                            button.innerHTML = '<i class="mdi mdi-stop-circle-outline"></i>' + (button.dataset.recordLabel ? ' Stop Recording' : '');
                        } catch (error) {
                            console.error(error);
                            stream?.getTracks().forEach((track) => track.stop());
                            resetRecordButtons();
                            alert('Could not start voice recording. Please allow microphone access and try again.');
                        }
                    });
                });

                form.addEventListener('submit', (event) => {
                    if (recorder && recorder.state === 'recording') {
                        event.preventDefault();
                        activeRecordButton?.focus();
                        alert('Please stop the recording first so it can be attached before sending.');
                    }
                });
            });
        });
    </script>
@endpush
