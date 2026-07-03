@extends('layouts.admin')

@section('title', 'Matter Instructions')
@section('page-title', 'Matter Instructions / Documents')

@section('content')
    @if (session('status'))
        <div class="kfms-alert">{{ session('status') }}</div>
    @endif

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>{{ $matter->reference_no }} - Instructions</h2>
                <span>{{ $matter->title }} - {{ $matter->client?->display_name }}</span>
            </div>
            <a class="kfms-link-btn" href="{{ route('matters.show', $matter) }}">
                <i class="mdi mdi-arrow-left"></i>
                Back to Workspace
            </a>
        </div>

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
                <span>Documents</span>
                <strong>{{ number_format($matter->attachments->count()) }}</strong>
            </div>
        </div>
    </section>

    <div class="kfms-grid-two">
        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Review / Add Instructions</h2>
                    <span>Keep the current instruction narrative for this file.</span>
                </div>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('matters.instructions.update', $matter) }}">
                @csrf
                @method('PATCH')
                <div class="kfms-form-grid">
                    <label class="kfms-span-2">
                        <span>Instructions / File Summary</span>
                        <textarea name="description" rows="8" required>{{ old('description', $matter->description) }}</textarea>
                        @error('description') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-content-save"></i>
                        Save Instructions
                    </button>
                </div>
            </form>
        </section>

        <section class="kfms-panel">
            <div class="kfms-panel-header">
                <div>
                    <h2>Add Document</h2>
                    <span>Upload instructions, pleadings, letters, opinions, or evidence.</span>
                </div>
            </div>

            <form class="kfms-form" method="POST" action="{{ route('matters.documents.store', $matter) }}" enctype="multipart/form-data">
                @csrf
                <div class="kfms-form-grid">
                    <label>
                        <span>Document Type</span>
                        <select name="category">
                            <option value="matter-document" @selected(old('category') === 'matter-document')>Matter Document</option>
                            <option value="instruction" @selected(old('category') === 'instruction')>Instruction</option>
                            <option value="client-letter" @selected(old('category') === 'client-letter')>Client Letter</option>
                            <option value="legal-opinion" @selected(old('category') === 'legal-opinion')>Legal Opinion</option>
                            <option value="pleading" @selected(old('category') === 'pleading')>Pleading</option>
                            <option value="evidence" @selected(old('category') === 'evidence')>Evidence</option>
                        </select>
                        @error('category') <small>{{ $message }}</small> @enderror
                    </label>
                    <label>
                        <span>File</span>
                        <input type="file" name="document" required>
                        @error('document') <small>{{ $message }}</small> @enderror
                    </label>
                </div>
                <div class="kfms-form-actions">
                    <button class="kfms-btn" type="submit">
                        <i class="mdi mdi-upload"></i>
                        Upload Document
                    </button>
                </div>
            </form>
        </section>
    </div>

    <section class="kfms-panel">
        <div class="kfms-panel-header">
            <div>
                <h2>Documents</h2>
                <span>{{ $matter->attachments->count() }} uploaded files</span>
            </div>
        </div>
        <div class="kfms-table-wrap">
            <table class="kfms-table">
                <thead>
                    <tr>
                        <th>Document</th>
                        <th>Type</th>
                        <th>Uploaded By</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($matter->attachments as $attachment)
                        <tr>
                            <td>{{ $attachment->original_name }}</td>
                            <td>{{ str($attachment->category ?: 'matter-document')->headline() }}</td>
                            <td>{{ $attachment->uploader?->name ?: '-' }}</td>
                            <td>{{ number_format(($attachment->size ?? 0) / 1024, 1) }} KB</td>
                            <td>{{ $attachment->created_at?->format('d M Y, H:i') }}</td>
                            <td>
                                <div class="kfms-table-actions">
                                    <a href="{{ route('attachments.view', $attachment) }}" target="_blank" rel="noopener"><i class="mdi mdi-eye-outline"></i> View</a>
                                    <a href="{{ route('attachments.download', $attachment) }}"><i class="mdi mdi-download"></i> Download</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="kfms-empty">No documents have been uploaded for this matter.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
