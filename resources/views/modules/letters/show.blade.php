@extends('layouts.admin')

@section('title', $letter->reference_no)
@section('page-title', 'Letter Details')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    @if ($errors->any())
        <div class="kfms-alert kfms-alert-danger">{{ $errors->first() }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $letter->reference_no }}</h2>
                <span>{{ $letter->typeLabel() }} - {{ $letter->statusLabel() }}</span>
            </div>
            <div class="kfms-toolbar-actions">
                @can('letters.pdf')
                    <a class="kfms-link-btn kfms-link-btn-success" href="{{ route('letters.pdf', $letter) }}">
                        <i class="mdi mdi-file-pdf-box"></i>
                        Download PDF
                    </a>
                @endcan
                @can('letters.edit')
                    @if (in_array($letter->status, ['draft', 'pending_review', 'approved'], true))
                        <a class="kfms-link-btn" href="{{ route('letters.edit', $letter) }}">
                            <i class="mdi mdi-pencil-outline"></i>
                            Edit
                        </a>
                    @endif
                @endcan
                <a class="kfms-link-btn" href="{{ route('letters.index') }}">
                    <i class="mdi mdi-arrow-left"></i>
                    Back
                </a>
            </div>
        </div>

        <div class="kfms-detail-grid">
            <div><span>Client</span><strong>{{ $letter->client?->display_name ?: $letter->matter?->client?->display_name ?: '-' }}</strong></div>
            <div><span>Matter</span><strong>{{ $letter->matter?->reference_no ?: '-' }}</strong></div>
            <div><span>Recipient</span><strong>{{ $letter->recipient_name }}</strong></div>
            <div><span>Created By</span><strong>{{ $letter->creator?->name ?: '-' }}</strong></div>
            <div><span>Signed By</span><strong>{{ $letter->signer?->name ?: '-' }}</strong></div>
            <div><span>Sent</span><strong>{{ $letter->sent_at?->format('d M Y, H:i') ?: '-' }}</strong></div>
            <div><span>Client Portal</span><strong>{{ $letter->client_visible ? 'Visible' : 'Hidden' }}</strong></div>
            <div><span>Received Copy</span><strong>{{ $letter->attachments->where('category', 'received_copy')->count() ? 'Uploaded' : 'Not uploaded' }}</strong></div>
        </div>

        @include('modules.letters.partials.workflow', ['letter' => $letter])
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Branded Preview</h2>
                    <span>Company logo, letter content, and signature</span>
                </div>
            </div>

            @include('modules.letters.partials.preview', ['letter' => $letter])
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Workflow Actions</h2>
                    <span>Review, send, share, and close the document loop</span>
                </div>
            </div>

            <div class="kfms-action-stack">
                @can('letters.submit')
                    @if ($letter->status === 'draft')
                        <form method="POST" action="{{ route('letters.submit', $letter) }}">
                            @csrf
                            @method('PATCH')
                            <button class="kfms-btn" type="submit"><i class="mdi mdi-send-check-outline"></i> Submit for Review</button>
                        </form>
                    @endif
                @endcan

                @can('letters.approve')
                    @if ($letter->status === 'pending_review')
                        <form class="kfms-form" method="POST" action="{{ route('letters.approve', $letter) }}">
                            @csrf
                            @method('PATCH')
                            <label>
                                <span>Approval Notes</span>
                                <textarea name="approval_notes" rows="3"></textarea>
                            </label>
                            <button class="kfms-btn" type="submit"><i class="mdi mdi-check-decagram"></i> Approve Letter</button>
                        </form>
                    @endif
                @endcan

                @can('letters.mark-sent')
                    @if (in_array($letter->status, ['approved', 'sent'], true))
                        <form class="kfms-form" method="POST" action="{{ route('letters.mark-sent', $letter) }}">
                            @csrf
                            @method('PATCH')
                            <div class="kfms-form-grid">
                                <label>
                                    <span>Sent Date</span>
                                    <input type="datetime-local" name="sent_at" value="{{ now()->format('Y-m-d\TH:i') }}">
                                </label>
                                <label class="kfms-checkbox-line">
                                    <input type="checkbox" name="client_visible" value="1" @checked($letter->client_visible)>
                                    <span>Make visible in client portal</span>
                                </label>
                                <label class="kfms-span-2">
                                    <span>Sending Notes</span>
                                    <textarea name="sent_notes" rows="3">{{ $letter->sent_notes }}</textarea>
                                </label>
                            </div>
                            <button class="kfms-btn" type="submit"><i class="mdi mdi-email-send-outline"></i> Mark as Sent</button>
                        </form>
                    @endif
                @endcan

                @can('letters.received-copy')
                    @if (in_array($letter->status, ['sent', 'received'], true))
                        <form class="kfms-form" method="POST" action="{{ route('letters.received-copy', $letter) }}" enctype="multipart/form-data">
                            @csrf
                            @method('PATCH')
                            <div class="kfms-form-grid">
                                <label>
                                    <span>Received Date</span>
                                    <input type="datetime-local" name="received_at" value="{{ now()->format('Y-m-d\TH:i') }}" required>
                                </label>
                                <label>
                                    <span>Received Copy</span>
                                    <input type="file" name="received_copy" required>
                                </label>
                                <label class="kfms-span-2">
                                    <span>Received Notes</span>
                                    <textarea name="received_notes" rows="3">{{ $letter->received_notes }}</textarea>
                                </label>
                            </div>
                            <button class="kfms-btn" type="submit"><i class="mdi mdi-upload"></i> Upload Received Copy</button>
                        </form>
                    @endif
                @endcan

                @can('letters.share')
                    <form class="kfms-form" method="POST" action="{{ route('letters.share', $letter) }}">
                        @csrf
                        <label>
                            <span>Share Internally</span>
                            <select name="user_ids[]" multiple required>
                                @foreach ($staff as $user)
                                    <option value="{{ $user->id }}">{{ $user->name }} - {{ $user->email }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Message</span>
                            <textarea name="message" rows="3" placeholder="Optional note for the staff receiving this letter"></textarea>
                        </label>
                        <button class="kfms-link-btn" type="submit"><i class="mdi mdi-share-variant-outline"></i> Share Letter</button>
                    </form>
                @endcan

                @can('letters.client-visibility')
                    <form method="POST" action="{{ route('letters.client-visibility', $letter) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="client_visible" value="{{ $letter->client_visible ? 0 : 1 }}">
                        <button class="kfms-link-btn" type="submit">
                            <i class="mdi mdi-account-eye-outline"></i>
                            {{ $letter->client_visible ? 'Hide from Client Portal' : 'Show in Client Portal' }}
                        </button>
                    </form>
                @endcan
            </div>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Attachments & Sharing</h2>
                <span>Supporting documents, received copies, and internal shares</span>
            </div>
        </div>

        <div class="kfms-grid-two">
            <div>
                <h3>Attachments</h3>
                <div class="kfms-client-list">
                    @forelse ($letter->attachments as $attachment)
                        <a href="{{ route('attachments.download', $attachment) }}">
                            <strong>{{ $attachment->title ?: $attachment->original_name }}</strong>
                            <span>{{ $attachment->category ?: 'Document' }} - {{ $attachment->created_at->format('d M Y') }}</span>
                        </a>
                    @empty
                        <div class="kfms-empty-state">No attachments uploaded.</div>
                    @endforelse
                </div>
            </div>
            <div>
                <h3>Internal Shares</h3>
                <div class="kfms-client-list">
                    @forelse ($letter->shares as $share)
                        <span>
                            <strong>{{ $share->user?->name }}</strong>
                            <em>{{ $share->message ?: 'Shared without note' }}</em>
                        </span>
                    @empty
                        <div class="kfms-empty-state">Not shared internally yet.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
